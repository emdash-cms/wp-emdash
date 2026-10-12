<?php
/**
 * Admin Page
 *
 * Guided 3-step migration wizard under Tools → EmDash Migration:
 *   1. Site check (preflight health checks with actionable fixes)
 *   2. Migration key (one-click application password, packaged as one string)
 *   3. Connect EmDash (paste key there — or deploy a new EmDash site first)
 */

defined('ABSPATH') || exit;

class EmDash_Admin_Page {

    const SLUG = 'emdash-migration';
    // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- link target of the deploy button, nothing is loaded from it.
    const DEPLOY_URL = 'https://deploy.workers.cloudflare.com/?url=https://github.com/emdash-cms/templates/tree/main/blog-cloudflare';
    const TEMPLATES_URL = 'https://github.com/emdash-cms/templates';

    public function register() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_emdash_generate_key', [$this, 'ajax_generate_key']);
        add_action('wp_ajax_emdash_revoke_key', [$this, 'ajax_revoke_key']);
        add_action('admin_notices', [$this, 'activation_pointer']);
        add_filter('plugin_action_links_' . plugin_basename(EMDASH_EXPORTER_PATH . 'emdash-exporter.php'), [$this, 'action_links']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
    }

    public function add_menu() {
        add_management_page(
            __('EmDash Migration', 'emdash-exporter'),
            __('EmDash Migration', 'emdash-exporter'),
            'export',
            self::SLUG,
            [$this, 'render']
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'tools_page_' . self::SLUG) {
            return;
        }

        // Keep the wizard free of unrelated admin noise (Migrate Guru pattern).
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        $base = plugin_dir_url(EMDASH_EXPORTER_PATH . 'emdash-exporter.php');
        wp_enqueue_style('emdash-wizard', $base . 'assets/wizard.css', [], EMDASH_EXPORTER_VERSION);
        wp_enqueue_script('emdash-wizard', $base . 'assets/wizard.js', [], EMDASH_EXPORTER_VERSION, true);
        wp_localize_script('emdash-wizard', 'emdashWizard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('emdash_wizard'),
            'i18n' => [
                'copied' => __('Copied!', 'emdash-exporter'),
                'copy' => __('Copy key', 'emdash-exporter'),
                'generating' => __('Generating…', 'emdash-exporter'),
                'error' => __('Something went wrong. Please try again.', 'emdash-exporter'),
                'revokeConfirm' => __('Revoke the migration key? EmDash will no longer be able to connect until you generate a new one.', 'emdash-exporter'),
            ],
        ]);
    }

    /**
     * Plugins-page action link to the wizard (replaces the old raw-JSON "Test API" link).
     */
    public function action_links($links) {
        array_unshift($links, sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('tools.php?page=' . self::SLUG)),
            esc_html__('Start migration', 'emdash-exporter')
        ));
        return $links;
    }

    /**
     * One-time redirect into the wizard right after activation.
     */
    public function maybe_redirect_after_activation() {
        if (!get_transient('emdash_exporter_activated')) {
            return;
        }
        delete_transient('emdash_exporter_activated');
        if (wp_doing_ajax() || !current_user_can('export')) {
            return;
        }
        wp_safe_redirect(admin_url('tools.php?page=' . self::SLUG));
        exit;
    }

    /**
     * Small pointer on the plugins screen only (replaces the old always-on notice).
     */
    public function activation_pointer() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'plugins' || !current_user_can('export')) {
            return;
        }
        printf(
            '<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('EmDash Exporter:', 'emdash-exporter'),
            esc_html__('Ready to move this site to EmDash?', 'emdash-exporter'),
            esc_url(admin_url('tools.php?page=' . self::SLUG)),
            esc_html__('Start the migration wizard', 'emdash-exporter')
        );
    }

    public function ajax_generate_key() {
        check_ajax_referer('emdash_wizard', 'nonce');
        if (!current_user_can('export')) {
            wp_send_json_error(['message' => __('You need export permissions to generate a migration key.', 'emdash-exporter')], 403);
        }

        $result = EmDash_Migration_Key::generate();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success($result);
    }

    public function ajax_revoke_key() {
        check_ajax_referer('emdash_wizard', 'nonce');
        if (!current_user_can('export')) {
            wp_send_json_error(['message' => __('You need export permissions.', 'emdash-exporter')], 403);
        }
        EmDash_Migration_Key::revoke();
        wp_send_json_success();
    }

    public function render() {
        $health = new EmDash_Health_Check();
        $checks = $health->run();
        $checks_ok = EmDash_Health_Check::all_ok($checks);
        $key_exists = EmDash_Migration_Key::exists();
        $overview = $this->content_overview();

        include EMDASH_EXPORTER_PATH . 'includes/views/wizard.php';
    }

    /**
     * Honest "what gets migrated" overview data.
     */
    private function content_overview() {
        $post_types = get_post_types(['public' => true], 'objects');
        $types = [];
        foreach ($post_types as $type) {
            if ($type->name === 'attachment') {
                continue;
            }
            $counts = wp_count_posts($type->name);
            $total = 0;
            foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
                $total += isset($counts->$status) ? (int) $counts->$status : 0;
            }
            if ($total > 0) {
                $types[] = ['label' => $type->label, 'count' => $total];
            }
        }

        $taxonomies = [];
        foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
            $count = wp_count_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);
            if (!is_wp_error($count) && (int) $count > 0) {
                $taxonomies[] = ['label' => $taxonomy->label, 'count' => (int) $count];
            }
        }

        $non_public = array_diff(
            get_post_types(['public' => false]),
            // WP-internal types that nobody expects to migrate
            ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'attachment']
        );

        $i18n = EmDash_I18n_Exporter::site_info();

        return [
            'types' => $types,
            'taxonomies' => $taxonomies,
            'media_count' => (int) wp_count_posts('attachment')->inherit,
            'menu_count' => count(wp_get_nav_menus()),
            'comment_count' => EmDash_Comment_Exporter::count(),
            'acf' => class_exists('ACF'),
            'yoast' => defined('WPSEO_VERSION'),
            'rankmath' => class_exists('RankMath'),
            'i18n' => $i18n,
            'non_public_types' => array_values($non_public),
            'page_builder' => $this->detect_page_builder(),
        ];
    }

    /**
     * Detect page builders whose layouts cannot be converted 1:1.
     */
    private function detect_page_builder() {
        $builders = [];
        if (defined('ELEMENTOR_VERSION')) {
            $builders[] = 'Elementor';
        }
        if (defined('ET_BUILDER_VERSION') || function_exists('et_setup_theme')) {
            $builders[] = 'Divi';
        }
        if (defined('WPB_VC_VERSION')) {
            $builders[] = 'WPBakery';
        }
        if (class_exists('FLBuilder')) {
            $builders[] = 'Beaver Builder';
        }
        if (defined('BRICKS_VERSION')) {
            $builders[] = 'Bricks';
        }
        return $builders;
    }
}
