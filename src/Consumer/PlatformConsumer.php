<?php

declare(strict_types=1);

namespace BertanexFlow\Consumer;

use BertanexFlow\Event\EventType;

/**
 * Bundled WordPress and WooCommerce events, condition fields, and default rules.
 *
 * Administrators edit the rules in the visual editor. Reset restores this file.
 */
final class PlatformConsumer
{
    public static function register(ConsumerRegistry $registry): void
    {
        $registry->register(new ConsumerDefinition(
            id: 'wordpress-woocommerce',
            label: 'WordPress and WooCommerce',
            defaultRulesJsonPath: \BERTANEX_FLOW_PLUGIN_DIR . 'rules/default.rules.json',
            events: self::events(),
            conditionFieldDefinitions: self::fields(),
            suggestedConditionFields: array_values(self::fields()),
            priority: 5,
            textDomain: 'bertanex-flow'
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function events(): array
    {
        $events = [];

        foreach (EventType::cases() as $case) {
            $events[] = [
                'value' => $case->value,
                'label' => bertanex_flow_event_label($case->value),
            ];
        }

        $extra = [
            'COMMENT_POSTED' => __('Comment posted', 'bertanex-flow'),
            'POST_PUBLISHED' => __('Post published', 'bertanex-flow'),
            'PASSWORD_CHANGED' => __('Password changed', 'bertanex-flow'),
            'woocommerce.order.completed' => __('Order completed', 'bertanex-flow'),
            'woocommerce.order.status_changed' => __('Order status changed', 'bertanex-flow'),
            'woocommerce.order.paid' => __('Order paid', 'bertanex-flow'),
            'woocommerce.customer.created' => __('Customer created', 'bertanex-flow'),
            'woocommerce.refund.created' => __('Refund created', 'bertanex-flow'),
        ];

        foreach ($extra as $value => $label) {
            $events[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $events;
    }

    /**
     * @return array<string, array{name: string, label: string, valueType?: string, values?: list<array{value: string, label: string}>}>
     */
    private static function fields(): array
    {
        return [
            'user_id' => [
                'name' => 'user_id',
                'label' => __('User ID', 'bertanex-flow'),
                'valueType' => 'integer',
            ],
            'user_role' => [
                'name' => 'user_role',
                'label' => __('User role', 'bertanex-flow'),
                'valueType' => 'string',
                'values' => [
                    ['value' => 'administrator', 'label' => __('Administrator', 'bertanex-flow')],
                    ['value' => 'editor', 'label' => __('Editor', 'bertanex-flow')],
                    ['value' => 'author', 'label' => __('Author', 'bertanex-flow')],
                    ['value' => 'contributor', 'label' => __('Contributor', 'bertanex-flow')],
                    ['value' => 'subscriber', 'label' => __('Subscriber', 'bertanex-flow')],
                    ['value' => 'customer', 'label' => __('Customer', 'bertanex-flow')],
                    ['value' => 'shop_manager', 'label' => __('Shop manager', 'bertanex-flow')],
                ],
            ],
            'user_email' => [
                'name' => 'user_email',
                'label' => __('User email', 'bertanex-flow'),
                'valueType' => 'string',
            ],
            'order.status' => [
                'name' => 'order.status',
                'label' => __('Order status', 'bertanex-flow'),
                'valueType' => 'string',
                'values' => [
                    ['value' => 'pending', 'label' => __('Pending', 'bertanex-flow')],
                    ['value' => 'processing', 'label' => __('Processing', 'bertanex-flow')],
                    ['value' => 'on-hold', 'label' => __('On hold', 'bertanex-flow')],
                    ['value' => 'completed', 'label' => __('Completed', 'bertanex-flow')],
                    ['value' => 'cancelled', 'label' => __('Cancelled', 'bertanex-flow')],
                    ['value' => 'refunded', 'label' => __('Refunded', 'bertanex-flow')],
                    ['value' => 'failed', 'label' => __('Failed', 'bertanex-flow')],
                ],
            ],
            'post_status' => [
                'name' => 'post_status',
                'label' => __('Post status', 'bertanex-flow'),
                'valueType' => 'string',
            ],
            'order_count' => [
                'name' => 'order_count',
                'label' => __('Order count', 'bertanex-flow'),
                'valueType' => 'integer',
            ],
        ];
    }
}
