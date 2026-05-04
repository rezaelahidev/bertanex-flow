<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

use GrowthOrchestrator\Sms\Exception\SmsTransportException;

/**
 * Twilio Messages API via {@see wp_remote_post()}. Throws {@see SmsTransportException} on failure so
 * {@see SmsDeliveryWorker} can retry (transient/network/5xx/429) or stop (auth/validation).
 *
 * @phpstan-import-type JobMeta from SmsTransportInterface
 */
final class TwilioSmsTransport implements SmsTransportInterface
{
    private const TWILIO_MESSAGES_PATH = '/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(
        private readonly TwilioSmsConfig $config = new TwilioSmsConfig()
    ) {
    }

    /**
     * @param JobMeta $meta
     */
    public function deliver(string $to, string $body, array $meta): void
    {
        $creds = $this->config->resolve();
        if ($creds === null) {
            throw new SmsTransportException(
                'Twilio SMS is not configured (missing SID, auth token, or From number).',
                false,
                null,
                null
            );
        }

        $url = sprintf('https://api.twilio.com' . self::TWILIO_MESSAGES_PATH, rawurlencode($creds['account_sid']));

        $args = [
            'timeout'     => max(15, (int) apply_filters('gro_twilio_http_timeout_seconds', 30)),
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => [
                'Authorization' => $this->basicAuthHeader($creds['account_sid'], $creds['auth_token']),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            /** @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent — identify integration */
            'user-agent'  => 'GrowthOrchestrator/WP;' . (\defined('GROWTH_ORCH_VERSION') ? (string) GROWTH_ORCH_VERSION : ''),
            'body'        => [
                'To'   => $to,
                'From' => $creds['from'],
                'Body' => $body,
            ],
        ];

        $args = apply_filters('gro_twilio_http_request_args', $args, $to, $meta);

        /** @var array<string, mixed> $response */
        $response = wp_remote_post($url, $args);

        if ($response instanceof \WP_Error) {
            throw new SmsTransportException(
                sprintf('SMS request failed (%s)', $response->get_error_message()),
                true,
                null,
                null,
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw    = wp_remote_retrieve_body($response);

        if ($status >= 200 && $status < 300) {
            $decoded = json_decode(is_string($raw) ? $raw : '', true);
            $sid     = is_array($decoded) && isset($decoded['sid']) && is_string($decoded['sid'])
                ? $decoded['sid']
                : '';

            /** @param array<string, mixed> $meta */
            do_action('gro_twilio_sms_accepted_by_provider', $sid, $to, $meta, $decoded);

            return;
        }

        throw $this->exceptionFromTwilioResponse($status, $raw);
    }

    private function basicAuthHeader(string $accountSid, string $authToken): string
    {
        return 'Basic ' . base64_encode($accountSid . ':' . $authToken); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    private function exceptionFromTwilioResponse(int $httpStatus, string $rawBody): SmsTransportException
    {
        $retry         = $this->defaultRetrySuggested($httpStatus);
        $message       = 'Twilio SMS request failed.';
        $providerCode  = null;
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            $retry = $httpStatus >= 500 || $httpStatus === 429;
            $retry = (bool) apply_filters('gro_twilio_retry_suggested_raw_body', $retry, $httpStatus, $rawBody);

            return new SmsTransportException(
                sprintf('%s (HTTP %s)', $message, (string) ($httpStatus > 0 ? $httpStatus : 'unknown')),
                $retry,
                $httpStatus > 0 ? $httpStatus : null,
                null
            );
        }

        if (isset($decoded['message']) && is_string($decoded['message']) && $decoded['message'] !== '') {
            $message = $decoded['message'];
        }

        $rawCode = $decoded['code'] ?? ($decoded['error_code'] ?? null);
        if (is_numeric($rawCode)) {
            $providerCode = (int) $rawCode;
        }

        if ($httpStatus === 429 || $httpStatus >= 500) {
            $retry = true;
        }
        if ($httpStatus === 401 || $httpStatus === 403 || $httpStatus === 404) {
            $retry = false;
        }
        /** Other 4xx Twilio payloads are usually persistent request/receiver issues — do not retry unless filtered. */
        if ($httpStatus >= 400 && $httpStatus < 500 && $httpStatus !== 429) {
            $retry = (bool) apply_filters(
                'gro_twilio_retry_on_client_error_http',
                false,
                $httpStatus,
                $providerCode,
                $decoded
            );
        }

        $retry = (bool) apply_filters('gro_twilio_retry_suggested', $retry, $httpStatus, $providerCode, $decoded);

        return new SmsTransportException(trim($message), $retry, $httpStatus > 0 ? $httpStatus : null, $providerCode);
    }

    /**
     * @phpstan-return bool whether the HTTP status alone implies a transient error
     */
    private function defaultRetrySuggested(int $httpStatus): bool
    {
        if ($httpStatus === 401 || $httpStatus === 403 || $httpStatus === 404) {
            return false;
        }

        if ($httpStatus === 429) {
            return true;
        }

        if ($httpStatus >= 500) {
            return true;
        }

        if ($httpStatus >= 400) {
            return false;
        }

        return true;
    }
}
