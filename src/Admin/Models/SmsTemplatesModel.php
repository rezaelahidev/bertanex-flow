<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Models;

/**
 * User-editable SMS template bodies (Settings API + runtime resolution).
 *
 * @phpstan-type SmsTemplateRow array{id: string, label: string, body: string, builtin?: bool}
 */
final class SmsTemplatesModel
{
    public const SETTINGS_GROUP = RulesAdminConfig::SETTINGS_GROUP;

    public const OPTION = 'growth_orchestrator_sms_templates';

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
                echo '<header class="gro-patterns-hero">';
                echo '<div class="gro-patterns-hero__icon" aria-hidden="true">';
                echo '<span class="dashicons dashicons-email-alt"></span>';
                echo '</div>';
                echo '<div class="gro-patterns-hero__text">';
                echo '<h3 class="gro-patterns-hero__title">' .
                    esc_html__('SMS message patterns', 'growth-orchestrator') .
                    '</h3>';
                echo '<p class="gro-patterns-hero__lede">' .
                    esc_html__(
                        'Persian texts sent to customers via Melipayamak. Each pattern matches a template_id in your automation rules.',
                        'growth-orchestrator'
                    ) .
                    '</p>';
                echo '</div>';
                echo '</header>';

                echo '<p class="gro-patterns-tip">' .
                    esc_html__(
                        'Write messages in Persian. Keep placeholders like {name} and {site_name} exactly as shown — they are replaced when the SMS is sent.',
                        'growth-orchestrator'
                    ) .
                    '</p>';

                echo '<div class="gro-patterns-vars">';
                echo '<span class="gro-patterns-vars__label">' .
                    esc_html__('Placeholders', 'growth-orchestrator') .
                    '</span>';
                echo '<ul class="gro-patterns-vars__list" role="list">';
                foreach (self::variableNames() as $var) {
                    echo '<li role="listitem"><button type="button" class="gro-patterns-var" data-gro-copy-var="{' .
                        esc_attr($var) .
                        '}" title="' .
                        esc_attr__('Click to copy', 'growth-orchestrator') .
                        '"><code>{' .
                        esc_html($var) .
                        '}</code></button></li>';
                }
                echo '</ul>';
                echo '</div>';
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
     * Built-in template catalog (ids referenced by default rules).
     *
     * @return list<array{id: string, label: string, defaultBody: string, builtin: true}>
     */
    public static function catalog(): array
    {
        return [
            [
                'id'          => 'welcome_registered',
                'label'       => __('Welcome SMS (registration)', 'growth-orchestrator'),
                'defaultBody' => __(
                    'Welcome {name}! Your account at {site_name} is ready: {site_url}',
                    'growth-orchestrator'
                ),
                'builtin'     => true,
            ],
            [
                'id'          => 'complete_profile',
                'label'       => __('Complete profile reminder', 'growth-orchestrator'),
                'defaultBody' => __(
                    'Hi {name}, please complete your profile at {site_url}. — {site_name}',
                    'growth-orchestrator'
                ),
                'builtin'     => true,
            ],
            [
                'id'          => 'subscription_expiring_3d',
                'label'       => __('Subscription expiring soon', 'growth-orchestrator'),
                'defaultBody' => __(
                    'Hi {name}, your subscription renews soon. — {site_name}',
                    'growth-orchestrator'
                ),
                'builtin'     => true,
            ],
            [
                'id'          => 'product_owner_no_sub',
                'label'       => __('Offer for product owner without subscription', 'growth-orchestrator'),
                'defaultBody' => __(
                    'Hi {name}, you have a special offer: {site_name}',
                    'growth-orchestrator'
                ),
                'builtin'     => true,
            ],
        ];
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
        $rows     = self::rowsForForm();
        $resolved = self::resolvedBodies();
        $defaults = [];
        foreach (self::catalog() as $item) {
            $defaults[$item['id']] = $item['defaultBody'];
        }
        ?>
        <div
            class="gro-patterns-editor"
            data-gro-sms-templates
            data-gro-badge-default="<?php echo esc_attr__('Default', 'growth-orchestrator'); ?>"
            data-gro-badge-custom="<?php echo esc_attr__('Custom', 'growth-orchestrator'); ?>"
            data-gro-badge-empty="<?php echo esc_attr__('Not set', 'growth-orchestrator'); ?>"
            data-gro-copied-label="<?php echo esc_attr__('Copied', 'growth-orchestrator'); ?>"
        >
            <ul class="gro-patterns-list" data-gro-sms-templates-rows role="list">
                <?php foreach ($rows as $index => $row) : ?>
                    <?php
                    $id = (string) ($row['id'] ?? '');
                    self::renderTemplateRow(
                        $index,
                        $row,
                        $id !== '' ? (string) ($resolved[$id] ?? '') : '',
                        (string) ($defaults[$id] ?? '')
                    );
                    ?>
                <?php endforeach; ?>
            </ul>
            <div class="gro-patterns-editor__footer">
                <button type="button" class="button gro-patterns-add" data-gro-sms-templates-add>
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Add pattern', 'growth-orchestrator'); ?>
                </button>
            </div>
            <template id="gro-sms-template-row-blank">
                <?php self::renderTemplateRow('__INDEX__', ['id' => '', 'label' => '', 'body' => '', 'builtin' => false], '', ''); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param int|string $index
     * @param SmsTemplateRow $row
     */
    private static function renderTemplateRow($index, array $row, string $currentValue, string $defaultBody): void
    {
        $builtin = ! empty($row['builtin']);
        $id      = (string) ($row['id'] ?? '');
        $label   = (string) ($row['label'] ?? '');
        $body    = (string) ($row['body'] ?? '');
        $name    = self::OPTION . '[' . $index . ']';
        $usesDefault = $body === '' && $defaultBody !== '' && $currentValue === $defaultBody;
        $usesCustom  = $body !== '';
        $statusClass = $usesCustom ? 'gro-pattern-card__status--custom' : ($usesDefault ? 'gro-pattern-card__status--default' : 'gro-pattern-card__status--empty');
        $statusLabel = $usesCustom
            ? __('Custom', 'growth-orchestrator')
            : ($usesDefault ? __('Default', 'growth-orchestrator') : __('Not set', 'growth-orchestrator'));
        $charCount   = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
        ?>
        <li
            class="gro-pattern-card<?php echo $builtin ? ' gro-pattern-card--builtin' : ''; ?>"
            data-gro-sms-templates-row
            data-gro-sms-default-body="<?php echo esc_attr($defaultBody); ?>"
            role="listitem"
        >
            <header class="gro-pattern-card__head">
                <div class="gro-pattern-card__identity">
                    <?php if ($builtin) : ?>
                        <span class="gro-pattern-card__id-badge" dir="ltr" lang="en">
                            <code><?php echo esc_html($id); ?></code>
                        </span>
                        <input type="hidden" name="<?php echo esc_attr($name . '[id]'); ?>" value="<?php echo esc_attr($id); ?>" />
                    <?php else : ?>
                        <input
                            type="text"
                            class="gro-pattern-card__id-input"
                            name="<?php echo esc_attr($name . '[id]'); ?>"
                            value="<?php echo esc_attr($id); ?>"
                            placeholder="<?php echo esc_attr__('pattern_id', 'growth-orchestrator'); ?>"
                            pattern="[a-z][a-z0-9_]*"
                            dir="ltr"
                            lang="en"
                            spellcheck="false"
                            required
                        />
                    <?php endif; ?>
                    <input
                        type="text"
                        class="gro-pattern-card__label-input"
                        name="<?php echo esc_attr($name . '[label]'); ?>"
                        value="<?php echo esc_attr($label); ?>"
                        placeholder="<?php echo esc_attr__('Short title for this pattern', 'growth-orchestrator'); ?>"
                        dir="auto"
                    />
                </div>
                <div class="gro-pattern-card__meta">
                    <span class="gro-pattern-card__status <?php echo esc_attr($statusClass); ?>" data-gro-pattern-status>
                        <?php echo esc_html($statusLabel); ?>
                    </span>
                    <?php if (! $builtin) : ?>
                        <button
                            type="button"
                            class="gro-pattern-card__remove"
                            data-gro-sms-templates-remove
                            data-confirm="<?php echo esc_attr__('Remove this pattern?', 'growth-orchestrator'); ?>"
                            aria-label="<?php echo esc_attr__('Remove pattern', 'growth-orchestrator'); ?>"
                        >
                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </div>
            </header>
            <div class="gro-pattern-card__grid">
                <div class="gro-pattern-card__col gro-pattern-card__col--edit">
                    <label class="gro-pattern-card__field-label">
                        <?php esc_html_e('Your message (override)', 'growth-orchestrator'); ?>
                    </label>
                    <textarea
                        name="<?php echo esc_attr($name . '[body]'); ?>"
                        rows="4"
                        class="gro-pattern-card__textarea"
                        maxlength="<?php echo esc_attr((string) self::MAX_BODY_LENGTH); ?>"
                        placeholder="<?php echo esc_attr__('Leave empty to use the built-in Persian default for this pattern.', 'growth-orchestrator'); ?>"
                        dir="auto"
                        data-gro-pattern-textarea
                    ><?php echo esc_textarea($body); ?></textarea>
                    <div class="gro-pattern-card__field-foot">
                        <span class="gro-pattern-card__hint">
                            <?php esc_html_e('Empty field = default text', 'growth-orchestrator'); ?>
                        </span>
                        <span class="gro-pattern-card__chars" data-gro-pattern-chars data-max="<?php echo esc_attr((string) self::MAX_BODY_LENGTH); ?>">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: current length, 2: max length */
                                    __('%1$d / %2$d characters', 'growth-orchestrator'),
                                    $charCount,
                                    self::MAX_BODY_LENGTH
                                )
                            );
                            ?>
                        </span>
                    </div>
                </div>
                <div class="gro-pattern-card__col gro-pattern-card__col--preview">
                    <label class="gro-pattern-card__field-label">
                        <?php esc_html_e('Current value (sent to user)', 'growth-orchestrator'); ?>
                    </label>
                    <div class="gro-pattern-card__preview" data-gro-sms-current aria-live="polite" dir="auto"><?php echo esc_html($currentValue !== '' ? $currentValue : '—'); ?></div>
                </div>
            </div>
        </li>
        <?php
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
