<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

/**
 * Templates from {@see 'gro_sms_templates'} merged with safe defaults.
 */
final class DefaultSmsTemplateProvider implements SmsTemplateProviderInterface
{
    public function get(string $templateId): ?string
    {
        $templates = array_merge(
            $this->defaults(),
            (array) apply_filters('gro_sms_templates', [])
        );

        return $templates[$templateId] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'complete_profile'         => 'Hi {name}, please complete your profile at {site_url}. — {site_name}',
            'subscription_expiring_3d' => 'Hi {name}, your subscription renews soon. — {site_name}',
            'product_owner_no_sub'     => 'Hi {name}, you have a special offer: {site_name}',
        ];
    }
}
