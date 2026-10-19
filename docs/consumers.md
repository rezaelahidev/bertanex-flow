# Integrated consumers

Bertanex Flow ships without domain-specific rules, SMS patterns, condition fields, or event catalogs. Integrated themes and plugins register a `ConsumerDefinition` on:

```php
do_action('gro_register_orchestrator_consumers', ConsumerRegistry $registry);
```

Each consumer may provide:

| Asset | ConsumerDefinition property |
| --- | --- |
| Default automation rules (JSON file) | `defaultRulesJsonPath` |
| Default SMS patterns (JSON file) | `defaultSmsPatternsJsonPath` |
| Admin event picker choices | `events` |
| Rule condition field metadata | `conditionFieldDefinitions` |
| Suggested condition fields in the editor | `suggestedConditionFields` |
| gettext text domain for bundled rule labels | `textDomain` |

Bundled rule labels (English msgids in consumer JSON) are translated via each consumer's `textDomain`. Event and condition labels are supplied already localized from the consumer registration code.

At runtime the orchestrator merges all registered consumer defaults for hydration, admin reset, SMS catalog resolution, and the rules visual editor.

## Current consumers

- **OMDBOX theme** — profile/product/subscription SMS patterns, onboarding rules, and user-state condition fields.
- **Growth Subscriptions** — subscription lifecycle rules and subscription condition fields.

## Extension filters

- `gro_automation_event_choices` — adjust merged event picker options.
- `gro_sms_patterns_catalog` — adjust merged built-in SMS pattern catalog.
- `gro_rules_editor_condition_field_definitions` — adjust merged condition field definitions (also set on `ConsumerDefinition`).
- `gro_rules_editor_suggested_condition_fields` — adjust suggested condition fields (also set on `ConsumerDefinition`).

User state for conditions is resolved separately via `gro_user_state_provider` and `gro_user_state_specs`.
