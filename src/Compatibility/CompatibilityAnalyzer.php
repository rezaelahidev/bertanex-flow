<?php

declare(strict_types=1);

namespace BertanexFlow\Compatibility;

use BertanexFlow\Core\Contracts\IntegrationInterface;
use BertanexFlow\Core\Registry\IntegrationRegistry;

/**
 * Explains which installed plugins Bertanex Flow can use.
 */
final class CompatibilityAnalyzer
{
    public function __construct(private readonly IntegrationRegistry $integrations)
    {
    }

    /**
     * @return list<array{id: string, name: string, status: string, message: string, available: bool}>
     */
    public function report(): array
    {
        $rows = [];

        foreach ($this->integrations->all() as $integration) {
            $rows[] = $this->describe($integration);
        }

        if (function_exists('apply_filters')) {
            $rows = apply_filters('bertanex_flow_compatibility_report', $rows, $this->integrations);
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{id: string, name: string, status: string, message: string, available: bool}
     */
    private function describe(IntegrationInterface $integration): array
    {
        $available = $integration->isAvailable();

        return [
            'id' => $integration->getId(),
            'name' => $integration->getName(),
            'status' => $available ? 'supported' : 'unavailable',
            'available' => $available,
            'message' => $available
                ? sprintf('%s is available to Flow.', $integration->getName())
                : sprintf('%s is not active. Workflows that depend on it will not run.', $integration->getName()),
        ];
    }

    /**
     * @return list<string>
     */
    public function explainMissing(string $integrationId): array
    {
        $integration = $this->integrations->get($integrationId);
        if ($integration === null) {
            return [
                sprintf('No Flow adapter is registered for "%s".', $integrationId),
                'Add an integration with the bertanex_flow_integrations filter.',
            ];
        }

        if ($integration->isAvailable()) {
            return [sprintf('%s is available.', $integration->getName())];
        }

        return [
            sprintf('The required %s event is unavailable.', $integration->getName()),
            sprintf('%s is inactive or its adapter cannot detect it.', $integration->getName()),
            'Enable the plugin or review compatibility diagnostics.',
        ];
    }
}
