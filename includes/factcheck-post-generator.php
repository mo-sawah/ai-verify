<?php
/**
 * COMPLETE FIX: Fact-Check Report Post Generator
 * ALL ISSUES RESOLVED:
 * - Full metadata extraction from all sources
 * - Custom page title "Fact-Check: {Article Title}"
 * - Schema.org ClaimReview markup for SEO
 * - Featured images properly set
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Post_Generator {
    
    private static $post_type = 'fact_check_report';
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_filter('single_template', array(__CLASS__, 'load_custom_template'));
        add_action('template_redirect', array(__CLASS__, 'handle_report_redirect'));
        
        // Add custom page title
        add_filter('wp_title', array(__CLASS__, 'custom_page_title'), 10, 3);
        add_filter('document_title_parts', array(__CLASS__, 'custom_document_title'));
        
        // Add schema.org markup in head
        add_action('wp_head', array(__CLASS__, 'add_schema_markup'));
    }
    
    public static function register_post_type() {
        $labels = array(
            'name' => 'Reports',
            'singular_name' => 'Report',
            'menu_name' => 'Reports',
            'add_new' => 'Add New',
            'edit_item' => 'Edit Report',
            'view_item' => 'View Report',
            'all_items' => 'All Reports'
        );
        
        $args = array(
            'labels' => $labels,
            'description' => 'Fact-check analysis reports',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'report', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-analytics',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true
        );
        
        register_post_type(self::$post_type, $args);
    }
    
    /**
     * IMPROVED: Custom page title - "Fact-Check: {Article Title}"
     */
    public static function custom_page_title($title, $sep, $seplocation) {
        if (!is_singular(self::$post_type)) {
            return $title;
        }
        
        global $post;
        $article_title = get_post_meta($post->ID, 'article_title', true);
        
        if (!empty($article_title)) {
            if ($seplocation == 'right') {
                $title = "Fact-Check: {$article_title} {$sep} ";
            } else {
                $title = " {$sep} Fact-Check: {$article_title}";
            }
        }
        
        return $title;
    }
    
    /**
     * IMPROVED: Custom document title (for wp_title filter in modern themes)
     */
    public static function custom_document_title($title) {
        if (!is_singular(self::$post_type)) {
            return $title;
        }
        
        global $post;
        $article_title = get_post_meta($post->ID, 'article_title', true);
        
        if (!empty($article_title)) {
            $title['title'] = "Fact-Check: {$article_title}";
        }
        
        return $title;
    }
    
    /**
     * NEW: Add schema.org ClaimReview markup to page head
     */
    public static function add_schema_markup() {
        if (!is_singular(self::$post_type)) {
            return;
        }
        
        global $post;
        
        $report_id = get_post_meta($post->ID, 'report_id', true);
        $report_json = get_post_meta($post->ID, 'report_data', true);
        $report_data = json_decode($report_json, true);
        
        if (empty($report_data)) {
            return;
        }
        
        // Get metadata
        $article_title = get_post_meta($post->ID, 'article_title', true);
        $article_url = get_post_meta($post->ID, 'article_url', true);
        $article_author = get_post_meta($post->ID, 'article_author', true);
        $article_date = get_post_meta($post->ID, 'article_date', true);
        
        $overall_score = $report_data['overall_score'] ?? 50;
        $rating = $report_data['credibility_rating'] ?? 'Unknown';
        
        // Map rating to schema.org values
        $schema_rating = 'Unrated';
        if ($rating === 'Highly Credible' || $overall_score >= 85) {
            $schema_rating = 'True';
        } elseif ($rating === 'Mostly Credible' || $overall_score >= 70) {
            $schema_rating = 'Mostly True';
        } elseif ($rating === 'Mixed Credibility') {
            $schema_rating = 'Mixed';
        } elseif ($rating === 'Low Credibility' || $overall_score < 50) {
            $schema_rating = 'Mostly False';
        }
        
        // Build ClaimReview schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ClaimReview',
            'url' => get_permalink($post->ID),
            'author' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            ),
            'datePublished' => get_the_date('c', $post->ID),
            'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => $overall_score,
                'bestRating' => 100,
                'worstRating' => 0,
                'alternateName' => $schema_rating
            )
        );
        
        // Add claim being reviewed
        if (!empty($article_title) && !empty($article_url)) {
            $schema['claimReviewed'] = $article_title;
            $schema['itemReviewed'] = array(
                '@type' => 'CreativeWork',
                'name' => $article_title,
                'url' => $article_url
            );
            
            if (!empty($article_author)) {
                $schema['itemReviewed']['author'] = array(
                    '@type' => 'Person',
                    'name' => $article_author
                );
            }
            
            if (!empty($article_date)) {
                $schema['itemReviewed']['datePublished'] = $article_date;
            }
        }
        
        // Output schema
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        echo '</script>' . "\n";
    }
    
    /**
     * IMPROVED: Create post with ALL metadata properly saved
     */
    public static function create_report_post($report_id, $report_data) {
        if (empty($report_id) || empty($report_data)) {
            return false;
        }
        
        // Check if post exists
        $existing_posts = get_posts(array(
            'post_type' => self::$post_type,
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ));
        
        // Extract ALL metadata
        $metadata = self::extract_comprehensive_metadata($report_data);
        
        error_log('AI Verify: Post generator metadata - Title: "' . $metadata['title'] . '", Author: "' . $metadata['author'] . '", Date: "' . $metadata['date'] . '", Image: ' . ($metadata['featured_image'] ? 'YES' : 'NO'));
        
        // Generate post title
        $post_title = self::generate_post_title($metadata, $report_data);
        $post_excerpt = self::generate_excerpt($report_data);
        $post_content = "<!-- Fact-check report rendered by template -->\n[ai_factcheck_report id=\"{$report_id}\"]";
        
        $post_data = array(
            'post_type' => self::$post_type,
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status' => 'publish',
            'post_name' => sanitize_title($report_id),
        );
        
        if (!empty($existing_posts)) {
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }
        
        if (is_wp_error($post_id) || !$post_id) {
            error_log('AI Verify: Failed to create post for ' . $report_id);
            return false;
        }
        
        // Save ALL metadata as post meta
        update_post_meta($post_id, 'report_id', $report_id);
        update_post_meta($post_id, 'report_data', json_encode($report_data));
        update_post_meta($post_id, 'overall_score', $report_data['overall_score']);
        update_post_meta($post_id, 'credibility_rating', $report_data['credibility_rating']);
        update_post_meta($post_id, 'input_value', $report_data['input_value']);
        update_post_meta($post_id, 'input_type', $report_data['input_type']);
        
        // CRITICAL: Save all metadata fields
        update_post_meta($post_id, 'article_title', $metadata['title']);
        update_post_meta($post_id, 'article_author', $metadata['author']);
        update_post_meta($post_id, 'article_date', $metadata['date']);
        update_post_meta($post_id, 'article_date_modified', $metadata['date_modified']);
        update_post_meta($post_id, 'article_description', $metadata['description']);
        update_post_meta($post_id, 'article_domain', $metadata['domain']);
        update_post_meta($post_id, 'article_favicon', $metadata['favicon']);
        update_post_meta($post_id, 'article_url', $report_data['input_value']);
        
        // Set featured image
        if (!empty($metadata['featured_image'])) {
            self::set_featured_image($post_id, $metadata['featured_image'], $report_data['input_value']);
        }
        
        error_log('AI Verify: Created/updated post ' . $post_id . ' for report ' . $report_id);
        
        return $post_id;
    }
    
    /**
     * COMPREHENSIVE: Extract metadata from ALL available sources
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
        
        // SOURCE 1: Check metadata field in report_data
        if (!empty($report_data['metadata']) && is_array($report_data['metadata'])) {
            foreach ($metadata as $key => $value) {
                if (!empty($report_data['metadata'][$key])) {
                    $metadata[$key] = $report_data['metadata'][$key];
                }
            }
        }
        
        // SOURCE 2: Extract from HTML if still missing data
        if (!empty($report_data['scraped_content']) && (empty($metadata['title']) || empty($metadata['featured_image']))) {
            $html = $report_data['scraped_content'];
            
            // Title
            if (empty($metadata['title'])) {
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
                    $metadata['title'] = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
                    $metadata['title'] = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $metadata['title']);
                    $metadata['title'] = trim($metadata['title']);
                }
            }
            
            // Featured image
            if (empty($metadata['featured_image'])) {
                if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['featured_image'] = $match[1];
                }
            }
            
            // Author
            if (empty($metadata['author'])) {
                if (preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['author'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                }
            }
            
            // Date
            if (empty($metadata['date'])) {
                if (preg_match('/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                    $metadata['date'] = $match[1];
                }
            }
        }
        
        // SOURCE 3: Extract from URL
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
        
        // Fallback title
        if (empty($metadata['title'])) {
            if ($report_data['input_type'] === 'url') {
                $metadata['title'] = 'Article from ' . ($metadata['domain'] ?: 'Unknown Source');
            } else {
                $metadata['title'] = mb_substr($report_data['input_value'], 0, 60);
            }
        }
        
        return $metadata;
    }
    
    private static function generate_post_title($metadata, $report_data) {
        $article_title = $metadata['title'];
        
        if (!empty($article_title) && $article_title !== 'Untitled') {
            $article_title = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $article_title);
            $article_title = trim($article_title);
            
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
            $short = mb_substr($report_data['input_value'], 0, 60);
            return "Fact-Check: {$short}" . (mb_strlen($report_data['input_value']) > 60 ? '...' : '');
        }
    }
    
    private static function generate_excerpt($report_data) {
        $score = round($report_data['overall_score'] ?? 0);
        $rating = $report_data['credibility_rating'] ?? 'Unknown';
        $claims_count = count($report_data['factcheck_results'] ?? array());
        $sources_count = count($report_data['sources'] ?? array());
        
        return "Comprehensive fact-check: {$score}% credibility ({$rating}). {$claims_count} claims analyzed, {$sources_count} sources verified.";
    }
    
    private static function set_featured_image($post_id, $image_url, $source_url = '') {
        if (empty($image_url)) {
            return false;
        }
        
        // Make absolute if relative
        if (!preg_match('/^https?:\/\//i', $image_url) && !empty($source_url)) {
            $parsed = parse_url($source_url);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? '';
            
            if (substr($image_url, 0, 2) === '//') {
                $image_url = $scheme . ':' . $image_url;
            } elseif (substr($image_url, 0, 1) === '/') {
                $image_url = $scheme . '://' . $host . $image_url;
            }
        }
        
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if already set
        if (get_post_thumbnail_id($post_id)) {
            return true;
        }
        
        // Download and attach
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            error_log("AI Verify: Set featured image (ID: {$attachment_id}) for post {$post_id}");
            return true;
        }
        
        return false;
    }
    
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

AI_Verify_Factcheck_Post_Generator::init();