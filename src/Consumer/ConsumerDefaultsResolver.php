<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Consumer;

/**
 * Merges bundled defaults from all registered consumers.
 */
final class ConsumerDefaultsResolver
{
    public function __construct(
        private readonly ConsumerRegistry $registry
    ) {
    }

    /**
     * Merged rules document JSON ({@code version} + {@code rules} array).
     */
    public function mergedRulesDocumentJson(): string
    {
        $rules   = [];
        $version = '1.0.0';

        foreach ($this->registry->all() as $consumer) {
            $path = $consumer->defaultRulesJsonPath;
            if ($path === null || $path === '' || ! is_readable($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['version']) && is_string($decoded['version']) && $decoded['version'] !== '') {
                $version = $decoded['version'];
            }

            if (! isset($decoded['rules']) || ! is_array($decoded['rules'])) {
                continue;
            }

            foreach ($decoded['rules'] as $rule) {
                if (is_array($rule)) {
                    $rules[] = $rule;
                }
            }
        }

        $document = [
            'version' => $version,
            'rules'   => $rules,
        ];

        $encoded = wp_json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{"version":"1.0.0","rules":[]}';
    }

    /**
     * @return list<array{id: string, label: string, defaultBody: string, builtin: true}>
     */
    public function mergedSmsPatternCatalog(): array
    {
        $catalog = [];
        $seen    = [];

        foreach ($this->registry->all() as $consumer) {
            $path = $consumer->defaultSmsPatternsJsonPath;
            if ($path === null || $path === '' || ! is_readable($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = sanitize_key((string) ($row['id'] ?? ''));
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $catalog[] = [
                    'id'          => $id,
                    'label'       => (string) ($row['label'] ?? $id),
                    'defaultBody' => (string) ($row['defaultBody'] ?? ''),
                    'builtin'     => true,
                ];
            }
        }

        /**
         * @param list<array{id: string, label: string, defaultBody: string, builtin?: bool}> $catalog
         */
        $filtered = apply_filters('gro_sms_patterns_catalog', $catalog);

        if (! is_array($filtered)) {
            return $catalog;
        }

        $normalized = [];
        foreach ($filtered as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id'          => $id,
                'label'       => (string) ($row['label'] ?? $id),
                'defaultBody' => (string) ($row['defaultBody'] ?? ''),
                'builtin'     => true,
            ];
        }

        return $normalized !== [] ? $normalized : $catalog;
    }
}
