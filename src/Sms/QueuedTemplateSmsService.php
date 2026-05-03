<?php

declare(strict_types=1);

namespace GrowthOrchestrator\Sms;

/**
 * Template rendering + rate limit + queue. Actual send happens asynchronously.
 */
final class QueuedTemplateSmsService implements SmsServiceInterface
{
    public function __construct(
        private readonly SmsTemplateRenderer $renderer,
        private readonly SmsTemplateProviderInterface $templates,
        private readonly SmsPhoneResolver $phones,
        private readonly SmsRateLimiter $rateLimiter,
        private readonly SmsJobQueue $queue
    ) {
    }

    public function send(int $userId, string $templateId, array $context = []): void
    {
        $phone = $this->phones->resolve($userId);

        if ($phone === null || $phone === '') {
            do_action('gro_sms_missing_phone', $userId, $templateId, $context);

            return;
        }

        $template = $this->templates->get($templateId);

        if ($template === null || $template === '') {
            do_action('gro_sms_unknown_template', $templateId, $userId, $context);

            return;
        }

        $vars = $this->buildVariables($userId, $context);
        $body = $this->renderer->render($template, $vars);

        if ($body === '') {
            do_action('gro_sms_empty_body', $userId, $templateId, $context);

            return;
        }

        if (! $this->rateLimiter->tryConsume($userId)) {
            do_action('gro_sms_rate_limited', $userId, $templateId, $context);

            return;
        }

        $this->queue->push([
            'user_id'     => $userId,
            'template_id' => $templateId,
            'to'          => $phone,
            'body'        => $body,
            'attempt'     => 1,
            'context'     => $this->trimContext($context),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, string|int|float|bool|null>
     */
    private function buildVariables(int $userId, array $context): array
    {
        $base = [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url('/'),
        ];

        $user = get_userdata($userId);

        if ($user instanceof \WP_User) {
            $base['name']        = $user->display_name;
            $base['user_login']  = $user->user_login;
            $base['user_email']  = $user->user_email;
            $base['first_name']  = (string) get_user_meta($userId, 'first_name', true);
            $base['last_name']   = (string) get_user_meta($userId, 'last_name', true);
        }

        foreach ($context as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $base[$key] = $value;
            }
        }

        /** @var array<string, string|int|float|bool|null> $filtered */
        $filtered = apply_filters('gro_sms_template_variables', $base, $userId, $context);

        return $filtered;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, scalar>
     */
    private function trimContext(array $context): array
    {
        $out = [];

        foreach ($context as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }

            if (is_scalar($v)) {
                $out[$k] = $v;
            }
        }

        /** @var array<string, scalar> */
        return apply_filters('gro_sms_queue_context', $out, $context);
    }
}
