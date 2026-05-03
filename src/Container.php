<?php

declare(strict_types=1);

namespace MarketingAutomation;

/**
 * Lightweight service locator with shared (singleton) factories.
 *
 * @template T of object
 */
final class Container
{
    /** @var array<string, array{0: callable(self): mixed, 1: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @param callable(self): mixed $factory
     */
    public function bind(string $id, callable $factory, bool $shared = false): void
    {
        $this->bindings[$id] = [$factory, $shared];
    }

    /**
     * @param callable(self): mixed $factory
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = [$factory, true];
    }

    /**
     * @template T
     * @param  class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (! isset($this->bindings[$id])) {
            throw new \InvalidArgumentException(sprintf('Unknown service: %s', $id));
        }

        [$factory, $shared] = $this->bindings[$id];
        $instance           = $factory($this);

        if ($shared) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }
}
