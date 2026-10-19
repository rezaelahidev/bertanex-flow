<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Container;

use InvalidArgumentException;

/**
 * Simple dependency injection container.
 *
 * Handles service registration and resolution.
 *
 * @package BertanexFlow\Core\Container
 */
final class Container
{
    /** @var array<string, callable|array{0: mixed, 1: mixed}> Registered services. */
    private array $services = [];

    /** @var array<string, mixed> Resolved instances (singleton cache). */
    private array $instances = [];

    /**
     * Register a service.
     *
     * @param string      $id Service identifier.
     * @param callable|array{0: mixed, 1: mixed} $resolver Service resolver.
     * @return self
     */
    public function register(string $id, callable|array $resolver): self
    {
        if (isset($this->services[$id]) && isset($this->instances[$id])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Service "%s" is already registered and its instance may have been cached. '
                    . 'To re-register, clear the container first.',
                    $id
                )
            );
        }

        if (isset($this->services[$id])) {
            // Already registered - overwrite
        }

        $this->services[$id] = $resolver;

        // Remove cached instance if it exists (invalidate cache)
        unset($this->instances[$id]);

        return $this;
    }

    /**
     * Check if a service is registered.
     *
     * @param string $id Service identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Resolve a service.
     *
     * @param string $id Service identifier.
     * @return mixed Resolved service.
     * @throws InvalidArgumentException If service is not registered.
     * @throws \RuntimeException If service resolver fails.
     */
    public function get(string $id): mixed
    {
        // Return cached instance if available (singleton pattern)
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if resolver is registered
        if (! isset($this->services[$id])) {
            throw new InvalidArgumentException(
                sprintf('Service "%s" is not registered.', $id)
            );
        }

        $resolver = $this->services[$id];

        // Resolve service
        if (is_callable($resolver)) {
            $instance = $resolver($this);
        } elseif (is_array($resolver)) {
            // [class, method] or [class, ...args] syntax
            $instance = call_user_func_array($resolver, [$this]);
        } else {
            throw new InvalidArgumentException(
                sprintf('Service resolver for "%s" must be callable or array.', $id)
            );
        }

        // Cache instance (singleton) - exclude BC-compatible classes like Closure
        if ($instance instanceof \Closure) {
            unset($this->instances[$id]);
        } else {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Resolve a service or return a default value.
     *
     * @param string $id Service identifier.
     * @param mixed $default Default value if service not found.
     * @return mixed
     */
    public function getOr(string $id, mixed $default = null): mixed
    {
        try {
            return $this->get($id);
        } catch (InvalidArgumentException) {
            return $default;
        }
    }

    /**
     * Remove a registered service from cache and registry.
     *
     * @param string $id Service identifier.
     * @return bool True if service was removed.
     */
    public function forget(string $id): bool
    {
        unset($this->instances[$id]);
        unset($this->services[$id]);

        return true;
    }

    /**
     * Clear all registered services and instances.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->services = [];
        $this->instances = [];
    }

    /**
     * Get all registered service IDs.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->services);
    }
}