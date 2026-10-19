<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Models;

/**
 * Editable email templates: subject, body, and {placeholder} values.
 *
 * @phpstan-type EmailTemplateRow array{id: string, label: string, subject: string, body: string}
 */
final class EmailTemplatesModel
{
    public const SETTINGS_GROUP = 'bertanex_flow_admin_email';

    public const OPTION = 'bertanex_flow_email_templates';

    private const MAX_TEMPLATES = 50;

    private const MAX_BODY_LENGTH = 5000;

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION,
            [
                'type' => 'array',
                'default' => [],
                'sanitize_callback' => [$this, 'sanitizeStoredTemplates'],
            ]
        );

        add_settings_section(
            'gro_email_templates_section',
            '',
            static function (): void {
                echo '<header class="gro-patterns-hero"><div>';
                echo '<h3 class="gro-patterns-hero__title">' . esc_html__('Email templates', 'bertanex-flow') . '</h3>';
                echo '<p class="gro-patterns-hero__lede">' . esc_html__('Create, edit, and remove the emails automations can send. Insert a placeholder into the subject or message at the cursor.', 'bertanex-flow') . '</p>';
                echo '</div></header>';
                self::renderVariableBar();
            },
            self::SETTINGS_GROUP
        );

        add_settings_field(
            self::OPTION,
            '',
            [$this, 'renderTemplatesField'],
            self::SETTINGS_GROUP,
            'gro_email_templates_section',
            ['label_for' => '']
        );
    }

    /**
     * @return list<string>
     */
    public static function variableNames(): array
    {
        return SmsTemplatesModel::variableNames();
    }

    public function renderTemplatesField(): void
    {
        $rows = self::rows();
        ?>
        <div
            class="gro-patterns-editor"
            data-gro-sms-templates
            data-gro-option-name="<?php echo esc_attr(self::OPTION); ?>"
            data-gro-row-template="gro-email-template-row-blank"
            data-gro-sample-values="<?php echo esc_attr((string) wp_json_encode(self::sampleValues())); ?>"
            data-gro-copied-label="<?php echo esc_attr__('Inserted', 'bertanex-flow'); ?>"
            data-gro-badge-custom="<?php echo esc_attr__('Ready', 'bertanex-flow'); ?>"
            data-gro-badge-empty="<?php echo esc_attr__('Empty', 'bertanex-flow'); ?>"
            data-gro-badge-default="<?php echo esc_attr__('Ready', 'bertanex-flow'); ?>"
        >
            <label class="gro-patterns-search">
                <span class="screen-reader-text"><?php esc_html_e('Search email templates', 'bertanex-flow'); ?></span>
                <input type="search" data-gro-pattern-search placeholder="<?php echo esc_attr__('Search templates', 'bertanex-flow'); ?>">
            </label>
            <ul class="gro-patterns-list" data-gro-sms-templates-rows role="list">
                <?php foreach ($rows as $index => $row) : ?>
                    <?php self::renderRow($index, $row); ?>
                <?php endforeach; ?>
            </ul>
            <p class="gro-patterns-empty" data-gro-patterns-empty <?php echo $rows === [] ? '' : 'hidden'; ?>>
                <?php esc_html_e('No email templates yet. Add one, then insert the values the message should include.', 'bertanex-flow'); ?>
            </p>
            <div class="gro-patterns-editor__footer">
                <button type="button" class="button gro-patterns-add" data-gro-sms-templates-add>
                    <?php esc_html_e('Add email template', 'bertanex-flow'); ?>
                </button>
            </div>
            <template id="gro-email-template-row-blank">
                <?php self::renderRow('__INDEX__', ['id' => '', 'label' => '', 'subject' => '', 'body' => '']); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param mixed $value
     * @return list<EmailTemplateRow>
     */
    public function sanitizeStoredTemplates($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($value as $row) {
            if (! is_array($row) || count($out) >= self::MAX_TEMPLATES) {
                continue;
            }

            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '' || isset($seen[$id]) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $id)) {
                continue;
            }

            $body = trim(sanitize_textarea_field((string) ($row['body'] ?? '')));
            if (strlen($body) > self::MAX_BODY_LENGTH) {
                $body = substr($body, 0, self::MAX_BODY_LENGTH);
            }

            $seen[$id] = true;
            $out[] = [
                'id' => $id,
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'subject' => sanitize_text_field((string) ($row['subject'] ?? '')),
                'body' => $body,
            ];
        }

        return $out;
    }

    /**
     * @return list<EmailTemplateRow>
     */
    private static function rows(): array
    {
        $raw = get_option(self::OPTION, []);
        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = [
                'id' => sanitize_key((string) ($row['id'] ?? '')),
                'label' => (string) ($row['label'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param int|string $index
     * @param EmailTemplateRow $row
     */
    private static function renderRow($index, array $row): void
    {
        $name = self::OPTION . '[' . $index . ']';
        $id = (string) ($row['id'] ?? '');
        $label = (string) ($row['label'] ?? '');
        $subject = (string) ($row['subject'] ?? '');
        $body = (string) ($row['body'] ?? '');
        $panelId = 'gro-email-panel-' . (is_string($index) ? 'new' : sanitize_key($id !== '' ? $id : 'row-' . (string) $index));
        $title = $label !== '' ? $label : ($id !== '' ? $id : __('New email template', 'bertanex-flow'));
        ?>
        <li class="gro-pattern-card gro-pattern-accordion" data-gro-sms-templates-row data-gro-pattern-accordion role="listitem">
            <div class="gro-pattern-accordion__bar">
                <button type="button" class="gro-pattern-accordion__trigger" data-gro-pattern-accordion-toggle aria-expanded="false" aria-controls="<?php echo esc_attr($panelId); ?>">
                    <span class="gro-pattern-accordion__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    <span class="gro-pattern-accordion__summary">
                        <span class="gro-pattern-accordion__title" data-gro-pattern-accordion-title><?php echo esc_html($title); ?></span>
                        <code class="gro-pattern-accordion__id" dir="ltr" lang="en"><?php echo esc_html($id); ?></code>
                    </span>
                </button>
                <button type="button" class="gro-pattern-card__remove" data-gro-sms-templates-remove data-confirm="<?php echo esc_attr__('Remove this email template?', 'bertanex-flow'); ?>" aria-label="<?php echo esc_attr__('Remove email template', 'bertanex-flow'); ?>">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                </button>
            </div>
            <div id="<?php echo esc_attr($panelId); ?>" class="gro-pattern-accordion__panel" hidden>
                <div class="gro-pattern-card__identity">
                    <label class="gro-pattern-card__inline-label">
                        <span><?php esc_html_e('Template ID', 'bertanex-flow'); ?></span>
                        <input type="text" class="gro-pattern-card__id-input" name="<?php echo esc_attr($name . '[id]'); ?>" value="<?php echo esc_attr($id); ?>" pattern="[a-z][a-z0-9_]*" dir="ltr" lang="en" required data-gro-pattern-id-input>
                    </label>
                    <label class="gro-pattern-card__inline-label gro-pattern-card__inline-label--grow">
                        <span><?php esc_html_e('Title', 'bertanex-flow'); ?></span>
                        <input type="text" name="<?php echo esc_attr($name . '[label]'); ?>" value="<?php echo esc_attr($label); ?>" data-gro-pattern-label-input>
                    </label>
                </div>
                <label class="gro-pattern-card__field-label"><?php esc_html_e('Subject', 'bertanex-flow'); ?></label>
                <input type="text" class="widefat" name="<?php echo esc_attr($name . '[subject]'); ?>" value="<?php echo esc_attr($subject); ?>" data-gro-pattern-subject>
                <label class="gro-pattern-card__field-label"><?php esc_html_e('Message', 'bertanex-flow'); ?></label>
                <textarea name="<?php echo esc_attr($name . '[body]'); ?>" rows="6" class="gro-pattern-card__textarea" data-gro-pattern-textarea><?php echo esc_textarea($body); ?></textarea>
                <p class="gro-pattern-card__field-label"><?php esc_html_e('Preview with sample values', 'bertanex-flow'); ?></p>
                <div class="gro-pattern-card__preview" data-gro-sample-preview>—</div>
            </div>
        </li>
        <?php
    }

    private static function renderVariableBar(): void
    {
        echo '<div class="gro-patterns-vars"><span class="gro-patterns-vars__label">' . esc_html__('Insert value', 'bertanex-flow') . '</span><ul class="gro-patterns-vars__list" role="list">';
        foreach (self::variableNames() as $var) {
            echo '<li><button type="button" class="gro-patterns-var" data-gro-insert-var="{' . esc_attr($var) . '}"><code>{' . esc_html($var) . '}</code></button></li>';
        }
        echo '</ul></div>';
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

        $filtered = apply_filters('gro_email_template_sample_values', $samples);

        return is_array($filtered) ? $filtered : $samples;
    }
}
