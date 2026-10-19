<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

interface SmsServiceInterface
{
    /**
     * @param array<string, mixed> $context Merge tags / provider payload.
     */
    public function send(int $userId, string $templateId, array $context = []): void;
}
