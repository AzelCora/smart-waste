#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin-runner.php
 *
 * Continuously simulates a single smart waste bin and prints generated
 * messages to stdout.
 *
 * Usage:
 *   php bin-runner.php <bin-id> <x> <y> <max-capacity> [tick-seconds] [initial-battery] [initial-weight]
 *
 * Examples:
 *   php bin-runner.php BIN-001 -8.6538 41.1579 100
 *   php bin-runner.php BIN-002 -8.6100 41.1480 150 2 85 30
 *
 * Run multiple bins simultaneously:
 *   php bin-runner.php BIN-001 -8.6538 41.1579 100 2 &
 *   php bin-runner.php BIN-002 -8.6100 41.1480 150 2 85 &
 *   php bin-runner.php BIN-003 -8.6200 41.1600  80 2 60 40 &
 */

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------
// Adjusted paths to reflect file movements to app/DTOs/, app/Repository/,
// and directly into app/Services/ for BinSimulator.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'SmartBin';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $map = [
        // DTOs
        'SmartBin\BinMessage'                   => __DIR__ . '/../DTOs/BinMessage.php',
        'SmartBin\MessageType'                  => __DIR__ . '/../DTOs/Types.php',
        'SmartBin\UsageEvent'                   => __DIR__ . '/../DTOs/Types.php',
        'SmartBin\BinState'                     => __DIR__ . '/../DTOs/Types.php',

        // Services (assuming AlertRules.php and MessageGenerator.php remain in app/Services/)
        'SmartBin\AlertRule'                    => __DIR__ . '/AlertRules.php',
        'SmartBin\UsageEventRule'               => __DIR__ . '/AlertRules.php',
        'SmartBin\CapacityWarningRule'          => __DIR__ . '/AlertRules.php',
        'SmartBin\LidAlertRule'                 => __DIR__ . '/AlertRules.php',
        'SmartBin\BatteryWarningRule'           => __DIR__ . '/AlertRules.php',
        'SmartBin\MessageGenerator'             => __DIR__ . '/MessageGenerator.php',

        // Repository
        'SmartBin\AlertStateRepository'         => __DIR__ . '/../Repository/AlertStateRepository.php',
        'SmartBin\InMemoryAlertStateRepository' => __DIR__ . '/../Repository/AlertStateRepository.php',

        // Simulation (BinSimulator.php is directly in app/Services/)
        'SmartBin\Simulation\BinSimulator'     => __DIR__ . '/BinSimulator.php',
    ];
    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

use SmartBin\InMemoryAlertStateRepository;
use SmartBin\MessageGenerator;
use SmartBin\Simulation\BinSimulator;

// ---------------------------------------------------------------------------
// ANSI colour helpers
// ---------------------------------------------------------------------------
const COLORS = [
    'reset'   => "\033[0m",
    'bold'    => "\033[1m",
    'dim'     => "\033[2m",
    'red'     => "\033[31m",
    'yellow'  => "\033[33m",
    'green'   => "\033[32m",
    'cyan'    => "\033[36m",
    'magenta' => "\033[35m",
    'white'   => "\033[37m",
    'gray'    => "\033[90m",
];

function c(string $color, string $text): string
{
    return COLORS[$color] . $text . COLORS['reset'];
}

/** Pick a colour based on message type string. */
function typeColor(string $type): string
{
    if (str_contains($type, 'capacity_warning_90')) return 'red';
    if (str_contains($type, 'capacity_warning_75')) return 'yellow';
    if (str_contains($type, 'capacity_warning_50')) return 'cyan';
    if (str_contains($type, 'battery_warning_10'))  return 'red';
    if (str_contains($type, 'battery_warning_25'))  return 'yellow';
    if (str_contains($type, 'battery_warning_50'))  return 'cyan';
    if (str_contains($type, 'lid_open'))            return 'magenta';
    if (str_contains($type, 'usage_event'))         return 'green';
    return 'white';
}

/** Format payload key=value pairs for compact display. */
function fmtPayload(array $payload): string
{
    $parts = [];
    foreach ($payload as $k => $v) {
        $val    = is_float($v) ? number_format($v, 2) : var_export($v, true);
        $parts[] = c('gray', $k . '=') . $val;
    }
    return implode('  ', $parts);
}

// ---------------------------------------------------------------------------
// Parse arguments
// ---------------------------------------------------------------------------
$argv = $argv ?? [];

if (count($argv) < 5) {
    fwrite(STDERR, "Usage: php bin-runner.php <bin-id> <x> <y> <max-capacity> [tick-seconds=2] [initial-battery=100] [initial-weight=0]
");
    exit(1);
}

$binId          = $argv[1];
$locationX      = (float) $argv[2];
$locationY      = (float) $argv[3];
$maxCapacity    = (float) $argv[4];
$tickSeconds    = isset($argv[5]) ? max(1, (int) $argv[5]) : 2;
$initialBattery = isset($argv[6]) ? (float) $argv[6] : 100.0;
$initialWeight  = isset($argv[7]) ? (float) $argv[7] : 0.0;

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------
$simulator = new BinSimulator(
    binId:          $binId,
    locationX:      $locationX,
    locationY:      $locationY,
    maxCapacity:    $maxCapacity,
    initialBattery: $initialBattery,
    initialWeight:  $initialWeight,
);

$repo      = new InMemoryAlertStateRepository();
$generator = MessageGenerator::withDefaultRules($repo);

// ---------------------------------------------------------------------------
// Boot banner
// ---------------------------------------------------------------------------
echo c('bold', "
╔══════════════════════════════════════════════════════╗
");
echo c('bold', "║  Smart Bin Simulator — " . str_pad($binId, 30) . "║
");
echo c('bold', "╚══════════════════════════════════════════════════════╝
");
echo c('dim',  "  Location  : ({$locationX}, {$locationY})
");
echo c('dim',  "  Capacity  : {$maxCapacity} kg
");
echo c('dim',  "  Battery   : {$initialBattery} %
");
echo c('dim',  "  Tick      : every {$tickSeconds}s
");
echo c('dim',  "  Started   : " . date('Y-m-d H:i:s') . "

");

// ---------------------------------------------------------------------------
// Main simulation loop
// ---------------------------------------------------------------------------
$tick = 0;

while (true) {
    $tick++;
    $state    = $simulator->tick();
    $messages = $generator->generate($state);

    foreach ($messages as $msg) {
        $ts      = date('H:i:s');
        $type    = $msg->type->value;
        $color   = typeColor($type);
        $payload = fmtPayload($msg->payload);

        printf(
            "%s  %s  %s  %s
",
            c('gray',  $ts),
            c('bold',  str_pad($msg->binId, 10)),
            c($color,  str_pad($type, 28)),
            $payload
        );
    }

    // Flush immediately so output appears in real time even when piped
    if (ob_get_level()) {
        ob_flush();
    }
    flush();

    sleep($tickSeconds);
}
