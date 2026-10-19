<?php

declare(strict_types=1);

namespace BertanexFlow\Integrations\WordPress;

use BertanexFlow\Core\Contracts\IntegrationInterface;
use BertanexFlow\Core\Events\GenericEvent;

/**
 * WordPress core lifecycle events.
 */
final class WordPressIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'wordpress';
    }

    public function getName(): string
    {
        return 'WordPress';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onPostStatus'], 10, 3);
        add_action('user_register', [$this, 'onUserRegister']);
        add_action('comment_post', [$this, 'onComment'], 10, 2);
    }

    public function onPostStatus(string $new, string $old, \WP_Post $post): void
    {
        if ($new === $old || $new !== 'publish') {
            return;
        }

        $this->emit('wordpress.post.published', [
            'post' => [
                'id' => (int) $post->ID,
                'type' => $post->post_type,
                'status' => $new,
                'title' => $post->post_title,
            ],
        ]);
    }

    public function onUserRegister(int $userId): void
    {
        $user = get_userdata($userId);
        $this->emit('wordpress.user.registered', [
            'user' => [
                'id' => $userId,
                'email' => $user instanceof \WP_User ? $user->user_email : '',
                'login' => $user instanceof \WP_User ? $user->user_login : '',
            ],
        ]);
    }

    public function onComment(int $commentId, int|string $approved): void
    {
        $comment = get_comment($commentId);
        $this->emit('wordpress.comment.created', [
            'comment' => [
                'id' => $commentId,
                'approved' => (string) $approved,
                'post_id' => $comment instanceof \WP_Comment ? (int) $comment->comment_post_ID : 0,
            ],
        ]);
    }

    public function getEvents(): array
    {
        return [
            'wordpress.post.published' => [
                'name' => 'wordpress.post.published',
                'label' => 'Post Published',
                'description' => 'A post or custom post type was published.',
            ],
            'wordpress.user.registered' => [
                'name' => 'wordpress.user.registered',
                'label' => 'User Registered',
                'description' => 'A user account was created.',
            ],
            'wordpress.comment.created' => [
                'name' => 'wordpress.comment.created',
                'label' => 'Comment Created',
                'description' => 'A comment was submitted.',
            ],
        ];
    }

    public function getActions(): array
    {
        return [
            'log.message' => [
                'name' => 'log.message',
                'label' => 'Log Message',
                'description' => 'Record a message on the execution.',
            ],
            'webhook.request' => [
                'name' => 'webhook.request',
                'label' => 'HTTP Request',
                'description' => 'Send an HTTP request to an allowed URL.',
            ],
            'data.map' => [
                'name' => 'data.map',
                'label' => 'Map Data',
                'description' => 'Copy context values into a mapped payload.',
            ],
        ];
    }

    public function getDataSchema(): array
    {
        return [
            'post.id' => ['type' => 'integer', 'label' => 'Post ID', 'description' => 'Published post ID.'],
            'post.title' => ['type' => 'string', 'label' => 'Post Title', 'description' => 'Published post title.'],
            'user.email' => ['type' => 'email', 'label' => 'User Email', 'description' => 'Registered user email.'],
            'site.url' => ['type' => 'string', 'label' => 'Site URL', 'description' => 'Current site home URL.'],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emit(string $name, array $context): void
    {
        $event = GenericEvent::create($name, $context, 'wordpress');
        $context['event'] = [
            'name' => $event->getName(),
            'id' => $event->getId(),
        ];

        do_action('bertanex_flow_event', $name, $context);
    }
}
