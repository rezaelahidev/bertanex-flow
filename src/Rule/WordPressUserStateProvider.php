<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Runtime user state snapshots are purely filter-driven closures ({@see 'gro_user_state_specs'}).
 *
 * Baseline meta keys use growth_orchestrator_* and growth_subscriptions_* prefixes.
 */
final class WordPressUserStateProvider implements UserStateProviderInterface
{
    private ?ThemeCompatibilityInterface $themeCompatibility;

    public function __construct(?ThemeCompatibilityInterface $themeCompatibility = null)
    {
        $this->themeCompatibility = $themeCompatibility;
    }

    /** @inheritDoc */
    public function getThemeCompatibility(): ?ThemeCompatibilityInterface
    {
        return $this->themeCompatibility;
    }

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
            'profile_completed' => fn (int $uid): bool => $this->getThemeCompatibility()
                                                    ? $this->themeCompatibility->isProfileCompleted($uid) :
                                                    $this->boolMeta($uid, 'growth_orchestrator_profile_completed'),
            'has_product' => fn (int $uid): bool => $this->getThemeCompatibility()
                                                    ? $this->themeCompatibility->hasProduct($uid) :
                                                    $this->boolMeta($uid, 'growth_orchestrator_has_product'),
            'has_subscription' => fn (int $uid): bool => $this->getThemeCompatibility()
                                                    ? $this->themeCompatibility->hasSubscription($uid) :
                                                    $this->boolMeta($uid, 'growth_subscriptions_has_subscription'),
            'subscription_status' => fn (int $uid): string => $this->getThemeCompatibility() ?
                                                    $this->themeCompatibility->getSubscriptionStatus($uid) :
                                                    $this->stringMeta($uid, 'growth_subscriptions_subscription_status'),
            'subscription_days_until_expiry' => fn (int $uid): ?int => $this->getThemeCompatibility() ?
                                                    $this->themeCompatibility->getCalculatedDaysToExpiry($uid) :
                                                    $this->nullableIntMeta($uid, 'growth_subscriptions_days_until_expiry'),
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

    private function boolMeta(int $userId, string $key): bool
    {
        $v = get_user_meta($userId, $key, true);

        return $v !== '' && $v !== null && (bool) $v;
    }

    private function stringMeta(int $userId, string $key): string
    {
        $v = get_user_meta($userId, $key, true);

        return is_string($v) ? $v : '';
    }

    private function nullableIntMeta(int $userId, string $key): ?int
    {
        $v = get_user_meta($userId, $key, true);

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
