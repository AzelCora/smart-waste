<?php
declare(strict_types=1);
namespace App\Services;

use App\DTOs\BinMessage;
use App\DTOs\BinState;
use App\DTOs\MessageType;
use App\Repository\AlertStateRepository;

final class BatteryWarningRule implements AlertRule
{
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

        $stateRepo->clearFired($dedupKey);
        return null;
    }
}