# User state fields

Condition field keys are declared by integrated consumers (`ConsumerDefinition::conditionFieldDefinitions`). Runtime values are resolved through `gro_user_state_provider` and `gro_user_state_specs`.

## OMDBOX theme

- `profile_completed`: true/false
- `has_product`: true/false
- `user_type`: supplier/buyer

## Growth Subscriptions

- `has_subscription`: true/false
- `subscription_status`: active/expired/cancelled
- `subscription_days_until_expiry`: integer days remaining