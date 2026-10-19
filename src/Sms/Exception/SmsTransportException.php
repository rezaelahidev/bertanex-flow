<?php

declare(strict_types=1);

namespace BertanexFlow\Sms\Exception;

/**
 * Thrown when an SMS provider API call fails. {@see retrySuggested()} drives default worker retries.
 */
final class SmsTransportException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $retrySuggested = true,
        private readonly ?int $httpStatus = null,
        private readonly ?int $providerCode = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function retrySuggested(): bool
    {
        return $this->retrySuggested;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function providerCode(): ?int
    {
        return $this->providerCode;
    }
}
