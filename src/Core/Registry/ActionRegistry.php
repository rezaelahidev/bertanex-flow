<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Registry;

use BertanexFlow\Core\Contracts\ActionInterface;
use BertanexFlow\Core\Actions\ActionExecutor;
use InvalidArgumentException;

/**
 * Registry for actions.
 *
 * Manages action implementations and execution.
 *
 * @package BertanexFlow\Core\Registry
 */
final class ActionRegistry
{
    /** @var array<string, ActionInterface> Registered actions. */
    private array $actions = [];

    /** @var ActionExecutor Action executor instance. */
    private ActionExecutor $executor;

    public function __construct(ActionExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Register an action.
     *
     * @param ActionInterface $action Action instance.
     * @return void
     */
    public function register(ActionInterface $action): void
    {
        $id = $action->getId();

        if ($id === '' || str_starts_with($id, '_')) {
            return;
        }

        $this->actions[$id] = $action;
    }

    /**
     * Get an action by ID.
     *
     * @param string $id Action identifier.
     * @return ActionInterface|null
     */
    public function get(string $id): ?ActionInterface
    {
        return $this->actions[$id] ?? null;
    }

    /**
     * Check if action is registered.
     *
     * @param string $id Action identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->actions[$id]);
    }

    /**
     * Get all registered actions.
     *
     * @return ActionInterface[]
     */
    public function all(): array
    {
        return array_values($this->actions);
    }

    /**
     * Get available action IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->actions);
    }

    /**
     * Execute an action.
     *
     * @param string $id Action identifier.
     * @param mixed $context Execution context.
     * @return mixed Action result.
     * @throws InvalidArgumentException If action not found.
     */
    public function execute(string $id, mixed $context): mixed
    {
        $action = $this->get($id);

        if ($action === null) {
            throw new InvalidArgumentException(
                sprintf('Action "%s" is not registered.', $id)
            );
        }

        return $this->executor->execute($action, $context);
    }

    /**
     * Execute an action with built-in error handling.
     *
     * @param string $id Action identifier.
     * @param mixed $context Execution context.
     * @return array{success: bool, result: mixed|null, error: string|null}
     */
    public function executeSafe(string $id, mixed $context): array
    {
        try {
            $result = $this->execute($id, $context);

            return [
                'success' => true,
                'result' => $result,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get action metadata.
     *
     * @param string $id Available only when action supports extended metadata.
     * @return array<mixed>|null
     */
    public function getMetadata(string $id): ?array
    {
        $action = $this->get($id);

        if ($action === null) {
            return null;
        }

        return [
            'name' => $action->getName(),
            'description' => $action->getDescription(),
            'config' => $action->getConfig(),
        ];
    }

    /**
     * Clear all registered actions (for testing).
     *
     * @return void
     */
    public static function resetForTesting(): void
    {
        // Instance will be recreated
    }
}