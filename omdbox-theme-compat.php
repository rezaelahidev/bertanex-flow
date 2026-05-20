<?php
/**
 * OMDBOX theme integration for Growth Orchestrator (backward-compatible entrypoints).
 *
 * @package GrowthOrchestrator
 */

declare(strict_types=1);

use GrowthOrchestrator\Event\EventType;
use GrowthOrchestrator\Rule\ThemeCompatibilityInterface;

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('growth_orchestrator_omdbox_theme_compat_active')) {
    function growth_orchestrator_omdbox_theme_compat_active(): bool
    {
        return function_exists('taktheme_check_subscribe_user')
            || function_exists('taktheme_persian_to_english');
    }
}

if (! function_exists('growth_orchestrator_omdbox_normalize_phone')) {
    function growth_orchestrator_omdbox_normalize_phone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        if (function_exists('taktheme_persian_to_english')) {
            $phone = taktheme_persian_to_english($phone);
        }

        $phone = preg_replace('/[^\d+]/', '', $phone) ?? $phone;

        return trim($phone);
    }
}

if (! function_exists('growth_orchestrator_omdbox_resolve_user_phone')) {
    /**
     * Supplier / seller MSISDN for SMS: pn1, user_login, pn2, Woo billing_phone.
     */
    function growth_orchestrator_omdbox_resolve_user_phone(?int $user_id = null): ?string
    {
        if ($user_id === null) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id < 1) {
            return null;
        }

        $candidates = [];

        foreach (['pn1', 'pn2', 'billing_phone', 'phone'] as $meta_key) {
            $raw = get_user_meta($user_id, $meta_key, true);
            if (is_string($raw) && $raw !== '') {
                $candidates[] = $raw;
            }
        }

        $user = get_userdata($user_id);
        if ($user instanceof \WP_User && is_string($user->user_login) && $user->user_login !== '') {
            $candidates[] = $user->user_login;
        }

        foreach ($candidates as $candidate) {
            $normalized = growth_orchestrator_omdbox_normalize_phone((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }
}

if (! function_exists('growth_orchestrator_omdbox_is_profile_completed')) {
    function growth_orchestrator_omdbox_is_profile_completed(int $user_id): bool
    {
        if ($user_id < 1) {
            return false;
        }

        $user = get_userdata($user_id);
        if (! $user instanceof \WP_User) {
            return false;
        }

        $pn1 = (string) get_user_meta($user_id, 'pn1', true);
        $display_name = (string) $user->display_name;

        if ($pn1 === '' || $display_name === '') {
            return false;
        }

        $display_check = function_exists('taktheme_persian_to_english')
            ? taktheme_persian_to_english($display_name)
            : $display_name;

        return ! preg_match('/^09[0-9]{9}$/', $display_check);
    }
}

if (! function_exists('growth_orchestrator_omdbox_user_has_product')) {
    function growth_orchestrator_omdbox_user_has_product(int $user_id): bool
    {
        if ($user_id < 1) {
            return false;
        }

        $query = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        wp_reset_postdata();

        return $query->have_posts();
    }
}

if (! function_exists('growth_orchestrator_omdbox_subscription_status')) {
    function growth_orchestrator_omdbox_subscription_status(int $user_id): string
    {
        if ($user_id < 1) {
            return 'unknown';
        }

        if (function_exists('taktheme_get_subscription_renewal_state')) {
            $state = taktheme_get_subscription_renewal_state($user_id);
            if (is_array($state) && isset($state['status']) && is_string($state['status']) && $state['status'] !== '') {
                return $state['status'];
            }
        }

        if (function_exists('taktheme_check_subscribe_user') && taktheme_check_subscribe_user($user_id)) {
            return 'active';
        }

        return 'expired';
    }
}

if (! function_exists('growth_orchestrator_omdbox_subscription_days_until_expiry')) {
    function growth_orchestrator_omdbox_subscription_days_until_expiry(int $user_id): int
    {
        if ($user_id < 1) {
            return 0;
        }

        if (function_exists('taktheme_get_subscription_renewal_state')) {
            $state = taktheme_get_subscription_renewal_state($user_id);
            if (is_array($state) && isset($state['days_remaining']) && is_numeric($state['days_remaining'])) {
                return max(0, (int) $state['days_remaining']);
            }
        }

        $expire_raw = '';
        if (function_exists('growth_subscriptions_subscription_expires_at')) {
            $expire_raw = growth_subscriptions_subscription_expires_at($user_id);
        }
        if ($expire_raw === '') {
            $expire_raw = (string) get_user_meta($user_id, 'user_subscribe_expire', true);
        }

        if ($expire_raw === '') {
            return 0;
        }

        $expire_ts = strtotime($expire_raw);
        if (! $expire_ts) {
            return 0;
        }

        if (function_exists('taktheme_subscription_expire_calendar_days_remaining')) {
            return max(0, taktheme_subscription_expire_calendar_days_remaining((int) $expire_ts));
        }

        $days = (int) floor(($expire_ts - time()) / DAY_IN_SECONDS);

        return max(0, $days);
    }
}

if (! function_exists('growth_orchestrator_omdbox_maybe_emit_profile_completed')) {
    function growth_orchestrator_omdbox_maybe_emit_profile_completed(int $user_id): void
    {
        if ($user_id < 1 || ! growth_orchestrator_omdbox_is_profile_completed($user_id)) {
            return;
        }

        $flag_key = '_growth_orchestrator_profile_completed_emitted';
        if (get_user_meta($user_id, $flag_key, true) === 'yes') {
            return;
        }

        update_user_meta($user_id, $flag_key, 'yes');
        do_action('gro_emit_event', EventType::ProfileCompleted->value, ['user_id' => $user_id]);
    }
}

add_filter('gro_theme_compatibility', static function (?ThemeCompatibilityInterface $compat): ?ThemeCompatibilityInterface {
    if ($compat instanceof ThemeCompatibilityInterface) {
        return $compat;
    }

    if (! growth_orchestrator_omdbox_theme_compat_active()) {
        return null;
    }

    return new \GrowthOrchestrator\Bridge\OmdboxThemeCompatibility();
}, 10, 1);

add_filter('gro_sms_user_phone', static function (?string $phone, int $userId): ?string {
    if (is_string($phone) && $phone !== '') {
        return $phone;
    }

    if (! growth_orchestrator_omdbox_theme_compat_active()) {
        return $phone;
    }

    return growth_orchestrator_omdbox_resolve_user_phone($userId) ?? $phone;
}, 10, 2);

add_filter('gro_sms_template_variables', static function (array $variables, int $userId, array $context): array {
    if (! growth_orchestrator_omdbox_theme_compat_active() || $userId < 1) {
        return $variables;
    }

    $shop_name = (string) get_user_meta($userId, 'shop_name', true);
    if ($shop_name === '' && isset($variables['name']) && is_string($variables['name'])) {
        $shop_name = $variables['name'];
    }

    if ($shop_name !== '') {
        $variables['shop_name'] = $shop_name;
    }

    $resolved_phone = growth_orchestrator_omdbox_resolve_user_phone($userId);
    if ($resolved_phone !== null && $resolved_phone !== '') {
        $variables['phone'] = $resolved_phone;
    }

    return $variables;
}, 10, 3);

add_action('profile_update', static function (int $user_id): void {
    if (! growth_orchestrator_omdbox_theme_compat_active()) {
        return;
    }

    growth_orchestrator_omdbox_maybe_emit_profile_completed($user_id);
}, 20, 1);

add_action('updated_user_meta', static function (int $meta_id, int $user_id, string $meta_key): void {
    if (! growth_orchestrator_omdbox_theme_compat_active()) {
        return;
    }

    if (! in_array($meta_key, ['pn1', 'pn2', 'shop_name'], true)) {
        return;
    }

    growth_orchestrator_omdbox_maybe_emit_profile_completed($user_id);
}, 10, 3);

add_action('transition_post_status', static function (string $new_status, string $old_status, \WP_Post $post): void {
    if (! growth_orchestrator_omdbox_theme_compat_active()) {
        return;
    }

    if ($new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'post') {
        return;
    }

    $user_id = (int) $post->post_author;
    if ($user_id < 1) {
        return;
    }

    do_action('gro_emit_event', EventType::ProductCreated->value, [
        'user_id' => $user_id,
        'post_id' => (int) $post->ID,
    ]);
}, 20, 3);
