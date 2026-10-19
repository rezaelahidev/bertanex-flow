<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Data;

/**
 * Named value transformations used by mapping and variable filters.
 */
final class TransformationRegistry
{
    /** @var array<string, callable> */
    private array $transformers = [];

    public function __construct()
    {
        $this->register('uppercase', static fn (mixed $value): string => strtoupper(self::string($value)));
        $this->register('lowercase', static fn (mixed $value): string => strtolower(self::string($value)));
        $this->register('trim', static fn (mixed $value): string => trim(self::string($value)));
        $this->register('format_date', static function (mixed $value, mixed $format = 'Y-m-d'): string {
            $timestamp = is_numeric($value) ? (int) $value : strtotime(self::string($value));

            if ($timestamp === false) {
                return '';
            }

            return gmdate(is_string($format) && $format !== '' ? $format : 'Y-m-d', $timestamp);
        });
        $this->register('format_currency', static function (mixed $value, mixed $currency = ''): string {
            $amount = is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0.00';

            return trim($amount . ' ' . self::string($currency));
        });
        $this->register('concat', static function (mixed $value, mixed $suffix = ''): string {
            return self::string($value) . self::string($suffix);
        });
        $this->register('split', static function (mixed $value, mixed $delimiter = ','): array {
            $parts = explode(self::string($delimiter) === '' ? ',' : self::string($delimiter), self::string($value));

            return array_values(array_map('trim', $parts));
        });
        $this->register('default', static fn (mixed $value, mixed $fallback = ''): mixed => ($value === null || $value === '') ? $fallback : $value);
        $this->register('conditional', static function (mixed $value, mixed $expected = '', mixed $whenTrue = '', mixed $whenFalse = ''): mixed {
            return (string) $value === (string) $expected ? $whenTrue : $whenFalse;
        });
    }

    public function register(string $name, callable $transformer): void
    {
        $this->transformers[$name] = $transformer;
    }

    public function has(string $name): bool
    {
        return isset($this->transformers[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->transformers);
    }

    /**
     * @param list<mixed> $args
     */
    public function apply(string $name, mixed $value, array $args = []): mixed
    {
        if (! isset($this->transformers[$name])) {
            return $value;
        }

        return ($this->transformers[$name])($value, ...$args);
    }

    private static function string(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }
}
