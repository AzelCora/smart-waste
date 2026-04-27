<?php
declare(strict_types=1);
namespace App\Repository;

interface AlertStateRepository
{
    public function hasAlreadyFired(string $key): bool;
    public function markFired(string $key): void;
    public function clearFired(string $key): void;
}