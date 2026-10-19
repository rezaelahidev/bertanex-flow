<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Data;

use BertanexFlow\Core\Contracts\ContextResolverInterface;

/**
 * Structured data available to one workflow execution.
 */
final class DataContext implements ContextResolverInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function resolve(string $path, mixed $default = null): mixed
    {
        if ($path === '') {
            return $default;
        }

        $current = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            if (is_array($current) && ctype_digit($segment) && array_key_exists((int) $segment, $current)) {
                $current = $current[(int) $segment];
                continue;
            }

            return $default;
        }

        return $current;
    }

    public function getAll(): array
    {
        return $this->data;
    }

    public function has(string $path): bool
    {
        $missing = new \stdClass();

        return $this->resolve($path, $missing) !== $missing;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data): void
    {
        $this->data = array_replace_recursive($this->data, $data);
    }
}
