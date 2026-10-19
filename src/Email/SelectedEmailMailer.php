<?php

declare(strict_types=1);

namespace BertanexFlow\Email;

use BertanexFlow\Admin\EmailTemplateType;
use BertanexFlow\Services\OutboundAction;
use BertanexFlow\Sms\SmsTemplateRenderer;

/**
 * Sends the email template chosen in the Rules settings. WordPress plain mail is the default.
 */
final class SelectedEmailMailer
{
    public function register(): void
    {
        add_action('gro_outbound_send_email', [$this, 'send']);
    }

    public function send(OutboundAction $action): void
    {
        $user = get_userdata($action->userId());
        $to = ($user instanceof \WP_User) ? (string) $user->user_email : '';
        if ($to === '' || ! is_email($to)) {
            do_action('gro_email_missing_recipient', $action);

            return;
        }

        $vars = $this->variables($user, $action->params());
        $renderer = new SmsTemplateRenderer();
        $template = $this->chosenTemplate($action);
        $rendered = $template instanceof \WP_Post
            ? $renderer->render(EmailTemplateType::renderTemplate((string) $template->post_name), $vars)
            : '';

        if (! $template instanceof \WP_Post || $rendered === '') {
            $subject = $this->paramString($action, 'subject', sprintf('[%s] %s', $vars['site_name'], __('Notification', 'bertanex-flow')));
            wp_mail($to, $renderer->render($subject, $vars), $renderer->render($this->plainBody($action), $vars));

            return;
        }

        $subject = (string) get_post_meta($template->ID, EmailTemplateType::SUBJECT_META, true);
        if ($subject === '') {
            $subject = $template->post_title !== '' ? $template->post_title : $template->post_name;
        }
        $htmlType = static fn (): string => 'text/html';
        add_filter('wp_mail_content_type', $htmlType);
        wp_mail($to, $renderer->render($subject, $vars), $rendered);
        remove_filter('wp_mail_content_type', $htmlType);
    }

    private function chosenTemplate(OutboundAction $action): ?\WP_Post
    {
        $fromAction = strtolower($action->templateId());
        if (
            $fromAction !== ''
            && $fromAction !== EmailTemplateType::WORDPRESS_TEMPLATE
            && preg_match('/^[a-z0-9_\-]+$/', $fromAction)
        ) {
            $chosen = $this->find($fromAction);
            if ($chosen instanceof \WP_Post) {
                return $chosen;
            }
        }

        $active = EmailTemplateType::activeSlug();
        if ($active === EmailTemplateType::WORDPRESS_TEMPLATE) {
            return null;
        }

        return $this->find($active);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function variables(\WP_User $user, array $params): array
    {
        $first = (string) get_user_meta($user->ID, 'first_name', true);
        $last = (string) get_user_meta($user->ID, 'last_name', true);
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = (string) $user->display_name;
        }
        $vars = [
            'name' => $name,
            'site_name' => (string) get_bloginfo('name'),
            'site_url' => (string) home_url('/'),
            'shop_name' => (string) get_bloginfo('name'),
            'phone' => '',
            'user_login' => (string) $user->user_login,
            'user_email' => (string) $user->user_email,
            'first_name' => $first,
            'last_name' => $last,
        ];
        foreach ($params as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $vars[$key] = $value === null ? '' : (string) $value;
            }
        }

        return $vars;
    }

    private function plainBody(OutboundAction $action): string
    {
        $message = $this->paramString($action, 'message', '');
        if ($message === '') {
            $message = $this->paramString($action, 'body', __('You have a new notification.', 'bertanex-flow'));
        }

        return "Hi {name},\n\n" . $message . "\n\n{site_name}\n{site_url}\n";
    }

    private function paramString(OutboundAction $action, string $key, string $fallback): string
    {
        $value = $action->params()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function find(string $slug): ?\WP_Post
    {
        $posts = get_posts(
            [
                'post_type' => EmailTemplateType::POST_TYPE,
                'name' => $slug,
                'post_status' => 'publish',
                'posts_per_page' => 1,
            ]
        );
        $post = $posts[0] ?? null;

        return $post instanceof \WP_Post ? $post : null;
    }
}
