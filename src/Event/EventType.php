<?php

declare(strict_types=1);

namespace BertanexFlow\Event;

/**
 * Domain events emitted by the automation engine (aligned with docs/events.md).
 */
enum EventType: string
{
    case Login                            = 'LOGIN';
    case UserRegistered          = 'USER_REGISTERED';
    case ProductCreated          = 'PRODUCT_CREATED';
    case ProductApproved         = 'PRODUCT_APPROVED';
    case ProductEditConfirmed    = 'PRODUCT_EDIT_CONFIRMED';
    case ProfileCompleted        = 'PROFILE_COMPLETED';
    case SubscriptionRenewed              = 'SUBSCRIPTION_RENEWED';
    case SubscriptionExpired              = 'SUBSCRIPTION_EXPIRED';
    case SubscriptionPurchased            = 'SUBSCRIPTION_PURCHASED';
    case SubscriptionCancelled            = 'SUBSCRIPTION_CANCELLED';
    case SubscriptionLadderCreditUsed     = 'SUBSCRIPTION_LADDER_CREDIT_USED';
    case SubscriptionListingCreditUsed    = 'SUBSCRIPTION_LISTING_CREDIT_USED';
    case SubscriptionRenewalReminderSent  = 'SUBSCRIPTION_RENEWAL_REMINDER_SENT';
    case SubscriptionRenewalInvoiceCreated = 'SUBSCRIPTION_RENEWAL_INVOICE_CREATED';
    case SubscriptionLegacyWooGrantApplied = 'SUBSCRIPTION_LEGACY_WOO_GRANT_APPLIED';
    case SubscriptionWooOrderBenefitsPending = 'SUBSCRIPTION_WOO_ORDER_BENEFITS_PENDING';
}
