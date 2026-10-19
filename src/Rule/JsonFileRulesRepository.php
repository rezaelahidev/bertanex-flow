<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Loads rules from a JSON file; path override via {@see 'gro_rules_json_path'}.
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

    /**
     * Path after {@see 'gro_rules_json_path'} (for tooling / admin fallbacks).
     */
    public function getResolvedPath(): string
    {
        /** @var string $path */
        $path = apply_filters('gro_rules_json_path', $this->defaultPath, $this);

        return $path;
    }

    /**
     * Raw JSON from the rules file, or empty string if missing/unreadable.
     */
    public function readRawDocument(): string
    {
        $path = $this->getResolvedPath();

        if (is_readable($path)) {
            $json = file_get_contents($path);
            if ($json !== false && trim($json) !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded['rules']) && is_array($decoded['rules']) && $decoded['rules'] !== []) {
                    return $json;
                }
            }
        }

        if (class_exists(\BertanexFlow\Consumer\ConsumerRegistry::class)) {
            $resolver = new \BertanexFlow\Consumer\ConsumerDefaultsResolver(
                \BertanexFlow\Consumer\ConsumerRegistry::instance()
            );
            $merged = $resolver->mergedRulesDocumentJson();
            if (trim($merged) !== '') {
                return $merged;
            }
        }

        return '';
    }

    public function getRules(): array
    {
        $path = $this->getResolvedPath();

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
        $rules = apply_filters('gro_parsed_rules', $this->cache, $path);

        return $rules;
    }
}
