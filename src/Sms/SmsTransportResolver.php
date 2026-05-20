<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

use GrowthOrchestrator\Support\FeatureFlags;

/**
 * Resolves the active SMS transport from options on each call so Growth Orchestrator → SMS admin changes apply immediately.
 */
final class SmsTransportResolver
{
    private const OPTION_PROVIDER = 'growth_orchestrator_sms_transport_provider';

    public function resolve(): SmsTransportInterface
    {
        /**
         * Force a transport (tests, alternate providers).
         *
         * @param SmsTransportInterface|null $transport
         */
        $filtered = apply_filters('gro_sms_transport', null);
        if ($filtered instanceof SmsTransportInterface) {
            return $filtered;
        }

        $provider = self::normalizeProvider((string) get_option(self::OPTION_PROVIDER, 'auto'));
        $melipayamak = new MelipayamakSmsConfig();
        $meliOk      = $melipayamak->isConfigured();
        $twilioOk    = FeatureFlags::twilioEnabled() && (new TwilioSmsConfig())->isConfigured();

        return match ($provider) {
            'melipayamak' => $meliOk
                ? new MelipayamakSmsTransport($melipayamak)
                : new LoggingSmsTransport(),
            'twilio' => $twilioOk
                ? new TwilioSmsTransport(new TwilioSmsConfig())
                : new LoggingSmsTransport(),
            'logging' => new LoggingSmsTransport(),
            default => $meliOk
                ? new MelipayamakSmsTransport($melipayamak)
                : ($twilioOk
                    ? new TwilioSmsTransport(new TwilioSmsConfig())
                    : new LoggingSmsTransport()),
        };
    }

    private static function normalizeProvider(string $provider): string
    {
        if ($provider === 'twilio' && ! FeatureFlags::twilioEnabled()) {
            return 'auto';
        }

        $allowed = FeatureFlags::twilioEnabled()
            ? ['auto', 'melipayamak', 'twilio', 'logging']
            : ['auto', 'melipayamak', 'logging'];

        return in_array($provider, $allowed, true) ? $provider : 'auto';
    }
}
