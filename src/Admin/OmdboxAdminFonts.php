<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin;

/**
 * Loads omdbox theme Yekan Bakh FaNum faces for the rules admin UI in RTL locales.
 */
final class OmdboxAdminFonts
{
    public const FONT_FAMILY = "'Yekan Bakh FaNum', Tahoma, serif";

    /**
     * @param list<array{weight: int, file: string}> $faces
     */
    public static function enqueue(string $styleHandle): void
    {
        if (! is_rtl()) {
            return;
        }

        $css = self::fontFaceCss();

        if ($css === '') {
            return;
        }

        wp_add_inline_style($styleHandle, $css);
    }

    public static function fontFamily(): string
    {
        /**
         * @param string $family CSS font-family value for RTL rules admin.
         */
        $family = apply_filters('gro_rules_admin_rtl_font_family', self::FONT_FAMILY);

        return is_string($family) && $family !== '' ? $family : self::FONT_FAMILY;
    }

    /**
     * @return array{dir: string, uri: string}|null
     */
    private static function themeFontBase(): ?array
    {
        foreach ([get_stylesheet_directory(), get_template_directory()] as $dir) {
            $fontDir = $dir . '/assets/fonts/yekanbakh/';

            if (! is_dir($fontDir)) {
                continue;
            }

            $uri = $dir === get_stylesheet_directory()
                ? get_stylesheet_directory_uri()
                : get_template_directory_uri();

            return ['dir' => $fontDir, 'uri' => $uri];
        }

        return null;
    }

    private static function fontFaceCss(): string
    {
        $base = self::themeFontBase();

        if ($base === null) {
            return '';
        }

        /** @var list<array{weight: int, file: string}> $faces */
        $faces = apply_filters(
            'gro_rules_admin_rtl_font_files',
            [
                ['weight' => 400, 'file' => 'YekanBakhFaNum-Regular.woff2'],
                ['weight' => 600, 'file' => 'YekanBakhFaNum-SemiBold.woff2'],
                ['weight' => 700, 'file' => 'YekanBakhFaNum-Bold.woff2'],
            ]
        );

        if (! is_array($faces) || $faces === []) {
            return '';
        }

        $fontDir = $base['dir'];
        $css     = '';

        foreach ($faces as $face) {
            if (! is_array($face)) {
                continue;
            }

            $file = isset($face['file']) ? (string) $face['file'] : '';
            $weight = isset($face['weight']) ? (int) $face['weight'] : 400;

            if ($file === '' || ! is_readable($fontDir . $file)) {
                continue;
            }

            $url = esc_url($base['uri'] . '/assets/fonts/yekanbakh/' . $file);
            $css .= '@font-face{';
            $css .= "font-family:'Yekan Bakh FaNum';";
            $css .= 'font-style:normal;';
            $css .= 'font-weight:' . $weight . ';';
            $css .= "src:url('{$url}') format('woff2');";
            $css .= 'font-display:swap;';
            $css .= '}';
        }

        return $css;
    }
}
