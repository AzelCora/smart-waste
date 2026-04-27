<?php
declare(strict_types=1);
namespace App\Services;

use App\DTOs\BinMessage;
use App\DTOs\BinState;
use App\Repository\AlertStateRepository;

interface AlertRule
{
    public function evaluate(BinState $state, AlertStateRepository $stateRepo): ?BinMessage;
}