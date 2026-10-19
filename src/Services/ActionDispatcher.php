<?php

declare(strict_types=1);

namespace BertanexFlow\Services;

use BertanexFlow\Sms\SmsServiceInterface;

/**
 * Routes rule actions to registered services and WordPress hooks for extension.
 */
final class ActionDispatcher
{
    /** @var array<string, callable(OutboundAction): void> */
    private array $handlers = [];

    public function __construct(
        private readonly AutomationServices $services
    ) {
        $this->registerCoreHandlers();
    }

    /**
     * Register or replace a handler for an action "type" string (e.g. from JSON rules).
     *
     * @param callable(OutboundAction): void $handler
     */
    public function registerHandler(string $actionType, callable $handler): void
    {
        $this->handlers[$actionType] = $handler;
    }

    public function unregisterHandler(string $actionType): void
    {
        unset($this->handlers[$actionType]);
    }

    public function dispatch(OutboundAction $action): void
    {
        $type = $action->type();

        if (isset($this->handlers[$type])) {
            ($this->handlers[$type])($action);

            return;
        }

        /**
         * Fires when no handler is registered for this action type.
         *
         * @param OutboundAction $action
         */
        do_action('gro_unhandled_outbound_action', $action);
    }

    private function registerCoreHandlers(): void
    {
        $this->handlers['send_sms'] = function (OutboundAction $action): void {
            $sms = $this->services->getAs(AutomationServiceIds::SMS, SmsServiceInterface::class);
            $sms->send(
                $action->userId(),
                $action->templateId(),
                $action->params()
            );
        };

        $this->handlers['send_email'] = function (OutboundAction $action): void {
            /**
             * Fires for send_email actions. Register an email service on {@see AutomationServices}
             * or handle the hook until a first-class email module ships.
             *
             * @param OutboundAction $action
             */
            do_action('gro_outbound_send_email', $action);
        };

        $this->handlers['send_discount'] = function (OutboundAction $action): void {
            /** @param OutboundAction $action */
            do_action('gro_outbound_send_discount', $action);
        };

        $this->handlers['webhook'] = function (OutboundAction $action): void {
            /** @param OutboundAction $action */
            do_action('gro_outbound_webhook', $action);
        };

        $this->handlers['log'] = function (OutboundAction $action): void {
            if (! defined('WP_DEBUG') || ! WP_DEBUG) {
                return;
            }

            error_log(
                sprintf(
                    '[Marketing Automation] log action user=%d template=%s params=%s',
                    $action->userId(),
                    $action->templateId(),
                    wp_json_encode($action->params()) ?: '{}'
                )
            );
        };
    }
}
