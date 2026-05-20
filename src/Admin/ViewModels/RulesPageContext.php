<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\ViewModels;

/**
 * View model for the Rules admin screen (presentation-only data).
 */
final class RulesPageContext
{
    /**
     * @param list<\GrowthOrchestrator\Rule\RuleDefinition> $definitions Parsed rules for the overview table (empty if parse error).
     */
    public function __construct(
        public readonly string $pageSlug,
        public readonly string $formActionUrl,
        public readonly string $textareaValue,
        public readonly ?string $parseError,
        public readonly array $definitions,
        public readonly bool $hasDraftTransient,
        public readonly bool $showSuccessNotice
    ) {
    }
}
