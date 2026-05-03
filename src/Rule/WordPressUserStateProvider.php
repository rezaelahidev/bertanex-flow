<?php

declare(strict_types=1);

namespace MarketingAutomation\Rule;

/**
 * Resolve user state from user meta. Keys are examples — adjust meta keys to your stack.
 */
final class WordPressUserStateProvider implements UserStateProviderInterface
{
    public function getStateForUser(int $userId): array
    {
        return [
            'profile_completed'              => (bool) get_user_meta($userId, 'ma_profile_completed', true),
            'has_product'                    => (bool) get_user_meta($userId, 'ma_has_product', true),
            'has_subscription'               => (bool) get_user_meta($userId, 'ma_has_subscription', true),
            'subscription_status'            => (string) get_user_meta($userId, 'ma_subscription_status', true),
            'installment_status'             => (string) get_user_meta($userId, 'ma_installment_status', true),
            'subscription_days_until_expiry' => $this->nullableInt(get_user_meta($userId, 'ma_subscription_days_until_expiry', true)),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
