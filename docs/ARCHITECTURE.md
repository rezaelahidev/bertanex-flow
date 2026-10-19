# Bertanex Flow architecture

Bertanex Flow is the integration layer inside the Bertanex product ecosystem. The public site presents Bertanex as the parent brand and Flow as the automation product. This plugin keeps two cooperating runtimes:

1. **Rules engine** (`BertanexFlow`) — existing SMS and delayed-action rules. This path is unchanged.
2. **Flow orchestration** (`BertanexFlow`) — versioned workflows, structured context, retries, idempotency, and plugin adapters.

## Current shape

```
Event from WordPress / WooCommerce / custom code
        │
        ▼
bertanex_flow_event
        │
        ▼
WorkflowExecutor
        ├── match active workflow trigger
        ├── DataContext + conditions
        ├── idempotency + dedupe window
        ├── actions (map, log, HTTP)
        └── ExecutionStore + retry schedule
```

WooCommerce hooks are registered only when `WooCommerce` is loaded. Subscription status is registered only when `WC_Subscriptions` exists.

## Extension points

- `bertanex_flow_integrations` — return additional `IntegrationInterface` instances.
- `bertanex_flow_event` — `do_action('bertanex_flow_event', 'custom.event', $context)`.
- `bertanex_flow_compatibility_report` — adjust the diagnostics report.
- `bertanex_flow_booted` — runs after adapters and REST routes are registered.

Third-party code should implement `BertanexFlow\Core\Contracts\IntegrationInterface` and register it through the filter. Do not edit plugin core to add a plugin adapter.

## Workflow document (version 1)

```json
{
  "version": 1,
  "id": "order-webhook",
  "name": "Notify CRM",
  "status": "active",
  "trigger": { "type": "woocommerce.order.completed", "config": {} },
  "conditions": [{ "field": "order.total", "operator": ">=", "value": 100 }],
  "actions": [{ "type": "webhook.request", "config": { "method": "POST", "url": "https://example.com/hook" } }],
  "metadata": {
    "retry": { "max_attempts": 5, "strategy": "exponential_backoff", "initial_delay": 60, "max_delay": 3600 },
    "dedupe_window": 120
  }
}
```

Workflows are stored in the `bertanex_flow_workflows` option. Executions, steps, and idempotency keys use custom tables because they are append-only and queried by status.

## Security boundaries

- REST routes require `manage_options`.
- Outbound HTTP allows only `http` and `https`, and rejects private or reserved addresses.
- Execution payloads redact password, token, secret, and API key fields.
- Workflow actions cannot execute PHP.

## What remains in the rules engine

SMS templates, Melipayamak, Twilio, and the visual rules editor stay on the `BertanexFlow` services. Flow does not replace those rules. A future migration can project a rule into a version 1 workflow without changing the stored rule rows.
