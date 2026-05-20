<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin\Views;

/**
 * Renders a single Settings API section (for split config cards).
 */
final class SettingsSectionRenderer
{
    public static function render(string $page, string $sectionId): void
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (! isset($wp_settings_sections[$page][$sectionId])) {
            return;
        }

        $section = $wp_settings_sections[$page][$sectionId];
        if (is_callable($section['callback'] ?? null)) {
            call_user_func($section['callback'], $section);
        }

        if (! isset($wp_settings_fields[$page][$sectionId])) {
            return;
        }

        echo '<table class="form-table gro-form-table" role="presentation">';

        foreach ($wp_settings_fields[$page][$sectionId] as $field) {
            if (! is_callable($field['callback'] ?? null)) {
                continue;
            }

            $labelFor = isset($field['args']['label_for']) ? (string) $field['args']['label_for'] : '';
            echo '<tr>';
            echo '<th scope="row">';

            if ($labelFor !== '') {
                echo '<label for="' . esc_attr($labelFor) . '">' . esc_html((string) $field['title']) . '</label>';
            } else {
                echo esc_html((string) $field['title']);
            }

            echo '</th><td>';
            call_user_func($field['callback'], $field['args']);
            echo '</td></tr>';
        }

        echo '</table>';
    }
}
