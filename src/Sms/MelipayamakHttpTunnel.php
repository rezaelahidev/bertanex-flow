<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

/**
 * Sends Melipayamak HTTP requests via cURL when WordPress blocks external {@see wp_remote_post()} calls.
 *
 * @see WP_HTTP_BLOCK_EXTERNAL
 */
final class MelipayamakHttpTunnel
{
    /**
     * @param array<string, mixed> $args {@see wp_remote_post()} request arguments.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public static function post(string $url, array $args): array|\WP_Error
    {
        if (! self::mustTunnel($url)) {
            return wp_remote_post($url, $args);
        }

        return self::curlPost($url, $args);
    }

    private static function mustTunnel(string $url): bool
    {
        /**
         * Force or skip the cURL tunnel regardless of {@see WP_HTTP_BLOCK_EXTERNAL}.
         *
         * @param bool|null $useTunnel Null = auto-detect from WordPress HTTP block rules.
         * @param string    $url
         */
        $forced = apply_filters('gro_melipayamak_use_http_tunnel', null, $url);
        if ($forced !== null) {
            return (bool) $forced;
        }

        if (! defined('WP_HTTP_BLOCK_EXTERNAL') || ! WP_HTTP_BLOCK_EXTERNAL) {
            return false;
        }

        return _wp_http_get_object()->block_request($url);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>|\WP_Error
     */
    private static function curlPost(string $url, array $args): array|\WP_Error
    {
        if (! function_exists('curl_init')) {
            return new \WP_Error(
                'http_request_failed',
                'Melipayamak SMS tunnel requires the PHP cURL extension.'
            );
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return new \WP_Error('http_request_failed', 'Melipayamak SMS tunnel could not initialize cURL.');
        }

        $headerLines = [];
        foreach ($args['headers'] ?? [] as $name => $value) {
            if (is_string($name) && (is_string($value) || is_numeric($value))) {
                $headerLines[] = $name . ': ' . $value;
            }
        }

        $body = $args['body'] ?? '';
        if (is_array($body)) {
            $body = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
        }

        $sslVerify = ! empty($args['sslverify']);

        $curlOptions = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => (string) $body,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(15, (int) ($args['timeout'] ?? 30)),
            CURLOPT_USERAGENT      => (string) ($args['user-agent'] ?? ''),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_HEADER         => true,
        ];

        /**
         * @param array<int, mixed>    $curlOptions
         * @param string               $url
         * @param array<string, mixed> $args
         */
        $curlOptions = apply_filters('gro_melipayamak_tunnel_curl_options', $curlOptions, $url, $args);

        curl_setopt_array($handle, $curlOptions);

        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        if ($raw === false) {
            $message = $error !== '' ? $error : 'Melipayamak SMS tunnel request failed.';

            return new \WP_Error('http_request_failed', $message, $errno !== 0 ? ['curl_errno' => $errno] : null);
        }

        $headerRaw = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return [
            'headers'  => self::parseResponseHeaders($headerRaw),
            'body'     => $responseBody,
            'response' => [
                'code'    => $httpCode,
                'message' => get_status_header_desc($httpCode) ?: '',
            ],
            'cookies'  => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function parseResponseHeaders(string $headerRaw): array
    {
        $headers = [];

        foreach (preg_split("/\r\n|\n|\r/", $headerRaw) ?: [] as $line) {
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}
