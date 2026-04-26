<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Minimal PSR-4-style autoloader (no Composer needed for this demo)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'SmartBin';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $map = [
        'SmartBin\BinMessage'                    => __DIR__ . '/../DTOs/BinMessage.php',
        'SmartBin\MessageType'                   => __DIR__ . '/../DTOs/Types.php',
        'SmartBin\UsageEvent'                    => __DIR__ . '/../DTOs/Types.php',
        'SmartBin\BinState'                      => __DIR__ . '/../DTOs/Types.php',
        'SmartBin\AlertRule'                     => __DIR__ . '/AlertRules.php',
        'SmartBin\UsageEventRule'                => __DIR__ . '/AlertRules.php',
        'SmartBin\CapacityWarningRule'           => __DIR__ . '/AlertRules.php',
        'SmartBin\LidAlertRule'                  => __DIR__ . '/AlertRules.php',
        'SmartBin\BatteryWarningRule'            => __DIR__ . '/AlertRules.php',
        'SmartBin\AlertStateRepository'          => __DIR__ . '/../Repository/AlertStateRepository.php',
        'SmartBin\InMemoryAlertStateRepository'  => __DIR__ . '/../Repository/AlertStateRepository.php',
        'SmartBin\MessageGenerator'              => __DIR__ . '/MessageGenerator.php',
    ];
    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

use SmartBin\{
    BinState,
    InMemoryAlertStateRepository,
    MessageGenerator,
    UsageEvent,
    MessageType,
    AlertRule,
    BinMessage,
    AlertStateRepository,
};

// ---------------------------------------------------------------------------
// Helper: print a section banner
// ---------------------------------------------------------------------------
function banner(string $text): void
{
    $line = str_repeat('─', 60);
    echo "
{$line}
  {$text}
{$line}
";
}

// ---------------------------------------------------------------------------
// Helper: print generated messages
// ---------------------------------------------------------------------------
function printMessages(array $messages, string $label = ''): void
{
    if ($label !== '') {
        echo "  [{$label}]
";
    }
    if (empty($messages)) {
        echo "  (no messages generated)
";
        return;
    }
    foreach ($messages as $msg) {
        $payload = json_encode($msg->payload, JSON_UNESCAPED_UNICODE);
        echo "  ▶ [{$msg->type->value}] bin={$msg->binId} payload={$payload}
";
    }
}

// ===========================================================================
// DEMO START
// ===========================================================================

$repo      = new InMemoryAlertStateRepository();
$generator = MessageGenerator::withDefaultRules($repo);

// ---------------------------------------------------------------------------
// Scenario 1 — Normal usage: single deposit, bin at 55 %, battery fine
// ---------------------------------------------------------------------------
banner('Scenario 1 — Usage event + capacity crosses 50 %');

$state1 = new BinState(
    binId:          'BIN-001',
    locationX:      -8.6538,
    locationY:      41.1579,
    maxCapacity:    100.0,
    currentWeight:  55.0,
    batteryLevel:   80.0,
    lidClosed:      true,
    lastUsageEvent: new UsageEvent('a1b2c3d4', 5.0),
);

$msgs = $generator->generate($state1);
printMessages($msgs, 'first call');

// Same state again — deduplication must suppress all messages
$msgs2 = $generator->generate($state1);
printMessages($msgs2, 'second call (same state, expect silence)');

// ---------------------------------------------------------------------------
// Scenario 2 — Bin fills to 80 %, lid left open, battery at 48 %
// ---------------------------------------------------------------------------
banner('Scenario 2 — Capacity 75 % warning + lid open + battery 50 %');

$state2 = new BinState(
    binId:          'BIN-001',
    locationX:      -8.6538,
    locationY:      41.1579,
    maxCapacity:    100.0,
    currentWeight:  80.0,
    batteryLevel:   48.0,
    lidClosed:      false,
    lastUsageEvent: new UsageEvent('f7e6d5c4', 25.0),   // new user/weight
);

$msgs = $generator->generate($state2);
printMessages($msgs, 'new deposit, lid open, battery 48 %');

// ---------------------------------------------------------------------------
// Scenario 3 — Critical state: 92 % full, lid still open, battery 9 %
// ---------------------------------------------------------------------------
banner('Scenario 3 — Capacity 90 %, lid still open (suppressed), battery 10 %');

$state3 = new BinState(
    binId:          'BIN-001',
    locationX:      -8.6538,
    locationY:      41.1579,
    maxCapacity:    100.0,
    currentWeight:  92.0,
    batteryLevel:   9.0,
    lidClosed:      false,    // still open — should NOT re-fire lid alert
    lastUsageEvent: null,
);

$msgs = $generator->generate($state3);
printMessages($msgs, '92 % / lid still open / battery 9 %');

// ---------------------------------------------------------------------------
// Scenario 4 — Bin is emptied and lid is closed (reset + re-arm)
// ---------------------------------------------------------------------------
banner('Scenario 4 — Bin emptied and lid closed (state reset)');

$state4 = new BinState(
    binId:          'BIN-001',
    locationX:      -8.6538,
    locationY:      41.1579,
    maxCapacity:    100.0,
    currentWeight:  5.0,     // emptied
    batteryLevel:   9.0,
    lidClosed:      true,    // properly closed
    lastUsageEvent: null,
);

$msgs = $generator->generate($state4);
printMessages($msgs, 'bin emptied + lid closed');

// Now fill again past 50 % — the 50 % warning should re-arm and fire
$state5 = new BinState(
    binId:          'BIN-001',
    locationX:      -8.6538,
    locationY:      41.1579,
    maxCapacity:    100.0,
    currentWeight:  53.0,
    batteryLevel:   9.0,
    lidClosed:      true,
    lastUsageEvent: new UsageEvent('00112233', 48.0),
);

$msgs = $generator->generate($state5);
printMessages($msgs, 'filled to 53 % again after emptying');

// ---------------------------------------------------------------------------
// Scenario 5 — Second independent bin (BIN-002)
// ---------------------------------------------------------------------------
banner('Scenario 5 — Independent bin BIN-002 (separate dedup namespace)');

$state6 = new BinState(
    binId:          'BIN-002',
    locationX:      -8.6100,
    locationY:      41.1480,
    maxCapacity:    200.0,
    currentWeight:  185.0,   // 92.5 % — all three capacity warnings should fire
    batteryLevel:   8.0,     // triggers 50 %, 25 %, and 10 % battery warnings
    lidClosed:      false,   // lid open
    lastUsageEvent: new UsageEvent('deadbeef', 30.0),
);

$msgs = $generator->generate($state6);
printMessages($msgs, 'BIN-002 critical state');

// ---------------------------------------------------------------------------
// Scenario 6 — Extension: custom temperature rule
// ---------------------------------------------------------------------------
banner('Scenario 6 — Custom sensor rule (temperature overheat)');

/**
 * Example of adding a new sensor type without touching any existing file.
 *
 * In a real project this would live in its own file (TemperatureOverheatRule.php).
 */
final class TemperatureOverheatRule implements AlertRule
{
    public function __construct(private readonly float $maxSafeTemp) {}

    public function evaluate(BinState $state, AlertStateRepository $repo): ?BinMessage
    {
        // BinState doesn't carry temperature natively; this rule would expect
        // an extended BinState subclass or a decorator in a real system.
        // Here we simulate a reading via a static stand-in.
        $currentTemp = 78.5; // °C — simulated reading
        $dedupKey    = "{$state->binId}:temp_overheat";

        if ($currentTemp > $this->maxSafeTemp) {
            if ($repo->hasAlreadyFired($dedupKey)) {
                return null;
            }
            $repo->markFired($dedupKey);
            return BinMessage::create(
                binId:     $state->binId,
                locationX: $state->locationX,
                locationY: $state->locationY,
                type:      MessageType::USAGE_EVENT, // reuse closest type; extend enum in prod
                payload:   [
                    '_note'           => 'custom:temperature_overheat',
                    'temperature'     => $currentTemp,
                    'max_safe_temp'   => $this->maxSafeTemp,
                ],
            );
        }

        $repo->clearFired($dedupKey);
        return null;
    }
}

$repoExt      = new InMemoryAlertStateRepository();
$generatorExt = MessageGenerator::withDefaultRules($repoExt);
$generatorExt->addRule(new TemperatureOverheatRule(maxSafeTemp: 60.0));

$state7 = new BinState(
    binId:          'BIN-003',
    locationX:      -8.6200,
    locationY:      41.1600,
    maxCapacity:    150.0,
    currentWeight:  10.0,
    batteryLevel:   95.0,
    lidClosed:      true,
    lastUsageEvent: null,
);

$msgs = $generator->generate($state7);
printMessages($msgs, 'BIN-003 without custom rule (baseline generator)');

$msgsExt = $generatorExt->generate($state7);
printMessages($msgsExt, 'BIN-003 with TemperatureOverheatRule added');

// ---------------------------------------------------------------------------
// Scenario 7 — JSON output
// ---------------------------------------------------------------------------
banner('Scenario 7 — JSON serialisation output');

$repoJson = new InMemoryAlertStateRepository();
$genJson  = MessageGenerator::withDefaultRules($repoJson);

$jsonState = new BinState(
    binId:          'BIN-004',
    locationX:      -8.6000,
    locationY:      41.1400,
    maxCapacity:    80.0,
    currentWeight:  73.0,   // 91.25 % — triggers 50, 75, 90 warnings
    batteryLevel:   22.0,   // triggers 50 and 25 % battery warnings
    lidClosed:      false,
    lastUsageEvent: new UsageEvent('cafebabe', 12.5),
);

echo $genJson->generateJson($jsonState);
echo "
";

banner('Demo complete');
