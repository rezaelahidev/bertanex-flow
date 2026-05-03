<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

interface SmsTemplateProviderInterface
{
    public function get(string $templateId): ?string;
}
