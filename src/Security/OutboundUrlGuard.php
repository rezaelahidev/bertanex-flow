<?php

declare(strict_types=1);

namespace BertanexFlow\Security;

/**
 * Rejects outbound URLs that can reach local or private networks.
 */
final class OutboundUrlGuard
{
    public function assertAllowed(string $url): void
    {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
        if (! is_array($parts)) {
            throw new \InvalidArgumentException('Outbound URL is not valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Outbound URL must use HTTP or HTTPS.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new \InvalidArgumentException('Outbound URL host is not allowed.');
        }

        if (function_exists('wp_http_validate_url') && wp_http_validate_url($url) === false) {
            throw new \InvalidArgumentException('Outbound URL failed validation.');
        }

        $ips = $this->resolve($host);
        foreach ($ips as $ip) {
            if ($this->isPrivate($ip)) {
                throw new \InvalidArgumentException('Outbound URL resolves to a private network.');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = gethostbynamel($host);

        return is_array($records) ? $records : [];
    }

    private function isPrivate(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
