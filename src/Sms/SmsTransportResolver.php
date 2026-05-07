<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

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

        $provider    = (string) get_option(self::OPTION_PROVIDER, 'auto');
        $melipayamak = new MelipayamakSmsConfig();
        $twilio      = new TwilioSmsConfig();

        $meliOk   = $melipayamak->isConfigured();
        $twilioOk = $twilio->isConfigured();

        return match ($provider) {
            'melipayamak' => $meliOk
                ? new MelipayamakSmsTransport($melipayamak)
                : new LoggingSmsTransport(),
            'twilio' => $twilioOk
                ? new TwilioSmsTransport($twilio)
                : new LoggingSmsTransport(),
            'logging' => new LoggingSmsTransport(),
            'auto' => $meliOk
                ? new MelipayamakSmsTransport($melipayamak)
                : ($twilioOk
                    ? new TwilioSmsTransport($twilio)
                    : new LoggingSmsTransport()),
            default => $meliOk
                ? new MelipayamakSmsTransport($melipayamak)
                : ($twilioOk
                    ? new TwilioSmsTransport($twilio)
                    : new LoggingSmsTransport()),
        };
    }
}
