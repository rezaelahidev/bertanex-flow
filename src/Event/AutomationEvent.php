<?php

declare(strict_types=1);

namespace BertanexFlow\Event;

/**
 * Generic automation event. Use any string for {@see name()} for custom events.
 */
final class AutomationEvent implements EventInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $name,
        private readonly array $payload = []
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromType(EventType $type, array $payload = []): self
    {
        return new self($type->value, $payload);
    }
}
