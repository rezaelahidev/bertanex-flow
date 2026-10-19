<?php

declare(strict_types=1);

namespace BertanexFlow\Support;

/**
 * Temporary feature toggles. Flip constants here to re-enable hidden integrations.
 */
final class FeatureFlags
{
    /** Set true to show Twilio in SMS settings and use it in auto transport resolution. */
    public const TWILIO_ENABLED = false;

    public static function twilioEnabled(): bool
    {
        return self::TWILIO_ENABLED;
    }
}
