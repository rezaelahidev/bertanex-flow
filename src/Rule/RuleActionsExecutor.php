<?php

declare(strict_types=1);

namespace MarketingAutomation\Rule;

use MarketingAutomation\Services\ActionDispatcher;
use MarketingAutomation\Services\OutboundAction;

/**
 * Runs rule actions immediately or defers them via {@see DelayedActionScheduler}.
 */
final class RuleActionsExecutor
{
    public function __construct(
        private readonly ActionDispatcher $dispatcher,
        private readonly DelayedActionScheduler $scheduler
    ) {
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed>       $eventPayload
     */
    public function execute(
        int $userId,
        array $actions,
        string $ruleId,
        string $eventName,
        array $eventPayload
    ): void {
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $delay = isset($action['delay_seconds']) ? (int) $action['delay_seconds'] : 0;

            if ($delay > 0) {
                $this->scheduler->schedule($delay, $userId, $action, $ruleId, $eventName);

                continue;
            }

            $this->dispatcher->dispatch($this->toOutbound($userId, $action, $ruleId, $eventName, $eventPayload));
        }
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $eventPayload
     */
    private function toOutbound(
        int $userId,
        array $action,
        string $ruleId,
        string $eventName,
        array $eventPayload
    ): OutboundAction {
        $type       = (string) ($action['type'] ?? '');
        $templateId = (string) ($action['template_id'] ?? '');
        /** @var array<string, mixed> $params */
        $params = isset($action['params']) && is_array($action['params']) ? $action['params'] : [];

        $params['rule_id']      = $ruleId;
        $params['event']        = $eventName;
        $params['event_payload'] = $eventPayload;

        return OutboundAction::custom($type, $userId, $templateId, $params);
    }
}
