<?php

declare(strict_types=1);

namespace BertanexFlow\Admin;

use BertanexFlow\Compatibility\CompatibilityAnalyzer;
use BertanexFlow\Core\Workflow\WorkflowExecutor;
use BertanexFlow\Reliability\ExecutionStore;
use BertanexFlow\Admin\Controllers\RulesController;

/**
 * Execution history and compatibility inside the existing Rules screen.
 */
final class ActivityPanel
{
    public function __construct(
        private readonly WorkflowExecutor $executor,
        private readonly ExecutionStore $executions,
        private readonly CompatibilityAnalyzer $compatibility
    ) {
    }

    public function register(): void
    {
        add_action('bertanex_flow_render_activity', [$this, 'render']);
        add_action('admin_post_bertanex_flow_replay_execution', [$this, 'replay']);
        add_action('admin_init', [$this, 'redirectLegacyScreens']);
    }

    public function redirectLegacyScreens(): void
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if (! in_array($page, ['bertanex-flow-workflows', 'bertanex-flow-diagnostics'], true)) {
            return;
        }

        wp_safe_redirect(add_query_arg(
            [
                'page' => RulesController::PAGE_SLUG,
                'gro_open_tab' => 'runs',
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function replay(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to replay executions.', 'bertanex-flow'));
        }

        $id = absint($_POST['execution_id'] ?? 0);
        check_admin_referer('bertanex_flow_replay_' . $id);
        $this->executor->replay($id);

        wp_safe_redirect(add_query_arg(
            [
                'page' => RulesController::PAGE_SLUG,
                'gro_open_tab' => 'runs',
                'execution' => (string) $id,
                'replayed' => '1',
            ],
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $executionId = isset($_GET['execution']) ? absint($_GET['execution']) : 0;
        if ($executionId > 0) {
            $this->renderExecution($executionId);
            return;
        }

        echo '<div class="gro-activity">';
        echo '<h2 class="gro-panel-title">' . esc_html__('Runs and compatibility', 'bertanex-flow') . '</h2>';
        echo '<p class="gro-panel-lede">' . esc_html__('Automations are built in the Automations tab. This view shows whether each run finished, and which integrations Flow can use.', 'bertanex-flow') . '</p>';

        if (isset($_GET['replayed']) && (string) $_GET['replayed'] === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Replay started.', 'bertanex-flow') . '</p></div>';
        }

        echo '<h3>' . esc_html__('Compatibility', 'bertanex-flow') . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Integration', 'bertanex-flow') . '</th><th>' . esc_html__('Status', 'bertanex-flow') . '</th><th>' . esc_html__('Detail', 'bertanex-flow') . '</th></tr></thead><tbody>';
        foreach ($this->compatibility->report() as $row) {
            echo '<tr><td>' . esc_html((string) $row['name']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . esc_html((string) $row['message']) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Recent executions', 'bertanex-flow') . '</h3>';
        $rows = $this->executions->recent(15);
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Workflow', 'bertanex-flow') . '</th><th>' . esc_html__('Event', 'bertanex-flow') . '</th><th>' . esc_html__('Status', 'bertanex-flow') . '</th><th>' . esc_html__('Attempt', 'bertanex-flow') . '</th><th>' . esc_html__('View', 'bertanex-flow') . '</th>';
        echo '</tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="5">' . esc_html__('No workflow executions have been recorded yet.', 'bertanex-flow') . '</td></tr>';
        }
        foreach ($rows as $row) {
            $link = add_query_arg(
                [
                    'page' => RulesController::PAGE_SLUG,
                    'gro_open_tab' => 'runs',
                    'execution' => (string) ($row['id'] ?? ''),
                ],
                admin_url('admin.php')
            );
            echo '<tr><td>' . esc_html((string) ($row['workflow_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['event_name'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['attempt'] ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($link) . '">' . esc_html__('Details', 'bertanex-flow') . '</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function renderExecution(int $id): void
    {
        $execution = $this->executions->find($id);
        $back = add_query_arg(
            ['page' => RulesController::PAGE_SLUG, 'gro_open_tab' => 'runs'],
            admin_url('admin.php')
        );

        echo '<div class="gro-activity">';
        echo '<p><a href="' . esc_url($back) . '">' . esc_html__('Back to runs', 'bertanex-flow') . '</a></p>';
        if ($execution === null) {
            echo '<p>' . esc_html__('Execution was not found.', 'bertanex-flow') . '</p></div>';
            return;
        }

        echo '<table class="widefat striped"><tbody>';
        foreach ([
            'workflow_id' => __('Workflow', 'bertanex-flow'),
            'event_name' => __('Event', 'bertanex-flow'),
            'status' => __('Status', 'bertanex-flow'),
            'attempt' => __('Attempt', 'bertanex-flow'),
            'error_message' => __('Error', 'bertanex-flow'),
            'started_at' => __('Started', 'bertanex-flow'),
            'completed_at' => __('Completed', 'bertanex-flow'),
        ] as $key => $label) {
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html((string) ($execution[$key] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Steps', 'bertanex-flow') . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Step', 'bertanex-flow') . '</th><th>' . esc_html__('Status', 'bertanex-flow') . '</th><th>' . esc_html__('Message', 'bertanex-flow') . '</th></tr></thead><tbody>';
        $steps = $this->executions->stepsFor($id);
        if ($steps === []) {
            echo '<tr><td colspan="3">' . esc_html__('No steps were recorded.', 'bertanex-flow') . '</td></tr>';
        }
        foreach ($steps as $step) {
            echo '<tr><td>' . esc_html((string) ($step['step_key'] ?? '')) . '</td><td>' . esc_html((string) ($step['status'] ?? '')) . '</td><td>' . esc_html((string) ($step['message'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="gro-activity__replay">';
        wp_nonce_field('bertanex_flow_replay_' . $id);
        echo '<input type="hidden" name="action" value="bertanex_flow_replay_execution">';
        echo '<input type="hidden" name="execution_id" value="' . esc_attr((string) $id) . '">';
        submit_button(__('Replay execution', 'bertanex-flow'), 'secondary', 'submit', false);
        echo '</form></div>';
    }
}
