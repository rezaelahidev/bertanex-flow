<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

use BertanexFlow\Reliability\ExecutionStore;

/**
 * Connects workflow runs to execution history, idempotency, and retries.
 */
final class WorkflowExecutor
{
    public const RETRY_HOOK = 'bertanex_flow_retry_execution';

    public function __construct(
        private readonly WorkflowRepository $workflows = new WorkflowRepository(),
        private readonly WorkflowRunner $runner = new WorkflowRunner(),
        private readonly ExecutionStore $executions = new ExecutionStore()
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(string $eventName, array $payload, int $attempt = 1): void
    {
        foreach ($this->workflows->all() as $workflow) {
            if (! $workflow->isActive() || ! $this->matches($workflow, $eventName)) {
                continue;
            }

            if ($this->isDuplicate($workflow, $eventName, $payload)) {
                continue;
            }

            $eventId = isset($payload['event']['id']) ? (string) $payload['event']['id'] : $eventName;
            $key = hash('sha256', $workflow->getId() . '|' . $eventId);
            if ($attempt === 1 && $this->executions->hasClaim($key)) {
                continue;
            }

            $executionId = $this->executions->start($key . ':' . $attempt, $workflow->getId(), $eventName, $payload, $attempt);
            $result = $this->runner->run($workflow, $payload, $attempt);

            foreach ($result['steps'] as $step) {
                $this->executions->addStep($executionId, $step['key'], $step['status'], $step['message']);
            }

            if ($result['status'] === 'completed') {
                $this->executions->claim($key, $executionId);
                $this->executions->finish($executionId, 'completed');
                continue;
            }

            if ($result['status'] === 'failed' && $result['retry_delay'] > 0) {
                $this->scheduleRetry($workflow->getId(), $eventName, $payload, $attempt + 1, $result['retry_delay']);
                $this->executions->finish($executionId, 'retrying', $result['error']);
                continue;
            }

            $this->executions->finish($executionId, $result['status'], $result['error']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function replay(int $executionId): bool
    {
        $row = $this->executions->find($executionId);
        if ($row === null) {
            return false;
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $this->handle((string) $row['event_name'], $payload, (int) $row['attempt'] + 1);

        return true;
    }

    public function register(): void
    {
        add_action('bertanex_flow_event', function (string $name, array $payload = []): void {
            $this->handle($name, $payload);
        }, 10, 2);

        add_action(self::RETRY_HOOK, function (string $workflowId, string $eventName, array $payload, int $attempt): void {
            unset($workflowId);
            $this->handle($eventName, $payload, $attempt);
        }, 10, 4);
    }

    private function matches(WorkflowDefinition $workflow, string $eventName): bool
    {
        foreach ($workflow->getTriggers() as $trigger) {
            if (($trigger['type'] ?? '') === $eventName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isDuplicate(WorkflowDefinition $workflow, string $eventName, array $payload): bool
    {
        $window = (int) ($workflow->getMetadata()['dedupe_window'] ?? 0);
        if ($window <= 0 || ! function_exists('set_transient')) {
            return false;
        }

        $key = 'bertanex_flow_dedupe_' . md5($workflow->getId() . '|' . $eventName . '|' . wp_json_encode($payload));
        if (get_transient($key)) {
            return true;
        }

        set_transient($key, 1, $window);

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function scheduleRetry(string $workflowId, string $eventName, array $payload, int $attempt, int $delay): void
    {
        $timestamp = time() + $delay;
        $args = [$workflowId, $eventName, $payload, $attempt];

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, self::RETRY_HOOK, $args, 'bertanex-flow');
            return;
        }

        wp_schedule_single_event($timestamp, self::RETRY_HOOK, $args);
    }
}
