<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

use GrowthOrchestrator\Db\RulesDbSchema;

/**
 * Resolves rule sources dynamically: filtered file override → database rows → legacy option/doc → bundled JSON fallback.
 */
final class DelegatingRulesRepository implements RulesRepositoryInterface
{
    public function __construct(
        private readonly DatabaseRulesRepository $database,
        private readonly OptionRulesRepository $optionFallthrough,
        private readonly JsonFileRulesRepository $fileOnlyRepository
    ) {
    }

    /**
     * @return list<RuleDefinition>
     */
    public function getRules(): array
    {
        if ((bool) apply_filters('gro_rules_force_json_file', false)) {
            return $this->fileOnlyRepository->getRules();
        }

        if (RulesDbSchema::tableReady() && $this->database->hasPersistedRules()) {
            return $this->database->getRules();
        }

        /** Legacy option-backed JSON mirrors and/or plugin file fallback behaviour from {@see OptionRulesRepository}. */
        return $this->optionFallthrough->getRules();
    }
}
