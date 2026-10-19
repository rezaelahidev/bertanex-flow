<?php

declare(strict_types=1);

namespace BertanexFlow;

use BertanexFlow\Rule\RuleEngine;
use BertanexFlow\Sms\SmsJobQueue;
use BertanexFlow\Db\RulesDbSchema;
use BertanexFlow\Loader\HooksLoader;
use BertanexFlow\Sms\SmsRateLimiter;
use BertanexFlow\Sms\SmsPhoneResolver;
use BertanexFlow\Event\AutomationEvent;
use BertanexFlow\Event\EventDispatcher;
use BertanexFlow\Sms\SmsDeliveryWorker;
use BertanexFlow\Rule\ConditionEvaluator;
use BertanexFlow\Sms\SmsServiceInterface;
use BertanexFlow\Sms\SmsTemplateRenderer;
use BertanexFlow\Rule\DelayedActionRunner;
use BertanexFlow\Rule\RuleActionsExecutor;
use BertanexFlow\Sms\SmsTransportResolver;
use BertanexFlow\Admin\PluginDefaultsReset;
use BertanexFlow\Admin\Views\RulesPageView;
use BertanexFlow\Services\ActionDispatcher;
use BertanexFlow\Event\WordPressEventBridge;
use BertanexFlow\Rule\OptionRulesRepository;
use BertanexFlow\Rule\DelayedActionScheduler;
use BertanexFlow\Services\AutomationServices;
use BertanexFlow\Rule\DatabaseRulesRepository;
use BertanexFlow\Rule\FilterUserStateProvider;
use BertanexFlow\Rule\JsonFileRulesRepository;
use BertanexFlow\Rule\WordPressRulesScheduler;
use BertanexFlow\Sms\QueuedTemplateSmsService;
use BertanexFlow\Admin\Models\SmsSettingsModel;
use BertanexFlow\Logging\AutomationAuditLogger;
use BertanexFlow\Rule\RulesRepositoryInterface;
use BertanexFlow\Services\AutomationServiceIds;
use BertanexFlow\Admin\Models\SmsTemplatesModel;
use BertanexFlow\Rule\ConditionOperatorRegistry;
use BertanexFlow\Rule\DelegatingRulesRepository;
use BertanexFlow\Rule\ScheduledRulesCoordinator;
use BertanexFlow\Sms\DefaultSmsTemplateProvider;
use BertanexFlow\Admin\Models\RulesDocumentModel;
use BertanexFlow\Rule\UserStateProviderInterface;
use BertanexFlow\Sms\SmsTemplateProviderInterface;
use BertanexFlow\Admin\Controllers\RulesController;
use BertanexFlow\Admin\Models\RulesEditorSettingsModel;
use BertanexFlow\Consumer\ConsumerDefaultsResolver;
use BertanexFlow\Consumer\ConsumerRegistry;
use BertanexFlow\Consumer\PlatformConsumer;

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

        add_action('gro_register_orchestrator_consumers', [PlatformConsumer::class, 'register']);
        add_action('bertanex_flow_rules_persisted', [$this, 'flushRulesCache']);

        $this->hooks->add_action($this, 'loadTextdomain', 'plugins_loaded', 0, 0);
        $this->hooks->add_action($this, 'loadTextdomain', 'init', 0, 0);
        $this->hooks->add_action($this, 'bootstrapPersistence', 'plugins_loaded', 2, 0);
        $this->hooks->add_action($this, 'bootstrapConsumerDefaults', 'init', 1, 0);
        $this->hooks->add_action($this, 'onPluginsLoaded', 'plugins_loaded', 5, 0);
        $this->hooks->add_action($this, 'bootstrapFlowKernel', 'plugins_loaded', 20, 0);
        $this->hooks->register();
        (new \BertanexFlow\Admin\EmailTemplateType())->register();
        (new \BertanexFlow\Admin\Onboarding())->register();
    }

    public function flushRulesCache(): void
    {
        $this->container->get(DelegatingRulesRepository::class)->flushCache();
    }

    public function loadTextdomain(): void
    {
        I18n::loadTextdomain();
    }

    public function bootstrapPersistence(): void
    {
        RulesDbSchema::migrateLegacyNames();
        RulesDbSchema::upgradeIfNeeded();
    }

    public function bootstrapConsumerDefaults(): void
    {
        if (get_option(RulesDbSchema::SOURCES_HYDRATED_OPTION)) {
            return;
        }

        $resolver = $this->container->get(ConsumerDefaultsResolver::class);
        RulesDbSchema::maybeHydrateFromLegacySourcesOnce($resolver->mergedRulesDocumentJson());
    }

    public function bootstrapFlowKernel(): void
    {
        \BertanexFlow\Core\Bootstrap\Kernel::boot();
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
        do_action('gro_register_automation_services', $services);

        $actionDispatcher = $this->container->get(ActionDispatcher::class);

        /**
         * Register custom outbound action handlers or replace built-ins.
         *
         * @param ActionDispatcher $actionDispatcher
         */
        do_action('gro_register_action_handlers', $actionDispatcher);

        $this->container->get(AutomationAuditLogger::class)->register();

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
            'gro_emit_event',
            10,
            2
        );

        $this->container->get(WordPressEventBridge::class)->register($this->hooks);

        if (is_admin()) {
            $this->container->get(RulesController::class)->register($this->hooks);
        }

        $this->container->get(WordPressRulesScheduler::class)->register($this->hooks);

        $this->hooks->register();
    }

    private function registerServices(): void
    {
        $c = $this->container;

        $c->singleton(ConsumerRegistry::class, static fn () => ConsumerRegistry::instance());

        $c->singleton(
            ConsumerDefaultsResolver::class,
            static fn (Container $c) => new ConsumerDefaultsResolver(
                $c->get(ConsumerRegistry::class)
            )
        );

        $c->singleton(EventDispatcher::class, static fn () => new EventDispatcher());

        $c->singleton(AutomationAuditLogger::class, static fn () => new AutomationAuditLogger());

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

        $c->singleton(SmsSettingsModel::class, static fn () => new SmsSettingsModel());

        $c->singleton(SmsTemplatesModel::class, static fn () => new SmsTemplatesModel());

        $c->singleton(SmsTransportResolver::class, static fn () => new SmsTransportResolver());

        $c->singleton(SmsTemplateRenderer::class, static fn () => new SmsTemplateRenderer());

        $c->singleton(SmsTemplateProviderInterface::class, static fn () => new DefaultSmsTemplateProvider());

        $c->singleton(SmsPhoneResolver::class, static fn () => new SmsPhoneResolver());

        $c->singleton(SmsRateLimiter::class, static fn () => new SmsRateLimiter());

        $c->singleton(SmsJobQueue::class, static fn () => new SmsJobQueue());

        $c->singleton(
            SmsDeliveryWorker::class,
            static fn (Container $c) => new SmsDeliveryWorker(
                $c->get(SmsTransportResolver::class),
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
            static fn (): UserStateProviderInterface => new FilterUserStateProvider()
        );

        $c->singleton(
            JsonFileRulesRepository::class,
            static fn () => new JsonFileRulesRepository(\BERTANEX_FLOW_PLUGIN_DIR . 'rules/default.rules.json')
        );

        $c->singleton(
            OptionRulesRepository::class,
            static fn (Container $c) => new OptionRulesRepository(
                $c->get(JsonFileRulesRepository::class)
            )
        );

        $c->singleton(DatabaseRulesRepository::class, static function (): DatabaseRulesRepository {
            global $wpdb;

            return new DatabaseRulesRepository($wpdb);
        });

        $c->singleton(
            DelegatingRulesRepository::class,
            static fn (Container $c) => new DelegatingRulesRepository(
                $c->get(DatabaseRulesRepository::class),
                $c->get(OptionRulesRepository::class),
                $c->get(JsonFileRulesRepository::class)
            )
        );

        $c->singleton(
            RulesRepositoryInterface::class,
            static fn (Container $c) => $c->get(DelegatingRulesRepository::class)
        );

        $c->singleton(
            RulesDocumentModel::class,
            static fn (Container $c) => new RulesDocumentModel(
                $c->get(DatabaseRulesRepository::class),
                $c->get(OptionRulesRepository::class)
            )
        );

        $c->singleton(RulesPageView::class, static fn () => new RulesPageView());

        $c->singleton(RulesEditorSettingsModel::class, static fn () => new RulesEditorSettingsModel());

        $c->singleton(
            PluginDefaultsReset::class,
            static fn (Container $c) => new PluginDefaultsReset(
                $c->get(RulesDocumentModel::class),
                $c->get(ConsumerDefaultsResolver::class)
            )
        );

        $c->singleton(
            RulesController::class,
            static fn (Container $c) => new RulesController(
                $c->get(RulesDocumentModel::class),
                $c->get(RulesPageView::class),
                $c->get(RulesEditorSettingsModel::class),
                $c->get(SmsSettingsModel::class),
                $c->get(SmsTemplatesModel::class),
                $c->get(PluginDefaultsReset::class)
            )
        );

        $c->singleton(ConditionOperatorRegistry::class, static fn () => new ConditionOperatorRegistry());

        $c->singleton(
            ConditionEvaluator::class,
            static fn (Container $c) => new ConditionEvaluator(
                $c->get(ConditionOperatorRegistry::class)
            )
        );

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
                $c->get(RulesRepositoryInterface::class),
                $c->get(UserStateProviderInterface::class),
                $c->get(ConditionEvaluator::class),
                $c->get(RuleActionsExecutor::class)
            )
        );

        $c->singleton(
            WordPressRulesScheduler::class,
            static fn (Container $c) => new WordPressRulesScheduler(
                $c->get(RulesRepositoryInterface::class),
                $c->get(ScheduledRulesCoordinator::class)
            )
        );

        $c->singleton(
            RuleEngine::class,
            static fn (Container $c) => new RuleEngine(
                $c->get(EventDispatcher::class),
                $c->get(RulesRepositoryInterface::class),
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
