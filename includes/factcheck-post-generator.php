<?php
/**
 * Fact-Check Report Post Generator
 * Creates actual WordPress posts for completed reports
 * Makes reports publicly accessible and SEO-friendly
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Post_Generator {
    
    private static $post_type = 'fact_check_report';
    
    /**
     * Initialize the post type and hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_filter('single_template', array(__CLASS__, 'load_custom_template'));
        add_action('template_redirect', array(__CLASS__, 'handle_report_redirect'));
    }
    
    /**
     * Register custom post type for fact-check reports
     */
    public static function register_post_type() {
        $labels = array(
            'name' => 'Reports',
            'singular_name' => 'Report',
            'menu_name' => 'Reports',
            'name_admin_bar' => 'Report',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Report',
            'new_item' => 'New Report',
            'edit_item' => 'Edit Report',
            'view_item' => 'View Report',
            'all_items' => 'All Reports',
            'search_items' => 'Search Reports',
            'parent_item_colon' => 'Parent Reports:',
            'not_found' => 'No reports found.',
            'not_found_in_trash' => 'No reports found in Trash.',
        );
        
        $args = array(
            'labels' => $labels,
            'description' => 'Fact-check analysis reports',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'report',
                'with_front' => false
            ),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-analytics',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true,
            'taxonomies' => array(),
        );
        
        register_post_type(self::$post_type, $args);
    }
    
    /**
     * Create or update WordPress post for a completed report
     */
    public static function create_report_post($report_id, $report_data) {
        if (empty($report_id) || empty($report_data)) {
            return false;
        }
        
        // Check if post already exists
        $existing_posts = get_posts(array(
            'post_type' => self::$post_type,
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));
        
        // Generate title
        $title = self::generate_title($report_data);
        
        // Generate excerpt
        $excerpt = self::generate_excerpt($report_data);
        
        // Generate content
        $content = self::generate_content($report_data);
        
        $post_data = array(
            'post_type' => self::$post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_name' => sanitize_title($report_id),
        );
        
        if (!empty($existing_posts)) {
            // Update existing post
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            // Create new post
            $post_id = wp_insert_post($post_data);
        }
        
        if (is_wp_error($post_id) || !$post_id) {
            error_log('AI Verify: Failed to create report post for ' . $report_id);
            return false;
        }
        
        // Save report data as post meta
        update_post_meta($post_id, 'report_id', $report_id);
        update_post_meta($post_id, 'report_data', json_encode($report_data));
        update_post_meta($post_id, 'overall_score', $report_data['overall_score']);
        update_post_meta($post_id, 'credibility_rating', $report_data['credibility_rating']);
        update_post_meta($post_id, 'input_value', $report_data['input_value']);
        update_post_meta($post_id, 'input_type', $report_data['input_type']);
        
        // Set featured image if available
        if ($report_data['input_type'] === 'url') {
            self::set_featured_image($post_id, $report_data);
        }
        
        error_log('AI Verify: Created/updated post ' . $post_id . ' for report ' . $report_id);
        
        return $post_id;
    }
    
    /**
     * Generate title for the report post
     */
    private static function generate_title($report_data) {
        $input = $report_data['input_value'] ?? '';
        
        if ($report_data['input_type'] === 'url') {
            // Try to get the actual article title from scraped content
            $article_title = '';
            
            // First check if we have scraped metadata
            if (!empty($report_data['scraped_content'])) {
                // Try to extract from HTML title tag
                if (preg_match('/<title>(.*?)<\/title>/i', $report_data['scraped_content'], $match)) {
                    $article_title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
                }
                // Or try to extract from first h1
                if (empty($article_title) && preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $report_data['scraped_content'], $match)) {
                    $article_title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
                }
            }
            
            // Clean up title - remove site names and separators
            if (!empty($article_title)) {
                // Remove common patterns like " - SiteName" or " | SiteName" from the end
                $article_title = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $article_title);
                $article_title = trim($article_title);
                
                // Limit length
                if (mb_strlen($article_title) > 80) {
                    $article_title = mb_substr($article_title, 0, 77) . '...';
                }
                
                return "Fact-Check: {$article_title}";
            }
            
            // Fallback to domain if no title found
            $parsed = parse_url($input);
            $domain = $parsed['host'] ?? 'Unknown Source';
            $domain = str_replace('www.', '', $domain);
            
            return "Fact-Check: {$domain} Article";
        } else {
            // For title or phrase input
            $short_input = mb_substr($input, 0, 60);
            if (mb_strlen($input) > 60) {
                $short_input .= '...';
            }
            return "Fact-Check: {$short_input}";
        }
    }
    
    /**
     * Generate excerpt for SEO
     */
    private static function generate_excerpt($report_data) {
        $score = round($report_data['overall_score'] ?? 0);
        $rating = $report_data['credibility_rating'] ?? 'Unknown';
        $claims_count = count($report_data['factcheck_results'] ?? array());
        $sources_count = count($report_data['sources'] ?? array());
        
        return "Comprehensive fact-check report with {$score}% credibility score ({$rating}). {$claims_count} claims analyzed across {$sources_count} verified sources.";
    }
    
    /**
     * Generate content - just a placeholder that redirects to the custom template
     */
    private static function generate_content($report_data) {
        $report_id = $report_data['report_id'] ?? '';
        
        return "<!-- This report is rendered by the custom template -->\n" .
               "[ai_factcheck_report id=\"{$report_id}\"]";
    }
    
    /**
     * Extract and set featured image from report
     */
    private static function set_featured_image($post_id, $report_data) {
        if (empty($report_data['scraped_content'])) {
            return false;
        }
        
        // Try to extract Open Graph image
        $content = $report_data['scraped_content'];
        
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $content, $match)) {
            $image_url = $match[1];
        } elseif (preg_match('/<meta[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\']/i', $content, $match)) {
            $image_url = $match[1];
        } else {
            return false;
        }
        
        // Download and attach image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Load custom template for report posts
     */
    public static function load_custom_template($template) {
        global $post;
        
        if ($post && $post->post_type === self::$post_type) {
            $custom_template = AI_VERIFY_PLUGIN_DIR . 'templates/single-fact-check-report.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Handle old-style report URLs and redirect to post
     */
    public static function handle_report_redirect() {
        // Check if we're on the old results page with a report parameter
        if (!isset($_GET['report'])) {
            return;
        }
        
        $report_id = sanitize_text_field($_GET['report']);
        
        // Find the post for this report
        $posts = get_posts(array(
            'post_type' => self::$post_type,
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($posts)) {
            // Redirect to the actual post
            wp_redirect(get_permalink($posts[0]->ID), 301);
            exit;
        }
        
        // If no post exists, let the normal flow handle it (will create report if needed)
    }
    
    /**
     * Get report URL from report ID
     */
    public static function get_report_url($report_id) {
        $posts = get_posts(array(
            'post_type' => self::$post_type,
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($posts)) {
            return get_permalink($posts[0]->ID);
        }
        
        return '';
    }
}

// Initialize
AI_Verify_Factcheck_Post_Generator::init();