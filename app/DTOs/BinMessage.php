<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Immutable value object representing a single message emitted by a smart bin.
 */
final class BinMessage implements \JsonSerializable
{
    public function __construct(
        public readonly string             $binId,
        public readonly float              $locationX,
        public readonly float              $locationY,
        public readonly MessageType        $type,
        public readonly array              $payload,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string      $binId,
        float       $locationX,
        float       $locationY,
        MessageType $type,
        array       $payload,
    ): self {
        return new self(
            binId:      $binId,
            locationX:  $locationX,
            locationY:  $locationY,
            type:       $type,
            payload:    $payload,
            occurredAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'bin_id'      => $this->binId,
            'location'    => ['x' => $this->locationX, 'y' => $this->locationY],
            'type'        => $this->type->value,
            'payload'     => $this->payload,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}