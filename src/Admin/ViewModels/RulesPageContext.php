<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\ViewModels;

/**
 * View model for the Rules admin screen (presentation-only data).
 */
final readonly class RulesPageContext
{
    /**
     * @param list<\GrowthOrchestrator\Rule\RuleDefinition> $definitions Parsed rules for the overview table (empty if parse error).
     */
    public function __construct(
        public string $pageSlug,
        public string $formActionUrl,
        public string $textareaValue,
        public ?string $parseError,
        public array $definitions,
        public bool $hasDraftTransient,
        public bool $showSuccessNotice
    ) {
    }
}
