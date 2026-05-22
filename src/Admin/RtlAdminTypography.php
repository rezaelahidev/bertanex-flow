<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin;

/**
 * RTL typography hooks for the rules admin UI. Theme or site code registers faces via
 * {@see 'gro_rules_admin_enqueue_rtl_typography'} and {@see 'gro_rules_admin_rtl_font_family'}.
 */
final class RtlAdminTypography
{
    public static function enqueue(string $styleHandle): void
    {
        if (! is_rtl()) {
            return;
        }

        /**
         * Enqueue RTL font faces for the Growth Orchestrator rules editor.
         *
         * @param string $styleHandle Primary rules editor stylesheet handle.
         */
        do_action('gro_rules_admin_enqueue_rtl_typography', $styleHandle);

        /**
         * @param string $fontFamily CSS font-family value for `--gro-font-fa`; empty skips inline CSS.
         */
        $fontFamily = apply_filters('gro_rules_admin_rtl_font_family', '');

        if (! is_string($fontFamily) || $fontFamily === '') {
            return;
        }

        wp_add_inline_style(
            $styleHandle,
            '.gro-rules-admin--rtl,.rtl .gro-rules-admin{--gro-font-fa:' . $fontFamily . ';}'
        );
    }
}
