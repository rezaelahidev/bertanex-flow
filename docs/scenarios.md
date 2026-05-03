# Scenarios (source)

Human-readable rules:

- IF `profile_completed` = false → send SMS (complete profile)
- IF `has_product` = true AND no subscription → send discount
- IF subscription expiring in 3 days → reminder

## JSON rule format

Machine-readable equivalents for a WordPress plugin:

- **Schema:** [`rule-schema.json`](./rule-schema.json) — validates `rules` documents and single `rule` objects (`$ref` / `oneOf` root).
- **These scenarios:** [`scenarios.rules.json`](./scenarios.rules.json) — three rules matching the list above.

User state fields are aligned with [`user-states.md`](./user-states.md). The expiry rule uses `subscription_days_until_expiry` (integer days remaining); your plugin should compute it from subscription end dates when evaluating (often on a daily cron via `trigger.schedule`).

Hook names in `trigger.events` match [`events.md`](./events.md).
