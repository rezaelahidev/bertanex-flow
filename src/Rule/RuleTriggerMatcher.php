<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Decides whether an event-driven run should consider a rule (skips schedule-only definitions).
 */
final class RuleTriggerMatcher
{
    public function matchesEvent(RuleDefinition $rule, string $eventName): bool
    {
        $t = $rule->trigger;

        if ($t === null) {
            return true;
        }

        $events  = isset($t['events']) && is_array($t['events']) ? $t['events'] : [];
        $hasSchedule = isset($t['schedule']) && is_array($t['schedule']);

        if ($events === [] && $hasSchedule) {
            return false;
        }

        if ($events === []) {
            return true;
        }

        return in_array($eventName, $events, true);
    }
}
