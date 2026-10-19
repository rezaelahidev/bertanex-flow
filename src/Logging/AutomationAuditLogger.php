<?php

declare(strict_types=1);

namespace BertanexFlow\Logging;

/**
 * End-to-end audit trail: rule match → queue → SMS result.
 *
 * Writes to WooCommerce logger when available; otherwise {@see error_log()}
 * when {@see WP_DEBUG_LOG} is true or the {@see 'gro_automation_audit_log_enabled'} filter returns true.
 */
final class AutomationAuditLogger
{
    private const CHANNEL = 'bertanex-flow';

    public function register(): void
    {
        add_action('gro_rule_matched', [$this, 'onRuleMatched'], 10, 1);
        add_action('gro_delayed_rule_action_scheduled', [$this, 'onDelayedScheduled'], 10, 5);
        add_action('gro_sms_job_enqueued', [$this, 'onSmsEnqueued'], 10, 1);
        add_action('gro_sms_missing_phone', [$this, 'onSmsSkipped'], 10, 3);
        add_action('gro_sms_unknown_template', [$this, 'onSmsSkippedTemplate'], 10, 3);
        add_action('gro_sms_rate_limited', [$this, 'onSmsRateLimited'], 10, 3);
        add_action('gro_sms_delivered', [$this, 'onSmsDelivered'], 10, 2);
        add_action('gro_sms_delivery_exhausted', [$this, 'onSmsExhausted'], 10, 3);
        add_action('gro_background_job_exhausted', [$this, 'onBackgroundJobExhausted'], 10, 4);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function enabled(array $context = []): bool
    {
        return (bool) apply_filters(
            'gro_automation_audit_log_enabled',
            (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) || (defined('WP_DEBUG') && WP_DEBUG),
            $context
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! $this->enabled($context)) {
            return;
        }

        $line = self::CHANNEL . ': ' . $message;
        $json = wp_json_encode($context, JSON_UNESCAPED_SLASHES);
        $suffix = ($json !== false && $json !== '{}' && $json !== '[]') ? ' ' . $json : '';

        if (\function_exists('wc_get_logger')) {
            \wc_get_logger()->log($level, $line . $suffix, ['source' => self::CHANNEL]);

            return;
        }

        error_log($line . $suffix);
    }

    /**
     * @param mixed $payload
     */
    public function onRuleMatched($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $this->log(
            'info',
            'Rule matched; executing actions.',
            $this->onlyStrings($payload)
        );
    }

    public function onDelayedScheduled(int $userId, string $ruleId, string $eventName, int $delaySeconds, string $actionType): void
    {
        $this->log(
            'info',
            'Delayed rule action scheduled via Action Scheduler.',
            [
                'user_id'        => $userId,
                'rule_id'        => $ruleId,
                'event'          => $eventName,
                'delay_seconds'  => $delaySeconds,
                'action_type'    => $actionType,
            ]
        );
    }

    /**
     * @param mixed $payload
     */
    public function onSmsEnqueued($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $sanitized = $this->onlyStrings($payload);

        if (isset($sanitized['to']) && is_string($sanitized['to'])) {
            $sanitized['to_masked'] = self::maskPhone($sanitized['to']);
            unset($sanitized['to']);
        }

        $this->log(
            'info',
            'SMS job enqueued for background delivery.',
            $sanitized
        );
    }

    /**
     * @param mixed $context
     */
    public function onSmsSkipped(int $userId, string $templateId, $context): void
    {
        $this->log(
            'warning',
            'SMS skipped: no phone.',
            [
                'user_id'     => $userId,
                'template_id' => $templateId,
            ]
        );
    }

    /**
     * @param mixed $context
     */
    public function onSmsSkippedTemplate(string $templateId, int $userId, $context): void
    {
        $this->log(
            'warning',
            'SMS skipped: unknown template.',
            ['user_id' => $userId, 'template_id' => $templateId]
        );
    }

    /**
     * @param mixed $context
     */
    public function onSmsRateLimited(int $userId, string $templateId, $context): void
    {
        $this->log(
            'notice',
            'SMS skipped: rate limited.',
            ['user_id' => $userId, 'template_id' => $templateId]
        );
    }

    /**
     * @param array<string, mixed> $job
     */
    public function onSmsDelivered(array $job, int $attempt): void
    {
        $templateId = isset($job['template_id']) && is_string($job['template_id']) ? $job['template_id'] : '';
        $uid        = isset($job['user_id']) ? (int) $job['user_id'] : 0;
        $to         = isset($job['to']) && is_string($job['to']) ? $job['to'] : '';

        $this->log(
            'info',
            'SMS delivered successfully.',
            [
                'user_id'     => $uid,
                'template_id' => $templateId,
                'attempt'     => $attempt,
                'to_masked'   => self::maskPhone($to),
            ]
        );
    }

    /**
     * @param array<string, mixed> $job
     */
    public function onSmsExhausted(\Throwable $e, array $job, int $attempt): void
    {
        $templateId = isset($job['template_id']) && is_string($job['template_id']) ? $job['template_id'] : '';
        $uid        = isset($job['user_id']) ? (int) $job['user_id'] : 0;

        $this->log(
            'error',
            'SMS delivery failed permanently: ' . $e->getMessage(),
            [
                'user_id'     => $uid,
                'template_id' => $templateId,
                'attempt'     => $attempt,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function onBackgroundJobExhausted(\Throwable $e, array $data, int $userId, int $attempt): void
    {
        $type = isset($data['action']['type']) && is_string($data['action']['type'])
            ? $data['action']['type']
            : '';

        $this->log(
            'error',
            'Background rule action failed permanently: ' . $e->getMessage(),
            [
                'user_id'      => $userId,
                'attempt'      => $attempt,
                'action_type'  => $type,
                'rule_id'      => isset($data['rule_id']) ? (string) $data['rule_id'] : '',
            ]
        );
    }

    private static function maskPhone(string $e164OrLocal): string
    {
        $digits = preg_replace('/\D+/', '', $e164OrLocal) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 4) {
            return '****';
        }

        return '***' . substr($digits, -4);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, scalar>
     */
    private function onlyStrings(array $row): array
    {
        $out = [];

        foreach ($row as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
