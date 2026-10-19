<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

use BertanexFlow\Db\RulesDbSchema;

/**
 * Resolves rule sources dynamically: filtered file override → database rows → legacy option/doc → bundled JSON fallback.
 */
final class DelegatingRulesRepository implements RulesRepositoryInterface
{
    /** @var list<RuleDefinition>|null */
    private ?array $resolvedRules = null;

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
        if ($this->resolvedRules !== null) {
            return $this->resolvedRules;
        }

        if ((bool) apply_filters('gro_rules_force_json_file', false)) {
            $this->resolvedRules = $this->fileOnlyRepository->getRules();

            return $this->resolvedRules;
        }

        if (RulesDbSchema::tableReady() && $this->database->hasPersistedRules()) {
            $this->resolvedRules = $this->database->getRules();

            return $this->resolvedRules;
        }

        /** Legacy option-backed JSON mirrors and/or plugin file fallback behaviour from {@see OptionRulesRepository}. */
        $this->resolvedRules = $this->optionFallthrough->getRules();

        return $this->resolvedRules;
    }

    public function flushCache(): void
    {
        $this->resolvedRules = null;
        $this->database->flushCache();
    }
}
