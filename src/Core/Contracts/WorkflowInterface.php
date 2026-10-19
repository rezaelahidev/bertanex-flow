<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Contracts;

use JsonSerializable;

/**
 * Defines the workflow contract.
 *
 * Workflows are declarative automation constructs comprising:
 * - Triggers (events that start workflows)
 * - Conditions (guards before execution)
 * - Actions (operations to perform)
 *
 * @package BertanexFlow\Core\Workflow
 */
interface WorkflowInterface extends JsonSerializable
{
    /**
     * Get workflow version.
     *
     * @return
     */
    public function getVersion(): int;

    /**
     * Get workflow unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get workflow display name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get workflow description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get workflow triggers.
     *
     * @return array<string, array{type: string, config: mixed}>
     */
    public function getTriggers(): array;

    /**
     * Get workflow conditions.
     *
     * @return array<string, array{field: string, operator: string, value: mixed}>
     */
    public function getConditions(): array;

    /**
     * Get workflow actions.
     *
     * @return array<string, array{type: string, config: mixed}>
     */
    public function getActions(): array;

    /**
     * Get campaign/tracking metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Validate workflow configuration.
     *
     * @throws \InvalidArgumentException If workflow is invalid.
     * @return bool
     */
    public function validate(): bool;
}