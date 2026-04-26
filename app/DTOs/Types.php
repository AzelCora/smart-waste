<?php

declare(strict_types=1);

namespace SmartBin;

// ---------------------------------------------------------------------------
// MessageType — backed enum so the value travels over the wire as a string
// ---------------------------------------------------------------------------

/**
 * All possible message categories the system can emit.
 *
 * Add a new case here (and a matching AlertRule) to extend the system
 * without touching existing logic.
 */
enum MessageType: string
{
    // Usage events
    case USAGE_EVENT        = 'usage_event';

    // Capacity thresholds
    case CAPACITY_50        = 'capacity_warning_50';
    case CAPACITY_75        = 'capacity_warning_75';
    case CAPACITY_90        = 'capacity_warning_90';

    // Lid alerts
    case LID_OPEN_ALERT     = 'lid_open_alert';

    // Battery thresholds
    case BATTERY_50         = 'battery_warning_50';
    case BATTERY_25         = 'battery_warning_25';
    case BATTERY_10         = 'battery_warning_10';
}

// ---------------------------------------------------------------------------
// UsageEvent — structured record of a single waste deposit
// ---------------------------------------------------------------------------

/**
 * Describes the most-recent usage interaction with the bin.
 *
 * @param string $userId         8-character hex user identifier
 * @param float  $weightDeposited Weight added during this interaction (kg)
 */
final readonly class UsageEvent
{
    public function __construct(
        public string $userId,
        public float  $weightDeposited,
    ) {
        if (!preg_match('/^[0-9a-fA-F]{8}$/', $userId)) {
            throw new \InvalidArgumentException(
                "userId must be an 8-character hexadecimal string, got: '{$userId}'"
            );
        }
        if ($weightDeposited < 0) {
            throw new \InvalidArgumentException('weightDeposited cannot be negative.');
        }
    }
}

// ---------------------------------------------------------------------------
// BinState — snapshot of every measurable bin property at a point in time
// ---------------------------------------------------------------------------

/**
 * Immutable snapshot of all sensor readings for one bin.
 *
 * Pass a fresh instance into {@see MessageGenerator::generate()} each time
 * you receive data from the device.
 *
 * @param string          $binId          Unique bin identifier
 * @param float           $locationX      X coordinate (e.g. longitude)
 * @param float           $locationY      Y coordinate (e.g. latitude)
 * @param float           $maxCapacity    Physical max capacity in weight units
 * @param float           $currentWeight  Current accumulated waste weight
 * @param float           $batteryLevel   Battery remaining, 0–100 %
 * @param bool            $lidClosed      TRUE when the lid is properly closed
 * @param UsageEvent|null $lastUsageEvent Most-recent deposit, or null if none
 */
final readonly class BinState
{
    public function __construct(
        public string      $binId,
        public float       $locationX,
        public float       $locationY,
        public float       $maxCapacity,
        public float       $currentWeight,
        public float       $batteryLevel,
        public bool        $lidClosed,
        public ?UsageEvent $lastUsageEvent = null,
    ) {
        if ($maxCapacity <= 0) {
            throw new \InvalidArgumentException('maxCapacity must be greater than zero.');
        }
        if ($currentWeight < 0 || $currentWeight > $maxCapacity) {
            throw new \InvalidArgumentException(
                'currentWeight must be between 0 and maxCapacity.'
            );
        }
        if ($batteryLevel < 0 || $batteryLevel > 100) {
            throw new \InvalidArgumentException('batteryLevel must be between 0 and 100.');
        }
    }

    /** Capacity fill percentage, rounded to two decimal places. */
    public function capacityPercent(): float
    {
        return round(($this->currentWeight / $this->maxCapacity) * 100, 2);
    }
}
