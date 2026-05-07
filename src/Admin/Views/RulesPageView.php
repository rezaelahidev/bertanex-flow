<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Views;

use GrowthOrchestrator\Admin\ViewModels\RulesPageContext;

/**
 * View: renders the Rules admin UI from a template (no request handling).
 */
final class RulesPageView
{
    public function render(RulesPageContext $context): void
    {
        $template = __DIR__ . '/templates/rules-page.php';

        if (! is_readable($template)) {
            echo '<div class="wrap"><p>' . esc_html__('Rules template missing.', 'growth-orchestrator') . '</p></div>';

            return;
        }

        include $template;
    }
}
