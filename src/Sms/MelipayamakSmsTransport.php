<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

use BertanexFlow\Sms\Exception\SmsTransportException;

/**
 * Melipayamak REST SendSMS ({@see https://rest.payamak-panel.com/api/SendSMS/SendSMS}) via {@see wp_remote_post()}
 * or {@see MelipayamakHttpTunnel} when {@see WP_HTTP_BLOCK_EXTERNAL} blocks the outbound request.
 *
 * @phpstan-import-type JobMeta from SmsTransportInterface
 */
final class MelipayamakSmsTransport implements SmsTransportInterface
{
    private const DEFAULT_ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';

    /** Client / business error codes — same retry intent as {@see MelipayamakSmsTransport::errorCodeRetrySuggested}. */
    private const KNOWN_NON_RETRY_CODES = [
        0,   // incorrect credentials
        2,   // insufficient credit
        3,   // daily limit
        4,   // volume limit
        5,   // sender invalid
        7,   // filtered words
        9,   // public line restriction
        10,  // inactive account
        14,  // links in text
        16,  // no recipient
        17,  // empty text
        18,  // invalid recipient
        108, // IP blocked
        109, // API key required
        110, // IP allowlist
    ];

    public function __construct(
        private readonly MelipayamakSmsConfig $config = new MelipayamakSmsConfig()
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
                'Melipayamak SMS is not configured (username, password, or sender line missing).',
                false,
                null,
                null
            );
        }

        /**
         * REST body matches Payamak-Panel samples (username, password, to, from, text).
         *
         * @param array<string, string> $fields
         */
        $fields = [
            'username' => $creds['username'],
            'password' => $creds['password'],
            'to'       => $to,
            'from'     => $creds['from'],
            'text'     => $body,
            // 'bodyId' => 418445,
        ];

        $fields = apply_filters('gro_melipayamak_send_fields', $fields, $meta);

        $url = (string) apply_filters('gro_melipayamak_send_sms_url', self::DEFAULT_ENDPOINT, $meta);

        $args = [
            /** @see gro_melipayamak_http_timeout_seconds */
            'timeout'     => max(15, (int) apply_filters('gro_melipayamak_http_timeout_seconds', 30)),
            'redirection' => 0,
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'         => $fields,
            'sslverify'    => (bool) apply_filters('gro_melipayamak_http_sslverify', true),
            'user-agent'   => 'BertanexFlow/WP;' . (\defined('BERTANEX_FLOW_VERSION') ? (string) BERTANEX_FLOW_VERSION : ''),
        ];

        $args = apply_filters('gro_melipayamak_http_request_args', $args, $to, $meta);

        /** @var array<string, mixed>|\WP_Error $response */
        $response = MelipayamakHttpTunnel::post($url, $args);

        if ($response instanceof \WP_Error) {
            throw new SmsTransportException(
                sprintf('Melipayamak SMS request failed (%s)', $response->get_error_message()),
                true,
                null,
                null
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw    = (string) wp_remote_retrieve_body($response);

        $this->assertSuccessfulResponse($status, $raw, $to, $meta);
    }

    private function assertSuccessfulResponse(int $httpStatus, string $rawBody, string $to, array $meta): void
    {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $retry = $httpStatus === 429 || $httpStatus >= 500;

            throw new SmsTransportException(
                sprintf('Melipayamak HTTP error %s', (string) $httpStatus),
                $retry,
                $httpStatus,
                null
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($rawBody, true);

        $interpreted = apply_filters(
            'gro_melipayamak_interpret_response',
            null,
            $rawBody,
            is_array($decoded) ? $decoded : null,
            $httpStatus,
            $to,
            $meta
        );

        if (is_bool($interpreted)) {
            if ($interpreted) {
                /** @phpstan-ignore-next-line */
                do_action('gro_melipayamak_sms_accepted', $rawBody, is_array($decoded) ? $decoded : [], $to, $meta);

                return;
            }

            throw new SmsTransportException(
                'Melipayamak rejected the message (see raw response).',
                false,
                $httpStatus,
                null
            );
        }

        [$ok, $message, $code, $retrySuggested] = $this->defaultInterpretation($rawBody, $decoded, $httpStatus);

        $retrySuggested = (bool) apply_filters(
            'gro_melipayamak_retry_suggested',
            $retrySuggested,
            $ok,
            $message,
            $code,
            is_array($decoded) ? $decoded : null,
            $rawBody
        );

        if ($ok) {
            /** @phpstan-ignore-next-line */
            do_action('gro_melipayamak_sms_accepted', $rawBody, is_array($decoded) ? $decoded : [], $to, $meta);

            return;
        }

        throw new SmsTransportException(
            $message !== '' ? $message : 'Melipayamak SMS send failed.',
            $retrySuggested,
            $httpStatus,
            $code
        );
    }

    /**
     * @param array<string, mixed>|null $decoded
     *
     * @return array{0: bool, 1: string, 2: ?int, 3: bool} ok, message, provider numeric code, retry
     */
    private function defaultInterpretation(string $rawBody, ?array $decoded, int $httpStatus): array
    {
        if (is_array($decoded)) {
            $ret = $decoded['RetStatus'] ?? $decoded['retStatus'] ?? null;

            if (is_numeric($ret)) {
                $code = (int) $ret;

                if ($code === 1) {
                    return [true, '', 1, false];
                }

                $retry = $this->errorCodeRetrySuggested($code);

                $msg = isset($decoded['StrRetStatus']) && is_string($decoded['StrRetStatus'])
                    ? $decoded['StrRetStatus']
                    : (isset($decoded['Str']) && is_string($decoded['Str']) ? $decoded['Str'] : 'Error ' . $code);

                return [false, $msg, $code, $retry];
            }

            $value = $decoded['Value'] ?? $decoded['value'] ?? null;
            if (is_string($value) || is_int($value) || is_float($value)) {
                $n = is_numeric($value) ? (int) $value : 0;
                if ($n > 0) {
                    return [true, '', $n, false];
                }
                if ($n < 0) {
                    return [false, (string) $value, $n, $this->errorCodeRetrySuggested($n)];
                }
            }
        }

        $trim = trim($rawBody);
        if ($trim !== '' && is_numeric($trim)) {
            $n = (int) $trim;
            if ($n > 0) {
                return [true, '', $n, false];
            }

            return [false, $trim, $n, $this->errorCodeRetrySuggested($n)];
        }

        return [
            false,
            'Unrecognized Melipayamak response (inspect raw body with WP_DEBUG / hooks).',
            null,
            true,
        ];
    }

    private function errorCodeRetrySuggested(int $code): bool
    {
        if (in_array($code, self::KNOWN_NON_RETRY_CODES, true)) {
            return false;
        }

        if ($code === 6) {
            return true;
        }

        return $code < 0;
    }
}
