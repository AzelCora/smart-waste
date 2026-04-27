<?php
declare(strict_types=1);
namespace App\Services;

use App\DTOs\BinMessage;
use App\DTOs\BinState;
use App\DTOs\MessageType;
use App\Repository\AlertStateRepository;

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
                payload:   ['lid_closed' => false],
            );
        }

        $stateRepo->clearFired($dedupKey);
        return null;
    }
}