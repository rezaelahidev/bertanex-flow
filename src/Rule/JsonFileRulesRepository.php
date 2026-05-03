<?php

declare(strict_types=1);

namespace MarketingAutomation\Rule;

/**
 * Loads rules from a JSON file; path override via {@see 'ma_rules_json_path'}.
 */
final class JsonFileRulesRepository implements RulesRepositoryInterface
{
    /** @var list<RuleDefinition>|null */
    private ?array $cache = null;

    private ?int $cachedMtime = null;

    public function __construct(
        private readonly string $defaultPath,
        private readonly JsonRulesParser $parser = new JsonRulesParser()
    ) {
    }

    public function getRules(): array
    {
        /** @var string $path */
        $path = apply_filters('ma_rules_json_path', $this->defaultPath, $this);

        if (! is_readable($path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Marketing Automation] Rules file not readable: %s', $path));
            }

            return [];
        }

        $mtime = (int) filemtime($path);
        if ($this->cache !== null && $this->cachedMtime === $mtime) {
            return $this->cache;
        }

        $json = file_get_contents($path);
        if ($json === false || $json === '') {
            return [];
        }

        $this->cache        = $this->parser->parse($json);
        $this->cachedMtime = $mtime;

        /** @var list<RuleDefinition> $rules */
        $rules = apply_filters('ma_parsed_rules', $this->cache, $path);

        return $rules;
    }
}
