<?php
declare(strict_types=1);
namespace App\Repository;

final class InMemoryAlertStateRepository implements AlertStateRepository
{
    /** @var array<string, true> */
    private array $firedKeys = [];

    public function hasAlreadyFired(string $key): bool
    {
        return isset($this->firedKeys[$key]);
    }

    public function markFired(string $key): void
    {
        $this->firedKeys[$key] = true;
    }

    public function clearFired(string $key): void
    {
        unset($this->firedKeys[$key]);
    }

    public function resetBin(string $binId): void
    {
        foreach (array_keys($this->firedKeys) as $key) {
            if (str_starts_with($key, "{$binId}:")) {
                unset($this->firedKeys[$key]);
            }
        }
    }
}