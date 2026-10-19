<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Events;

use BertanexFlow\Core\Contracts\EventInterface;

/**
 * Immutable event passed through the Flow dispatcher.
 */
final class GenericEvent implements EventInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $source,
        private readonly array $payload,
        private readonly string $timestamp,
        private readonly ?string $correlationId = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(string $name, array $payload, string $source = 'custom'): self
    {
        $id = isset($payload['event_id']) && is_string($payload['event_id']) && $payload['event_id'] !== ''
            ? $payload['event_id']
            : bin2hex(random_bytes(8));

        return new self(
            $id,
            $name,
            $source,
            $payload,
            gmdate('c'),
            isset($payload['correlation_id']) && is_string($payload['correlation_id']) ? $payload['correlation_id'] : $id
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }
}
