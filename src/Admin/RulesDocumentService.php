<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin;

use GrowthOrchestrator\Db\RulesDbSchema;
use GrowthOrchestrator\Rule\DatabaseRulesRepository;
use GrowthOrchestrator\Rule\DatabaseRulesSync;
use GrowthOrchestrator\Rule\JsonRulesParser;
use GrowthOrchestrator\Rule\OptionRulesRepository;
use GrowthOrchestrator\Rule\RuleDefinition;

/**
 * Hydrates editor JSON from authoritative storage and persists validated documents into `{prefix}growth_orchestrator_rules`.
 */
final class RulesDocumentService
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
            return apply_filters(
                'gro_rules_editor_document_json',
                $this->database->exportPrettyDocumentJson(),
                'database'
            );
        }

        $legacy = $this->optionFallback->getEditableDocumentJson();

        /** @phpstan-ignore-next-line */
        return apply_filters('gro_rules_editor_document_json', $legacy, 'legacy-option-or-file');
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
            throw new \RuntimeException(__('Rules database table missing or could not be created.', 'growth-orchestrator'));
        }

        /** @phpstan-ignore-next-line */
        $definitions = $this->parser->parse($documentJson);

        global $wpdb;

        /** @phpstan-ignore-next-line */
        if (! DatabaseRulesSync::persistDefinitions($wpdb, $definitions)) {
            throw new \RuntimeException(__('Failed to persist rules to the database.', 'growth-orchestrator'));
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
