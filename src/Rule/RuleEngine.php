<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Event\EventDispatcher;
use GrowthOrchestrator\Event\EventInterface;

/**
 * Event-driven evaluation of JSON rules: trigger → conditions → actions (with optional delay).
 */
final class RuleEngine
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly RulesRepositoryInterface $rules,
        private readonly UserStateProviderInterface $userState,
        private readonly ConditionEvaluator $evaluator,
        private readonly RuleActionsExecutor $executor,
        private readonly RuleTriggerMatcher $triggerMatcher = new RuleTriggerMatcher()
    ) {
        $this->dispatcher->listen(
            EventDispatcher::WILDCARD,
            function (EventInterface $event): void {
                $this->handle($event);
            }
        );
    }

    private function handle(EventInterface $event): void
    {
        $userId = (int) ($event->payload()['user_id'] ?? 0);

        if ($userId < 1) {
            return;
        }

        $definitions = $this->rules->getRules();

        if ($definitions === []) {
            return;
        }

        usort(
            $definitions,
            static fn (RuleDefinition $a, RuleDefinition $b): int => $a->priority <=> $b->priority
        );

        $state       = $this->userState->getStateForUser($userId);
        $eventName   = $event->name();
        $rawPayload  = $event->payload();

        foreach ($definitions as $rule) {
            if (! $rule->enabled) {
                continue;
            }

            if (! $this->triggerMatcher->matchesEvent($rule, $eventName)) {
                continue;
            }

            if (! $this->evaluator->evaluate($rule->conditions, $state)) {
                continue;
            }

            /**
             * Fires after a rule's conditions matched and actions are about to run.
             *
             * @param array{rule_id: string, rule_label: string, user_id: int, event: string} $context
             */
            do_action(
                'gro_rule_matched',
                [
                    'rule_id'    => $rule->id,
                    'rule_label' => (string) ($rule->label ?? ''),
                    'user_id'    => $userId,
                    'event'      => $eventName,
                ]
            );

            $this->executor->execute($userId, $rule->actions, $rule->id, $eventName, $rawPayload);
        }
    }
}
