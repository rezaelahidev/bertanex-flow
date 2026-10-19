<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * WordPress account fields, plus WooCommerce order count when WooCommerce is active.
 *
 * Themes can still replace this through {@see 'gro_user_state_provider'}.
 */
final class WordPressUserStateProvider implements UserStateProviderInterface
{
    /** @inheritDoc */
    public function getStateForUser(int $userId): array
    {
        $state = [
            'user_id'    => $userId,
            'user_role'  => '',
            'user_email' => '',
            'user_login' => '',
        ];

        $user = get_userdata($userId);
        if ($user instanceof \WP_User) {
            $state['user_role']  = (string) ($user->roles[0] ?? '');
            $state['user_email'] = (string) $user->user_email;
            $state['user_login'] = (string) $user->user_login;
        }

        if (function_exists('wc_get_customer_order_count')) {
            $state['order_count'] = (int) wc_get_customer_order_count($userId);
        }

        /** @phpstan-ignore-next-line */
        $filtered = apply_filters('gro_rule_user_state', $state, $userId);

        return is_array($filtered) ? $filtered : $state;
    }

    /** @inheritDoc */
    public function getThemeCompatibility(): ?ThemeCompatibilityInterface
    {
        return null;
    }
}
