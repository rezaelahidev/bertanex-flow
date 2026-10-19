<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Contracts;

/**
 * Defines the event contract.
 *
 * Events represent something that happened in the system or external world.
 *
 * @package BertanexFlow\Core\Events
 */
interface EventInterface
{
    /**
     * Get event unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get event name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get event source.
     *
     * Examples: 'woocommerce', 'wordpress', 'webhook', 'cron'
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Get event timestamp.
     *
     * @return string ISO 8601 timestamp.
     */
    public function getTimestamp(): string;

    /**
     * Get event payload data.
     *
     * @return array<mixed>
     */
    public function getPayload(): array;

    /**
     * Get correlation ID (for tracking workflow executions).
     *
     * @return string|null
     */
    public function getCorrelationId(): ?string;
}