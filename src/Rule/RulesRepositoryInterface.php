<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

interface RulesRepositoryInterface
{
    /**
     * @return list<RuleDefinition>
     */
    public function getRules(): array;
}
