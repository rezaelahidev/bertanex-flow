<?php

declare(strict_types=1);

namespace BertanexFlow\Sms;

use BertanexFlow\Admin\Models\SmsTemplatesModel;

/**
 * SMS bodies from admin-configured templates ({@see SmsTemplatesModel}) plus {@see 'gro_sms_templates'} filter.
 */
final class DefaultSmsTemplateProvider implements SmsTemplateProviderInterface
{
    public function get(string $templateId): ?string
    {
        $templates = SmsTemplatesModel::resolvedBodies();

        return $templates[$templateId] ?? null;
    }
}
