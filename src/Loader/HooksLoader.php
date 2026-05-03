<?php

declare(strict_types=1);

namespace MarketingAutomation\Loader;

/**
 * Registers WordPress actions and filters from a queue, then flushes on {@see register()}.
 */
final class HooksLoader
{
    /** @var list<array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
    private array $actions = [];

    /** @var list<array{hook: string, callback: callable, priority: int, accepted_args: int}> */
    private array $actionCallables = [];

    /** @var list<array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
    private array $filters = [];

    public function add_action(object $component, string $callback, string $hook, int $priority = 10, int $accepted_args = 1): void
    {
        $this->actions[] = [
            'hook'           => $hook,
            'component'      => $component,
            'callback'       => $callback,
            'priority'       => $priority,
            'accepted_args'  => $accepted_args,
        ];
    }

    /**
     * @param callable(mixed ...$args): void $callback
     */
    public function add_action_callable(callable $callback, string $hook, int $priority = 10, int $accepted_args = 1): void
    {
        $this->actionCallables[] = [
            'hook'           => $hook,
            'callback'       => $callback,
            'priority'       => $priority,
            'accepted_args'  => $accepted_args,
        ];
    }

    public function add_filter(object $component, string $callback, string $hook, int $priority = 10, int $accepted_args = 1): void
    {
        $this->filters[] = [
            'hook'           => $hook,
            'component'      => $component,
            'callback'       => $callback,
            'priority'       => $priority,
            'accepted_args'  => $accepted_args,
        ];
    }

    public function register(): void
    {
        foreach ($this->actions as $row) {
            add_action(
                $row['hook'],
                [$row['component'], $row['callback']],
                $row['priority'],
                $row['accepted_args']
            );
        }

        foreach ($this->actionCallables as $row) {
            add_action(
                $row['hook'],
                $row['callback'],
                $row['priority'],
                $row['accepted_args']
            );
        }

        foreach ($this->filters as $row) {
            add_filter(
                $row['hook'],
                [$row['component'], $row['callback']],
                $row['priority'],
                $row['accepted_args']
            );
        }

        $this->actions           = [];
        $this->actionCallables   = [];
        $this->filters           = [];
    }
}
