<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

/**
 * Queues SMS jobs for Action Scheduler (async) or WP-Cron fallback.
 */
final class SmsJobQueue
{
    public const HOOK  = 'ma_sms_deliver';
    public const GROUP = 'marketing-automation-sms';

    /**
     * @param array<string, mixed> $job Must include: user_id, template_id, to, body, attempt (int). Optional: meta.
     */
    public function push(array $job): void
    {
        $job = apply_filters('ma_sms_queue_job', $job);

        $json = wp_json_encode($job);
        if ($json === false) {
            return;
        }

        $useAs = apply_filters(
            'ma_sms_queue_use_action_scheduler',
            function_exists('as_enqueue_async_action')
        );

        if ($useAs && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, [$json], self::GROUP);

            return;
        }

        wp_schedule_single_event(time() + 5, self::HOOK, [$json]);
    }

    /**
     * @param array<string, mixed> $job
     */
    public function scheduleRetry(int $delaySeconds, array $job): void
    {
        $json = wp_json_encode($job);
        if ($json === false) {
            return;
        }

        $runAt = time() + max(1, $delaySeconds);

        $useAs = apply_filters(
            'ma_sms_queue_use_action_scheduler',
            function_exists('as_schedule_single_action')
        );

        if ($useAs && function_exists('as_schedule_single_action')) {
            as_schedule_single_action($runAt, self::HOOK, [$json], self::GROUP);

            return;
        }

        wp_schedule_single_event($runAt, self::HOOK, [$json]);
    }
}
