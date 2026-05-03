<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

/**
 * Renders SMS templates: placeholders {@code {variable_name}} replaced from a flat variable map.
 */
final class SmsTemplateRenderer
{
    /**
     * @param array<string, string|int|float|bool|null> $variables
     */
    public function render(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{([a-zA-Z][a-zA-Z0-9_]*)\}/',
            static function (array $m) use ($variables): string {
                $key = $m[1];

                if (! array_key_exists($key, $variables)) {
                    return (string) apply_filters('gro_sms_template_missing_var', '', $key, $variables);
                }

                $val = $variables[$key];

                return $val === null ? '' : (string) $val;
            },
            $template
        );
    }
}
