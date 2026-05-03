<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Resolve user state from user meta. Keys use {@code gro_*} with legacy {@code ma_*} fallback.
 */
final class WordPressUserStateProvider implements UserStateProviderInterface
{
    public function getStateForUser(int $userId): array
    {
        return [
            'profile_completed'              => $this->boolMeta($userId, 'gro_profile_completed', 'ma_profile_completed'),
            'has_product'                    => $this->boolMeta($userId, 'gro_has_product', 'ma_has_product'),
            'has_subscription'               => $this->boolMeta($userId, 'gro_has_subscription', 'ma_has_subscription'),
            'subscription_status'            => $this->stringMeta($userId, 'gro_subscription_status', 'ma_subscription_status'),
            'installment_status'             => $this->stringMeta($userId, 'gro_installment_status', 'ma_installment_status'),
            'subscription_days_until_expiry' => $this->nullableIntMeta($userId, 'gro_subscription_days_until_expiry', 'ma_subscription_days_until_expiry'),
        ];
    }

    private function boolMeta(int $userId, string $primaryKey, string $legacyKey): bool
    {
        $primary = get_user_meta($userId, $primaryKey, true);
        if ($primary !== '' && $primary !== null) {
            return (bool) $primary;
        }

        return (bool) get_user_meta($userId, $legacyKey, true);
    }

    private function stringMeta(int $userId, string $primaryKey, string $legacyKey): string
    {
        $primary = get_user_meta($userId, $primaryKey, true);
        if (is_string($primary) && $primary !== '') {
            return $primary;
        }

        return (string) get_user_meta($userId, $legacyKey, true);
    }

    private function nullableIntMeta(int $userId, string $primaryKey, string $legacyKey): ?int
    {
        $primary = get_user_meta($userId, $primaryKey, true);
        $v       = ($primary !== '' && $primary !== null) ? $primary : get_user_meta($userId, $legacyKey, true);

        return $this->nullableInt($v);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
