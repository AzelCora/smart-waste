<?php

declare(strict_types=1);

namespace SmartBin;

// ---------------------------------------------------------------------------
// AlertStateRepository — persistence contract
// ---------------------------------------------------------------------------

/**
 * Stores which alert conditions have already been signalled for each bin,
 * preventing duplicate messages across multiple generator invocations.
 *
 * Implement this interface backed by Redis, a relational DB, or any other
 * durable store for production deployments. The in-memory implementation
 * below is suitable for single-process testing and demos.
 */
interface AlertStateRepository
{
    /** Returns true if the given dedup key has been flagged as fired. */
    public function hasAlreadyFired(string $key): bool;

    /** Persist that the alert identified by $key has fired. */
    public function markFired(string $key): void;

    /** Remove the fired flag so the alert can trigger again. */
    public function clearFired(string $key): void;
}

// ---------------------------------------------------------------------------
// InMemoryAlertStateRepository
// ---------------------------------------------------------------------------

/**
 * Non-persistent implementation — state lives only for the lifetime of the
 * PHP process. Use this for unit tests or single-request CLI scripts.
 */
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

    /**
     * Utility for testing — wipe all state for a specific bin.
     *
     * @param string $binId
     */
    public function resetBin(string $binId): void
    {
        foreach (array_keys($this->firedKeys) as $key) {
            if (str_starts_with($key, "{$binId}:")) {
                unset($this->firedKeys[$key]);
            }
        }
    }

    /** Dump the raw fired-key set (useful for debugging / assertions). */
    public function dump(): array
    {
        return array_keys($this->firedKeys);
    }
}
