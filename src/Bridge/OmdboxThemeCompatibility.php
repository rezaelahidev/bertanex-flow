<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Bridge;

use GrowthOrchestrator\Rule\ThemeCompatibilityInterface;

if (! defined('ABSPATH')) {
    exit;
}

final class OmdboxThemeCompatibility implements ThemeCompatibilityInterface
{
    public function isProfileCompleted(int $userId): bool
    {
        return growth_orchestrator_omdbox_is_profile_completed($userId);
    }

    public function hasProduct(int $userId): bool
    {
        return growth_orchestrator_omdbox_user_has_product($userId);
    }

    public function hasSubscription(int $userId): bool
    {
        if (! function_exists('taktheme_check_subscribe_user')) {
            return false;
        }

        return taktheme_check_subscribe_user($userId);
    }

    public function getSubscriptionStatus(int $userId): string
    {
        return growth_orchestrator_omdbox_subscription_status($userId);
    }

    public function getCalculatedDaysToExpiry(int $userId): int
    {
        return growth_orchestrator_omdbox_subscription_days_until_expiry($userId);
    }
}
