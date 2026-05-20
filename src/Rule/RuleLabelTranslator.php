<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Rule;

/**
 * Translates default rule labels for the admin UI while keeping English msgids in persisted JSON/DB.
 *
 * English labels in {@see GROWTH_ORCH_PLUGIN_DIR}/rules/default.rules.json are the gettext msgids.
 */
final class RuleLabelTranslator
{
    /** @var array<string, string>|null rule id => English label */
    private static ?array $defaultEnglishById = null;

    /**
     * Localized label for admin display (overview, visual builder).
     */
    public static function display(string $ruleId, ?string $storedLabel): string
    {
        $english = self::defaultEnglishForId($ruleId);

        if ($english !== null) {
            /** @phpstan-ignore-next-line */
            $translated = (string) apply_filters(
                'gro_rule_label',
                __( $english, 'growth-orchestrator' ),
                $ruleId,
                $storedLabel,
                $english
            );

            return $translated;
        }

        if ($storedLabel !== null && $storedLabel !== '') {
            /** @phpstan-ignore-next-line */
            return (string) apply_filters('gro_rule_label', $storedLabel, $ruleId, $storedLabel, null);
        }

        return '';
    }

    /**
     * Restore canonical English labels for bundled default rules before persistence.
     */
    public static function canonicalize(string $ruleId, ?string $label): ?string
    {
        $english = self::defaultEnglishForId($ruleId);

        if ($english === null) {
            return $label;
        }

        if ($label === null || $label === '') {
            return $english;
        }

        $translated = __( $english, 'growth-orchestrator' );

        if ($label === $english || $label === $translated) {
            return $english;
        }

        return $label;
    }

    /**
     * Decode rules document JSON, translate labels for editor display, re-encode.
     */
    public static function localizeDocumentJson(string $json): string
    {
        $data = self::decodeDocument($json);

        if ($data === null) {
            return $json;
        }

        if (! isset($data['rules']) || ! is_array($data['rules'])) {
            return $json;
        }

        foreach ($data['rules'] as $index => $rule) {
            if (! is_array($rule) || ! isset($rule['id'])) {
                continue;
            }

            $id           = (string) $rule['id'];
            $stored       = isset($rule['label']) ? (string) $rule['label'] : null;
            $display      = self::display($id, $stored);
            $data['rules'][$index]['label'] = $display;
        }

        return self::encodeDocument($data) ?? $json;
    }

    /**
     * Decode rules document JSON, canonicalize default rule labels, re-encode before save.
     */
    public static function canonicalizeDocumentJson(string $json): string
    {
        $data = self::decodeDocument($json);

        if ($data === null) {
            return $json;
        }

        if (! isset($data['rules']) || ! is_array($data['rules'])) {
            return $json;
        }

        foreach ($data['rules'] as $index => $rule) {
            if (! is_array($rule) || ! isset($rule['id'])) {
                continue;
            }

            $id    = (string) $rule['id'];
            $label = isset($rule['label']) ? (string) $rule['label'] : null;
            $canonical = self::canonicalize($id, $label);

            if ($canonical === null || $canonical === '') {
                unset($data['rules'][$index]['label']);
            } else {
                $data['rules'][$index]['label'] = $canonical;
            }
        }

        return self::encodeDocument($data) ?? $json;
    }

    /**
     * @return array<string, string>
     */
    public static function defaultEnglishCatalog(): array
    {
        if (self::$defaultEnglishById !== null) {
            return self::$defaultEnglishById;
        }

        self::$defaultEnglishById = [];

        if (! defined('GROWTH_ORCH_PLUGIN_DIR')) {
            return self::$defaultEnglishById;
        }

        $path = GROWTH_ORCH_PLUGIN_DIR . 'rules/default.rules.json';

        if (! is_readable($path)) {
            return self::$defaultEnglishById;
        }

        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            return self::$defaultEnglishById;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::$defaultEnglishById;
        }

        if (! is_array($decoded) || ! isset($decoded['rules']) || ! is_array($decoded['rules'])) {
            return self::$defaultEnglishById;
        }

        foreach ($decoded['rules'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $id    = isset($rule['id']) ? (string) $rule['id'] : '';
            $label = isset($rule['label']) ? (string) $rule['label'] : '';

            if ($id !== '' && $label !== '') {
                self::$defaultEnglishById[$id] = $label;
            }
        }

        return self::$defaultEnglishById;
    }

    private static function defaultEnglishForId(string $ruleId): ?string
    {
        $catalog = self::defaultEnglishCatalog();

        return $catalog[$ruleId] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeDocument(string $json): ?array
    {
        $trimmed = trim($json);

        if ($trimmed === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encodeDocument(array $data): ?string
    {
        try {
            $encoded = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
        } catch (\JsonException) {
            return null;
        }

        return is_string($encoded) ? $encoded . "\n" : null;
    }
}
