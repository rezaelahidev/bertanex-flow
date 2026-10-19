<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Contracts;

/**
 * Defines the action contract.
 *
 * Actions represent operations that workflows can perform.
 *
 * @package BertanexFlow\Core\Actions
 */
interface ActionInterface
{
    /**
     * Get action unique identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get action display name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get action description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Execute this action with the given context.
     *
     * @param mixed $context Execution context containing data.
     * @return mixed Action execution result.
     * @throws \RuntimeException If action execution fails.
     */
    public function execute(mixed $context): mixed;

    /**
     * Get any custom configuration for this action.
     *
     * @return array<mixed>
     */
    public function getConfig(): array;
}