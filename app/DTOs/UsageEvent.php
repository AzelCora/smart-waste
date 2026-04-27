<?php
declare(strict_types=1);
namespace App\DTOs;

final readonly class UsageEvent
{
    public function __construct(
        public string $userId,
        public float  $weightDeposited,
    ) {
        if (!preg_match('/^[0-9a-fA-F]{8}$/', $userId)) {
            throw new \InvalidArgumentException(
                "userId must be an 8-character hexadecimal string, got: '{$userId}'"
            );
        }
        if ($weightDeposited < 0) {
            throw new \InvalidArgumentException('weightDeposited cannot be negative.');
        }
    }
}