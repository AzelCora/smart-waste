<?php

declare(strict_types=1);

namespace SmartBin\Simulation;

use SmartBin\BinState;
use SmartBin\UsageEvent;

/**
 * Holds mutable runtime state for one physical bin and advances it
 * one tick at a time. Each tick represents a configurable number of
 * real seconds and drives:
 *
 *  - Random waste deposits from random user IDs
 *  - Realistic battery drain (faster when active)
 *  - Lid open/close lifecycle (open on deposit, auto-close after a delay)
 *  - Bin collection when maintenance empties it at high capacity
 */
final class BinSimulator
{
    // Physical identity (immutable)
    public readonly string $binId;
    public readonly float  $locationX;
    public readonly float  $locationY;
    public readonly float  $maxCapacity;

    // Mutable runtime state
    private float        $currentWeight        = 0.0;
    private float        $batteryLevel;
    private bool         $lidClosed            = true;
    private int          $lidOpenTicksLeft     = 0;
    private ?UsageEvent  $pendingUsageEvent    = null;
    private int          $depositCooldownTicks = 0;

    // Tuning
    private const LID_OPEN_TICKS             = 3;
    private const COLLECTION_THRESHOLD_PCT   = 90.0;
    private const COLLECTION_CHANCE_PER_TICK = 0.15;
    private const BATTERY_DRAIN_IDLE         = 0.03;   // % per tick (idle)
    private const BATTERY_DRAIN_ACTIVE       = 0.12;   // % per tick (deposit)
    private const MIN_COOLDOWN_TICKS         = 2;
    private const MAX_COOLDOWN_TICKS         = 10;

    public function __construct(
        string $binId,
        float  $locationX,
        float  $locationY,
        float  $maxCapacity,
        float  $initialBattery = 100.0,
        float  $initialWeight  = 0.0,
    ) {
        $this->binId         = $binId;
        $this->locationX     = $locationX;
        $this->locationY     = $locationY;
        $this->maxCapacity   = $maxCapacity;
        $this->batteryLevel  = $initialBattery;
        $this->currentWeight = min($initialWeight, $maxCapacity);
    }

    /**
     * Advance the bin by one tick and return a fresh immutable BinState
     * snapshot ready for MessageGenerator::generate().
     */
    public function tick(): BinState
    {
        $this->pendingUsageEvent = null;

        // 1. Close lid after delay
        if (!$this->lidClosed) {
            $this->lidOpenTicksLeft--;
            if ($this->lidOpenTicksLeft <= 0) {
                $this->lidClosed = true;
            }
        }

        // 2. Maybe make a deposit
        if ($this->depositCooldownTicks > 0) {
            $this->depositCooldownTicks--;
        } elseif ($this->currentWeight < $this->maxCapacity) {
            $this->doDeposit();
        }

        // 3. Maybe trigger collection when nearly full
        if ($this->capacityPercent() >= self::COLLECTION_THRESHOLD_PCT) {
            if ((mt_rand() / mt_getrandmax()) < self::COLLECTION_CHANCE_PER_TICK) {
                $this->doCollection();
            }
        }

        // 4. Idle battery drain
        $this->batteryLevel = max(0.0, $this->batteryLevel - self::BATTERY_DRAIN_IDLE);

        return new BinState(
            binId:          $this->binId,
            locationX:      $this->locationX,
            locationY:      $this->locationY,
            maxCapacity:    $this->maxCapacity,
            currentWeight:  round($this->currentWeight, 3),
            batteryLevel:   round($this->batteryLevel, 2),
            lidClosed:      $this->lidClosed,
            lastUsageEvent: $this->pendingUsageEvent,
        );
    }

    // ------------------------------------------------------------------

    private function doDeposit(): void
    {
        $maxDeposit = $this->maxCapacity - $this->currentWeight;
        $weight     = round(lcg_value() * min(8.0, $maxDeposit), 2);

        if ($weight <= 0) {
            return;
        }

        $this->currentWeight  += $weight;
        $this->batteryLevel    = max(0.0, $this->batteryLevel - self::BATTERY_DRAIN_ACTIVE);
        $this->lidClosed       = false;
        $this->lidOpenTicksLeft = self::LID_OPEN_TICKS;
        $this->pendingUsageEvent = new UsageEvent($this->randomUserId(), $weight);

        $this->depositCooldownTicks = mt_rand(
            self::MIN_COOLDOWN_TICKS,
            self::MAX_COOLDOWN_TICKS
        );
    }

    private function doCollection(): void
    {
        $this->currentWeight = 0.0;
        $this->lidClosed     = true;
    }

    private function capacityPercent(): float
    {
        return ($this->currentWeight / $this->maxCapacity) * 100;
    }

    private function randomUserId(): string
    {
        return sprintf('%08x', mt_rand(0, 0xFFFFFFFF));
    }
}
