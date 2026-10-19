<?php

declare(strict_types=1);

namespace BertanexFlow\Consumer;

/**
 * Collects integrated consumers via {@see 'gro_register_orchestrator_consumers'}.
 */
final class ConsumerRegistry
{
    private static ?self $instance = null;

    /** @var array<string, ConsumerDefinition> */
    private array $consumers = [];

    private bool $bootstrapped = false;

    /** @var array<string, string>|null rule id => English label */
    private ?array $defaultRuleLabels = null;

    /** @var array<string, string>|null rule id => text domain */
    private ?array $ruleTextDomains = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(ConsumerDefinition $consumer): void
    {
        $id = sanitize_key($consumer->id);
        if ($id === '') {
            return;
        }

        $this->consumers[$id] = $consumer;
    }

    /** @return list<ConsumerDefinition> */
    public function all(): array
    {
        $this->ensureBootstrapped();

        $list = array_values($this->consumers);
        usort(
            $list,
            static fn (ConsumerDefinition $a, ConsumerDefinition $b): int => $a->priority <=> $b->priority
                ?: strcmp($a->id, $b->id)
        );

        return $list;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function eventChoices(): array
    {
        $seen  = [];
        $out   = [];

        foreach ($this->all() as $consumer) {
            foreach ($consumer->events as $event) {
                if (! is_array($event)) {
                    continue;
                }

                $value = isset($event['value']) ? trim((string) $event['value']) : '';
                if ($value === '' || isset($seen[$value])) {
                    continue;
                }

                $label = isset($event['label']) ? trim((string) $event['label']) : $value;
                if ($label === '') {
                    $label = $value;
                }

                $seen[$value] = true;
                $out[]        = ['value' => $value, 'label' => $label];
            }
        }

        /**
         * @param list<array{value: string, label: string}> $choices
         */
        $filtered = apply_filters('gro_automation_event_choices', $out);

        return is_array($filtered) ? $filtered : $out;
    }

    /**
     * @return array<string, array{name: string, label: string, valueType?: string, values?: list<array{value: bool|int|float|string, label: string}>}>
     */
    public function conditionFieldDefinitions(): array
    {
        $merged = [];

        foreach ($this->all() as $consumer) {
            foreach ($consumer->conditionFieldDefinitions as $key => $definition) {
                if (! is_string($key) || $key === '' || ! is_array($definition)) {
                    continue;
                }

                $merged[$key] = $definition;
            }
        }

        /**
         * @param array<string, array{name: string, label: string, valueType?: string, values?: list<array{value: bool|int|float|string, label: string}>}> $definitions
         */
        $filtered = apply_filters('gro_rules_editor_condition_field_definitions', $merged);

        return is_array($filtered) ? $filtered : $merged;
    }

    /**
     * @return list<string|array{name?: string, label?: string, valueType?: string, values?: list<array{value?: mixed, label?: string}>}>
     */
    public function suggestedConditionFields(): array
    {
        $out = [];

        foreach ($this->all() as $consumer) {
            foreach ($consumer->suggestedConditionFields as $field) {
                $out[] = $field;
            }
        }

        /**
         * @param list<string|array{name?: string, label?: string, valueType?: string, values?: list<array{value?: mixed, label?: string}>}> $fields
         */
        $filtered = apply_filters('gro_rules_editor_suggested_condition_fields', $out);

        return is_array($filtered) ? $filtered : $out;
    }

    /**
     * Canonical English labels for bundled default rules (rule id => label).
     *
     * @return array<string, string>
     */
    public function defaultRuleLabelsCatalog(): array
    {
        $this->loadRuleLabelIndex();

        return $this->defaultRuleLabels ?? [];
    }

    /**
     * Localized admin label for a bundled default rule.
     */
    public function translateRuleLabel(string $ruleId, string $englishLabel, ?string $storedLabel = null): string
    {
        $textDomain = $this->textDomainForRuleId($ruleId);
        $label      = $englishLabel;

        if ($textDomain !== null && $textDomain !== '' && did_action('init')) {
            $label = __($englishLabel, $textDomain);
        }

        /**
         * @param string      $label        Candidate display label.
         * @param string      $ruleId       Rule slug.
         * @param string|null $storedLabel  Label persisted in JSON/DB.
         * @param string|null $englishLabel Canonical English msgid from consumer defaults, if any.
         */
        $filtered = apply_filters('gro_rule_label', $label, $ruleId, $storedLabel, $englishLabel);

        return is_string($filtered) ? $filtered : $label;
    }

    public function ensureBootstrapped(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;

        /**
         * Register integrated consumers (rules, SMS patterns, events, condition fields).
         *
         * @param ConsumerRegistry $registry
         */
        do_action('gro_register_orchestrator_consumers', $this);
    }

    private function textDomainForRuleId(string $ruleId): ?string
    {
        $this->loadRuleLabelIndex();

        return $this->ruleTextDomains[$ruleId] ?? null;
    }

    private function loadRuleLabelIndex(): void
    {
        if ($this->defaultRuleLabels !== null) {
            return;
        }

        $this->defaultRuleLabels = [];
        $this->ruleTextDomains   = [];

        foreach ($this->all() as $consumer) {
            $path = $consumer->defaultRulesJsonPath;
            if ($path === null || $path === '' || ! is_readable($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || ! isset($decoded['rules']) || ! is_array($decoded['rules'])) {
                continue;
            }

            foreach ($decoded['rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $id    = isset($rule['id']) ? (string) $rule['id'] : '';
                $label = isset($rule['label']) ? (string) $rule['label'] : '';

                if ($id === '' || $label === '' || isset($this->defaultRuleLabels[$id])) {
                    continue;
                }

                $this->defaultRuleLabels[$id] = $label;

                if ($consumer->textDomain !== null && $consumer->textDomain !== '') {
                    $this->ruleTextDomains[$id] = $consumer->textDomain;
                }
            }
        }
    }

    /** Visible for tests; avoid using in production code. */
    public static function resetForTesting(): void
    {
        self::$instance = null;
    }
}
