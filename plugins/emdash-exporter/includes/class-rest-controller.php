<?php
/**
 * REST API Controller for EmDash Exporter
 * 
 * Exposes endpoints for:
 * - /probe - Site detection and capabilities
 * - /analyze - Full site analysis (post types, taxonomies, meta)
 * - /content - Stream content by post type
 * - /media - Export media items
 * - /taxonomies - Export taxonomy terms
 * - /options - Export site options
 */

defined('ABSPATH') || exit;

class EmDash_Exporter_REST_Controller {
    
    const NAMESPACE = 'emdash/v1';
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // Public probe endpoint (no auth required)
        register_rest_route(self::NAMESPACE, '/probe', [
            'methods' => 'GET',
            'callback' => [$this, 'probe'],
            'permission_callback' => '__return_true',
        ]);
        
        // Protected endpoints (require authentication)
        register_rest_route(self::NAMESPACE, '/analyze', [
            'methods' => 'GET',
            'callback' => [$this, 'analyze'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/content', [
            'methods' => 'GET',
            'callback' => [$this, 'get_content'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'post_type' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'status' => [
                    'type' => 'string',
                    'default' => 'any',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 500,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'include_meta' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/media', [
            'methods' => 'GET',
            'callback' => [$this, 'get_media'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'per_page' => [
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 500,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'include_data' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Include base64-encoded file data (use with caution)',
                ],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/media/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_media_item'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'include_data' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/taxonomies', [
            'methods' => 'GET',
            'callback' => [$this, 'get_taxonomies'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/options', [
            'methods' => 'GET',
            'callback' => [$this, 'get_options'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/menus', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menus'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/comments', [
            'methods' => 'GET',
            'callback' => [$this, 'get_comments'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'per_page' => [
                    'type' => 'integer',
                    'default' => 500,
                    'minimum' => 1,
                    'maximum' => 500,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
            ],
        ]);
        
        // Public: reflects whether the Authorization header reaches PHP.
        // Used by the wizard's loopback health check (some Apache/CGI setups
        // strip the header, which silently breaks Application Passwords).
        register_rest_route(self::NAMESPACE, '/header-check', [
            'methods' => 'GET',
            'callback' => [$this, 'header_check'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Check if user has permission to export.
     *
     * Requires the `export` capability (by default only Administrators). The
     * export exposes all content including drafts, private posts, author
     * emails, and site options -- `edit_posts` (Contributors) is not enough.
     */
    public function check_permission() {
        if (current_user_can('export')) {
            return true;
        }
        
        return new WP_Error(
            'rest_forbidden',
            __('You must be authenticated with export permissions.', 'emdash-exporter'),
            ['status' => rest_authorization_required_code()]
        );
    }
    
    /**
     * Header check endpoint - reports whether an Authorization header arrived
     */
    public function header_check() {
        $header = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            // mod_php can expose the header via the Apache API only,
            // without populating $_SERVER['HTTP_AUTHORIZATION'].
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = $value;
                    break;
                }
            }
        }
        
        return [
            'authorization_header_received' => !empty($header),
        ];
    }
    
    /**
     * Get all navigation menus
     */
    public function get_menus() {
        $exporter = new EmDash_Menu_Exporter();
        return $exporter->get_menus();
    }
    
    /**
     * Get comments (approved + pending), paginated
     */
    public function get_comments($request) {
        $exporter = new EmDash_Comment_Exporter();
        return $exporter->get_comments(
            $request->get_param('per_page'),
            $request->get_param('page')
        );
    }
    
    /**
     * Probe endpoint - returns site info and capabilities
     * No authentication required
     */
    public function probe() {
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_info = [];
        
        foreach ($post_types as $type) {
            if ($type->name === 'attachment') continue;
            
            $count = wp_count_posts($type->name);
            $post_type_info[] = [
                'name' => $type->name,
                'label' => $type->label,
                'count' => (int) $count->publish,
            ];
        }
        
        // Check for ACF
        $has_acf = class_exists('ACF');
        
        // Check for common SEO plugins
        $has_yoast = defined('WPSEO_VERSION');
        $has_rankmath = class_exists('RankMath');
        
        $i18n = EmDash_I18n_Exporter::site_info();
        
        return array_merge($i18n ? ['i18n' => $i18n] : [], [
            'emdash_exporter' => EMDASH_EXPORTER_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'site' => [
                'title' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => get_site_url(),
                'home' => get_home_url(),
                'language' => get_bloginfo('language'),
                'timezone' => wp_timezone_string(),
            ],
            'capabilities' => [
                'application_passwords' => wp_is_application_passwords_available(),
                'acf' => $has_acf,
                'yoast' => $has_yoast,
                'rankmath' => $has_rankmath,
            ],
            'post_types' => $post_type_info,
            'media_count' => (int) wp_count_posts('attachment')->inherit,
            'menu_count' => count(wp_get_nav_menus()),
            'endpoints' => [
                'analyze' => rest_url(self::NAMESPACE . '/analyze'),
                'content' => rest_url(self::NAMESPACE . '/content'),
                'media' => rest_url(self::NAMESPACE . '/media'),
                'taxonomies' => rest_url(self::NAMESPACE . '/taxonomies'),
                'options' => rest_url(self::NAMESPACE . '/options'),
                'menus' => rest_url(self::NAMESPACE . '/menus'),
                'comments' => rest_url(self::NAMESPACE . '/comments'),
            ],
            'auth_instructions' => $this->get_auth_instructions(),
        ]);
    }
    
    /**
     * Full site analysis - requires auth
     */
    public function analyze() {
        $exporter = new EmDash_Content_Exporter();
        return $exporter->analyze();
    }
    
    /**
     * Get content by post type
     */
    public function get_content($request) {
        $exporter = new EmDash_Content_Exporter();
        return $exporter->get_posts(
            $request->get_param('post_type'),
            $request->get_param('status'),
            $request->get_param('per_page'),
            $request->get_param('page'),
            $request->get_param('include_meta')
        );
    }
    
    /**
     * Get media items
     */
    public function get_media($request) {
        $exporter = new EmDash_Media_Exporter();
        return $exporter->get_media(
            $request->get_param('per_page'),
            $request->get_param('page'),
            $request->get_param('include_data')
        );
    }
    
    /**
     * Get single media item with optional data
     */
    public function get_media_item($request) {
        $exporter = new EmDash_Media_Exporter();
        return $exporter->get_media_item(
            $request->get_param('id'),
            $request->get_param('include_data')
        );
    }
    
    /**
     * Get all taxonomies and terms
     */
    public function get_taxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $result = [];
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
            ]);
            
            if (is_wp_error($terms)) {
                continue;
            }
            
            $term_data = [];
            foreach ($terms as $term) {
                $term_data[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'parent' => $term->parent ?: null,
                    'count' => $term->count,
                ];
            }
            
            $result[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'label_singular' => $taxonomy->labels->singular_name ?? $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
                'post_types' => array_values((array) $taxonomy->object_type),
                'terms' => $term_data,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get site options
     */
    public function get_options() {
        // Core options that are useful for migration
        $option_keys = [
            'blogname',
            'blogdescription',
            'siteurl',
            'home',
            'admin_email',
            'timezone_string',
            'gmt_offset',
            'date_format',
            'time_format',
            'permalink_structure',
            'posts_per_page',
            'default_category',
            'default_post_format',
            'show_on_front',
            'page_on_front',
            'page_for_posts',
        ];
        
        $options = [];
        foreach ($option_keys as $key) {
            $options[$key] = get_option($key);
        }
        
        // Site identity media (Customizer logo + site icon), with resolved
        // URLs so the importer can side-load the files.
        $custom_logo = (int) get_theme_mod('custom_logo');
        if ($custom_logo) {
            $logo_url = wp_get_attachment_url($custom_logo);
            if ($logo_url) {
                $options['custom_logo'] = $custom_logo;
                $options['custom_logo_url'] = $logo_url;
            }
        }
        $site_icon = (int) get_option('site_icon');
        if ($site_icon) {
            $icon_url = wp_get_attachment_url($site_icon);
            if ($icon_url) {
                $options['site_icon'] = $site_icon;
                $options['site_icon_url'] = $icon_url;
            }
        }
        
        // Include Yoast settings if available
        if (defined('WPSEO_VERSION')) {
            $yoast_keys = [
                'wpseo_titles',
                'wpseo_social',
            ];
            foreach ($yoast_keys as $key) {
                $value = get_option($key);
                if ($value) {
                    $options[$key] = $value;
                }
            }
        }
        
        return $options;
    }
    
    /**
     * Get authentication instructions
     */
    private function get_auth_instructions() {
        if (wp_is_application_passwords_available()) {
            return [
                'method' => 'application_password',
                'instructions' => 'Go to Users → Your Profile → Application Passwords to create a password for EmDash.',
                'url' => admin_url('profile.php#application-passwords-section'),
            ];
        }
        
        return [
            'method' => 'basic_auth',
            'instructions' => 'Application Passwords are not available. You may need to install a plugin for REST API authentication.',
        ];
    }
}
