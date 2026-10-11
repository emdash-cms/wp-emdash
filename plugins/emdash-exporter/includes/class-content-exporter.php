<?php
/**
 * Content Exporter
 * 
 * Handles exporting posts and pages with full metadata
 */

defined('ABSPATH') || exit;

class EmDash_Content_Exporter {
    
    /**
     * Analyze the site for import planning
     */
    public function analyze() {
        $post_types = get_post_types(['public' => true], 'objects');
        $result = [
            'site' => [
                'title' => get_bloginfo('name'),
                'url' => get_site_url(),
            ],
            'post_types' => [],
            'taxonomies' => [],
            'custom_fields' => [],
            'authors' => [],
            'attachments' => [
                'count' => 0,
                'by_type' => [],
            ],
        ];
        
        // Analyze post types
        foreach ($post_types as $type) {
            $counts = wp_count_posts($type->name);
            $total = 0;
            $by_status = [];
            
            foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
                $count = isset($counts->$status) ? (int) $counts->$status : 0;
                $by_status[$status] = $count;
                $total += $count;
            }
            
            if ($type->name === 'attachment') {
                // Attachments use 'inherit' status, not the standard statuses
                $attachment_counts = wp_count_posts('attachment');
                $result['attachments']['count'] = (int) ($attachment_counts->inherit ?? 0);
                $result['attachments']['by_type'] = $this->count_media_by_type();
                continue;
            }
            
            if ($total === 0) continue;
            
            // Analyze custom fields for this post type
            $custom_fields = $this->analyze_custom_fields($type->name);
            
            $result['post_types'][] = [
                'name' => $type->name,
                'label' => $type->label,
                'label_singular' => $type->labels->singular_name,
                'total' => $total,
                'by_status' => $by_status,
                'supports' => get_all_post_type_supports($type->name),
                'taxonomies' => get_object_taxonomies($type->name),
                'custom_fields' => $custom_fields,
                'hierarchical' => $type->hierarchical,
                'has_archive' => $type->has_archive,
            ];
        }
        
        // Analyze taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        foreach ($taxonomies as $taxonomy) {
            $term_count = wp_count_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);
            if (is_wp_error($term_count)) {
                $term_count = 0;
            }
            
            $result['taxonomies'][] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
                'term_count' => (int) $term_count,
                'object_types' => $taxonomy->object_type,
            ];
        }
        
        // Get authors
        $authors = get_users(['who' => 'authors']);
        foreach ($authors as $author) {
            $result['authors'][] = [
                'id' => $author->ID,
                'login' => $author->user_login,
                'email' => $author->user_email,
                'display_name' => $author->display_name,
                'post_count' => count_user_posts($author->ID),
            ];
        }
        
        // ACF field groups if available
        if (class_exists('ACF')) {
            $result['acf'] = $this->analyze_acf_fields();
        }
        
        // Multilingual plugin info (WPML/Polylang) if available
        $i18n = EmDash_I18n_Exporter::site_info();
        if ($i18n) {
            $result['i18n'] = $i18n;
        }
        
        return $result;
    }
    
    /**
     * Get posts with full data
     */
    public function get_posts($post_type, $status = 'any', $per_page = 100, $page = 1, $include_meta = true) {
        $args = [
            'post_type' => $post_type,
            'post_status' => $status,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        $query = new WP_Query($args);
        $posts = [];
        
        foreach ($query->posts as $post) {
            $posts[] = $this->format_post($post, $include_meta);
        }
        
        return [
            'items' => $posts,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
    
    /**
     * Format a single post for export
     */
    private function format_post($post, $include_meta = true) {
        $data = [
            'id' => $post->ID,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'date' => $post->post_date,
            'date_gmt' => $post->post_date_gmt,
            'modified' => $post->post_modified,
            'modified_gmt' => $post->post_modified_gmt,
            'author' => $this->get_author_info($post->post_author),
            'parent' => $post->post_parent ?: null,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'guid' => $post->guid,
            // The real public URL, so the importer can build a redirect map
            // that preserves SEO when the URL structure changes.
            'permalink' => get_permalink($post),
        ];
        
        // Locale + translation group from WPML/Polylang, when active
        $i18n = EmDash_I18n_Exporter::post_info($post->ID);
        if (!empty($i18n)) {
            $data = array_merge($data, $i18n);
        }
        
        // Taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        $data['taxonomies'] = [];
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy);
            if (!is_wp_error($terms) && !empty($terms)) {
                $data['taxonomies'][$taxonomy] = array_map(function($term) {
                    return [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }, $terms);
            }
        }
        
        // Featured image
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $data['featured_image'] = $this->get_attachment_info($thumbnail_id);
        }
        
        // Meta fields
        if ($include_meta) {
            $data['meta'] = $this->get_post_meta_clean($post->ID);
            
            // ACF fields if available
            if (function_exists('get_fields')) {
                $acf_fields = get_fields($post->ID);
                if ($acf_fields) {
                    $data['acf'] = $this->format_acf_fields($acf_fields);
                }
            }
            
            // Yoast SEO data
            if (defined('WPSEO_VERSION')) {
                $data['yoast'] = $this->get_yoast_meta($post->ID);
            }
            
            // Rank Math data
            if (class_exists('RankMath')) {
                $data['rankmath'] = $this->get_rankmath_meta($post->ID);
            }
        }
        
        return $data;
    }
    
    /**
     * Get author info
     */
    private function get_author_info($author_id) {
        $author = get_user_by('ID', $author_id);
        if (!$author) return null;
        
        return [
            'id' => $author->ID,
            'login' => $author->user_login,
            'email' => $author->user_email,
            'display_name' => $author->display_name,
        ];
    }
    
    /**
     * Get attachment info for a media item
     */
    private function get_attachment_info($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) return null;
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        $url = wp_get_attachment_url($attachment_id);
        
        return [
            'id' => $attachment_id,
            'url' => $url,
            'filename' => basename($url),
            'mime_type' => $attachment->post_mime_type,
            'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'title' => $attachment->post_title,
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
        ];
    }
    
    /**
     * Get post meta, filtering out internal WordPress keys
     */
    private function get_post_meta_clean($post_id) {
        $all_meta = get_post_meta($post_id);
        $clean_meta = [];
        
        // Keys to skip (internal WordPress)
        $skip_prefixes = ['_edit_', '_wp_', '_pingme', '_encloseme'];
        $skip_keys = ['_edit_last', '_edit_lock'];
        
        foreach ($all_meta as $key => $values) {
            // Skip internal keys
            if (in_array($key, $skip_keys)) continue;
            
            $skip = false;
            foreach ($skip_prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            // Keep Yoast and other useful prefixes
            // Single values are unwrapped
            $clean_meta[$key] = count($values) === 1 ? $values[0] : $values;
            
            // Decode PHP-serialized data. allowed_classes=false prevents
            // object injection from untrusted database values.
            if (is_string($clean_meta[$key]) && is_serialized($clean_meta[$key])) {
                $unserialized = @unserialize($clean_meta[$key], ['allowed_classes' => false]);
                if ($unserialized !== false) {
                    $clean_meta[$key] = $unserialized;
                }
            }
        }
        
        return $clean_meta;
    }
    
    /**
     * Analyze custom fields used by a post type
     */
    private function analyze_custom_fields($post_type) {
        global $wpdb;
        
        // Get most common meta keys for this post type
        $query = $wpdb->prepare("
            SELECT pm.meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\\_%%'
            GROUP BY pm.meta_key
            HAVING count > 1
            ORDER BY count DESC
            LIMIT 50
        ", $post_type);
        
        $results = $wpdb->get_results($query);
        
        $fields = [];
        foreach ($results as $row) {
            // Get a sample value to infer type
            $sample = $wpdb->get_var($wpdb->prepare("
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value != ''
                LIMIT 1
            ", $post_type, $row->meta_key));
            
            $fields[] = [
                'key' => $row->meta_key,
                'count' => (int) $row->count,
                'inferred_type' => $this->infer_field_type($row->meta_key, $sample),
                'sample' => $sample ? mb_substr($sample, 0, 200) : null,
            ];
        }
        
        return $fields;
    }
    
    /**
     * Infer field type from key name and sample value
     */
    private function infer_field_type($key, $sample) {
        // Check for common patterns in key name
        if (preg_match('/_(id|image|thumbnail|media|photo|attachment)$/', $key)) {
            return 'reference';
        }
        if (preg_match('/_(date|time)$/', $key)) {
            return 'datetime';
        }
        if (preg_match('/_(count|number|price|amount)$/', $key)) {
            return 'number';
        }
        if (preg_match('/_(enabled|active|featured)$/', $key)) {
            return 'boolean';
        }
        
        if (!$sample) return 'string';
        
        // Check sample value
        if (is_serialized($sample)) {
            return 'json';
        }
        if (is_numeric($sample)) {
            return strpos($sample, '.') !== false ? 'number' : 'integer';
        }
        if (in_array($sample, ['0', '1', 'true', 'false', 'yes', 'no'])) {
            return 'boolean';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $sample)) {
            return 'datetime';
        }
        if (strlen($sample) > 500) {
            return 'text';
        }
        
        return 'string';
    }
    
    /**
     * Count media items by MIME type
     */
    private function count_media_by_type() {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT 
                SUBSTRING_INDEX(post_mime_type, '/', 1) as type,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            GROUP BY type
        ");
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row->type] = (int) $row->count;
        }
        
        return $counts;
    }
    
    /**
     * Analyze ACF field groups
     */
    private function analyze_acf_fields() {
        if (!function_exists('acf_get_field_groups')) {
            return null;
        }
        
        $field_groups = acf_get_field_groups();
        $result = [];
        
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);
            
            $field_info = [];
            if ($fields) {
                foreach ($fields as $field) {
                    $field_info[] = [
                        'key' => $field['key'],
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'required' => !empty($field['required']),
                    ];
                }
            }
            
            $result[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'location' => $group['location'],
                'fields' => $field_info,
            ];
        }
        
        return $result;
    }
    
    /**
     * Format ACF fields for export
     */
    private function format_acf_fields($fields) {
        if (!is_array($fields)) return $fields;
        
        $formatted = [];
        foreach ($fields as $key => $value) {
            // Relationship, post object, user and taxonomy fields set to the
            // object return format give WP_Post, WP_User and WP_Term objects
            if (is_object($value)) {
                $formatted[$key] = $this->format_acf_object($value);
            }
            // User fields on the default array format, one user or several
            elseif ($this->is_acf_user_array($value)) {
                $formatted[$key] = $value['ID'];
            }
            elseif (is_array($value) && isset($value[0]) && $this->is_acf_user_array($value[0])) {
                $formatted[$key] = array_column($value, 'ID');
            }
            // Handle image fields (return ID or array)
            elseif (is_array($value) && isset($value['ID'])) {
                $formatted[$key] = [
                    'type' => 'image',
                    'id' => $value['ID'],
                    'url' => $value['url'] ?? null,
                    'alt' => $value['alt'] ?? null,
                ];
            }
            // Handle repeater/flexible content
            elseif (is_array($value) && isset($value[0]) && is_array($value[0])) {
                $formatted[$key] = array_map([$this, 'format_acf_fields'], $value);
            }
            // Handle other arrays
            elseif (is_array($value)) {
                $formatted[$key] = $this->format_acf_fields($value);
            }
            else {
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Reduce a post, user or term object to its ID, so the export never
     * carries a related post's password or a user's password hash
     */
    private function format_acf_object($value) {
        if ($value instanceof WP_Post || $value instanceof WP_User) {
            return $value->ID;
        }
        if ($value instanceof WP_Term) {
            return $value->term_id;
        }
        return $value;
    }
    
    /**
     * Whether a value is one user from an ACF user field on the array format
     */
    private function is_acf_user_array($value) {
        return is_array($value) && isset($value['ID'], $value['user_email']);
    }
    
    /**
     * Get Yoast SEO meta
     */
    private function get_yoast_meta($post_id) {
        return [
            'title' => get_post_meta($post_id, '_yoast_wpseo_title', true),
            'description' => get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
            'focuskw' => get_post_meta($post_id, '_yoast_wpseo_focuskw', true),
            'canonical' => get_post_meta($post_id, '_yoast_wpseo_canonical', true),
            'noindex' => get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true),
            'nofollow' => get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true),
            'opengraph_title' => get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true),
            'opengraph_description' => get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true),
            'opengraph_image' => get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true),
            'twitter_title' => get_post_meta($post_id, '_yoast_wpseo_twitter-title', true),
            'twitter_description' => get_post_meta($post_id, '_yoast_wpseo_twitter-description', true),
            'twitter_image' => get_post_meta($post_id, '_yoast_wpseo_twitter-image', true),
        ];
    }
    
    /**
     * Get Rank Math SEO meta
     */
    private function get_rankmath_meta($post_id) {
        return [
            'title' => get_post_meta($post_id, 'rank_math_title', true),
            'description' => get_post_meta($post_id, 'rank_math_description', true),
            'focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'canonical_url' => get_post_meta($post_id, 'rank_math_canonical_url', true),
            'robots' => get_post_meta($post_id, 'rank_math_robots', true),
            'facebook_title' => get_post_meta($post_id, 'rank_math_facebook_title', true),
            'facebook_description' => get_post_meta($post_id, 'rank_math_facebook_description', true),
            'facebook_image' => get_post_meta($post_id, 'rank_math_facebook_image', true),
            'twitter_title' => get_post_meta($post_id, 'rank_math_twitter_title', true),
            'twitter_description' => get_post_meta($post_id, 'rank_math_twitter_description', true),
        ];
    }
}
