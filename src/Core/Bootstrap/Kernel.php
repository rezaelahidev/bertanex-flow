<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Bootstrap;

use BertanexFlow\Admin\ActivityPanel;
use BertanexFlow\Compatibility\CompatibilityAnalyzer;
use BertanexFlow\Core\Registry\IntegrationRegistry;
use BertanexFlow\Core\Workflow\WorkflowExecutor;
use BertanexFlow\Core\Workflow\WorkflowRepository;
use BertanexFlow\Integrations\WooCommerce\WooCommerceIntegration;
use BertanexFlow\Integrations\WordPress\WordPressIntegration;
use BertanexFlow\Reliability\ExecutionStore;
use BertanexFlow\REST\RestRegistrar;

/**
 * Boots the Flow orchestration layer beside the existing rules engine.
 */
final class Kernel
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        self::install();

        $integrations = new IntegrationRegistry();
        $integrations->register(new WordPressIntegration());
        $integrations->register(new WooCommerceIntegration());

        $filtered = apply_filters('bertanex_flow_integrations', $integrations->all());
        if (is_array($filtered)) {
            foreach ($filtered as $integration) {
                if ($integration instanceof \BertanexFlow\Core\Contracts\IntegrationInterface && ! $integrations->has($integration->getId())) {
                    $integrations->register($integration);
                }
            }
        }

        foreach ($integrations->all() as $integration) {
            if ($integration->isAvailable()) {
                $integration->register();
            }
        }

        $executions = new ExecutionStore();
        $executor = new WorkflowExecutor(new WorkflowRepository(), new \BertanexFlow\Core\Workflow\WorkflowRunner(), $executions);
        $executor->register();

        $compatibility = new CompatibilityAnalyzer($integrations);
        (new RestRegistrar(new WorkflowRepository(), $executor, $executions, $integrations, $compatibility))->register();

        if (is_admin()) {
            (new ActivityPanel($executor, $executions, $compatibility))->register();
        }

        do_action('bertanex_flow_booted', $integrations);
    }

    public static function install(): void
    {
        if ((string) get_option('bertanex_flow_schema_version', '') === '1') {
            return;
        }

        ExecutionStore::install();
        update_option('bertanex_flow_schema_version', '1', true);
    }
}
