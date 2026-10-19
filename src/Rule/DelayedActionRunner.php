<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

use BertanexFlow\Services\ActionDispatcher;
use BertanexFlow\Services\OutboundAction;

/**
 * Runs one background rule action (Action Scheduler or WP-Cron). Retries with exponential backoff on failure.
 */
final class DelayedActionRunner
{
    public function __construct(
        private readonly ActionDispatcher $dispatcher,
        private readonly DelayedActionScheduler $scheduler
    ) {
    }

    /**
     * Action Scheduler passes each scheduled arg separately; signature is compatible with WP-Cron.
     */
    public function __invoke(mixed $userId, mixed $payloadJson): void
    {
        if (! is_string($payloadJson) || $payloadJson === '') {
            return;
        }

        try {
            $data = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (! is_array($data)) {
            return;
        }

        $uid    = is_numeric($userId) ? (int) $userId : 0;
        $action = $data['action'] ?? null;

        if ($uid < 1 || ! is_array($action)) {
            return;
        }

        unset($action['delay_seconds']);

        $attempt = max(1, (int) ($data['attempt'] ?? 1));

        try {
            $this->dispatcher->dispatch($this->toOutbound($uid, $action, $data));

            /** @param array<string, mixed> $data */
            do_action('gro_background_job_succeeded', $data, $uid, $attempt);
        } catch (\Throwable $e) {
            $this->handleFailure($e, $data, $uid, $attempt);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleFailure(\Throwable $e, array $data, int $userId, int $attempt): void
    {
        $maxAttempts = max(1, (int) apply_filters('gro_background_job_max_attempts', 5, $data, $userId));

        $shouldRetry = (bool) apply_filters('gro_background_job_should_retry', true, $e, $attempt, $data, $userId);

        if (! $shouldRetry || $attempt >= $maxAttempts) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    sprintf(
                        '[Marketing Automation] Background job failed after %d attempt(s): %s',
                        $attempt,
                        $e->getMessage()
                    )
                );
            }

            do_action('gro_background_job_exhausted', $e, $data, $userId, $attempt);

            throw $e;
        }

        $backoff = (int) apply_filters(
            'gro_background_job_retry_delay_seconds',
            min(3600, 60 * (2 ** ($attempt - 1))),
            $attempt,
            $e,
            $data,
            $userId
        );

        $data['attempt'] = $attempt + 1;

        do_action('gro_background_job_retry_scheduled', $e, $data, $userId, $attempt, $backoff);

        $this->scheduler->scheduleEnvelope(max(1, $backoff), $userId, $data);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $envelope
     */
    private function toOutbound(int $userId, array $action, array $envelope): OutboundAction
    {
        $type       = (string) ($action['type'] ?? '');
        $templateId = (string) ($action['template_id'] ?? '');
        /** @var array<string, mixed> $params */
        $params = isset($action['params']) && is_array($action['params']) ? $action['params'] : [];
        $params['rule_id']        = $envelope['rule_id'] ?? '';
        $params['scheduled']      = true;
        $params['source_event']   = $envelope['event'] ?? '';
        $params['attempt']        = (int) ($envelope['attempt'] ?? 1);

        return OutboundAction::custom($type, $userId, $templateId, $params);
    }
}
