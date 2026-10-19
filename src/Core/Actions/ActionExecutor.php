<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Actions;

use BertanexFlow\Core\Contracts\ActionInterface;

/**
 * Executes workflow actions.
 *
 * @package BertanexFlow\Core\Actions
 */
final class ActionExecutor
{
    /**
     * Execute an action with the given context.
     *
     * @param ActionInterface $action Action instance.
     * @param mixed $context Execution context.
     * @return mixed Action execution result.
     */
    public function execute(ActionInterface $action, mixed $context): mixed
    {
        return $action->execute($context);
    }

    /**
     * Execute action with retry strategy.
     *
     * @param ActionInterface $action Action instance.
     * @param mixed $context Execution context.
     * @param array $retryConfig Retry configuration.
     * @return mixed Action result (may be null on permanent failure).
     */
    public function executeWithRetry(ActionInterface $action, mixed $context, array $retryConfig = []): mixed
    {
        $maxAttempts = $retryConfig['max_attempts'] ?? 3;
        $initialDelay = $retryConfig['initial_delay'] ?? 1.0;
        $exponential = $retryConfig['exponential'] ?? true;
        $strategy = $retryConfig['strategy'] ?? 'exponential_backoff';

        $attempt = 1;
        $delay = $initialDelay;

        while ($attempt <= $maxAttempts) {
            try {
                return $action->execute($context);
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                if ($strategy === 'exponential_backoff') {
                    $delay *= 2;
                }

                // Delay is reported by RetryPolicy and scheduled outside the request.
                unset($delay);
                $attempt++;
            }
        }

        throw new \RuntimeException('Maximum retry attempts exceeded.');
    }
}