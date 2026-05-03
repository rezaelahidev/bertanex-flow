<?php

declare(strict_types=1);

namespace MarketingAutomation;

use MarketingAutomation\Event\AutomationEvent;
use MarketingAutomation\Event\EventDispatcher;
use MarketingAutomation\Event\WordPressEventBridge;
use MarketingAutomation\Loader\HooksLoader;
use MarketingAutomation\Rule\ConditionEvaluator;
use MarketingAutomation\Rule\DelayedActionRunner;
use MarketingAutomation\Rule\DelayedActionScheduler;
use MarketingAutomation\Rule\JsonFileRulesRepository;
use MarketingAutomation\Rule\RuleActionsExecutor;
use MarketingAutomation\Rule\RuleEngine;
use MarketingAutomation\Rule\ScheduledRulesCoordinator;
use MarketingAutomation\Rule\UserStateProviderInterface;
use MarketingAutomation\Rule\WordPressRulesScheduler;
use MarketingAutomation\Rule\WordPressUserStateProvider;
use MarketingAutomation\Services\ActionDispatcher;
use MarketingAutomation\Services\AutomationServiceIds;
use MarketingAutomation\Services\AutomationServices;
use MarketingAutomation\Sms\DefaultSmsTemplateProvider;
use MarketingAutomation\Sms\LoggingSmsTransport;
use MarketingAutomation\Sms\QueuedTemplateSmsService;
use MarketingAutomation\Sms\SmsDeliveryWorker;
use MarketingAutomation\Sms\SmsJobQueue;
use MarketingAutomation\Sms\SmsPhoneResolver;
use MarketingAutomation\Sms\SmsRateLimiter;
use MarketingAutomation\Sms\SmsServiceInterface;
use MarketingAutomation\Sms\SmsTemplateProviderInterface;
use MarketingAutomation\Sms\SmsTemplateRenderer;
use MarketingAutomation\Sms\SmsTransportInterface;

final class Plugin
{
    public function __construct(
        private readonly Container $container,
        private readonly HooksLoader $hooks
    ) {
    }

    public function run(): void
    {
        $this->registerServices();

        $this->hooks->add_action($this, 'onPluginsLoaded', 'plugins_loaded', 5, 0);
        $this->hooks->register();
    }

    public function onPluginsLoaded(): void
    {
        $services = $this->container->get(AutomationServices::class);
        $this->seedDefaultAutomationServices($services);

        /**
         * Register or replace services (SMS, future email, etc.).
         *
         * @param AutomationServices $services
         */
        do_action('ma_register_automation_services', $services);

        $actionDispatcher = $this->container->get(ActionDispatcher::class);

        /**
         * Register custom outbound action handlers or replace built-ins.
         *
         * @param ActionDispatcher $actionDispatcher
         */
        do_action('ma_register_action_handlers', $actionDispatcher);

        $this->container->get(RuleEngine::class);

        $this->hooks->add_action_callable(
            $this->container->get(SmsDeliveryWorker::class),
            SmsJobQueue::HOOK,
            10,
            1
        );

        $this->hooks->add_action_callable(
            $this->container->get(DelayedActionRunner::class),
            DelayedActionScheduler::HOOK,
            10,
            2
        );

        $eventDispatcher = $this->container->get(EventDispatcher::class);
        $this->hooks->add_action_callable(
            static function (...$args) use ($eventDispatcher): void {
                $name = (string) ($args[0] ?? '');
                if ($name === '') {
                    return;
                }

                $raw   = $args[1] ?? [];
                $payload = is_array($raw) ? $raw : [];

                $eventDispatcher->dispatch(new AutomationEvent($name, $payload));
            },
            'ma_emit_event',
            10,
            2
        );

        $this->container->get(WordPressEventBridge::class)->register($this->hooks);

        $this->container->get(WordPressRulesScheduler::class)->register($this->hooks);

        $this->hooks->register();
    }

    private function registerServices(): void
    {
        $c = $this->container;

        $c->singleton(EventDispatcher::class, static fn () => new EventDispatcher());

        $c->singleton(
            WordPressEventBridge::class,
            static fn (Container $c) => new WordPressEventBridge(
                $c->get(EventDispatcher::class)
            )
        );

        $c->singleton(AutomationServices::class, static fn () => new AutomationServices());

        $c->singleton(
            ActionDispatcher::class,
            static fn (Container $c) => new ActionDispatcher(
                $c->get(AutomationServices::class)
            )
        );

        $c->singleton(SmsTransportInterface::class, static fn () => new LoggingSmsTransport());

        $c->singleton(SmsTemplateRenderer::class, static fn () => new SmsTemplateRenderer());

        $c->singleton(SmsTemplateProviderInterface::class, static fn () => new DefaultSmsTemplateProvider());

        $c->singleton(SmsPhoneResolver::class, static fn () => new SmsPhoneResolver());

        $c->singleton(SmsRateLimiter::class, static fn () => new SmsRateLimiter());

        $c->singleton(SmsJobQueue::class, static fn () => new SmsJobQueue());

        $c->singleton(
            SmsDeliveryWorker::class,
            static fn (Container $c) => new SmsDeliveryWorker(
                $c->get(SmsTransportInterface::class),
                $c->get(SmsJobQueue::class)
            )
        );

        $c->singleton(
            QueuedTemplateSmsService::class,
            static fn (Container $c) => new QueuedTemplateSmsService(
                $c->get(SmsTemplateRenderer::class),
                $c->get(SmsTemplateProviderInterface::class),
                $c->get(SmsPhoneResolver::class),
                $c->get(SmsRateLimiter::class),
                $c->get(SmsJobQueue::class)
            )
        );

        $c->singleton(
            SmsServiceInterface::class,
            static fn (Container $c) => $c->get(QueuedTemplateSmsService::class)
        );

        $c->singleton(
            UserStateProviderInterface::class,
            static fn () => new WordPressUserStateProvider()
        );

        $c->singleton(
            JsonFileRulesRepository::class,
            static fn () => new JsonFileRulesRepository(\MA_PLUGIN_DIR . 'rules/default.rules.json')
        );

        $c->singleton(ConditionEvaluator::class, static fn () => new ConditionEvaluator());

        $c->singleton(DelayedActionScheduler::class, static fn () => new DelayedActionScheduler());

        $c->singleton(
            RuleActionsExecutor::class,
            static fn (Container $c) => new RuleActionsExecutor(
                $c->get(ActionDispatcher::class),
                $c->get(DelayedActionScheduler::class)
            )
        );

        $c->singleton(
            DelayedActionRunner::class,
            static fn (Container $c) => new DelayedActionRunner(
                $c->get(ActionDispatcher::class),
                $c->get(DelayedActionScheduler::class)
            )
        );

        $c->singleton(
            ScheduledRulesCoordinator::class,
            static fn (Container $c) => new ScheduledRulesCoordinator(
                $c->get(JsonFileRulesRepository::class),
                $c->get(UserStateProviderInterface::class),
                $c->get(ConditionEvaluator::class),
                $c->get(RuleActionsExecutor::class)
            )
        );

        $c->singleton(
            WordPressRulesScheduler::class,
            static fn (Container $c) => new WordPressRulesScheduler(
                $c->get(JsonFileRulesRepository::class),
                $c->get(ScheduledRulesCoordinator::class)
            )
        );

        $c->singleton(
            RuleEngine::class,
            static fn (Container $c) => new RuleEngine(
                $c->get(EventDispatcher::class),
                $c->get(JsonFileRulesRepository::class),
                $c->get(UserStateProviderInterface::class),
                $c->get(ConditionEvaluator::class),
                $c->get(RuleActionsExecutor::class)
            )
        );
    }

    private function seedDefaultAutomationServices(AutomationServices $services): void
    {
        if (! $services->has(AutomationServiceIds::SMS)) {
            $services->register(AutomationServiceIds::SMS, $this->container->get(QueuedTemplateSmsService::class));
        }
    }
}
