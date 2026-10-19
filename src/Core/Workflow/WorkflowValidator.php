<?php

declare(strict_types=1);

namespace BertanexFlow\Core\Workflow;

use BertanexFlow\Core\Contracts\WorkflowInterface;
use InvalidArgumentException;

/**
 * Validates workflow configurations.
 *
 * @package BertanexFlow\Core\Workflow
 */
final class WorkflowValidator
{
    /**
     * Validate workflow structure.
     *
     * @param WorkflowInterface $workflow Workflow to validate.
     * @return list<array{level: string, message: string}> Validation errors.
     */
    public function validate(WorkflowInterface $workflow): array
    {
        $errors = [];

        // Check version
        if ($workflow->getVersion() < 1) {
            $errors[] = [
                'level' => 'error',
                'message' => 'Workflow version must be at least 1.',
            ];
        }

        // Check required fields
        if (empty($workflow->getId())) {
            $errors[] = [
                'level' => 'error',
                'message' => 'Workflow ID is required.',
            ];
        }

        if (empty($workflow->getName())) {
            $errors[] = [
                'level' => 'error',
                'message' => 'Workflow name is required.',
            ];
        }

        // Validate triggers
        $triggers = $workflow->getTriggers();
        if (empty($triggers)) {
            $errors[] = [
                'level' => 'error',
                'message' => 'Workflow must have at least one trigger.',
            ];
        } else {
            foreach ($triggers as $index => $trigger) {
                if (! isset($trigger['type']) || empty($trigger['type'])) {
                    $errors[] = [
                        'level' => 'error',
                        'message' => sprintf('Trigger #%d is missing type.', $index + 1),
                    ];
                }
            }
        }

        // Validate actions
        $actions = $workflow->getActions();
        if (empty($actions)) {
            $errors[] = [
                'level' => 'error',
                'message' => 'Workflow must have at least one action.',
            ];
        } else {
            foreach ($actions as $index => $action) {
                if (! isset($action['type']) || empty($action['type'])) {
                    $errors[] = [
                        'level' => 'error',
                        'message' => sprintf('Action #%d is missing type.', $index + 1),
                    ];
                }
            }
        }

        // Check for circular references
        $warnings = $this->detectCircularReferences($workflow);
        $errors = array_merge($errors, $warnings);

        return $errors;
    }

    /**
     * Detect circular dependencies in action chains.
     *
     * @param WorkflowInterface $workflow Workflow to check.
     * @return list<array{level: string, message: string}> Security warnings.
     */
    private function detectCircularReferences(WorkflowInterface $workflow): array
    {
        $warnings = [];

        // Simple check: prevent self-referencing actions
        // This is preliminary - full circular detection requires graph analysis
        $topLevelActions = $workflow->getActions();

        foreach ($topLevelActions as $index => $action) {
            $type = $action['type'] ?? '';

            // Check for recursion markers
            if (str_contains($type, 'loop')) {
                $warnings[] = [
                    'level' => 'warning',
                    'message' => 'Workflow contains recursive-like behavior. Review carefully.',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Check if workflow is valid.
     *
     * @param WorkflowInterface $workflow Workflow to check.
     * @return bool
     */
    public function isValid(WorkflowInterface $workflow): bool
    {
        $errors = $this->validate($workflow);

        foreach ($errors as $error) {
            if ($error['level'] === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * Render validation errors as a single message.
     *
     * @param WorkflowInterface $workflow Workflow to validate.
     * @return string|null Human-readable error message or null if valid.
     */
    public function renderErrors(WorkflowInterface $workflow): ?string
    {
        $errors = $this->validate($workflow);

        if (empty($errors)) {
            return null;
        }

        $messages = array_map(
            fn($error) => $error['message'],
            $errors
        );

        return implode("\n", $messages);
    }
}