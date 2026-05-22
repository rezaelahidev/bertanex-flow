<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Admin;

use GrowthOrchestrator\Admin\Models\RulesDocumentModel;
use GrowthOrchestrator\Admin\Models\RulesEditorSettingsModel;
use GrowthOrchestrator\Admin\Models\SmsSettingsModel;
use GrowthOrchestrator\Admin\Models\SmsTemplatesModel;
use GrowthOrchestrator\Rule\OptionRulesRepository;
use GrowthOrchestrator\Sms\MelipayamakSmsConfig;

/**
 * Restores plugin admin customizations to shipped defaults (rules file, empty options).
 */
final class PluginDefaultsReset
{
    public function __construct(
        private readonly RulesDocumentModel $rulesDocument,
        private readonly string $defaultRulesJsonPath
    ) {
    }

    /**
     * @throws \RuntimeException When default rules cannot be read or persisted.
     */
    public function resetAll(): void
    {
        /**
         * Fires before admin "reset to defaults" clears stored customizations.
         */
        do_action('gro_before_reset_defaults');

        $this->resetRulesDocument();
        $this->resetSmsTemplates();
        $this->resetEditorPanelSettings();
        $this->resetSmsDeliverySettings();
        $this->clearCurrentUserRulesDraft();

        /**
         * Fires after admin "reset to defaults" completed successfully.
         */
        do_action('gro_after_reset_defaults');
    }

    private function resetRulesDocument(): void
    {
        if (! is_readable($this->defaultRulesJsonPath)) {
            throw new \RuntimeException(
                __('Default rules file is missing or not readable.', 'growth-orchestrator')
            );
        }

        $json = file_get_contents($this->defaultRulesJsonPath);
        if ($json === false || trim($json) === '') {
            throw new \RuntimeException(
                __('Default rules file is empty.', 'growth-orchestrator')
            );
        }

        $this->rulesDocument->saveValidatedDocument($json);

        /** @phpstan-ignore-next-line */
        delete_option((string) apply_filters('gro_rules_option_name', OptionRulesRepository::DEFAULT_OPTION_NAME));
    }

    private function resetSmsTemplates(): void
    {
        delete_option(SmsTemplatesModel::OPTION);
    }

    private function resetEditorPanelSettings(): void
    {
        delete_option(RulesEditorSettingsModel::OPTION_MAX_CONDITIONS);
        delete_option(RulesEditorSettingsModel::OPTION_MAX_ACTIONS);
        delete_option(RulesEditorSettingsModel::OPTION_DISPLAY_COLUMNS);
    }

    private function resetSmsDeliverySettings(): void
    {
        delete_option(SmsSettingsModel::OPTION_PROVIDER);
        delete_option(MelipayamakSmsConfig::OPTION_USERNAME);
        delete_option(MelipayamakSmsConfig::OPTION_PASSWORD);
        delete_option(MelipayamakSmsConfig::OPTION_FROM);
    }

    private function clearCurrentUserRulesDraft(): void
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            delete_transient('gro_rules_admin_draft_' . $userId);
        }
    }
}
