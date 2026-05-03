<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Loader\HooksLoader;

/**
 * Registers WP-Cron events and hook listeners for rules that declare {@code trigger.schedule}.
 */
final class WordPressRulesScheduler
{
    /** @var list<string> */
    private const ALLOWED_RECURRENCE = ['hourly', 'twicedaily', 'daily', 'weekly'];

    public function __construct(
        private readonly RulesRepositoryInterface $rules,
        private readonly ScheduledRulesCoordinator $coordinator
    ) {
    }

    public function register(HooksLoader $hooks): void
    {
        $definitions = $this->rules->getRules();
        /** @var array<string, true> $scheduledHooks */
        $scheduledHooks = [];

        foreach ($definitions as $rule) {
            if (! $rule->enabled) {
                continue;
            }

            $sched = $rule->trigger['schedule'] ?? null;
            if (! is_array($sched)) {
                continue;
            }

            $hookName  = isset($sched['hook']) ? (string) $sched['hook'] : '';
            $recurrence = isset($sched['recurrence']) ? (string) $sched['recurrence'] : '';

            if ($hookName === '' || ! in_array($recurrence, self::ALLOWED_RECURRENCE, true)) {
                continue;
            }

            if (isset($scheduledHooks[$hookName])) {
                continue;
            }

            $scheduledHooks[$hookName] = true;

            if (! wp_next_scheduled($hookName)) {
                wp_schedule_event(time() + 120, $recurrence, $hookName);
            }

            $coordinator = $this->coordinator;
            $hooks->add_action_callable(
                static function () use ($coordinator, $hookName): void {
                    $coordinator->runScheduleHook($hookName);
                },
                $hookName,
                10,
                0
            );
        }
    }
}
