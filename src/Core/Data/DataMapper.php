<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Data;

/**
 * Maps context paths onto a target payload.
 */
final class DataMapper
{
    public function __construct(private readonly TransformationRegistry $transformations)
    {
    }

    /**
     * @param list<array{source?: string, target?: string, transform?: string, fallback?: mixed}> $mappings
     * @return array<string, mixed>
     */
    public function map(DataContext $context, array $mappings): array
    {
        $target = [];
        $resolver = new VariableResolver($context, $this->transformations);

        foreach ($mappings as $mapping) {
            $path = isset($mapping['target']) ? (string) $mapping['target'] : '';
            if ($path === '') {
                continue;
            }

            $source = isset($mapping['source']) ? (string) $mapping['source'] : '';
            $value = $source !== '' && str_contains($source, '{{')
                ? $resolver->resolve($source)
                : $context->resolve(trim($source, '{} '), $mapping['fallback'] ?? null);

            if (($value === null || $value === '') && array_key_exists('fallback', $mapping)) {
                $value = $mapping['fallback'];
            }

            if (isset($mapping['transform']) && is_string($mapping['transform']) && $mapping['transform'] !== '') {
                $value = $this->transformations->apply($mapping['transform'], $value);
            }

            $this->assign($target, $path, $value);
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function assign(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor = &$target;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                return;
            }

            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }
    }
}
