<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Models;

use BertanexFlow\Db\RulesDbSchema;
use BertanexFlow\Rule\DatabaseRulesRepository;
use BertanexFlow\Rule\DatabaseRulesSync;
use BertanexFlow\Rule\JsonRulesParser;
use BertanexFlow\Rule\OptionRulesRepository;
use BertanexFlow\Rule\RuleDefinition;
use BertanexFlow\Rule\RuleLabelTranslator;

/**
 * Model: rules document I/O for the admin editor (DB primary, legacy option/file fallback).
 */
final class RulesDocumentModel
{
    public function __construct(
        private readonly DatabaseRulesRepository $database,
        private readonly OptionRulesRepository $optionFallback,
        private readonly JsonRulesParser $parser = new JsonRulesParser()
    ) {
    }

    public function getEditorJson(): string
    {
        RulesDbSchema::upgradeIfNeeded();

        if ($this->database->hasPersistedRules()) {
            /** @phpstan-ignore-next-line */
            $json = apply_filters(
                'gro_rules_editor_document_json',
                $this->database->exportPrettyDocumentJson(),
                'database'
            );
        } else {
            $legacy = $this->optionFallback->getEditableDocumentJson();

            /** @phpstan-ignore-next-line */
            $json = apply_filters('gro_rules_editor_document_json', $legacy, 'legacy-option-or-file');
        }

        return RuleLabelTranslator::localizeDocumentJson($json);
    }

    /**
     * Parse JSON for read-only preview in the view (does not persist).
     *
     * @return array{error: ?string, definitions: list<RuleDefinition>}
     */
    public function parseForDisplay(string $documentJson): array
    {
        try {
            return [
                'error'        => null,
                'definitions' => $this->parser->parse($documentJson),
            ];
        } catch (\Throwable $e) {
            return [
                'error'        => $e->getMessage(),
                'definitions' => [],
            ];
        }
    }

    /**
     * Persist JSON into the runtime database table after validation.
     *
     * @return list<RuleDefinition>
     */
    public function saveValidatedDocument(string $documentJson): array
    {
        RulesDbSchema::upgradeIfNeeded();

        if (! RulesDbSchema::tableReady()) {
            throw new \RuntimeException(__('Rules database table missing or could not be created.', 'bertanex-flow'));
        }

        $documentJson = RuleLabelTranslator::canonicalizeDocumentJson($documentJson);

        /** @phpstan-ignore-next-line */
        $definitions = $this->parser->parse($documentJson);

        global $wpdb;

        /** @phpstan-ignore-next-line */
        if (! DatabaseRulesSync::persistDefinitions($wpdb, $definitions)) {
            throw new \RuntimeException(__('Failed to persist rules to the database.', 'bertanex-flow'));
        }

        if ((bool) apply_filters('gro_rules_mirror_json_option_on_save', false)) {
            /** @phpstan-ignore-next-line */
            update_option(
                (string) apply_filters(
                    'gro_rules_option_name',
                    OptionRulesRepository::DEFAULT_OPTION_NAME
                ),
                $documentJson,
                true
            );
        }

        return $definitions;
    }
}
