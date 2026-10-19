<?php

declare(strict_types=1);

/**
 * WordPress-free checks for the Flow data, workflow, and retry core.
 */

$root = dirname(__DIR__);
require_once $root . '/src/Autoloader.php';
BertanexFlow\Autoloader::register($root . '/src', 'BertanexFlow\\');

use BertanexFlow\Admin\WorkflowForm;
use BertanexFlow\Compatibility\CompatibilityAnalyzer;
use BertanexFlow\Core\Data\DataContext;
use BertanexFlow\Core\Data\DataMapper;
use BertanexFlow\Core\Data\TransformationRegistry;
use BertanexFlow\Core\Data\VariableResolver;
use BertanexFlow\Core\Registry\IntegrationRegistry;
use BertanexFlow\Core\Workflow\WorkflowDefinition;
use BertanexFlow\Core\Workflow\WorkflowRunner;
use BertanexFlow\Integrations\WooCommerce\WooCommerceIntegration;
use BertanexFlow\Integrations\WordPress\WordPressIntegration;
use BertanexFlow\Reliability\PayloadRedactor;
use BertanexFlow\Reliability\RetryPolicy;
use BertanexFlow\Security\OutboundUrlGuard;

$failures = 0;

function check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok  {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL {$message}\n";
}

$context = DataContext::fromArray([
    'order' => ['id' => 1001, 'total' => 129],
    'customer' => ['email' => 'Customer@Example.com'],
    'secret' => 'hide-me',
]);

check($context->resolve('order.total') === 129, 'resolves nested order total');
check($context->has('customer.email'), 'detects an existing path');
check(! $context->has('customer.phone'), 'rejects a missing path');

$resolver = new VariableResolver($context, new TransformationRegistry());
check($resolver->resolve('{{customer.email|lowercase}}') === 'customer@example.com', 'applies lowercase token filter');
check($resolver->resolve('{{missing|default:guest}}') === 'guest', 'applies default filter');

$mapped = (new DataMapper(new TransformationRegistry()))->map($context, [
    ['source' => '{{customer.email}}', 'target' => 'crm.email', 'transform' => 'lowercase'],
    ['source' => 'order.total', 'target' => 'crm.total'],
]);
check(($mapped['crm']['email'] ?? '') === 'customer@example.com', 'maps email onto a nested target');
check(($mapped['crm']['total'] ?? 0) === 129, 'maps a numeric source path');

$redacted = (new PayloadRedactor())->redact(['token' => 'abc', 'order' => ['id' => 1]]);
check($redacted['token'] === '[redacted]', 'redacts token fields');
check($redacted['order']['id'] === 1, 'keeps non-secret fields');

$policy = RetryPolicy::fromArray([
    'max_attempts' => 3,
    'strategy' => 'exponential_backoff',
    'initial_delay' => 60,
    'max_delay' => 3600,
]);
check($policy->delayForAttempt(1) === 60, 'first retry waits the initial delay');
check($policy->delayForAttempt(2) === 120, 'second retry doubles the delay');
check($policy->shouldRetry(3) === false, 'stops after the attempt limit');

$workflow = WorkflowDefinition::fromArray([
    'version' => 1,
    'id' => 'order-log',
    'name' => 'Order log',
    'status' => 'active',
    'trigger' => ['type' => 'woocommerce.order.completed', 'config' => []],
    'conditions' => [['field' => 'order.total', 'operator' => '>=', 'value' => 100]],
    'actions' => [['type' => 'log.message', 'config' => ['message' => 'Order {{order.id}}']]],
]);
check($workflow->getVersion() === 1, 'keeps workflow schema version');

$run = (new WorkflowRunner())->run($workflow, [
    'order' => ['id' => 1001, 'total' => 129],
    'event' => ['name' => 'woocommerce.order.completed', 'id' => 'evt_1'],
]);
check($run['status'] === 'completed', 'runs a matching workflow');
check($run['steps'][3]['message'] === 'Logged: Order 1001', 'interpolates the log action');

$skipped = (new WorkflowRunner())->run($workflow, ['order' => ['id' => 2, 'total' => 10]]);
check($skipped['status'] === 'skipped', 'skips a workflow when the condition fails');

$invalid = false;
try {
    WorkflowDefinition::fromArray(['version' => 1, 'id' => '', 'name' => '', 'actions' => []]);
} catch (InvalidArgumentException) {
    $invalid = true;
}
check($invalid, 'rejects a workflow without an id');

$guard = new OutboundUrlGuard();
$blocked = false;
try {
    $guard->assertAllowed('http://127.0.0.1/hook');
} catch (InvalidArgumentException) {
    $blocked = true;
}
check($blocked, 'blocks a private outbound URL');

$blockedFile = false;
try {
    $guard->assertAllowed('file:///etc/passwd');
} catch (InvalidArgumentException) {
    $blockedFile = true;
}
check($blockedFile, 'blocks a non-HTTP outbound URL');

$httpWorkflow = WorkflowDefinition::fromArray([
    'version' => 1,
    'id' => 'http',
    'name' => 'HTTP',
    'status' => 'active',
    'trigger' => ['type' => 'custom.test', 'config' => []],
    'actions' => [[
        'type' => 'webhook.request',
        'config' => ['method' => 'POST', 'url' => 'https://example.com/hook', 'body' => ['email' => '{{customer.email|lowercase}}']],
    ]],
    'metadata' => ['retry' => ['max_attempts' => 2, 'initial_delay' => 30, 'max_delay' => 120]],
]);
$failedHttp = (new WorkflowRunner(http: static fn (): int => 500))->run($httpWorkflow, [
    'customer' => ['email' => 'A@B.com'],
], 1);
check($failedHttp['status'] === 'failed', 'records an HTTP failure');
check($failedHttp['retry_delay'] === 30, 'returns a retry delay without sleeping');

$document = WorkflowForm::toDocument([
    'name' => 'Notify CRM',
    'status' => 'active',
    'trigger' => 'woocommerce.order.completed',
    'conditions' => [
        ['field' => 'order.total', 'operator' => 'gte', 'value' => '100'],
        ['field' => '', 'operator' => 'eq', 'value' => ''],
    ],
    'actions' => [
        ['type' => 'log.message', 'message' => 'Order {{order.id}}'],
        ['type' => 'webhook.request', 'method' => 'post', 'url' => 'https://example.com/hook', 'body' => '{"email":"{{customer.email}}"}'],
        ['type' => 'data.map', 'into' => 'crm', 'mappings' => [
            ['source' => '{{customer.email}}', 'target' => 'email', 'transform' => 'lowercase'],
        ]],
    ],
    'retry_max_attempts' => '3',
    'retry_initial_delay' => '30',
    'dedupe_window' => '120',
]);
check($document['id'] === 'notify-crm', 'builds a workflow id from the name');
check($document['conditions'][0]['value'] === 100, 'casts numeric condition values');
check(count($document['actions']) === 3, 'keeps the three action types');
check($document['actions'][1]['config']['method'] === 'POST', 'normalizes the HTTP method');
check($document['metadata']['dedupe_window'] === 120, 'stores the dedupe window');

$badBody = false;
try {
    WorkflowForm::toDocument([
        'name' => 'Bad',
        'trigger' => 'custom.test',
        'actions' => [['type' => 'webhook.request', 'body' => 'not-json']],
    ]);
} catch (InvalidArgumentException) {
    $badBody = true;
}
check($badBody, 'rejects an HTTP body that is not JSON');

$registry = new IntegrationRegistry();
$registry->register(new WordPressIntegration());
$registry->register(new WooCommerceIntegration());
$report = (new CompatibilityAnalyzer($registry))->report();
check($report[0]['id'] === 'wordpress' && $report[0]['available'] === true, 'marks WordPress as available');
check($report[1]['id'] === 'woocommerce' && $report[1]['available'] === false, 'marks WooCommerce unavailable when the plugin class is absent');

echo $failures === 0 ? "\nAll core checks passed.\n" : "\n{$failures} checks failed.\n";
exit($failures === 0 ? 0 : 1);
