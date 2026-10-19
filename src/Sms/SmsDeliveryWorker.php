<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

use BertanexFlow\Sms\Exception\SmsTransportException;

/**
 * Action Scheduler / WP-Cron worker: delivers one queued SMS with backoff retries.
 */
final class SmsDeliveryWorker
{
    public function __construct(
        private readonly SmsTransportResolver $transportResolver,
        private readonly SmsJobQueue $queue
    ) {
    }

    public function __invoke(mixed $payloadJson): void
    {
        if (! is_string($payloadJson) || $payloadJson === '') {
            return;
        }

        try {
            /** @var array<string, mixed> $job */
            $job = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $to   = isset($job['to']) && is_string($job['to']) ? $job['to'] : '';
        $body = isset($job['body']) && is_string($job['body']) ? $job['body'] : '';

        if ($to === '' || $body === '') {
            return;
        }

        $attempt = max(1, (int) ($job['attempt'] ?? 1));

        try {
            $this->transportResolver->resolve()->deliver($to, $body, $job);

            do_action('gro_sms_delivered', $job, $attempt);
        } catch (\Throwable $e) {
            $this->handleFailure($e, $job, $attempt);
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleFailure(\Throwable $e, array $job, int $attempt): void
    {
        $max = max(1, (int) apply_filters('gro_sms_delivery_max_attempts', 5, $job));

        $defaultRetry = true;
        if ($e instanceof SmsTransportException) {
            $defaultRetry = $e->retrySuggested();
        }

        $shouldRetry = (bool) apply_filters('gro_sms_delivery_should_retry', $defaultRetry, $e, $attempt, $job);

        if (! $shouldRetry || $attempt >= $max) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    sprintf(
                        '[Marketing Automation SMS] delivery exhausted after %d attempt(s): %s',
                        $attempt,
                        $e->getMessage()
                    )
                );
            }

            do_action('gro_sms_delivery_exhausted', $e, $job, $attempt);

            throw $e;
        }

        $job['attempt'] = $attempt + 1;

        $delay = (int) apply_filters(
            'gro_sms_delivery_retry_seconds',
            min(600, 30 * (2 ** ($attempt - 1))),
            $attempt,
            $e,
            $job
        );

        do_action('gro_sms_delivery_retry_scheduled', $e, $job, $attempt, $delay);

        $this->queue->scheduleRetry($delay, $job);
    }
}
