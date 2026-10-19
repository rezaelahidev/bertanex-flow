<?php

declare(strict_types=1);

namespace BertanexFlow\Rule;

/**
 * Resolves {@see UserStateProviderInterface} from {@see 'gro_user_state_provider'} on first use
 * so themes and other consumers can register during {@code after_setup_theme}.
 */
final class FilterUserStateProvider implements UserStateProviderInterface
{
    private ?UserStateProviderInterface $resolved = null;

    /** @inheritDoc */
    public function getStateForUser(int $userId): array
    {
        return $this->resolve()->getStateForUser($userId);
    }

    /** @inheritDoc */
    public function getThemeCompatibility(): ?ThemeCompatibilityInterface
    {
        return $this->resolve()->getThemeCompatibility();
    }

    private function resolve(): UserStateProviderInterface
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /**
         * Register the rule-engine user state provider.
         *
         * @param UserStateProviderInterface|null $provider Return an implementation of {@see UserStateProviderInterface}.
         */
        $provider = apply_filters('gro_user_state_provider', null);

        $this->resolved = $provider instanceof UserStateProviderInterface
            ? $provider
            : new WordPressUserStateProvider();

        return $this->resolved;
    }
}
