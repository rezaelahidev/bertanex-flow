<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Services;

/**
 * Registry of pluggable automation backends (SMS, email, CRM push, etc.).
 *
 * Services are plain objects; contracts live in their own namespaces (e.g. {@see \GrowthOrchestrator\Sms\SmsServiceInterface}).
 * Third-party code typically runs on {@see 'gro_register_automation_services'} to {@see replace()} defaults.
 */
final class AutomationServices
{
    /** @var array<string, object> */
    private array $services = [];

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function register(string $id, object $service): void
    {
        if (isset($this->services[$id])) {
            throw new \InvalidArgumentException(sprintf('Automation service "%s" is already registered.', $id));
        }

        $this->services[$id] = $service;
    }

    public function replace(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): object
    {
        if (! isset($this->services[$id])) {
            throw new \InvalidArgumentException(sprintf('Unknown automation service "%s".', $id));
        }

        return $this->services[$id];
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public function getAs(string $id, string $type): object
    {
        $service = $this->get($id);

        if (! $service instanceof $type) {
            throw new \InvalidArgumentException(
                sprintf('Service "%s" must implement %s, %s given.', $id, $type, $service::class)
            );
        }

        return $service;
    }
}
