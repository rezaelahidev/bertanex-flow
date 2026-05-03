<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Evaluates rules tied to a recurring WP-Cron hook (see JSON {@code trigger.schedule}).
 *
 * Candidate users come from {@see 'gro_scheduled_rules_user_ids'} (default: none).
 */
final class ScheduledRulesCoordinator
{
    public function __construct(
        private readonly RulesRepositoryInterface $rules,
        private readonly UserStateProviderInterface $userState,
        private readonly ConditionEvaluator $evaluator,
        private readonly RuleActionsExecutor $executor
    ) {
    }

    public function runScheduleHook(string $hookName): void
    {
        $definitions = $this->rules->getRules();

        if ($definitions === []) {
            return;
        }

        usort(
            $definitions,
            static fn (RuleDefinition $a, RuleDefinition $b): int => $a->priority <=> $b->priority
        );

        $eventName = 'SCHEDULED:' . $hookName;

        foreach ($definitions as $rule) {
            if (! $rule->enabled) {
                continue;
            }

            $sched = $rule->trigger['schedule'] ?? null;
            if (! is_array($sched) || ($sched['hook'] ?? '') !== $hookName) {
                continue;
            }

            /** @var list<int|string> $userIds */
            $userIds = apply_filters('gro_scheduled_rules_user_ids', [], $hookName, $rule);
            if (! is_array($userIds) || $userIds === []) {
                continue;
            }

            foreach ($userIds as $uid) {
                $userId = (int) $uid;
                if ($userId < 1) {
                    continue;
                }

                $state = $this->userState->getStateForUser($userId);

                if (! $this->evaluator->evaluate($rule->conditions, $state)) {
                    continue;
                }

                $this->executor->execute(
                    $userId,
                    $rule->actions,
                    $rule->id,
                    $eventName,
                    ['schedule_hook' => $hookName]
                );
            }
        }
    }
}
