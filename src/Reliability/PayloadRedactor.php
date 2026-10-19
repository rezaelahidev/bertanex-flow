<?php

declare(strict_types=1);

namespace BertanexFlow\Reliability;

/**
 * Removes secret-looking values before they are stored or returned.
 */
final class PayloadRedactor
{
    /** @var list<string> */
    private const SENSITIVE = [
        'password',
        'secret',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::SENSITIVE, true)) {
                $clean[$key] = '[redacted]';
                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }
}
