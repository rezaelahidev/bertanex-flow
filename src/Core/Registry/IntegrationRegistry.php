<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Registry;

use BertanexFlow\Core\Contracts\IntegrationInterface;
use InvalidArgumentException;

/**
 * Registry for integration adapters.
 *
 * Manages registration and discovery of WordPress plugins, services,
 * and third-party integrations.
 *
 * @package BertanexFlow\Core\Registry
 */
final class IntegrationRegistry
{
    /** @var array<string, IntegrationInterface> Registered integrations. */
    private array $integrations = [];

    public function register(IntegrationInterface $integration): void
    {
        $id = $integration->getId();

        if ($id === '' || str_starts_with($id, '_')) {
            return;
        }

        $this->integrations[$id] = $integration;
    }

    /**
     * Get an integration by ID.
     *
     * @param string $id Integration identifier.
     * @return IntegrationInterface|null
     */
    public function get(string $id): ?IntegrationInterface
    {
        return $this->integrations[$id] ?? null;
    }

    /**
     * Check if integration is registered.
     *
     * @param string $id Integration identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->integrations[$id]);
    }

    /**
     * Get all registered integrations.
     *
     * @return IntegrationInterface[]
     */
    public function all(): array
    {
        return array_values($this->integrations);
    }

    /**
     * Get available integration IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->integrations);
    }

    /**
     * Get integrated events from all registered integrations.
     *
     * @return array{value: string, label: string}[]
     */
    public function eventChoices(): array
    {
        $choices = [];

        foreach ($this->integrations as $integration) {
            $source = $integration->getName();
            $events = $integration->getEvents();

            foreach ($events as $event) {
                $name = $event['name'] ?? '';
                if ($name === '') {
                    continue;
                }

                $value = $integration->getId() . '.' . $name;
                $label = isset($event['label'])
                    ? $event['label']
                    : $source . ': ' . $name;

                $choices[] = [
                    'value' => $value,
                    'label' => $label,
                    'integration' => $integration->getId(),
                ];
            }
        }

        /**
         * Filter integration event choices.
         *
         * @param array{value: string, label: string}[] $choices Event choices.
         * @return array{value: string, label: string}[] Filtered choices.
         */
        return apply_filters('bertanex_flow_integration_event_choices', $choices);
    }

    /**
     * Get integrated actions from all registered integrations.
     *
     * @return array{value: string, label: string}[]
     */
    public function actionChoices(): array
    {
        $choices = [];

        foreach ($this->integrations as $integration) {
            $source = $integration->getName();
            $actions = $integration->getActions();

            foreach ($actions as $action) {
                $name = $action['name'] ?? '';
                if ($name === '') {
                    continue;
                }

                $value = $integration->getId() . '.' . $name;
                $label = isset($action['label'])
                    ? $action['label']
                    : $source . ': ' . $name;

                $choices[] = [
                    'value' => $value,
                    'label' => $label,
                    'integration' => $integration->getId(),
                ];
            }
        }

        /**
         * Filter integration action choices.
         *
         * @param array{value: string, label: string}[] $choices Action choices.
         * @return array{value: string, label: string}[] Filtered choices.
         */
        return apply_filters('bertanex_flow_integration_action_choices', $choices);
    }

    /**
     * Get supported integration IDs.
     *
     * @return array<int, string>
     */
    public function supported(): array
    {
        $supported = [];

        foreach ($this->integrations as $integration) {
            if ($integration->isAvailable()) {
                $supported[] = $integration->getId();
            }
        }

        return $supported;
    }

    /**
     * Get unsupported integration IDs.
     *
     * @return array<int, string>
     */
    public function unavailable(): array
    {
        $unavailable = [];

        foreach ($this->integrations as $integration) {
            if (! $integration->isAvailable()) {
                $unavailable[] = $integration->getId();
            }
        }

        return $unavailable;
    }

    /**
     * Clear all registered integrations (for testing).
     *
     * @return void
     */
    public static function resetForTesting(): void
    {
        // Instance will be recreated - nothing to do
    }
}