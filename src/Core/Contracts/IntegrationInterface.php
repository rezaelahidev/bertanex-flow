<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Contracts;

/**
 * Defines the integration adapter interface.
 *
 * Integrations are adapters that bridge Bertanex Flow with external systems,
 * WordPress plugins, services, or APIs.
 *
 * @package BertanexFlow\Core\Integrations
 */
interface IntegrationInterface
{
    /**
     * Get integration ID.
     *
     * @return string Unique integration identifier (e.g., 'woocommerce', 'gravity-forms').
     */
    public function getId(): string;

    /**
     * Get integration display name.
     *
     * @return string Human-readable name.
     */
    public function getName(): string;

    /**
     * Check if integration is available (plugin/theme/dependency installed).
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Register integration hooks and listeners.
     *
     * Called during bootstrapping.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Get available events for this integration.
     *
     * @return array<string, array{name: string, label: string, description: string}>
     */
    public function getEvents(): array;

    /**
     * Get available actions for this integration.
     *
     * @return array<string, array{name: string, label: string, description: string}>
     */
    public function getActions(): array;

    /**
     * Get data schema for this integration.
     *
     * Defines available data points for context resolution.
     *
     * @return array<string, array{type: string, label: string, description: string}>
     */
    public function getDataSchema(): array;
}