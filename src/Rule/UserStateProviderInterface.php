<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Snapshot of user fields used by the rule engine (see docs/user-states.md).
 *
 * @return array{
 *     profile_completed?: bool,
 *     has_product?: bool,
 *     has_subscription?: bool,
 *     subscription_status?: string,
 *     subscription_days_until_expiry?: int|null
 * }
 */
interface UserStateProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getStateForUser(int $userId): array;

    /**
     * @return ThemeCompatibilityInterface|null
     *     null if the theme compatibility is not available
     */
    public function getThemeCompatibility(): ?ThemeCompatibilityInterface;
}
