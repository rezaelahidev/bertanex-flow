<?php

declare(strict_types=1);

namespace BertanexFlow\Admin;

use BertanexFlow\Admin\Controllers\RulesController;
use BertanexFlow\Admin\Models\EmailTemplatesModel;

/**
 * Email templates as Site Editor block templates backed by gro_email posts.
 */
final class EmailTemplateType
{
    public const POST_TYPE = 'gro_email';

    public const SUBJECT_META = '_gro_email_subject';

    public const MIGRATED_OPTION = 'bertanex_flow_email_templates_migrated';

    public const STARTER_SLUG = 'email';

    public const WORDPRESS_TEMPLATE = 'wordpress';

    public const ACTIVE_OPTION = 'bertanex_flow_active_email_template';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerMeta']);
        add_action('init', [$this, 'migrateOptionRows'], 15);
        add_action('init', [$this, 'registerBlockTemplates'], 20);
        add_action('admin_post_gro_add_email', [$this, 'handleAdd']);
        add_action('admin_post_gro_delete_email', [$this, 'handleDelete']);
        add_action('save_post_wp_template', [$this, 'syncFromSiteEditor'], 20, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueSidebar']);
        add_action('admin_enqueue_scripts', [$this, 'enqueuePicker']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
        (new \BertanexFlow\Email\SelectedEmailMailer())->register();
    }

    public function registerPostType(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name'          => __('Email templates', 'bertanex-flow'),
                    'singular_name' => __('Email template', 'bertanex-flow'),
                ],
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'show_in_rest'        => true,
                'rest_base'           => self::POST_TYPE,
                'supports'            => ['title', 'editor', 'excerpt', 'custom-fields'],
                'capability_type'     => 'gro_email',
                'capabilities'        => [
                    'edit_posts'             => 'manage_options',
                    'edit_others_posts'      => 'manage_options',
                    'publish_posts'          => 'manage_options',
                    'read_private_posts'     => 'manage_options',
                    'delete_posts'           => 'manage_options',
                    'delete_private_posts'   => 'manage_options',
                    'delete_published_posts' => 'manage_options',
                    'delete_others_posts'    => 'manage_options',
                    'edit_private_posts'     => 'manage_options',
                    'edit_published_posts'   => 'manage_options',
                    'create_posts'           => 'manage_options',
                ],
                'map_meta_cap'        => true,
                'rewrite'             => false,
                'query_var'           => false,
            ]
        );
    }

    public function registerMeta(): void
    {
        register_post_meta(
            self::POST_TYPE,
            self::SUBJECT_META,
            [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static fn (): bool => current_user_can('manage_options'),
            ]
        );
    }

    public function migrateOptionRows(): void
    {
        if (get_option(self::MIGRATED_OPTION)) {
            return;
        }

        $rows = get_option(EmailTemplatesModel::OPTION, []);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) ? sanitize_title((string) $row['id']) : '';
                if ($id === '' || ! preg_match('/^[a-z0-9_\-]+$/', $id)) {
                    continue;
                }
                $slug = 'email-' . $id;
                if ($this->findBySlug($slug) instanceof \WP_Post) {
                    continue;
                }
                $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : $id;
                $subject = isset($row['subject']) ? sanitize_text_field((string) $row['subject']) : '';
                $body = isset($row['body']) ? (string) $row['body'] : '';
                wp_insert_post(
                    [
                        'post_type'    => self::POST_TYPE,
                        'post_status'  => 'publish',
                        'post_title'   => $label !== '' ? $label : $id,
                        'post_name'    => $slug,
                        'post_content' => self::blocksFromBody($body),
                        'meta_input'   => [
                            self::SUBJECT_META => $subject,
                        ],
                    ],
                    true
                );
            }
        }

        update_option(self::MIGRATED_OPTION, '1', false);
    }

    public function registerBlockTemplates(): void
    {
        if (! function_exists('register_block_template')) {
            return;
        }

        register_block_template(
            'bertanex-flow//' . self::STARTER_SLUG,
            [
                'title'       => __('Email template', 'bertanex-flow'),
                'description' => __('A message about 600px wide. Placeholders use {name} tokens.', 'bertanex-flow'),
                'content'     => self::starterContent(),
                'plugin'      => 'bertanex-flow',
            ]
        );

        foreach ($this->publishedTemplates() as $post) {
            $slug = (string) $post->post_name;
            if (! preg_match('/^[a-z0-9_\-]+$/', $slug)) {
                continue;
            }
            register_block_template(
                'bertanex-flow//' . $slug,
                [
                    'title'       => $post->post_title !== '' ? $post->post_title : $slug,
                    'description' => __('Email message designed in the Site Editor.', 'bertanex-flow'),
                    'content'     => (string) $post->post_content,
                    'plugin'      => 'bertanex-flow',
                ]
            );
        }
    }

    public function handleAdd(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to add email templates.', 'bertanex-flow'));
        }
        check_admin_referer('gro_add_email');

        $title = isset($_POST['gro_email_title']) ? sanitize_text_field(wp_unslash((string) $_POST['gro_email_title'])) : '';
        $created = $this->createTemplate($title);
        if (is_wp_error($created)) {
            wp_die(esc_html($created->get_error_message()));
        }

        wp_safe_redirect(self::editorUrl((string) get_post_field('post_name', $created)));
        exit;
    }

    public function handleDelete(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete email templates.', 'bertanex-flow'));
        }
        $postId = isset($_POST['gro_email_id']) ? (int) $_POST['gro_email_id'] : 0;
        check_admin_referer('gro_delete_email_' . $postId);

        $post = get_post($postId);
        if ($post instanceof \WP_Post && $post->post_type === self::POST_TYPE) {
            $slug = (string) $post->post_name;
            wp_delete_post($postId, true);
            $customized = get_posts(
                [
                    'post_type'      => 'wp_template',
                    'name'           => $slug,
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ]
            );
            foreach ($customized as $templateId) {
                wp_delete_post((int) $templateId, true);
            }
            if (self::activeSlug() === $slug) {
                update_option(self::ACTIVE_OPTION, self::WORDPRESS_TEMPLATE, false);
            }
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => RulesController::PAGE_SLUG,
                    'gro_open_tab' => 'email',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * @param int $postId
     */
    public function syncFromSiteEditor(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || $post->post_status === 'auto-draft') {
            return;
        }
        $slug = (string) $post->post_name;
        if (! str_starts_with($slug, 'email-')) {
            return;
        }
        $email = $this->findBySlug($slug);
        if (! $email instanceof \WP_Post) {
            return;
        }
        if ($email->post_content === $post->post_content && $email->post_title === $post->post_title) {
            return;
        }

        remove_action('save_post_wp_template', [$this, 'syncFromSiteEditor'], 20);
        wp_update_post(
            wp_slash(
                [
                    'ID'           => $email->ID,
                    'post_content' => $post->post_content,
                    'post_title'   => $post->post_title,
                ]
            )
        );
        add_action('save_post_wp_template', [$this, 'syncFromSiteEditor'], 20, 2);
    }

    public function enqueueSidebar(): void
    {
        if (! function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (! $screen || $screen->base !== 'site-editor') {
            return;
        }

        $templates = [];
        foreach ($this->publishedTemplates() as $post) {
            $templates[(string) $post->post_name] = (int) $post->ID;
        }

        $handle = 'gro-email-template-sidebar';
        wp_enqueue_script(
            $handle,
            plugins_url('assets/admin/email-template-sidebar.js', BERTANEX_FLOW_PLUGIN_FILE),
            ['wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-api-fetch', 'wp-i18n'],
            BERTANEX_FLOW_VERSION,
            true
        );
        wp_localize_script(
            $handle,
            'groEmailEditor',
            [
                'templates' => $templates,
                'variables' => EmailTemplatesModel::variableNames(),
                'restBase'  => self::POST_TYPE,
                'subjectKey'=> self::SUBJECT_META,
            ]
        );
    }

    public function renderDirectory(): void
    {
        echo '<div id="gro-email-picker" class="gro-email-picker"></div>';
    }

    public function enqueuePicker(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . RulesController::PAGE_SLUG) {
            return;
        }

        $handle = 'gro-email-template-picker';
        wp_enqueue_style('wp-components');
        wp_enqueue_script(
            $handle,
            plugins_url('assets/admin/email-template-picker.js', BERTANEX_FLOW_PLUGIN_FILE),
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            (string) filemtime(BERTANEX_FLOW_PLUGIN_DIR . 'assets/admin/email-template-picker.js'),
            true
        );
        wp_localize_script($handle, 'groEmailPicker', $this->pickerConfig());
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'bertanex-flow/v1',
            '/email-templates',
            [
                [
                    'methods' => 'GET',
                    'callback' => fn (): \WP_REST_Response => new \WP_REST_Response($this->pickerConfig()),
                    'permission_callback' => [$this, 'canManage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => function (\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
                        $created = $this->createTemplate((string) $request->get_param('title'));
                        if (is_wp_error($created)) {
                            return $created;
                        }
                        $config = $this->pickerConfig();
                        $config['designUrl'] = self::editorUrl((string) get_post_field('post_name', $created));

                        return new \WP_REST_Response($config);
                    },
                    'permission_callback' => [$this, 'canManage'],
                ],
            ]
        );

        register_rest_route(
            'bertanex-flow/v1',
            '/email-templates/(?P<id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
                    $post = get_post((int) $request->get_param('id'));
                    if (! $post instanceof \WP_Post || $post->post_type !== self::POST_TYPE) {
                        return new \WP_Error('gro_email_missing', __('Email template not found.', 'bertanex-flow'), ['status' => 404]);
                    }
                    $slug = (string) $post->post_name;
                    wp_delete_post($post->ID, true);
                    $customized = get_posts(
                        [
                            'post_type' => 'wp_template',
                            'name' => $slug,
                            'post_status' => 'any',
                            'posts_per_page' => 1,
                            'fields' => 'ids',
                        ]
                    );
                    foreach ($customized as $templateId) {
                        wp_delete_post((int) $templateId, true);
                    }
                    if (self::activeSlug() === $slug) {
                        update_option(self::ACTIVE_OPTION, self::WORDPRESS_TEMPLATE, false);
                    }

                    return new \WP_REST_Response($this->pickerConfig());
                },
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'bertanex-flow/v1',
            '/email-templates/active',
            [
                'methods' => 'POST',
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
                    $id = strtolower((string) $request->get_param('id'));
                    if (! preg_match('/^[a-z0-9_\-]+$/', $id)) {
                        return new \WP_Error('gro_email_unknown', __('Choose the WordPress default or a template from this list.', 'bertanex-flow'), ['status' => 400]);
                    }
                    if ($id !== self::WORDPRESS_TEMPLATE && ! $this->findBySlug($id) instanceof \WP_Post) {
                        return new \WP_Error('gro_email_unknown', __('Choose the WordPress default or a template from this list.', 'bertanex-flow'), ['status' => 400]);
                    }
                    update_option(self::ACTIVE_OPTION, $id === '' ? self::WORDPRESS_TEMPLATE : $id, false);

                    return new \WP_REST_Response($this->pickerConfig());
                },
                'permission_callback' => [$this, 'canManage'],
            ]
        );
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function activeSlug(): string
    {
        $stored = get_option(self::ACTIVE_OPTION, self::WORDPRESS_TEMPLATE);
        $slug = is_string($stored) && $stored !== '' ? $stored : self::WORDPRESS_TEMPLATE;
        if ($slug === self::WORDPRESS_TEMPLATE) {
            return self::WORDPRESS_TEMPLATE;
        }
        $posts = get_posts(
            [
                'post_type' => self::POST_TYPE,
                'name' => $slug,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]
        );

        return $posts === [] ? self::WORDPRESS_TEMPLATE : $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function pickerConfig(): array
    {
        $templates = [
            [
                'id' => self::WORDPRESS_TEMPLATE,
                'title' => __('WordPress default', 'bertanex-flow'),
                'subject' => sprintf('[%s] %s', get_bloginfo('name'), __('Notification', 'bertanex-flow')),
                'previewHtml' => $this->wordpressPreviewHtml(),
                'designUrl' => '',
                'postId' => 0,
            ],
        ];
        $renderer = new \BertanexFlow\Sms\SmsTemplateRenderer();
        $samples = $this->previewSamples();
        foreach ($this->publishedTemplates() as $post) {
            $subject = (string) get_post_meta($post->ID, self::SUBJECT_META, true);
            $templates[] = [
                'id' => (string) $post->post_name,
                'title' => $post->post_title !== '' ? $post->post_title : (string) $post->post_name,
                'subject' => $renderer->render($subject !== '' ? $subject : (string) $post->post_title, $samples),
                'previewHtml' => $renderer->render(do_blocks((string) $post->post_content), $samples),
                'designUrl' => self::editorUrl((string) $post->post_name),
                'postId' => (int) $post->ID,
            ];
        }

        return [
            'active' => self::activeSlug(),
            'templates' => $templates,
            'routes' => [
                'active' => '/bertanex-flow/v1/email-templates/active',
                'create' => '/bertanex-flow/v1/email-templates',
                'remove' => '/bertanex-flow/v1/email-templates',
            ],
            'labels' => [
                'heading' => __('Email templates', 'bertanex-flow'),
                'intro' => __('Flow sends the WordPress default email until you choose a designed template. Click a card to use that layout.', 'bertanex-flow'),
                'add' => __('Add email template', 'bertanex-flow'),
                'use' => __('Use this template', 'bertanex-flow'),
                'inUse' => __('In use', 'bertanex-flow'),
                'selected' => __('Selected', 'bertanex-flow'),
                'design' => __('Design', 'bertanex-flow'),
                'delete' => __('Delete', 'bertanex-flow'),
                'confirmDelete' => __('Delete this email template?', 'bertanex-flow'),
                'saveError' => __('Could not save the template choice.', 'bertanex-flow'),
                'addError' => __('Could not add the template.', 'bertanex-flow'),
                'deleteError' => __('Could not delete the template.', 'bertanex-flow'),
            ],
        ];
    }

    /**
     * @return int|\WP_Error
     */
    private function createTemplate(string $title): int|\WP_Error
    {
        $title = sanitize_text_field($title);
        if ($title === '') {
            $title = __('Email template', 'bertanex-flow');
        }
        $base = sanitize_title($title);
        if ($base === '' || $base === 'email') {
            $base = 'message';
        }
        $slug = 'email-' . $base;
        $suffix = 2;
        while ($this->findBySlug($slug) instanceof \WP_Post) {
            $slug = 'email-' . $base . '-' . $suffix;
            $suffix++;
        }

        $created = wp_insert_post(
            [
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => self::starterContent(),
                'meta_input' => [
                    self::SUBJECT_META => '',
                ],
            ],
            true
        );

        return is_int($created) ? $created : $created;
    }

    private function wordpressPreviewHtml(): string
    {
        $samples = $this->previewSamples();

        return '<div style="padding:16px">'
            . '<p style="margin:0 0 12px">Hi ' . esc_html($samples['name']) . ',</p>'
            . '<p style="margin:0 0 12px">' . esc_html__('You have a new notification.', 'bertanex-flow') . '</p>'
            . '<p style="margin:0">' . esc_html($samples['site_name']) . '<br>' . esc_html($samples['site_url']) . '</p>'
            . '</div>';
    }

    /**
     * @return array<string, string>
     */
    private function previewSamples(): array
    {
        $samples = [
            'name' => 'Sara Rahimi',
            'site_name' => (string) get_bloginfo('name'),
            'site_url' => (string) home_url('/'),
            'shop_name' => (string) get_bloginfo('name'),
            'phone' => '09120000000',
            'user_login' => 'sara',
            'user_email' => 'sara@example.com',
            'first_name' => 'Sara',
            'last_name' => 'Rahimi',
        ];
        $filtered = apply_filters('gro_email_template_sample_values', $samples);

        if (! is_array($filtered)) {
            return $samples;
        }
        $out = [];
        foreach ($filtered as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $out[$key] = $value === null ? '' : (string) $value;
            }
        }

        return $out === [] ? $samples : $out;
    }

    public static function editorUrl(string $templateSlug): string
    {
        $encoded = rawurlencode(get_stylesheet() . '//' . $templateSlug);

        return admin_url('site-editor.php?p=/wp_template/' . $encoded . '&canvas=edit');
    }

    public static function starterContent(): string
    {
        $copy = __('Write the message here. Placeholders such as {name} and {site_name} are replaced when the email is sent.', 'bertanex-flow');

        return '<!-- wp:group {"layout":{"type":"constrained","contentSize":"600px"}} -->'
            . '<div class="wp-block-group">'
            . '<!-- wp:paragraph --><p>' . esc_html($copy) . '</p><!-- /wp:paragraph -->'
            . '</div><!-- /wp:group -->';
    }

    /**
     * @return list<\WP_Post>
     */
    private function publishedTemplates(): array
    {
        $posts = get_posts(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        return array_values(array_filter($posts, static fn ($post): bool => $post instanceof \WP_Post));
    }

    private function findBySlug(string $slug): ?\WP_Post
    {
        $posts = get_posts(
            [
                'post_type'      => self::POST_TYPE,
                'name'           => $slug,
                'post_status'    => 'any',
                'posts_per_page' => 1,
            ]
        );
        $post = $posts[0] ?? null;

        return $post instanceof \WP_Post ? $post : null;
    }

    private static function blocksFromBody(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return self::starterContent();
        }
        if (str_contains($body, '<!-- wp:')) {
            return $body;
        }
        $blocks = '';
        foreach (preg_split("/\r\n|\r|\n/", $body) ?: [] as $line) {
            $blocks .= "<!-- wp:paragraph -->\n<p>" . esc_html($line) . "</p>\n<!-- /wp:paragraph -->\n";
        }

        return $blocks;
    }
}
