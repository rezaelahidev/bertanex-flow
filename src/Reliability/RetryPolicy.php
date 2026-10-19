<?php

declare(strict_types=1);

namespace BertanexFlow\Reliability;

/**
 * Calculates the next delay for a failed workflow attempt.
 */
final class RetryPolicy
{
    public function __construct(
        private readonly int $maxAttempts = 5,
        private readonly string $strategy = 'exponential_backoff',
        private readonly int $initialDelay = 60,
        private readonly int $maxDelay = 3600
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            max(1, (int) ($config['max_attempts'] ?? 5)),
            is_string($config['strategy'] ?? null) ? $config['strategy'] : 'exponential_backoff',
            max(1, (int) ($config['initial_delay'] ?? 60)),
            max(1, (int) ($config['max_delay'] ?? 3600))
        );
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function shouldRetry(int $completedAttempts): bool
    {
        return $completedAttempts < $this->maxAttempts;
    }

    public function delayForAttempt(int $completedAttempts): int
    {
        $attempt = max(1, $completedAttempts);
        $delay = match ($this->strategy) {
            'fixed' => $this->initialDelay,
            default => $this->initialDelay * (2 ** ($attempt - 1)),
        };

        return min($this->maxDelay, $delay);
    }
}
