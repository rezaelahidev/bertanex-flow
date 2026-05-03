<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

/**
 * Resolves MSISDN / phone for a user. Filter wins, then common meta keys.
 */
final class SmsPhoneResolver
{
    public function resolve(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }

        $filtered = apply_filters('ma_sms_user_phone', null, $userId);

        if (is_string($filtered) && $filtered !== '') {
            return $this->normalize($filtered);
        }

        foreach (['ma_phone', 'billing_phone', 'phone'] as $metaKey) {
            $raw = get_user_meta($userId, $metaKey, true);
            if (is_string($raw) && $raw !== '') {
                return $this->normalize($raw);
            }
        }

        return null;
    }

    private function normalize(string $phone): string
    {
        return trim(preg_replace('/\s+/', ' ', $phone) ?? $phone);
    }
}
