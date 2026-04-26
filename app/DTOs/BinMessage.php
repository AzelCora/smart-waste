<?php

declare(strict_types=1);

namespace SmartBin;

/**
 * Immutable value object representing a single message emitted by a smart bin.
 *
 * Every message carries the bin's identity and coordinates so consumers
 * can route or display it without additional lookups.
 */
final class BinMessage implements \JsonSerializable
{
    /**
     * @param string               $binId       Unique bin identifier
     * @param float                $locationX   Bin X coordinate
     * @param float                $locationY   Bin Y coordinate
     * @param MessageType          $type        Semantic category of the message
     * @param array<string, mixed> $payload     Alert-specific data (capacity %, weight, …)
     * @param \DateTimeImmutable   $occurredAt  When the condition was detected
     */
    public function __construct(
        public readonly string           $binId,
        public readonly float            $locationX,
        public readonly float            $locationY,
        public readonly MessageType      $type,
        public readonly array            $payload,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}

    // ------------------------------------------------------------------
    // Convenience factory — keeps call sites readable
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    // Serialisation
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
