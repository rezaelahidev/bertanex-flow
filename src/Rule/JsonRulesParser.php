<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Parses rules documents (full file or single rule object) from JSON strings.
 */
final class JsonRulesParser
{
    /**
     * @return list<RuleDefinition>
     */
    public function parse(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Rules JSON must decode to an object.');
        }

        if (isset($data['rules']) && is_array($data['rules'])) {
            return $this->mapRulesList($data['rules']);
        }

        if (isset($data['id'], $data['conditions'], $data['actions'])) {
            return [RuleDefinition::fromArray($data)];
        }

        throw new \InvalidArgumentException('Rules JSON must contain a "rules" array or a single rule object.');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<RuleDefinition>
     */
    private function mapRulesList(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = RuleDefinition::fromArray($row);
        }

        return $out;
    }
}
