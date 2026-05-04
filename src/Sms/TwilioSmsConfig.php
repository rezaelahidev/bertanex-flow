<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

/**
 * Twilio credentials: wp-config constants and/or {@see 'gro_twilio_sms_config'} filter.
 *
 * Define in wp-config.php (recommended):
 * <code>
 * define('GROWTH_ORCH_TWILIO_ACCOUNT_SID', 'AC...');
 * define('GROWTH_ORCH_TWILIO_AUTH_TOKEN', '...');
 * define('GROWTH_ORCH_TWILIO_FROM_NUMBER', '+15005550006'); // E.164 or Twilio number
 * </code>
 */
final class TwilioSmsConfig
{
    /**
     * @return array{account_sid: string, auth_token: string, from: string}|null
     */
    public function resolve(): ?array
    {
        /** @var array{account_sid?: string, auth_token?: string, from?: string}|null $filtered */
        $filtered = apply_filters('gro_twilio_sms_config', null);

        $sid   = $this->stringFrom($filtered['account_sid'] ?? null, defined('GROWTH_ORCH_TWILIO_ACCOUNT_SID') ? (string) GROWTH_ORCH_TWILIO_ACCOUNT_SID : '');
        $token = $this->stringFrom($filtered['auth_token'] ?? null, defined('GROWTH_ORCH_TWILIO_AUTH_TOKEN') ? (string) GROWTH_ORCH_TWILIO_AUTH_TOKEN : '');
        $from  = $this->stringFrom($filtered['from'] ?? null, defined('GROWTH_ORCH_TWILIO_FROM_NUMBER') ? (string) GROWTH_ORCH_TWILIO_FROM_NUMBER : '');

        if ($sid === '' || $token === '' || $from === '') {
            return null;
        }

        return [
            'account_sid' => $sid,
            'auth_token'  => $token,
            'from'        => $from,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->resolve() !== null;
    }

    private function stringFrom(?string $filterValue, string $constantValue): string
    {
        if (is_string($filterValue) && $filterValue !== '') {
            return trim($filterValue);
        }

        return trim($constantValue);
    }
}
