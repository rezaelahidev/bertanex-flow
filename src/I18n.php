<?php

declare(strict_types=1);

namespace BertanexFlow;

/**
 * Loads plugin translations, including explicit fa_IR when the locale is any Persian variant (fa, fa_IR, …).
 */
final class I18n
{
    private const TEXT_DOMAIN = 'bertanex-flow';

    private static bool $baseLoaded = false;

    private static bool $faLoaded = false;

    public static function loadTextdomain(): void
    {
        if (! \defined('BERTANEX_FLOW_PLUGIN_DIR') || ! \defined('BERTANEX_FLOW_PLUGIN_FILE')) {
            return;
        }

        if (! self::$baseLoaded) {
            self::$baseLoaded = true;
            $relative = dirname(\plugin_basename(\BERTANEX_FLOW_PLUGIN_FILE)) . '/languages';
            \load_plugin_textdomain(self::TEXT_DOMAIN, false, $relative);
        }

        if (self::$faLoaded) {
            return;
        }

        $locale = self::resolveLocale();
        if (! str_starts_with($locale, 'fa')) {
            return;
        }

        $mofile = \BERTANEX_FLOW_PLUGIN_DIR . 'languages/' . self::TEXT_DOMAIN . '-fa_IR.mo';
        if (! is_readable($mofile)) {
            return;
        }

        self::$faLoaded = true;

        \unload_textdomain(self::TEXT_DOMAIN);
        \load_textdomain(self::TEXT_DOMAIN, $mofile);

        self::syncGlobalMo($mofile);
    }

    private static function resolveLocale(): string
    {
        if (\function_exists('determine_locale')) {
            return (string) \determine_locale();
        }

        if (\is_admin() && \function_exists('get_user_locale')) {
            return (string) \get_user_locale();
        }

        return (string) \get_locale();
    }

    private static function syncGlobalMo(string $sourceMo): void
    {
        if (! \defined('WP_LANG_DIR')) {
            return;
        }

        $targetDir = \WP_LANG_DIR . '/plugins';
        if (! is_dir($targetDir) && ! wp_mkdir_p($targetDir)) {
            return;
        }

        foreach (['fa_IR', 'fa'] as $suffix) {
            $target = $targetDir . '/' . self::TEXT_DOMAIN . '-' . $suffix . '.mo';
            if (! is_readable($target) || filemtime($target) < filemtime($sourceMo)) {
                @copy($sourceMo, $target);
            }
        }
    }
}
