<?php
declare(strict_types=1);
namespace App\DTOs;

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
            throw new \InvalidArgumentException('currentWeight must be between 0 and maxCapacity.');
        }
        if ($batteryLevel < 0 || $batteryLevel > 100) {
            throw new \InvalidArgumentException('batteryLevel must be between 0 and 100.');
        }
    }

    public function capacityPercent(): float
    {
        return round(($this->currentWeight / $this->maxCapacity) * 100, 2);
    }
}