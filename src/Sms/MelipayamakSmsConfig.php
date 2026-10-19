<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

/**
 * Melipayamak (Payamak-Panel) REST credentials: options, wp-config constants, and {@see 'gro_melipayamak_sms_config'}.
 *
 * Recommended production: define secrets in wp-config.php and leave admin password empty, or use filters.
 *
 * Options (when using admin UI): {@see \BertanexFlow\Admin\Models\SmsSettingsModel}.
 */
final class MelipayamakSmsConfig
{
    public const OPTION_USERNAME = 'bertanex_flow_melipayamak_username';

    public const OPTION_PASSWORD = 'bertanex_flow_melipayamak_password';

    public const OPTION_FROM = 'bertanex_flow_melipayamak_from';

    /**
     * @return array{username: string, password: string, from: string}|null
     */
    public function resolve(): ?array
    {
        /** @var array{username?: string, password?: string, from?: string}|null $filtered */
        $filtered = apply_filters('gro_melipayamak_sms_config', null);

        $user = $this->stringFrom(
            $filtered['username'] ?? null,
            (string) get_option(self::OPTION_USERNAME, ''),
            defined('BERTANEX_FLOW_MELIPAYAMAK_USERNAME') ? (string) BERTANEX_FLOW_MELIPAYAMAK_USERNAME : ''
        );
        $pass = $this->passwordFrom(
            $filtered['password'] ?? null,
            (string) get_option(self::OPTION_PASSWORD, ''),
            defined('BERTANEX_FLOW_MELIPAYAMAK_PASSWORD') ? (string) BERTANEX_FLOW_MELIPAYAMAK_PASSWORD : ''
        );
        $from = $this->stringFrom(
            $filtered['from'] ?? null,
            (string) get_option(self::OPTION_FROM, ''),
            defined('BERTANEX_FLOW_MELIPAYAMAK_FROM') ? (string) BERTANEX_FLOW_MELIPAYAMAK_FROM : ''
        );

        if ($user === '' || $pass === '') {
            return null;
        }

        return [
            'username' => $user,
            'password' => $pass,
            'from'     => $from,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->resolve() !== null;
    }

    private function stringFrom(?string $filterOverride, string $optionValue, string $constantFallback): string
    {
        if (is_string($filterOverride) && $filterOverride !== '') {
            return trim($filterOverride);
        }

        if ($optionValue !== '') {
            return trim($optionValue);
        }

        return trim($constantFallback);
    }

    private function passwordFrom(?string $filterOverride, string $optionValue, string $constantFallback): string
    {
        if (is_string($filterOverride) && $filterOverride !== '') {
            return $filterOverride;
        }

        if ($optionValue !== '') {
            return $optionValue;
        }

        return $constantFallback;
    }
}
