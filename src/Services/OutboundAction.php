<?php

declare(strict_types=1);

namespace BertanexFlow\Services;

/**
 * Normalized outbound step produced by the rule engine (aligns with docs/rule-schema action "type").
 *
 * @phpstan-type Params array<string, mixed>
 */
final class OutboundAction
{
    /**
     * @param Params $params
     */
    public function __construct(
        private readonly string $type,
        private readonly int $userId,
        private readonly string $templateId,
        private readonly array $params = []
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function templateId(): string
    {
        return $this->templateId;
    }

    /**
     * @return Params
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @param Params $params
     */
    public static function sendSms(int $userId, string $templateId, array $params = []): self
    {
        return new self('send_sms', $userId, $templateId, $params);
    }

    /**
     * @param Params $params
     */
    public static function sendEmail(int $userId, string $templateId, array $params = []): self
    {
        return new self('send_email', $userId, $templateId, $params);
    }

    /**
     * @param Params $params
     */
    public static function sendDiscount(int $userId, string $templateId, array $params = []): self
    {
        return new self('send_discount', $userId, $templateId, $params);
    }

    /**
     * @param Params $params
     */
    public static function webhook(int $userId, string $templateId, array $params = []): self
    {
        return new self('webhook', $userId, $templateId, $params);
    }

    /**
     * @param Params $params
     */
    public static function log(int $userId, string $templateId, array $params = []): self
    {
        return new self('log', $userId, $templateId, $params);
    }

    /**
     * @param Params $params
     */
    public static function custom(string $type, int $userId, string $templateId, array $params = []): self
    {
        return new self($type, $userId, $templateId, $params);
    }
}
