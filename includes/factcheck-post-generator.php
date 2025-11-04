<?php
/**
 * Fact-Check Report Post Generator - ENHANCED
 * IMPROVEMENTS:
 * - Better metadata extraction from scraped content
 * - Properly passes all metadata (title, author, dates, image) to posts
 * - Extracts from multiple sources (scraped data, metadata field, HTML)
 * - Never fails due to missing metadata
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
     * IMPROVED: Create or update WordPress post with COMPREHENSIVE metadata extraction
     */
    public static function create_report_post($report_id, $report_data) {
        if (empty($report_id) || empty($report_data)) {
            error_log('AI Verify: Cannot create post - missing report data');
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
        
        // STEP 1: Extract comprehensive metadata from ALL available sources
        $metadata = self::extract_comprehensive_metadata($report_data);
        
        error_log('AI Verify: Extracted metadata - Title: "' . substr($metadata['title'], 0, 50) . '", Author: "' . $metadata['author'] . '", Date: "' . $metadata['date'] . '", Image: ' . ($metadata['featured_image'] ? 'YES' : 'NO'));
        
        // STEP 2: Generate post title, excerpt, content
        $title = self::generate_title($report_data, $metadata);
        $excerpt = self::generate_excerpt($report_data);
        $content = self::generate_content($report_data);
        
        $post_data = array(
            'post_type' => self::$post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_name' => sanitize_title($report_id),
        );
        
        // STEP 3: Create or update post
        if (!empty($existing_posts)) {
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }
        
        if (is_wp_error($post_id) || !$post_id) {
            error_log('AI Verify: Failed to create report post for ' . $report_id);
            return false;
        }
        
        // STEP 4: Save all report data and metadata as post meta
        update_post_meta($post_id, 'report_id', $report_id);
        update_post_meta($post_id, 'report_data', json_encode($report_data));
        update_post_meta($post_id, 'overall_score', $report_data['overall_score']);
        update_post_meta($post_id, 'credibility_rating', $report_data['credibility_rating']);
        update_post_meta($post_id, 'input_value', $report_data['input_value']);
        update_post_meta($post_id, 'input_type', $report_data['input_type']);
        
        // STEP 5: Save comprehensive metadata
        update_post_meta($post_id, 'article_title', $metadata['title']);
        update_post_meta($post_id, 'article_author', $metadata['author']);
        update_post_meta($post_id, 'article_date', $metadata['date']);
        update_post_meta($post_id, 'article_date_modified', $metadata['date_modified']);
        update_post_meta($post_id, 'article_description', $metadata['description']);
        update_post_meta($post_id, 'article_domain', $metadata['domain']);
        update_post_meta($post_id, 'article_favicon', $metadata['favicon']);
        update_post_meta($post_id, 'article_url', $report_data['input_value']);
        
        // STEP 6: Set featured image
        if ($report_data['input_type'] === 'url') {
            $image_set = self::set_featured_image($post_id, $metadata['featured_image'], $report_data['input_value']);
            if ($image_set) {
                error_log('AI Verify: Successfully set featured image for post ' . $post_id);
            } else {
                error_log('AI Verify: Could not set featured image for post ' . $post_id);
            }
        }
        
        error_log('AI Verify: Created/updated post ' . $post_id . ' for report ' . $report_id);
        
        return $post_id;
    }
    
    /**
     * NEW: Extract comprehensive metadata from ALL available sources
     * Priority: metadata field > scraped_content HTML > URL re-extraction
     */
    private static function extract_comprehensive_metadata($report_data) {
        $metadata = array(
            'title' => '',
            'description' => '',
            'featured_image' => '',
            'author' => '',
            'date' => '',
            'date_modified' => '',
            'domain' => '',
            'favicon' => ''
        );
        
        // SOURCE 1: Check if metadata already exists in report_data['metadata']
        if (!empty($report_data['metadata']) && is_array($report_data['metadata'])) {
            $existing = $report_data['metadata'];
            
            if (!empty($existing['title'])) $metadata['title'] = $existing['title'];
            if (!empty($existing['description'])) $metadata['description'] = $existing['description'];
            if (!empty($existing['featured_image'])) $metadata['featured_image'] = $existing['featured_image'];
            if (!empty($existing['author'])) $metadata['author'] = $existing['author'];
            if (!empty($existing['date'])) $metadata['date'] = $existing['date'];
            if (!empty($existing['date_modified'])) $metadata['date_modified'] = $existing['date_modified'];
            if (!empty($existing['domain'])) $metadata['domain'] = $existing['domain'];
            if (!empty($existing['favicon'])) $metadata['favicon'] = $existing['favicon'];
            
            error_log('AI Verify: Using metadata from database - Title: ' . (!empty($metadata['title']) ? 'YES' : 'NO'));
        }
        
        // SOURCE 2: Extract from scraped HTML content if we still need data
        if (!empty($report_data['scraped_content']) && 
            (empty($metadata['title']) || empty($metadata['featured_image']))) {
            
            $html = $report_data['scraped_content'];
            
            // Title extraction
            if (empty($metadata['title'])) {
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
                    $metadata['title'] = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
                    $metadata['title'] = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $metadata['title']);
                    $metadata['title'] = trim($metadata['title']);
                }
            }
            
            // Description extraction
            if (empty($metadata['description'])) {
                if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['description'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                } elseif (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['description'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                }
            }
            
            // Featured Image extraction
            if (empty($metadata['featured_image'])) {
                if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['featured_image'] = $match[1];
                } elseif (preg_match('/<meta[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['featured_image'] = $match[1];
                }
            }
            
            // Author extraction
            if (empty($metadata['author'])) {
                if (preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['author'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                } elseif (preg_match('/<meta[^>]*property=["\']article:author["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['author'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                }
            }
            
            // Date extraction
            if (empty($metadata['date'])) {
                if (preg_match('/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['date'] = $match[1];
                } elseif (preg_match('/<time[^>]*datetime=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['date'] = $match[1];
                }
            }
            
            // Modified date extraction
            if (empty($metadata['date_modified'])) {
                if (preg_match('/<meta[^>]*property=["\']article:modified_time["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['date_modified'] = $match[1];
                }
            }
            
            error_log('AI Verify: Extracted from HTML - Title: ' . (!empty($metadata['title']) ? 'YES' : 'NO') . ', Image: ' . (!empty($metadata['featured_image']) ? 'YES' : 'NO'));
        }
        
        // SOURCE 3: Extract domain and favicon from URL
        if (!empty($report_data['input_value']) && $report_data['input_type'] === 'url') {
            $parsed = parse_url($report_data['input_value']);
            if (!empty($parsed['host'])) {
                if (empty($metadata['domain'])) {
                    $metadata['domain'] = str_replace('www.', '', $parsed['host']);
                }
                if (empty($metadata['favicon'])) {
                    $metadata['favicon'] = "https://www.google.com/s2/favicons?domain=" . $metadata['domain'] . "&sz=128";
                }
            }
        }
        
        // Fallback title if still empty
        if (empty($metadata['title'])) {
            if ($report_data['input_type'] === 'url') {
                $metadata['title'] = 'Article from ' . ($metadata['domain'] ?: 'Unknown Source');
            } else {
                $metadata['title'] = mb_substr($report_data['input_value'], 0, 60);
            }
        }
        
        // Format dates
        if (!empty($metadata['date'])) {
            try {
                $timestamp = strtotime($metadata['date']);
                if ($timestamp) {
                    $metadata['date'] = date('F j, Y', $timestamp);
                }
            } catch (Exception $e) {
                // Keep original format
            }
        }
        
        if (!empty($metadata['date_modified'])) {
            try {
                $timestamp = strtotime($metadata['date_modified']);
                if ($timestamp) {
                    $metadata['date_modified'] = date('F j, Y', $timestamp);
                }
            } catch (Exception $e) {
                // Keep original format
            }
        }
        
        return $metadata;
    }
    
    /**
     * Generate title for the report post using extracted metadata
     */
    private static function generate_title($report_data, $metadata) {
        $article_title = $metadata['title'];
        
        if (!empty($article_title) && $article_title !== 'Untitled') {
            // Clean up title - remove site names
            $article_title = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $article_title);
            $article_title = trim($article_title);
            
            // Limit length
            if (mb_strlen($article_title) > 80) {
                $article_title = mb_substr($article_title, 0, 77) . '...';
            }
            
            return "Fact-Check: {$article_title}";
        }
        
        // Fallback
        if ($report_data['input_type'] === 'url') {
            $domain = $metadata['domain'] ?: 'Unknown Source';
            return "Fact-Check: Article from {$domain}";
        } else {
            $short_input = mb_substr($report_data['input_value'], 0, 60);
            if (mb_strlen($report_data['input_value']) > 60) {
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
     * Generate content
     */
    private static function generate_content($report_data) {
        $report_id = $report_data['report_id'] ?? '';
        
        return "<!-- This report is rendered by the custom template -->\n" .
               "[ai_factcheck_report id=\"{$report_id}\"]";
    }
    
    /**
     * IMPROVED: Set featured image from URL or download if needed
     */
    private static function set_featured_image($post_id, $image_url, $source_url = '') {
        if (empty($image_url)) {
            error_log("AI Verify: No featured image URL provided for post {$post_id}");
            return false;
        }
        
        // Make URL absolute if relative
        if (!preg_match('/^https?:\/\//i', $image_url) && !empty($source_url)) {
            $parsed = parse_url($source_url);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? '';
            
            if (substr($image_url, 0, 2) === '//') {
                $image_url = $scheme . ':' . $image_url;
            } elseif (substr($image_url, 0, 1) === '/') {
                $image_url = $scheme . '://' . $host . $image_url;
            } else {
                $path = $parsed['path'] ?? '';
                $path = substr($path, 0, strrpos($path, '/') + 1);
                $image_url = $scheme . '://' . $host . $path . $image_url;
            }
        }
        
        // Validate URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            error_log("AI Verify: Invalid image URL: {$image_url}");
            return false;
        }
        
        // Check if image already attached
        $existing_thumbnail = get_post_thumbnail_id($post_id);
        if ($existing_thumbnail) {
            error_log("AI Verify: Post {$post_id} already has a featured image (ID: {$existing_thumbnail})");
            return true;
        }
        
        // Download and attach image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Try to download the image
        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            error_log("AI Verify: Successfully set featured image (attachment {$attachment_id}) for post {$post_id}");
            return true;
        } else {
            error_log("AI Verify: Failed to download featured image for post {$post_id}: " . $attachment_id->get_error_message());
            return false;
        }
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
        if (!isset($_GET['report'])) {
            return;
        }
        
        $report_id = sanitize_text_field($_GET['report']);
        
        $posts = get_posts(array(
            'post_type' => self::$post_type,
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($posts)) {
            wp_redirect(get_permalink($posts[0]->ID), 301);
            exit;
        }
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