<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Runtime user state snapshots are purely filter-driven closures ({@see 'gro_user_state_specs'}).
 *
 * Ship sensible defaults wired to legacy `gro_`/`ma_*` meta keys—extend/replace maps without editing core PHP.
 */
final class WordPressUserStateProvider implements UserStateProviderInterface
{
    /** @inheritDoc */
    public function getStateForUser(int $userId): array
    {
        if ($userId < 1) {
            /** @phpstan-ignore-next-line */
            return apply_filters('gro_rule_user_state', [], $userId);
        }

        $state = [];

        foreach ($this->resolvedSpecifications($userId) as $fieldKey => $callback) {
            if (! is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            if (! is_callable($callback)) {
                continue;
            }

            try {
                $state[$fieldKey] = $callback($userId);
            } catch (\Throwable) {
                $state[$fieldKey] = null;
            }
        }

        /** @phpstan-ignore-next-line */
        return apply_filters('gro_rule_user_state', $state, $userId);
    }

    /** @return array<string, callable(int $userId): mixed> */
    private function baselineSpecifications(): array
    {
        return [
            'profile_completed'               => fn (int $uid): bool => $this->boolMeta($uid, 'gro_profile_completed', 'ma_profile_completed'),
            'has_product'                   => fn (int $uid): bool => $this->boolMeta($uid, 'gro_has_product', 'ma_has_product'),
            'has_subscription'              => fn (int $uid): bool => $this->boolMeta($uid, 'gro_has_subscription', 'ma_has_subscription'),
            'subscription_status'           => fn (int $uid): string => $this->stringMeta($uid, 'gro_subscription_status', 'ma_subscription_status'),
            'installment_status'            => fn (int $uid): string => $this->stringMeta($uid, 'gro_installment_status', 'ma_installment_status'),
            'subscription_days_until_expiry' => fn (int $uid): ?int => $this->nullableIntMeta(
                $uid,
                'gro_subscription_days_until_expiry',
                'ma_subscription_days_until_expiry'
            ),
        ];
    }

    /** @return array<string, callable(int $userId): mixed> */
    private function resolvedSpecifications(int $userId): array
    {
        /**
         * @param array<string, callable(int): mixed> $specs Indexed by state field key surfaced to JSON rules (`field` predicates).
         * @param int                                 $userId Context user for filters that hydrate dynamic maps.
         */
        $filtered = apply_filters('gro_user_state_specs', $this->baselineSpecifications(), $userId);

        return is_array($filtered) ? $filtered : $this->baselineSpecifications();
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
