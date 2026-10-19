<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Models;

use BertanexFlow\Consumer\ConsumerDefaultsResolver;
use BertanexFlow\Consumer\ConsumerRegistry;

/**
 * User-editable SMS template bodies (Settings API + runtime resolution).
 *
 * @phpstan-type SmsTemplateRow array{id: string, label: string, body: string, builtin?: bool}
 */
final class SmsTemplatesModel
{
    public const SETTINGS_GROUP = RulesAdminConfig::PATTERNS_SETTINGS_GROUP;

    public const OPTION = 'bertanex_flow_sms_templates';

    private const MAX_TEMPLATES = 50;

    private const MAX_BODY_LENGTH = 500;

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [$this, 'sanitizeStoredTemplates'],
            ]
        );

        add_settings_section(
            'gro_sms_templates_section',
            '',
            static function (): void {
            },
            self::SETTINGS_GROUP
        );

        add_settings_field(
            self::OPTION,
            '',
            [$this, 'renderTemplatesField'],
            self::SETTINGS_GROUP,
            'gro_sms_templates_section',
            ['label_for' => '']
        );
    }

    /**
     * @return list<string>
     */
    public static function variableNames(): array
    {
        $vars = [
            'name',
            'site_name',
            'site_url',
            'shop_name',
            'phone',
            'user_login',
            'user_email',
            'first_name',
            'last_name',
        ];

        /**
         * @param list<string> $vars
         */
        $filtered = apply_filters('gro_sms_template_variable_names', $vars);

        return is_array($filtered) ? array_values(array_filter(array_map('strval', $filtered))) : $vars;
    }

    /**
     * Built-in template catalog from integrated consumers.
     *
     * @return list<array{id: string, label: string, defaultBody: string, builtin: true}>
     */
    public static function catalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        if (class_exists(ConsumerRegistry::class)) {
            $resolver = new ConsumerDefaultsResolver(ConsumerRegistry::instance());
            $catalog  = $resolver->mergedSmsPatternCatalog();

            return $catalog;
        }

        $catalog = [];

        return $catalog;
    }

    /**
     * @return array<string, string> template_id => message body
     */
    public static function resolvedBodies(): array
    {
        $map = [];

        foreach (self::catalog() as $item) {
            $map[$item['id']] = $item['defaultBody'];
        }

        foreach (self::storedRowsIndexed() as $id => $row) {
            $body = trim((string) ($row['body'] ?? ''));
            if ($body !== '') {
                $map[$id] = $body;
            }
        }

        /** @var array<string, string> $filtered */
        $filtered = apply_filters('gro_sms_templates', []);

        if (is_array($filtered)) {
            foreach ($filtered as $id => $body) {
                if (is_string($id) && $id !== '' && is_string($body) && $body !== '') {
                    $map[$id] = $body;
                }
            }
        }

        return $map;
    }

    /**
     * @return list<array{id: string, label: string, body: string}>
     */
    public static function forEditor(): array
    {
        $bodies = self::resolvedBodies();
        $labels = self::labelsById();
        $out    = [];

        foreach (self::catalog() as $item) {
            $id    = $item['id'];
            $out[] = [
                'id'    => $id,
                'label' => $labels[$id] ?? $item['label'],
                'body'  => $bodies[$id] ?? $item['defaultBody'],
            ];
        }

        foreach ($bodies as $id => $body) {
            if (self::catalogHasId($id)) {
                continue;
            }
            $out[] = [
                'id'    => $id,
                'label' => $labels[$id] ?? $id,
                'body'  => $body,
            ];
        }

        return $out;
    }

    /**
     * Rows for the settings form (catalog + custom saved).
     *
     * @return list<SmsTemplateRow>
     */
    public static function rowsForForm(): array
    {
        $stored   = self::storedRowsIndexed();
        $catalog  = self::catalog();
        $rows     = [];
        $seenIds  = [];

        foreach ($catalog as $item) {
            $id = $item['id'];
            $seenIds[$id] = true;
            $saved        = $stored[$id] ?? null;
            $rows[]       = [
                'id'      => $id,
                'label'   => is_array($saved) && (string) ($saved['label'] ?? '') !== ''
                    ? (string) $saved['label']
                    : $item['label'],
                'body'    => is_array($saved) ? (string) ($saved['body'] ?? '') : '',
                'builtin' => true,
            ];
        }

        foreach ($stored as $id => $saved) {
            if (isset($seenIds[$id]) || ! is_array($saved)) {
                continue;
            }
            $rows[] = [
                'id'      => $id,
                'label'   => (string) ($saved['label'] ?? $id),
                'body'    => (string) ($saved['body'] ?? ''),
                'builtin' => false,
            ];
        }

        return $rows;
    }

    public function renderTemplatesField(): void
    {
        $defaults = [];
        foreach (self::catalog() as $item) {
            $defaults[$item['id']] = $item['defaultBody'];
        }
        $rows = [];
        foreach (self::rowsForForm() as $row) {
            $id = (string) ($row['id'] ?? '');
            $rows[] = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'builtin' => ! empty($row['builtin']),
                'defaultBody' => (string) ($defaults[$id] ?? ''),
            ];
        }
        $payload = [
            'option' => self::OPTION,
            'maxBody' => self::MAX_BODY_LENGTH,
            'rows' => $rows,
            'samples' => self::sampleValues(),
            'variables' => self::variableNames(),
            'labels' => [
                'title' => __('SMS message patterns', 'bertanex-flow'),
                'intro' => __('Persian texts sent to customers via Melipayamak. Each pattern matches a template_id in your automation rules.', 'bertanex-flow'),
                'search' => __('Search patterns', 'bertanex-flow'),
                'add' => __('Add pattern', 'bertanex-flow'),
                'id' => __('Pattern ID', 'bertanex-flow'),
                'name' => __('Title', 'bertanex-flow'),
                'body' => __('Your message (override)', 'bertanex-flow'),
                'emptyHint' => __('Empty field = default text', 'bertanex-flow'),
                'preview' => __('Preview with sample values', 'bertanex-flow'),
                'remove' => __('Remove pattern', 'bertanex-flow'),
                'confirmRemove' => __('Remove this pattern?', 'bertanex-flow'),
                'placeholders' => __('Placeholders', 'bertanex-flow'),
                'default' => __('Default', 'bertanex-flow'),
                'custom' => __('Custom', 'bertanex-flow'),
                'empty' => __('Not set', 'bertanex-flow'),
                'chars' => __('%1$d / %2$d characters', 'bertanex-flow'),
            ],
        ];
        echo '<div id="gro-sms-patterns"></div>';
        echo '<script type="application/json" id="gro-sms-patterns-data">' . wp_json_encode($payload) . '</script>';
    }


    /**
     * @param mixed $value
     *
     * @return list<SmsTemplateRow>
     */
    public function sanitizeStoredTemplates($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $catalogIds = [];
        foreach (self::catalog() as $item) {
            $catalogIds[$item['id']] = true;
        }

        $out    = [];
        $seen   = [];

        foreach ($value as $row) {
            if (! is_array($row) || count($out) >= self::MAX_TEMPLATES) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            if (! isset($catalogIds[$id]) && ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $id)) {
                continue;
            }

            $body = trim(sanitize_textarea_field((string) ($row['body'] ?? '')));
            if (strlen($body) > self::MAX_BODY_LENGTH) {
                $body = substr($body, 0, self::MAX_BODY_LENGTH);
            }

            $seen[$id] = true;
            $out[]     = [
                'id'    => $id,
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'body'  => $body,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{id?: string, label?: string, body?: string}>
     */
    private static function storedRowsIndexed(): array
    {
        $raw = get_option(self::OPTION, []);
        if (! is_array($raw)) {
            return [];
        }

        $indexed = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }

    /**
     * @return array<string, string>
     */
    private static function labelsById(): array
    {
        $labels = [];
        foreach (self::catalog() as $item) {
            $labels[$item['id']] = $item['label'];
        }
        foreach (self::storedRowsIndexed() as $id => $row) {
            $custom = trim((string) ($row['label'] ?? ''));
            if ($custom !== '') {
                $labels[$id] = $custom;
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private static function sampleValues(): array
    {
        $samples = [
            'name' => 'Sara Rahimi',
            'site_name' => 'Bertanex',
            'site_url' => 'https://bertanex.test',
            'shop_name' => 'Bertanex Shop',
            'phone' => '09120000000',
            'user_login' => 'sara',
            'user_email' => 'sara@example.com',
            'first_name' => 'Sara',
            'last_name' => 'Rahimi',
        ];

        $filtered = apply_filters('gro_sms_template_sample_values', $samples);

        return is_array($filtered) ? $filtered : $samples;
    }

    private static function catalogHasId(string $id): bool
    {
        foreach (self::catalog() as $item) {
            if ($item['id'] === $id) {
                return true;
            }
        }

        return false;
    }
}
