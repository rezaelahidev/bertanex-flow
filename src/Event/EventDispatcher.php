<?php

declare(strict_types=1);

namespace MarketingAutomation\Event;

/**
 * In-process event bus with WordPress hooks for third-party listeners.
 *
 * - Subscribe with {@see listen()} using a core {@see EventType} value or any custom string.
 * - Use {@see WILDCARD} to receive every dispatched event.
 * - Each dispatch also runs {@see 'ma_event'} and {@see 'ma_event_{NAME}'} (NAME = {@see EventInterface::name()}).
 */
final class EventDispatcher
{
    public const WILDCARD = '*';

    /** @var array<string, list<array{0: callable(EventInterface): void, 1: int}>> */
    private array $listeners = [];

    /**
     * @param callable(EventInterface): void $listener
     */
    public function listen(EventType|string $eventName, callable $listener, int $priority = 10): void
    {
        $key = $this->normalizeName($eventName);

        if (! isset($this->listeners[$key])) {
            $this->listeners[$key] = [];
        }

        $this->listeners[$key][] = [$listener, $priority];
    }

    /**
     * @param callable(EventInterface): void $listener
     */
    public function removeListener(EventType|string $eventName, callable $listener): void
    {
        $key = $this->normalizeName($eventName);

        if (! isset($this->listeners[$key])) {
            return;
        }

        $this->listeners[$key] = array_values(array_filter(
            $this->listeners[$key],
            static fn (array $row): bool => $row[0] !== $listener
        ));
    }

    public function dispatch(EventInterface $event): void
    {
        $this->invokeListeners($event->name(), $event);
        $this->invokeListeners(self::WILDCARD, $event);

        /**
         * Fires for every automation event, after in-process listeners for that name.
         *
         * @param string           $name  {@see EventInterface::name()}
         * @param EventInterface  $event
         */
        do_action('ma_event', $event->name(), $event);

        /**
         * Fires for a specific event name. Hook suffix matches {@see EventInterface::name()} exactly.
         *
         * @param EventInterface $event
         */
        do_action('ma_event_' . $event->name(), $event);
    }

    private function invokeListeners(string $key, EventInterface $event): void
    {
        if (! isset($this->listeners[$key])) {
            return;
        }

        $items = $this->listeners[$key];
        usort($items, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        foreach ($items as [$listener]) {
            $listener($event);
        }
    }

    private function normalizeName(EventType|string $eventName): string
    {
        return $eventName instanceof EventType ? $eventName->value : $eventName;
    }
}
