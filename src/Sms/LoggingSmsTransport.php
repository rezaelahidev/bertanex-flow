<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

/**
 * Development transport: logs when WP_DEBUG; no throw (delivery “succeeds”).
 * Replace with an API-backed transport that throws on failure to exercise retries.
 */
final class LoggingSmsTransport implements SmsTransportInterface
{
    public function deliver(string $to, string $body, array $meta): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        error_log(
            sprintf(
                '[Marketing Automation SMS] to=%s body=%s meta=%s',
                $to,
                $body,
                wp_json_encode($meta) ?: '{}'
            )
        );
    }
}
