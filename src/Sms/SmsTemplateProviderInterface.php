<?php

declare(strict_types=1);

namespace MarketingAutomation\Sms;

interface SmsTemplateProviderInterface
{
    public function get(string $templateId): ?string;
}
