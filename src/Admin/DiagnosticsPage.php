<?php

declare(strict_types=1);

namespace BertanexFlow\Admin;

use BertanexFlow\Compatibility\CompatibilityAnalyzer;
use BertanexFlow\Reliability\ExecutionStore;

/**
 * Admin screen for compatibility and recent workflow executions.
 */
final class DiagnosticsPage
{
    public function __construct(
        private readonly CompatibilityAnalyzer $compatibility,
        private readonly ExecutionStore $executions
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
    }

    public function menu(): void
    {
        add_submenu_page(
            'bertanex-flow',
            __('Flow Diagnostics', 'bertanex-flow'),
            __('Diagnostics', 'bertanex-flow'),
            'manage_options',
            'bertanex-flow-diagnostics',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view Flow diagnostics.', 'bertanex-flow'));
        }

        $report = $this->compatibility->report();
        $executions = $this->executions->recent(15);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Bertanex Flow diagnostics', 'bertanex-flow') . '</h1>';
        echo '<p>' . esc_html__('Flow connects events from WordPress and other plugins to versioned workflows. The existing Rules screen still controls SMS automations.', 'bertanex-flow') . '</p>';

        echo '<h2>' . esc_html__('Compatibility', 'bertanex-flow') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Integration', 'bertanex-flow') . '</th><th>' . esc_html__('Status', 'bertanex-flow') . '</th><th>' . esc_html__('Detail', 'bertanex-flow') . '</th></tr></thead><tbody>';
        foreach ($report as $row) {
            echo '<tr><td>' . esc_html((string) $row['name']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . esc_html((string) $row['message']) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Recent executions', 'bertanex-flow') . '</h2>';
        if ($executions === []) {
            echo '<p>' . esc_html__('No workflow executions have been recorded yet.', 'bertanex-flow') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Workflow', 'bertanex-flow') . '</th><th>' . esc_html__('Event', 'bertanex-flow') . '</th><th>' . esc_html__('Status', 'bertanex-flow') . '</th><th>' . esc_html__('Attempt', 'bertanex-flow') . '</th><th>' . esc_html__('Error', 'bertanex-flow') . '</th></tr></thead><tbody>';
            foreach ($executions as $execution) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($execution['workflow_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($execution['event_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($execution['status'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($execution['attempt'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($execution['error_message'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }
}
