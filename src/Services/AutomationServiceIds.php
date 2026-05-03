<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Services;

/**
 * Built-in registry keys for {@see AutomationServices}.
 * Add-on code should define its own string constants to avoid collisions.
 */
final class AutomationServiceIds
{
    public const SMS = 'sms';

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
