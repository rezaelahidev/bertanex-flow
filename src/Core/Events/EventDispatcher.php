<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Events;

use BertanexFlow\Core\Contracts\EventInterface;
use BertanexFlow\Core\Registry\EventRegistry;

/**
 * Dispatches events to registered listeners.
 *
 * Supports middleware, error handling, and async execution.
 *
 * @package BertanexFlow\Core\Events
 */
final class EventDispatcher
{
    /** @var EventRegistry Event registry instance. */
    private EventRegistry $registry;

    /** @var bool Whether to dispatch asynchronously. */
    private bool $async = false;

    public function __construct(EventRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Enable async event dispatching.
     *
     * @return self
     */
    public function async(): self
    {
        $this->async = true;
        return $this;
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
        $listeners = $this->registry->getListeners($eventName);

        foreach ($listeners as $listener) {
            try {
                $listener($eventName, $event);
            } catch (\Throwable $e) {
                // Log error but don't stop other listeners
                $this->handleListenerError($eventName, $listener, $e);
            }
        }
    }

    /**
     * Dispatch synchronously (blocks until all listeners complete).
     *
     * @param string $eventName Event name.
     * @param EventInterface $event Event instance.
     * @return void
     */
    public function dispatchSync(string $eventName, EventInterface $event): void
    {
        // Just call dispatch for now - async implementation would use Action Scheduler
        $this->dispatch($eventName, $event);
    }

    /**
     * Dispatch after all WordPress core actions have run.
     *
     * Uses WordPress shutdown hook to ensure order.
     *
     * @param string $eventName Event name.
     * @param EventInterface $event Event instance.
     * @return void
     */
    public function dispatchPostWordPress(string $eventName, EventInterface $event): void
    {
        add_action('shutdown', function () use ($eventName, $event) {
            $this->dispatch($eventName, $event);
        }, 999);
    }

    /**
     * Handle listener execution error.
     *
     * @param string $eventName Event name.
     * @param callable $listener Listener callback.
     * @param \Throwable $error Exception.
     * @return void
     */
    private function handleListenerError(string $eventName, callable $listener, \Throwable $error): void
    {
        // Log error - integrate with Bertanex Flow logging system when implemented
        error_log(sprintf(
            'Bertanex Flow: Error in listener for event "%s": %s',
            $eventName,
            $error->getMessage()
        ));
    }
}