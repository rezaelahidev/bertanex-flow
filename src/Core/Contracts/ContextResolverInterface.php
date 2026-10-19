<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Contracts;

/**
 * Defines the context resolver contract.
 *
 * Context resolvers provide access to data during workflow execution.
 *
 * @package BertanexFlow\Core\Data
 */
interface ContextResolverInterface
{
    /**
     * Resolve a value by path notation (e.g., "order.total", "customer.email").
     *
     * @param string $path Data path (nested via dot notation).
     * @param mixed $default Default value if path not found.
     * @return mixed Resolved value.
     */
    public function resolve(string $path, mixed $default = null): mixed;

    /**
     * Get all available data.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array;

    /**
     * Check if a data path exists.
     *
     * @param string $path Data path.
     * @return bool
     */
    public function has(string $path): bool;
}