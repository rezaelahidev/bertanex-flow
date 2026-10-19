<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * No-op user state provider used when no consumer registers {@see 'gro_user_state_provider'}.
 */
final class NullUserStateProvider implements UserStateProviderInterface
{
    /** @inheritDoc */
    public function getStateForUser(int $userId): array
    {
        /** @phpstan-ignore-next-line */
        return apply_filters('gro_rule_user_state', [], $userId);
    }

    /** @inheritDoc */
    public function getThemeCompatibility(): ?ThemeCompatibilityInterface
    {
        return null;
    }
}
