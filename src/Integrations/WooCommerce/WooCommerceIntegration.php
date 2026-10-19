<?php

declare(strict_types=1);

namespace BertanexFlow\Integrations\WooCommerce;

use BertanexFlow\Core\Contracts\IntegrationInterface;
use BertanexFlow\Core\Events\GenericEvent;

/**
 * WooCommerce lifecycle events. Hooks are registered only when WooCommerce is active.
 */
final class WooCommerceIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'woocommerce';
    }

    public function getName(): string
    {
        return 'WooCommerce';
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function register(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        add_action('woocommerce_order_status_changed', [$this, 'onStatusChanged'], 10, 4);
        add_action('woocommerce_payment_complete', [$this, 'onPaymentComplete']);
        add_action('woocommerce_created_customer', [$this, 'onCustomerCreated']);
        add_action('woocommerce_update_product', [$this, 'onProductUpdated']);
        add_action('woocommerce_product_set_stock', [$this, 'onStockChanged']);
        add_action('woocommerce_refund_created', [$this, 'onRefundCreated'], 10, 2);

        if (class_exists('WC_Subscriptions')) {
            add_action('woocommerce_subscription_status_updated', [$this, 'onSubscriptionStatus'], 10, 3);
        }
    }

    /**
     * @param \WC_Order $order
     */
    public function onStatusChanged(int $orderId, string $from, string $to, $order): void
    {
        $context = $this->orderContext($orderId, $order);
        $context['order']['previous_status'] = $from;
        $this->emit('woocommerce.order.status_changed', $context);

        if ($to === 'completed') {
            $this->emit('woocommerce.order.completed', $context);
        }
    }

    public function onPaymentComplete(int $orderId): void
    {
        $this->emit('woocommerce.order.paid', $this->orderContext($orderId));
    }

    public function onCustomerCreated(int $customerId): void
    {
        $user = get_userdata($customerId);
        $this->emit('woocommerce.customer.created', [
            'customer' => [
                'id' => $customerId,
                'email' => $user instanceof \WP_User ? $user->user_email : '',
                'name' => $user instanceof \WP_User ? $user->display_name : '',
            ],
        ]);
    }

    public function onProductUpdated(int $productId): void
    {
        $this->emit('woocommerce.product.updated', [
            'product' => ['id' => $productId],
        ]);
    }

    public function onStockChanged(object $product): void
    {
        $this->emit('woocommerce.stock.changed', [
            'product' => [
                'id' => method_exists($product, 'get_id') ? (int) $product->get_id() : 0,
                'stock' => method_exists($product, 'get_stock_quantity') ? $product->get_stock_quantity() : null,
            ],
        ]);
    }

    public function onRefundCreated(int $refundId, array $args): void
    {
        $this->emit('woocommerce.refund.created', [
            'refund' => [
                'id' => $refundId,
                'order_id' => (int) ($args['order_id'] ?? 0),
                'amount' => $args['amount'] ?? null,
            ],
        ]);
    }

    public function onSubscriptionStatus(object $subscription, string $newStatus, string $oldStatus): void
    {
        $this->emit('woocommerce.subscription.status_changed', [
            'subscription' => [
                'id' => method_exists($subscription, 'get_id') ? (int) $subscription->get_id() : 0,
                'status' => $newStatus,
                'previous_status' => $oldStatus,
            ],
        ]);
    }

    public function getEvents(): array
    {
        return [
            'woocommerce.order.completed' => [
                'name' => 'woocommerce.order.completed',
                'label' => 'Order Completed',
                'description' => 'A WooCommerce order reached completed.',
            ],
            'woocommerce.order.status_changed' => [
                'name' => 'woocommerce.order.status_changed',
                'label' => 'Order Status Changed',
                'description' => 'A WooCommerce order changed status.',
            ],
            'woocommerce.order.paid' => [
                'name' => 'woocommerce.order.paid',
                'label' => 'Payment Complete',
                'description' => 'A WooCommerce payment was completed.',
            ],
            'woocommerce.customer.created' => [
                'name' => 'woocommerce.customer.created',
                'label' => 'Customer Created',
                'description' => 'A WooCommerce customer was created.',
            ],
            'woocommerce.product.updated' => [
                'name' => 'woocommerce.product.updated',
                'label' => 'Product Updated',
                'description' => 'A WooCommerce product was updated.',
            ],
            'woocommerce.stock.changed' => [
                'name' => 'woocommerce.stock.changed',
                'label' => 'Stock Changed',
                'description' => 'A product stock quantity changed.',
            ],
            'woocommerce.refund.created' => [
                'name' => 'woocommerce.refund.created',
                'label' => 'Refund Created',
                'description' => 'A WooCommerce refund was created.',
            ],
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function getDataSchema(): array
    {
        return [
            'order.id' => ['type' => 'integer', 'label' => 'Order ID', 'description' => 'WooCommerce order ID.'],
            'order.status' => ['type' => 'string', 'label' => 'Order Status', 'description' => 'Current order status.'],
            'order.total' => ['type' => 'number', 'label' => 'Order Total', 'description' => 'Order total.'],
            'order.currency' => ['type' => 'string', 'label' => 'Currency', 'description' => 'Order currency.'],
            'customer.id' => ['type' => 'integer', 'label' => 'Customer ID', 'description' => 'Customer user ID.'],
            'customer.email' => ['type' => 'email', 'label' => 'Customer Email', 'description' => 'Customer email address.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderContext(int $orderId, mixed $order = null): array
    {
        if (! is_object($order) && function_exists('wc_get_order')) {
            $order = wc_get_order($orderId);
        }

        $total = is_object($order) && method_exists($order, 'get_total') ? (float) $order->get_total() : 0;
        $status = is_object($order) && method_exists($order, 'get_status') ? (string) $order->get_status() : '';
        $currency = is_object($order) && method_exists($order, 'get_currency') ? (string) $order->get_currency() : '';
        $customerId = is_object($order) && method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0;
        $email = is_object($order) && method_exists($order, 'get_billing_email') ? (string) $order->get_billing_email() : '';

        return [
            'order' => [
                'id' => $orderId,
                'status' => $status,
                'total' => $total,
                'currency' => $currency,
            ],
            'customer' => [
                'id' => $customerId,
                'email' => $email,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emit(string $name, array $context): void
    {
        $event = GenericEvent::create($name, $context, 'woocommerce');
        $context['event'] = [
            'name' => $event->getName(),
            'id' => $event->getId(),
        ];

        do_action('bertanex_flow_event', $name, $context);
    }
}
