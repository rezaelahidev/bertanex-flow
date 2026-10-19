<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Data;

/**
 * Replaces {{path}} and {{path|transform:arg}} tokens in strings.
 */
final class VariableResolver
{
    public function __construct(
        private readonly DataContext $context,
        private readonly TransformationRegistry $transformations
    ) {
    }

    public function resolve(string $template): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)(?:\|([a-zA-Z0-9_]+)(?::([^}]+))?)?\s*\}\}/',
            function (array $matches): string {
                $value = $this->context->resolve($matches[1], '');

                if (isset($matches[2]) && $matches[2] !== '') {
                    $args = [];
                    if (isset($matches[3]) && $matches[3] !== '') {
                        $args = array_map('trim', explode(',', $matches[3]));
                    }
                    $value = $this->transformations->apply($matches[2], $value, $args);
                }

                if (is_array($value)) {
                    return implode(', ', array_map('strval', $value));
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                return is_scalar($value) || $value === null ? (string) $value : '';
            },
            $template
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function resolveArray(array $payload): array
    {
        $resolved = [];

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $resolved[$key] = $this->resolve($value);
                continue;
            }

            if (is_array($value)) {
                $resolved[$key] = $this->resolveArray($value);
                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }
}
