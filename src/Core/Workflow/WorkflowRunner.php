<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

use BertanexFlow\Core\Data\DataContext;
use BertanexFlow\Core\Data\DataMapper;
use BertanexFlow\Core\Data\TransformationRegistry;
use BertanexFlow\Core\Data\VariableResolver;
use BertanexFlow\Reliability\RetryPolicy;
use BertanexFlow\Security\OutboundUrlGuard;

/**
 * Runs one workflow against an event payload.
 *
 * Persistence and scheduling stay outside this class so the rules can be tested
 * without WordPress.
 */
final class WorkflowRunner
{
    public function __construct(
        private readonly GuardEvaluator $guards = new GuardEvaluator(),
        private readonly TransformationRegistry $transformations = new TransformationRegistry(),
        private readonly ?OutboundUrlGuard $urls = null,
        private readonly mixed $http = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, steps: list<array{key: string, status: string, message: string}>, error: string, retry_delay: int}
     */
    public function run(WorkflowDefinition $workflow, array $payload, int $attempt = 1): array
    {
        $steps = [];
        $context = DataContext::fromArray($payload);
        if (! $context->has('site.url') && function_exists('home_url')) {
            $context->merge(['site' => ['url' => home_url('/')]]);
        }

        $steps[] = $this->step('trigger', 'passed', 'Trigger received.');
        $steps[] = $this->step('context', 'passed', 'Context generated.');

        if (! $this->guards->passes($context, $workflow->getConditions())) {
            $steps[] = $this->step('condition', 'skipped', 'Condition did not pass.');

            return $this->result('skipped', $steps, '');
        }

        $steps[] = $this->step('condition', 'passed', 'Condition passed.');
        $resolver = new VariableResolver($context, $this->transformations);

        foreach ($workflow->getActions() as $index => $action) {
            $type = (string) ($action['type'] ?? '');
            $config = is_array($action['config'] ?? null) ? $action['config'] : [];

            try {
                $message = $this->perform($type, $config, $context, $resolver);
                $steps[] = $this->step('action_' . $index, 'passed', $message);
            } catch (\Throwable $error) {
                $steps[] = $this->step('action_' . $index, 'failed', $error->getMessage());
                $policy = RetryPolicy::fromArray(is_array($workflow->getMetadata()['retry'] ?? null) ? $workflow->getMetadata()['retry'] : []);
                $delay = $policy->shouldRetry($attempt) ? $policy->delayForAttempt($attempt) : 0;

                return $this->result('failed', $steps, $error->getMessage(), $delay);
            }
        }

        return $this->result('completed', $steps, '');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function perform(string $type, array $config, DataContext $context, VariableResolver $resolver): string
    {
        return match ($type) {
            'log.message' => 'Logged: ' . $resolver->resolve((string) ($config['message'] ?? '')),
            'data.map' => $this->mapData($context, $config),
            'webhook.request' => $this->request($resolver, $config),
            default => throw new \RuntimeException(sprintf('Unknown action "%s".', $type)),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mapData(DataContext $context, array $config): string
    {
        $mappings = is_array($config['mappings'] ?? null) ? $config['mappings'] : [];
        $mapped = (new DataMapper($this->transformations))->map($context, $mappings);
        $target = isset($config['into']) && is_string($config['into']) && $config['into'] !== ''
            ? $config['into']
            : 'mapped';
        $context->merge([$target => $mapped]);

        return 'Data mapped.';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function request(VariableResolver $resolver, array $config): string
    {
        $method = strtoupper($resolver->resolve((string) ($config['method'] ?? 'POST')));
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \InvalidArgumentException('HTTP method is not allowed.');
        }

        $url = $resolver->resolve((string) ($config['url'] ?? ''));
        ($this->urls ?? new OutboundUrlGuard())->assertAllowed($url);

        $body = $resolver->resolveArray(is_array($config['body'] ?? null) ? $config['body'] : []);
        $http = $this->http;

        if (is_callable($http)) {
            $status = (int) $http($method, $url, $body);
        } elseif (function_exists('wp_remote_request')) {
            $response = wp_remote_request($url, [
                'method' => $method,
                'timeout' => 15,
                'body' => $body,
            ]);

            if (is_wp_error($response)) {
                throw new \RuntimeException($response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
        } else {
            throw new \RuntimeException('No HTTP client is available.');
        }

        if ($status >= 400 || $status === 0) {
            throw new \RuntimeException(sprintf('HTTP request failed with status %d.', $status));
        }

        return sprintf('HTTP request succeeded (%d).', $status);
    }

    /**
     * @return array{key: string, status: string, message: string}
     */
    private function step(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * @param list<array{key: string, status: string, message: string}> $steps
     * @return array{status: string, steps: list<array{key: string, status: string, message: string}>, error: string, retry_delay: int}
     */
    private function result(string $status, array $steps, string $error, int $retryDelay = 0): array
    {
        return [
            'status' => $status,
            'steps' => $steps,
            'error' => $error,
            'retry_delay' => $retryDelay,
        ];
    }
}
