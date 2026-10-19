<?php

declare(strict_types=1);

namespace BertanexFlow\Reliability;

/**
 * Persists workflow executions, steps, and idempotency keys.
 */
final class ExecutionStore
{
    public static function executionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bertanex_flow_executions';
    }

    public static function stepsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bertanex_flow_execution_steps';
    }

    public static function idempotencyTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bertanex_flow_idempotency';
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $executions = self::executionsTable();
        $steps = self::stepsTable();
        $idempotency = self::idempotencyTable();

        dbDelta(
            "CREATE TABLE {$executions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                execution_key varchar(64) NOT NULL,
                workflow_id varchar(191) NOT NULL,
                event_name varchar(191) NOT NULL,
                status varchar(32) NOT NULL,
                attempt smallint unsigned NOT NULL DEFAULT 1,
                payload longtext NULL,
                error_message text NULL,
                started_at datetime NOT NULL,
                completed_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY execution_key (execution_key),
                KEY workflow_status (workflow_id, status)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$steps} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                execution_id bigint(20) unsigned NOT NULL,
                step_key varchar(64) NOT NULL,
                status varchar(32) NOT NULL,
                message text NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY execution_id (execution_id)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$idempotency} (
                idempotency_key varchar(191) NOT NULL,
                execution_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (idempotency_key)
            ) {$charset};"
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function start(string $executionKey, string $workflowId, string $eventName, array $payload, int $attempt = 1): int
    {
        global $wpdb;

        $wpdb->insert(
            self::executionsTable(),
            [
                'execution_key' => $executionKey,
                'workflow_id' => $workflowId,
                'event_name' => $eventName,
                'status' => 'running',
                'attempt' => $attempt,
                'payload' => wp_json_encode((new PayloadRedactor())->redact($payload)),
                'started_at' => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function addStep(int $executionId, string $stepKey, string $status, string $message): void
    {
        global $wpdb;

        $wpdb->insert(
            self::stepsTable(),
            [
                'execution_id' => $executionId,
                'step_key' => $stepKey,
                'status' => $status,
                'message' => $message,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    public function finish(int $executionId, string $status, string $error = ''): void
    {
        global $wpdb;

        $wpdb->update(
            self::executionsTable(),
            [
                'status' => $status,
                'error_message' => $error !== '' ? $error : null,
                'completed_at' => current_time('mysql', true),
            ],
            ['id' => $executionId],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function claim(string $key, int $executionId): bool
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::idempotencyTable(),
            [
                'idempotency_key' => $key,
                'execution_id' => $executionId,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%d', '%s']
        );

        return $inserted !== false;
    }

    public function hasClaim(string $key): bool
    {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT execution_id FROM ' . self::idempotencyTable() . ' WHERE idempotency_key = %s',
                $key
            )
        );

        return $found !== null;
    }

    public function release(string $key): void
    {
        global $wpdb;

        $wpdb->delete(self::idempotencyTable(), ['idempotency_key' => $key], ['%s']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        global $wpdb;

        $limit = max(1, min(100, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, execution_key, workflow_id, event_name, status, attempt, error_message, started_at, completed_at FROM ' . self::executionsTable() . ' ORDER BY id DESC LIMIT %d',
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::executionsTable() . ' WHERE id = %d',
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stepsFor(int $executionId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT step_key, status, message, created_at FROM ' . self::stepsTable() . ' WHERE execution_id = %d ORDER BY id ASC',
                $executionId
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }
}
