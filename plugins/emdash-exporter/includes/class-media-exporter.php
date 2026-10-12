<?php
/**
 * Media Exporter
 * 
 * Handles exporting media attachments with metadata
 */

defined('ABSPATH') || exit;

class EmDash_Media_Exporter {
    
    /**
     * Get paginated list of media items
     */
    public function get_media($per_page = 100, $page = 1) {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        $query = new WP_Query($args);
        $items = [];
        
        foreach ($query->posts as $attachment) {
            $items[] = $this->format_attachment($attachment);
        }
        
        return [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
    
    /**
     * Get a single media item
     */
    public function get_media_item($id) {
        $attachment = get_post($id);
        
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error(
                'not_found',
                __('Media item not found.', 'emdash-exporter'),
                ['status' => 404]
            );
        }
        
        return $this->format_attachment($attachment);
    }
    
    /**
     * Format an attachment for export
     */
    private function format_attachment($attachment) {
        $metadata = wp_get_attachment_metadata($attachment->ID);
        $url = wp_get_attachment_url($attachment->ID);
        $file_path = get_attached_file($attachment->ID);
        
        $data = [
            'id' => $attachment->ID,
            'url' => $url,
            'filename' => basename($url),
            'mime_type' => $attachment->post_mime_type,
            'title' => $attachment->post_title,
            'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'date' => $attachment->post_date,
            'modified' => $attachment->post_modified,
            'author' => [
                'id' => (int) $attachment->post_author,
            ],
        ];
        
        // Add dimensions for images
        if (isset($metadata['width']) && isset($metadata['height'])) {
            $data['width'] = (int) $metadata['width'];
            $data['height'] = (int) $metadata['height'];
        }
        
        // Add file size
        if ($file_path && file_exists($file_path)) {
            $data['filesize'] = filesize($file_path);
        }
        
        // Add image sizes (thumbnails, etc.)
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_url = trailingslashit($upload_dir['baseurl']);
            $subdir = dirname($metadata['file'] ?? '');
            
            $data['sizes'] = [];
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $data['sizes'][$size_name] = [
                    'url' => $base_url . ($subdir ? trailingslashit($subdir) : '') . $size_data['file'],
                    'width' => (int) $size_data['width'],
                    'height' => (int) $size_data['height'],
                    'mime_type' => $size_data['mime-type'] ?? $attachment->post_mime_type,
                ];
            }
        }
        
        // Add any custom meta
        $custom_meta = $this->get_attachment_custom_meta($attachment->ID);
        if (!empty($custom_meta)) {
            $data['meta'] = $custom_meta;
        }
        
        return $data;
    }
    
    /**
     * Get custom meta for an attachment
     */
    private function get_attachment_custom_meta($attachment_id) {
        $all_meta = get_post_meta($attachment_id);
        $custom = [];
        
        // Keys to skip
        $skip_keys = [
            '_wp_attached_file',
            '_wp_attachment_metadata',
            '_wp_attachment_image_alt',
        ];
        
        foreach ($all_meta as $key => $values) {
            if (in_array($key, $skip_keys)) continue;
            if (strpos($key, '_wp_') === 0) continue;
            if (strpos($key, '_edit_') === 0) continue;
            
            // Single values
            $custom[$key] = count($values) === 1 ? $values[0] : $values;
            
            // Decode PHP-serialized data. allowed_classes=false prevents
            // object injection from untrusted database values.
            if (is_string($custom[$key]) && is_serialized($custom[$key])) {
                $unserialized = @unserialize($custom[$key], ['allowed_classes' => false]);
                if ($unserialized !== false) {
                    $custom[$key] = $unserialized;
                }
            }
        }
        
        return $custom;
    }
}
