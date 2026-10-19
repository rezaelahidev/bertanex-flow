<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Registry;

use BertanexFlow\Core\Contracts\EventInterface;

/**
 * Registry for events.
 *
 * Maintains a collection of events and dispatches them to registered listeners.
 *
 * @package BertanexFlow\Core\Registry
 */
final class EventRegistry
{
    /** @var array<string, list<callable>> Event listeners by event name. */
    private array $listeners = [];

    /**
     * Listeners live here. Dispatching is owned by EventDispatcher so the
     * two classes can be constructed without a circular dependency.
     */
    public function __construct()
    {
    }

    /**
     * Add a listener for a specific event.
     *
     * @param string $eventName Event name.
     * @param callable $listener Listener callback.
     * @return void
     */
    public function addListener(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Remove a listener for an event.
     *
     * @param string $eventName Event name.
     * @param callable $listener Listener callback to remove.
     * @return bool True if listener was removed.
     */
    public function removeListener(string $eventName, callable $listener): bool
    {
        if (! isset($this->listeners[$eventName])) {
            return false;
        }

        $key = array_search($listener, $this->listeners[$eventName], true);

        if ($key === false) {
            return false;
        }

        unset($this->listeners[$eventName][$key]);
        return true;
    }

    /**
     * Dispatch an event.
     *
     * @param string $eventName Event name.
     * @param EventInterface $event Event instance.
     * @return void
     */
    public function dispatch(string $eventName, EventInterface $event): void
    {
        foreach ($this->getListeners($eventName) as $listener) {
            $listener($eventName, $event);
        }
    }

    /**
     * Dispatch an event synchronously.
     *
     * @param string $eventName Event name.
     * @param EventInterface $event Event instance.
     * @return void
     */
    public function dispatchSync(string $eventName, EventInterface $event): void
    {
        $this->dispatch($eventName, $event);
    }

    /**
     * Dispatch after all WordPress core actions have run.
     *
     * @param string $eventName Event name.
     * @param EventInterface $event Event instance.
     * @return void
     */
    public function dispatchPostWordPress(string $eventName, EventInterface $event): void
    {
        if (! function_exists('add_action')) {
            $this->dispatch($eventName, $event);

            return;
        }

        add_action('shutdown', function () use ($eventName, $event): void {
            $this->dispatch($eventName, $event);
        }, 999);
    }

    /**
     * Clear all listeners for an event.
     *
     * @param string $eventName Event name.
     * @return void
     */
    public function clearListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    /**
     * Get listeners for an event.
     *
     * @param string $eventName Event name.
     * @return list<callable>
     */
    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }

    /**
     * Get all event names with registered listeners.
     *
     * @return array<int, string>
     */
    public function eventNames(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Get total listener count.
     *
     * @return int
     */
    public function listenerCount(): int
    {
        $count = 0;

        foreach ($this->listeners as $listeners) {
            $count += count($listeners);
        }

        return $count;
    }
}