<?php
declare(strict_types=1);
namespace App\Services;

use App\DTOs\BinMessage;
use App\DTOs\BinState;
use App\DTOs\MessageType;
use App\Repository\AlertStateRepository;

final class UsageEventRule implements AlertRule
{
    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage
    {
        if ($state->lastUsageEvent === null) {
            return null;
        }

        $event    = $state->lastUsageEvent;
        $dedupKey = sprintf('%s:usage:%s:%.4f', $state->binId, $event->userId, $event->weightDeposited);

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