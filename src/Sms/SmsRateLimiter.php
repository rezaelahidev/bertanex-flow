<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

/**
 * Fixed window per user: max sends per hour (site-local hour bucket via {@see wp_date}).
 */
final class SmsRateLimiter
{
    public function tryConsume(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        $max = max(1, (int) apply_filters('ma_sms_rate_limit_per_user_per_hour', 10, $userId));

        $bucket = wp_date('Y-m-d-H');
        $key    = 'ma_sms_rl_' . $userId . '_' . $bucket;

        $count = (int) get_transient($key);

        if ($count >= $max) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);

        return true;
    }
}
