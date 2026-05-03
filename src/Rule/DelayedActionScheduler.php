<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Schedules delayed rule actions. Prefers Action Scheduler when loaded; falls back to WP-Cron.
 */
final class DelayedActionScheduler
{
    /** @see add_action() callback in {@see DelayedActionRunner} */
    public const HOOK = 'gro_delayed_rule_action';

    /** Action Scheduler group for Tools → Scheduled Actions UI. */
    public const ACTION_SCHEDULER_GROUP = 'growth-orchestrator';

    /**
     * @param array<string, mixed> $action Original JSON action row (including type, template_id, params).
     */
    public function schedule(int $delaySeconds, int $userId, array $action, string $ruleId, string $eventName): void
    {
        $this->scheduleEnvelope($delaySeconds, $userId, [
            'action'   => $action,
            'rule_id'  => $ruleId,
            'event'    => $eventName,
            'attempt'  => 1,
        ]);
    }

    /**
     * Schedule a follow-up attempt (used by {@see DelayedActionRunner} retries).
     *
     * @param array{action: array<string, mixed>, rule_id: string, event: string, attempt: int, ...}|array<string, mixed> $envelope
     */
    public function scheduleEnvelope(int $delaySeconds, int $userId, array $envelope): void
    {
        if ($delaySeconds < 1 || $userId < 1) {
            return;
        }

        if (! isset($envelope['attempt']) || (int) $envelope['attempt'] < 1) {
            $envelope['attempt'] = 1;
        }

        $runAt = time() + $delaySeconds;

        $payload = wp_json_encode($envelope);
        if ($payload === false) {
            return;
        }

        $useAs = apply_filters(
            'gro_use_action_scheduler_for_background_jobs',
            function_exists('as_schedule_single_action')
        );

        if ($useAs && function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                $runAt,
                self::HOOK,
                [$userId, $payload],
                self::ACTION_SCHEDULER_GROUP
            );

            return;
        }

        wp_schedule_single_event($runAt, self::HOOK, [$userId, $payload]);
    }
}
