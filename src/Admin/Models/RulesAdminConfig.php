<?php

declare(strict_types=1);

namespace BertanexFlow\Admin\Models;

/**
 * Settings API groups for the Rules admin workspace.
 *
 * Config and patterns use separate groups so options.php only updates options
 * present on the submitted form (missing keys are saved as null otherwise).
 */
final class RulesAdminConfig
{
    /** Configuration tab: panel layout + SMS delivery credentials. */
    public const SETTINGS_GROUP = 'bertanex_flow_admin_config';

    /** Message patterns tab: editable SMS template bodies. */
    public const PATTERNS_SETTINGS_GROUP = 'bertanex_flow_admin_patterns';
}
