<?php

declare(strict_types=1);

namespace BertanexFlow\REST;

use BertanexFlow\Compatibility\CompatibilityAnalyzer;
use BertanexFlow\Core\Registry\IntegrationRegistry;
use BertanexFlow\Core\Workflow\WorkflowDefinition;
use BertanexFlow\Core\Workflow\WorkflowExecutor;
use BertanexFlow\Core\Workflow\WorkflowRepository;
use BertanexFlow\Reliability\ExecutionStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Permission-checked REST routes for workflows, executions, and diagnostics.
 */
final class RestRegistrar
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly WorkflowExecutor $executor,
        private readonly ExecutionStore $executions,
        private readonly IntegrationRegistry $integrations,
        private readonly CompatibilityAnalyzer $compatibility
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $permission = [$this, 'canManage'];

            register_rest_route('bertanex/v1', '/workflows', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'listWorkflows'],
                    'permission_callback' => $permission,
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'saveWorkflow'],
                    'permission_callback' => $permission,
                ],
            ]);

            register_rest_route('bertanex/v1', '/workflows/(?P<id>[a-zA-Z0-9_-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'getWorkflow'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/executions', [
                'methods' => 'GET',
                'callback' => [$this, 'listExecutions'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/executions/(?P<id>\d+)/replay', [
                'methods' => 'POST',
                'callback' => [$this, 'replayExecution'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/integrations', [
                'methods' => 'GET',
                'callback' => [$this, 'listIntegrations'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/events', [
                'methods' => 'GET',
                'callback' => [$this, 'listEvents'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/actions', [
                'methods' => 'GET',
                'callback' => [$this, 'listActions'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/compatibility', [
                'methods' => 'GET',
                'callback' => [$this, 'compatibility'],
                'permission_callback' => $permission,
            ]);

            register_rest_route('bertanex/v1', '/diagnostics', [
                'methods' => 'GET',
                'callback' => [$this, 'diagnostics'],
                'permission_callback' => $permission,
            ]);
        });
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function listWorkflows(): WP_REST_Response
    {
        $items = array_map(
            static fn (WorkflowDefinition $workflow): array => $workflow->jsonSerialize(),
            $this->workflows->all()
        );

        return new WP_REST_Response(['workflows' => $items]);
    }

    public function getWorkflow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $workflow = $this->workflows->find((string) $request['id']);
        if ($workflow === null) {
            return new WP_Error('bertanex_flow_not_found', __('Workflow was not found.', 'bertanex-flow'), ['status' => 404]);
        }

        return new WP_REST_Response($workflow->jsonSerialize());
    }

    public function saveWorkflow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            return new WP_Error('bertanex_flow_invalid', __('Workflow document must be a JSON object.', 'bertanex-flow'), ['status' => 400]);
        }

        try {
            $workflow = WorkflowDefinition::fromArray($body);
        } catch (\InvalidArgumentException $error) {
            return new WP_Error('bertanex_flow_invalid', $error->getMessage(), ['status' => 400]);
        }

        $this->workflows->save($workflow);

        return new WP_REST_Response($workflow->jsonSerialize(), 201);
    }

    public function listExecutions(): WP_REST_Response
    {
        return new WP_REST_Response(['executions' => $this->executions->recent()]);
    }

    public function replayExecution(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request['id'];
        if (! $this->executor->replay($id)) {
            return new WP_Error('bertanex_flow_not_found', __('Execution was not found.', 'bertanex-flow'), ['status' => 404]);
        }

        return new WP_REST_Response(['replayed' => true, 'execution_id' => $id]);
    }

    public function listIntegrations(): WP_REST_Response
    {
        $items = [];
        foreach ($this->integrations->all() as $integration) {
            $items[] = [
                'id' => $integration->getId(),
                'name' => $integration->getName(),
                'available' => $integration->isAvailable(),
            ];
        }

        return new WP_REST_Response(['integrations' => $items]);
    }

    public function listEvents(): WP_REST_Response
    {
        return new WP_REST_Response(['events' => $this->collect('getEvents')]);
    }

    public function listActions(): WP_REST_Response
    {
        return new WP_REST_Response(['actions' => $this->collect('getActions')]);
    }

    public function compatibility(): WP_REST_Response
    {
        return new WP_REST_Response(['compatibility' => $this->compatibility->report()]);
    }

    public function diagnostics(): WP_REST_Response
    {
        return new WP_REST_Response([
            'compatibility' => $this->compatibility->report(),
            'workflows' => count($this->workflows->all()),
            'executions' => $this->executions->recent(5),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(string $method): array
    {
        $items = [];

        foreach ($this->integrations->all() as $integration) {
            if (! $integration->isAvailable()) {
                continue;
            }

            foreach ($integration->{$method}() as $id => $meta) {
                $items[] = array_merge(['id' => (string) $id, 'integration' => $integration->getId()], $meta);
            }
        }

        return $items;
    }
}
