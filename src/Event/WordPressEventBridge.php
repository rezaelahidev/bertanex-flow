<?php

declare(strict_types=1);

namespace BertanexFlow\Event;

use BertanexFlow\Loader\HooksLoader;

/**
 * Maps WordPress core/plugin actions to automation events and exposes a small DSL for more mappings.
 *
 * Register extra mappings from {@see 'gro_register_wordpress_event_mappings'}.
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
        do_action('gro_register_wordpress_event_mappings', $this, $hooks);

        foreach ($this->mappings as $row) {
            $dispatcher = $this->dispatcher;
            $hooks->add_action_callable(
                static function (...$args) use ($dispatcher, $row): void {
                    $payload = ($row['factory'])(...$args);
                    if (! empty($payload['_gro_skip'])) {
                        return;
                    }

                    unset($payload['_gro_skip']);
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

        $this->map(
            'comment_post',
            'COMMENT_POSTED',
            static function (int|string $commentId, mixed $approved, mixed $commentdata): array {
                $data = is_array($commentdata) ? $commentdata : [];

                return [
                    'user_id'           => (int) ($data['user_id'] ?? 0),
                    'comment_id'        => (int) $commentId,
                    'comment_approved'  => is_scalar($approved) ? (string) $approved : '',
                ];
            },
            10,
            3
        );

        $this->map(
            'transition_post_status',
            'POST_PUBLISHED',
            static function (string $newStatus, string $oldStatus, mixed $post): array {
                if (
                    $newStatus !== 'publish'
                    || $oldStatus === 'publish'
                    || ! $post instanceof \WP_Post
                    || $post->post_type !== 'post'
                ) {
                    return ['user_id' => 0, '_gro_skip' => true];
                }

                return [
                    'user_id'     => (int) $post->post_author,
                    'post_id'     => (int) $post->ID,
                    'post_status' => 'publish',
                ];
            },
            10,
            3
        );

        $this->map(
            'password_reset',
            'PASSWORD_CHANGED',
            static function (\WP_User $user): array {
                return ['user_id' => (int) $user->ID];
            }
        );

        if (! class_exists('WooCommerce')) {
            return;
        }

        $this->map(
            'woocommerce_order_status_completed',
            'woocommerce.order.completed',
            fn (int|string $orderId): array => $this->orderPayload((int) $orderId, 'completed')
        );

        $this->map(
            'woocommerce_order_status_changed',
            'woocommerce.order.status_changed',
            function (int|string $orderId, string $from, string $to): array {
                $payload = $this->orderPayload((int) $orderId, $to);
                $payload['order']['previous_status'] = $from;

                return $payload;
            },
            10,
            3
        );

        $this->map(
            'woocommerce_payment_complete',
            'woocommerce.order.paid',
            fn (int|string $orderId): array => $this->orderPayload((int) $orderId, null)
        );

        $this->map(
            'woocommerce_created_customer',
            'woocommerce.customer.created',
            static function (int|string $customerId): array {
                return ['user_id' => (int) $customerId];
            }
        );

        $this->map(
            'woocommerce_order_refunded',
            'woocommerce.refund.created',
            fn (int|string $orderId, int|string $refundId): array => $this->orderPayload((int) $orderId, 'refunded') + [
                'refund_id' => (int) $refundId,
            ],
            10,
            2
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(int $orderId, ?string $status): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : null;
        $customerId = is_object($order) && method_exists($order, 'get_customer_id')
            ? (int) $order->get_customer_id()
            : 0;
        $resolvedStatus = $status;
        if ($resolvedStatus === null && is_object($order) && method_exists($order, 'get_status')) {
            $resolvedStatus = (string) $order->get_status();
        }

        $payload = [
            'user_id' => $customerId,
            'order'   => [
                'id'     => $orderId,
                'status' => (string) $resolvedStatus,
            ],
        ];

        if ($customerId < 1) {
            $payload['_gro_skip'] = true;
        }

        return $payload;
    }
}
