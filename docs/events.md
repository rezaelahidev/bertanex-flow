# Automation events

Event names are declared by integrated consumers on `gro_register_orchestrator_consumers` and surfaced in the Rules admin UI via `ConsumerRegistry::eventChoices()`.

## OMDBOX theme (`omdbox`)

- `LOGIN` (includes OTP via `growth_auth_otp_verified`; payload may include `auth_method`, `is_new_registration`)
- `USER_REGISTERED`
- `PROFILE_COMPLETED`
- `PRODUCT_CREATED`

## Growth Subscriptions (`growth-subscriptions`)

- `SUBSCRIPTION_PURCHASED`
- `SUBSCRIPTION_RENEWED`
- `SUBSCRIPTION_CANCELLED`
- `SUBSCRIPTION_EXPIRED`
- `SUBSCRIPTION_LISTING_CREDIT_USED`
- `SUBSCRIPTION_LADDER_CREDIT_USED`
- `SUBSCRIPTION_RENEWAL_REMINDER_SENT`
- `SUBSCRIPTION_RENEWAL_INVOICE_CREATED`
- `SUBSCRIPTION_WOO_ORDER_BENEFITS_PENDING`
- `SUBSCRIPTION_LEGACY_WOO_GRANT_APPLIED`

WordPress core hooks (`user_register`, `wp_login`) are still mapped by the orchestrator bridge; consumers emit domain events through `gro_emit_event`.
