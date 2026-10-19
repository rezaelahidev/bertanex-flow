<?php

if (!function_exists('bertanex_flow_event_label')) {
    function bertanex_flow_event_label($event) {
        $labels = [
            'LOGIN' => __('Login', 'bertanex-flow'),
            'USER_REGISTERED' => __('User registered', 'bertanex-flow'),
            // 'PRODUCT_CREATED' => __('Product created', 'bertanex-flow'),
            // 'PRODUCT_APPROVED' => __('Product approved', 'bertanex-flow'),
            // 'PRODUCT_EDIT_CONFIRMED' => __('Product edit confirmed', 'bertanex-flow'),
            // 'PROFILE_COMPLETED' => __('Profile completed', 'bertanex-flow'),
            // 'SUBSCRIPTION_RENEWED' => __('Subscription renewed', 'bertanex-flow'),
            // 'SUBSCRIPTION_EXPIRED' => __('Subscription expired', 'bertanex-flow'),
            // 'SUBSCRIPTION_PURCHASED' => __('Subscription purchased', 'bertanex-flow'),
            // 'SUBSCRIPTION_CANCELLED' => __('Subscription cancelled', 'bertanex-flow'),
            // 'SUBSCRIPTION_LADDER_CREDIT_USED' => __('Subscription ladder credit used', 'bertanex-flow'),
            // 'SUBSCRIPTION_LISTING_CREDIT_USED' => __('Subscription listing credit used', 'bertanex-flow'),
            // 'SUBSCRIPTION_RENEWAL_REMINDER_SENT' => __('Subscription renewal reminder sent', 'bertanex-flow'),
            // 'SUBSCRIPTION_RENEWAL_INVOICE_CREATED' => __('Subscription renewal invoice created', 'bertanex-flow'),
            // 'SUBSCRIPTION_LEGACY_WOO_GRANT_APPLIED' => __('Subscription legacy Woo grant applied', 'bertanex-flow'),
            // 'SUBSCRIPTION_WOO_ORDER_BENEFITS_PENDING' => __('Subscription Woo order benefits pending', 'bertanex-flow'),
        ];

        return $labels[$event] ?? $event;
    }
}
