<?php

declare(strict_types=1);

namespace MarketingAutomation\Event;

use MarketingAutomation\Loader\HooksLoader;

/**
 * Maps WordPress core/plugin actions to automation events and exposes a small DSL for more mappings.
 *
 * Register extra mappings from {@see 'ma_register_wordpress_event_mappings'}.
 */
final class WordPressEventBridge
{
    /**
     * @var list<array{
     *     wp_hook: string,
     *     event_name: string,
     *     factory: callable(mixed ...$args): array<string, mixed>,
     *     priority: int,
     *     accepted_args: int
     * }>
     */
    private array $mappings = [];

    public function __construct(
        private readonly EventDispatcher $dispatcher
    ) {
    }

    /**
     * Map a WordPress action to an automation event name.
     *
     * @param callable(mixed ...$args): array<string, mixed> $payloadFactory Receives WP hook arguments; returns event payload.
     */
    public function map(
        string $wpHook,
        EventType|string $eventName,
        callable $payloadFactory,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $name = $eventName instanceof EventType ? $eventName->value : $eventName;

        $this->mappings[] = [
            'wp_hook'       => $wpHook,
            'event_name'    => $name,
            'factory'       => $payloadFactory,
            'priority'       => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    public function register(HooksLoader $hooks): void
    {
        $this->registerDefaultMaps();
        /**
         * Add or override WordPress → automation event mappings.
         *
         * @param WordPressEventBridge $bridge
         * @param HooksLoader          $hooks
         */
        do_action('ma_register_wordpress_event_mappings', $this, $hooks);

        foreach ($this->mappings as $row) {
            $dispatcher = $this->dispatcher;
            $hooks->add_action_callable(
                static function (...$args) use ($dispatcher, $row): void {
                    $payload = ($row['factory'])(...$args);
                    $dispatcher->dispatch(new AutomationEvent($row['event_name'], $payload));
                },
                $row['wp_hook'],
                $row['priority'],
                $row['accepted_args']
            );
        }

        $this->mappings = [];
    }

    private function registerDefaultMaps(): void
    {
        $this->map(
            'user_register',
            EventType::UserRegistered,
            static fn (int|string $userId): array => ['user_id' => (int) $userId]
        );

        $this->map(
            'wp_login',
            EventType::Login,
            static fn (string $userLogin, \WP_User $user): array => [
                'user_id' => (int) $user->ID,
                'login'   => $userLogin,
            ],
            10,
            2
        );
    }
}
