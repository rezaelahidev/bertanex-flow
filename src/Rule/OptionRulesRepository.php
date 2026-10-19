<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Legacy option + JSON file fallthrough when the `{prefix}bertanex_flow_rules` table is empty.
 *
 * Prefer {@see DelegatingRulesRepository} for runtime resolution and {@see DatabaseRulesRepository}
 * plus {@see \BertanexFlow\Admin\Models\RulesDocumentModel} / {@see \BertanexFlow\Admin\Controllers\RulesController} for authoring.
 */
final class OptionRulesRepository implements RulesRepositoryInterface
{
    public const DEFAULT_OPTION_NAME = 'bertanex_flow_rules_document';

    public function __construct(
        private readonly JsonFileRulesRepository $fileFallback,
        private readonly JsonRulesParser $parser = new JsonRulesParser()
    ) {
    }

    public function storageOptionName(): string
    {
        return (string) apply_filters('gro_rules_option_name', self::DEFAULT_OPTION_NAME);
    }

    public function getRules(): array
    {
        if (apply_filters('gro_rules_force_json_file', false)) {
            return $this->fileFallback->getRules();
        }

        $key = $this->storageOptionName();
        $raw = get_option($key, '');

        if (! is_string($raw) || $raw === '') {
            return $this->fileFallback->getRules();
        }

        try {
            $rules = $this->parser->parse($raw);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    sprintf('[Bertanex Flow] Stored rules JSON invalid, using file fallback: %s', $e->getMessage())
                );
            }

            return $this->fileFallback->getRules();
        }

        $sourceLabel = 'option:' . $key;

        /** @var list<RuleDefinition> $rules */
        $rules = apply_filters('gro_parsed_rules', $rules, $sourceLabel);

        return $rules;
    }

    /**
     * JSON shown in the admin editor: saved option, else file contents, else minimal empty document.
     */
    public function getEditableDocumentJson(): string
    {
        $key = $this->storageOptionName();
        $raw = get_option($key, '');

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        $fromFile = $this->fileFallback->readRawDocument();

        if ($fromFile !== '') {
            return $fromFile;
        }

        return "{\n  \"version\": \"1.0.0\",\n  \"rules\": []\n}";
    }
}
