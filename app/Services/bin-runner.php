#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin-runner.php
 *
 * Usage (run from the project root):
 *   php app/Services/bin-runner.php <bin-id> <x> <y> <max-capacity> [tick-seconds] [initial-battery] [initial-weight]
 *
 * Examples:
 *   php app/Services/bin-runner.php BIN-001 -8.6538 41.1579 100
 *   php app/Services/bin-runner.php BIN-002 -8.6100 41.1480 150 2 85 30
 */

$autoloader = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "[ERROR] vendor/autoload.php not found. Run: composer install\n");
    exit(1);
}

require_once $autoloader;

use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use App\Repository\InMemoryAlertStateRepository;
use App\Services\MessageGenerator;
use App\Services\BinSimulator;

const KAFKA_BOOTSTRAP = 'localhost:9092';
const KAFKA_TOPIC     = 'bin-activity';

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

function fmtPayload(array $payload): string
{
    $parts = [];
    foreach ($payload as $k => $v) {
        $val     = is_float($v) ? number_format($v, 2) : var_export($v, true);
        $parts[] = c('gray', $k . '=') . $val;
    }
    return implode('  ', $parts);
}

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------
if (count($argv) < 5) {
    fwrite(STDERR, "Usage: php bin-runner.php <bin-id> <x> <y> <max-capacity> [tick-seconds=2] [initial-battery=100] [initial-weight=0]\n");
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
// Kafka
// ---------------------------------------------------------------------------
$kafkaOnline = false;
$producer    = null;

try {
    $config = new ProducerConfig();
    $config->setBootstrapServer(KAFKA_BOOTSTRAP);
    $config->setAcks(-1);
    $config->setConnectTimeout(3);
    $config->setSendTimeout(3);
    $producer    = new Producer($config);
    $kafkaOnline = true;
} catch (\Throwable $e) {
    fwrite(STDERR, c('yellow', "[WARN] Kafka unavailable — terminal-only mode: " . $e->getMessage() . "\n"));
}

// ---------------------------------------------------------------------------
// Banner
// ---------------------------------------------------------------------------
$kafkaStatus = $kafkaOnline
    ? c('green',  'connected (' . KAFKA_BOOTSTRAP . ' -> ' . KAFKA_TOPIC . ')')
    : c('yellow', 'offline (terminal-only)');

echo c('bold', "\n╔══════════════════════════════════════════════════════╗\n");
echo c('bold', "║  Smart Bin Simulator — " . str_pad($binId, 30) . "║\n");
echo c('bold', "╚══════════════════════════════════════════════════════╝\n");
echo c('dim',  "  Location  : ({$locationX}, {$locationY})\n");
echo c('dim',  "  Capacity  : {$maxCapacity} kg\n");
echo c('dim',  "  Battery   : {$initialBattery} %\n");
echo c('dim',  "  Tick      : every {$tickSeconds}s\n");
echo       "  Kafka     : " . $kafkaStatus . "\n";
echo c('dim',  "  Started   : " . date('Y-m-d H:i:s') . "\n\n");

// ---------------------------------------------------------------------------
// Loop
// ---------------------------------------------------------------------------
while (true) {
    $state    = $simulator->tick();
    $messages = $generator->generate($state);

    foreach ($messages as $msg) {
        $ts    = date('H:i:s');
        $type  = $msg->type->value;

        printf(
            "%s  %s  %s  %s\n",
            c('gray',  $ts),
            c('bold',  str_pad($msg->binId, 16)),
            c(typeColor($type), str_pad($type, 28)),
            fmtPayload($msg->payload)
        );

        if ($kafkaOnline && $producer !== null) {
            try {
                $producer->send(
                    topic: KAFKA_TOPIC,
                    value: json_encode($msg->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    key:   $msg->binId,
                );
            } catch (\Throwable $e) {
                fwrite(STDERR, c('yellow', "  [WARN] Kafka send failed: " . $e->getMessage() . "\n"));
            }
        }
    }

    if (ob_get_level()) ob_flush();
    flush();
    sleep($tickSeconds);
}