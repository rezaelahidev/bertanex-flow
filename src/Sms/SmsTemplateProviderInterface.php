<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

interface SmsTemplateProviderInterface
{
    public function get(string $templateId): ?string;
}
