<?php

declare(strict_types=1);

namespace MarketingAutomation\Event;

/**
 * Domain events emitted by the automation engine (aligned with docs/events.md).
 */
enum EventType: string
{
    case UserRegistered          = 'USER_REGISTERED';
    case ProfileCompleted        = 'PROFILE_COMPLETED';
    case ProductCreated          = 'PRODUCT_CREATED';
    case SubscriptionPurchased   = 'SUBSCRIPTION_PURCHASED';
    case SubscriptionExpired     = 'SUBSCRIPTION_EXPIRED';
    case InstallmentDue          = 'INSTALLMENT_DUE';
    case InstallmentOverdue      = 'INSTALLMENT_OVERDUE';
    case Login                   = 'LOGIN';
}
