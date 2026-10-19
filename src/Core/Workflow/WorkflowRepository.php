<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

/**
 * Stores workflow documents in a WordPress option.
 */
final class WorkflowRepository
{
    public const OPTION = 'bertanex_flow_workflows';

    /**
     * @return list<WorkflowDefinition>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            return [];
        }

        $workflows = [];
        foreach ($stored as $document) {
            if (! is_array($document)) {
                continue;
            }

            try {
                $workflows[] = WorkflowDefinition::fromArray($document);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $workflows;
    }

    public function find(string $id): ?WorkflowDefinition
    {
        foreach ($this->all() as $workflow) {
            if ($workflow->getId() === $id) {
                return $workflow;
            }
        }

        return null;
    }

    public function save(WorkflowDefinition $workflow): void
    {
        $documents = [];
        $replaced = false;

        foreach ($this->all() as $existing) {
            if ($existing->getId() === $workflow->getId()) {
                $documents[] = $workflow->jsonSerialize();
                $replaced = true;
                continue;
            }

            $documents[] = $existing->jsonSerialize();
        }

        if (! $replaced) {
            $documents[] = $workflow->jsonSerialize();
        }

        update_option(self::OPTION, $documents, false);
    }
}
