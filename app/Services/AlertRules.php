<?php

declare(strict_types=1);

namespace SmartBin;

// ---------------------------------------------------------------------------
// AlertRule interface — the extension point
// ---------------------------------------------------------------------------

/**
 * A single, self-contained rule that decides whether a BinState warrants
 * a message and, if so, constructs it.
 *
 * To add new sensor types or thresholds:
 *   1. Implement this interface.
 *   2. Add a case to {@see MessageType}.
 *   3. Register the rule in {@see MessageGenerator::defaultRules()}.
 *
 * No existing code needs modification (Open/Closed Principle).
 */
interface AlertRule
{
    /**
     * Evaluate the current state and — when the rule fires — return a
     * BinMessage. Return null when the condition is not met.
     *
     * @param BinState             $state       Live sensor snapshot
     * @param AlertStateRepository $stateRepo   Persistent alert-state store
     */
    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage;
}

// ---------------------------------------------------------------------------
// UsageEventRule
// ---------------------------------------------------------------------------

/**
 * Emits a USAGE_EVENT message every time the bin records a new deposit.
 *
 * Deduplication: the event is only emitted once per unique (binId, userId,
 * weight) tuple. In practice the calling layer should only push a UsageEvent
 * when genuinely new data arrives from the device.
 */
final class UsageEventRule implements AlertRule
{
    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage
    {
        if ($state->lastUsageEvent === null) {
            return null;
        }

        $event = $state->lastUsageEvent;

        // Build a dedup key that encodes the specific event.
        $dedupKey = sprintf(
            '%s:usage:%s:%.4f',
            $state->binId,
            $event->userId,
            $event->weightDeposited
        );

        if ($stateRepo->hasAlreadyFired($dedupKey)) {
            return null;
        }

        $stateRepo->markFired($dedupKey);

        return BinMessage::create(
            binId:     $state->binId,
            locationX: $state->locationX,
            locationY: $state->locationY,
            type:      MessageType::USAGE_EVENT,
            payload:   [
                'user_id'          => $event->userId,
                'weight_deposited' => $event->weightDeposited,
                'current_weight'   => $state->currentWeight,
                'capacity_percent' => $state->capacityPercent(),
            ],
        );
    }
}

// ---------------------------------------------------------------------------
// CapacityWarningRule  (handles all three thresholds generically)
// ---------------------------------------------------------------------------

/**
 * Fires once per capacity band (50 %, 75 %, 90 %) and resets when the bin
 * is emptied below the threshold, allowing the warning to re-arm.
 */
final class CapacityWarningRule implements AlertRule
{
    /**
     * @param float       $threshold  e.g. 50.0, 75.0, 90.0
     * @param MessageType $type       Matching enum case
     */
    public function __construct(
        private readonly float       $threshold,
        private readonly MessageType $type,
    ) {}

    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage
    {
        $percent  = $state->capacityPercent();
        $dedupKey = "{$state->binId}:capacity:{$this->threshold}";

        if ($percent >= $this->threshold) {
            if ($stateRepo->hasAlreadyFired($dedupKey)) {
                return null; // already warned for this fill cycle
            }
            $stateRepo->markFired($dedupKey);

            return BinMessage::create(
                binId:     $state->binId,
                locationX: $state->locationX,
                locationY: $state->locationY,
                type:      $this->type,
                payload:   [
                    'threshold_percent' => $this->threshold,
                    'capacity_percent'  => $percent,
                    'current_weight'    => $state->currentWeight,
                    'max_capacity'      => $state->maxCapacity,
                ],
            );
        }

        // Below threshold → clear the flag so it can re-arm after emptying.
        $stateRepo->clearFired($dedupKey);
        return null;
    }
}

// ---------------------------------------------------------------------------
// LidAlertRule
// ---------------------------------------------------------------------------

/**
 * Fires when the lid is detected open (not properly closed).
 *
 * The alert fires once and is suppressed until the lid is closed and
 * re-opened, preventing a storm of repeated messages.
 */
final class LidAlertRule implements AlertRule
{
    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage
    {
        $dedupKey = "{$state->binId}:lid_open";

        if (!$state->lidClosed) {
            if ($stateRepo->hasAlreadyFired($dedupKey)) {
                return null;
            }
            $stateRepo->markFired($dedupKey);

            return BinMessage::create(
                binId:     $state->binId,
                locationX: $state->locationX,
                locationY: $state->locationY,
                type:      MessageType::LID_OPEN_ALERT,
                payload:   [
                    'lid_closed' => false,
                ],
            );
        }

        // Lid is now closed → reset so a future open event fires again.
        $stateRepo->clearFired($dedupKey);
        return null;
    }
}

// ---------------------------------------------------------------------------
// BatteryWarningRule  (handles all three thresholds generically)
// ---------------------------------------------------------------------------

/**
 * Fires once per battery band (50 %, 25 %, 10 %) and does NOT reset on
 * charge (most field bins are not recharged mid-deployment; adjust if
 * your hardware supports recharging by calling clearFired on charge events).
 */
final class BatteryWarningRule implements AlertRule
{
    /**
     * @param float       $threshold  e.g. 50.0, 25.0, 10.0
     * @param MessageType $type       Matching enum case
     */
    public function __construct(
        private readonly float       $threshold,
        private readonly MessageType $type,
    ) {}

    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage
    {
        $dedupKey = "{$state->binId}:battery:{$this->threshold}";

        if ($state->batteryLevel <= $this->threshold) {
            if ($stateRepo->hasAlreadyFired($dedupKey)) {
                return null;
            }
            $stateRepo->markFired($dedupKey);

            return BinMessage::create(
                binId:     $state->binId,
                locationX: $state->locationX,
                locationY: $state->locationY,
                type:      $this->type,
                payload:   [
                    'threshold_percent' => $this->threshold,
                    'battery_level'     => $state->batteryLevel,
                ],
            );
        }

        // Battery recovered above threshold (e.g. after swap/charge).
        $stateRepo->clearFired($dedupKey);
        return null;
    }
}
