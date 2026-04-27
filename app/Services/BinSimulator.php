<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BinState;
use App\DTOs\UsageEvent;

final class BinSimulator
{
    public readonly string $binId;
    public readonly float  $locationX;
    public readonly float  $locationY;
    public readonly float  $maxCapacity;

    private float        $currentWeight        = 0.0;
    private float        $batteryLevel;
    private bool         $lidClosed            = true;
    private int          $lidOpenTicksLeft     = 0;
    private ?UsageEvent  $pendingUsageEvent    = null;
    private int          $depositCooldownTicks = 0;

    private const LID_OPEN_TICKS             = 3;
    private const COLLECTION_THRESHOLD_PCT   = 90.0;
    private const COLLECTION_CHANCE_PER_TICK = 0.15;
    private const BATTERY_DRAIN_IDLE         = 0.03;
    private const BATTERY_DRAIN_ACTIVE       = 0.12;
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

    public function tick(): BinState
    {
        $this->pendingUsageEvent = null;

        // Close lid after delay
        if (!$this->lidClosed) {
            $this->lidOpenTicksLeft--;
            if ($this->lidOpenTicksLeft <= 0) {
                $this->lidClosed = true;
            }
        }

        // Maybe deposit
        if ($this->depositCooldownTicks > 0) {
            $this->depositCooldownTicks--;
        } elseif ($this->currentWeight < $this->maxCapacity) {
            $this->doDeposit();
        }

        // Maybe collect when nearly full
        if ($this->capacityPercent() >= self::COLLECTION_THRESHOLD_PCT) {
            if ((mt_rand() / mt_getrandmax()) < self::COLLECTION_CHANCE_PER_TICK) {
                $this->doCollection();
            }
        }

        // Idle battery drain
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

    private function doDeposit(): void
    {
        $maxDeposit = $this->maxCapacity - $this->currentWeight;
        $weight     = round((mt_rand(10, 800) / 100), 2);
        $weight     = min($weight, $maxDeposit);

        if ($weight <= 0) {
            return;
        }

        $this->currentWeight           += $weight;
        $this->batteryLevel             = max(0.0, $this->batteryLevel - self::BATTERY_DRAIN_ACTIVE);
        $this->lidClosed                = false;
        $this->lidOpenTicksLeft         = self::LID_OPEN_TICKS;
        $this->pendingUsageEvent        = new UsageEvent($this->randomUserId(), $weight);
        $this->depositCooldownTicks     = mt_rand(self::MIN_COOLDOWN_TICKS, self::MAX_COOLDOWN_TICKS);
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