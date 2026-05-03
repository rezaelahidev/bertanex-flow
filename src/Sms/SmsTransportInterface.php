<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

/**
 * Real send path. Throw on hard failure so the worker can apply retry policy.
 *
 * @phpstan-type JobMeta array<string, mixed>
 */
interface SmsTransportInterface
{
    /**
     * @param JobMeta $meta
     */
    public function deliver(string $to, string $body, array $meta): void;
}
