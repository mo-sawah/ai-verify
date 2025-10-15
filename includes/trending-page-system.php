<?php
/**
 * Trending Page System Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trending_Page_System {
    
    public static function init() {
        // Register shortcode
        add_shortcode('ai_verify_trending_page', array(__CLASS__, 'render_trending_page'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        
        // AJAX handler for refreshing data
        add_action('wp_ajax_ai_verify_refresh_trending', array(__CLASS__, 'ajax_refresh_trending'));
        add_action('wp_ajax_nopriv_ai_verify_refresh_trending', array(__CLASS__, 'ajax_refresh_trending'));
        
        // Schedule hourly refresh
        add_action('ai_verify_refresh_external_factchecks', array(__CLASS__, 'scheduled_refresh'));
        if (!wp_next_scheduled('ai_verify_refresh_external_factchecks')) {
            wp_schedule_event(time(), 'hourly', 'ai_verify_refresh_external_factchecks');
        }
    }
    
    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_verify_trending_page')) {
            return;
        }
        
        wp_enqueue_style(
            'ai-verify-trending-page',
            AI_VERIFY_PLUGIN_URL . 'assets/css/trending-page.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-trending-page',
            AI_VERIFY_PLUGIN_URL . 'assets/js/trending-page.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-trending-page', 'aiVerifyTrendingPage', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_trending_nonce'),
            'factcheck_url' => get_option('ai_verify_results_page_url', home_url('/fact-check-results/'))
        ));
    }
    
    /**
     * Render trending page
     */
    public static function render_trending_page($atts) {
        $atts = shortcode_atts(array(
            'limit' => 50,
            'show_search' => 'yes',
            'show_filters' => 'yes',
            'show_cta' => 'yes'
        ), $atts);
        
        ob_start();
        include AI_VERIFY_PLUGIN_DIR . 'templates/trending-misinformation-page.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Refresh trending data
     */
    public static function ajax_refresh_trending() {
        check_ajax_referer('ai_verify_trending_nonce', 'nonce');
        
        // Clear cache
        AI_Verify_External_Factcheck_Aggregator::clear_cache();
        
        // Get fresh data
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
        $trending = AI_Verify_External_Factcheck_Aggregator::get_all_trending(50, $category);
        
        wp_send_json_success(array(
            'count' => count($trending),
            'message' => 'Refreshed successfully'
        ));
    }
    
    /**
     * Scheduled refresh (runs hourly)
     */
    public static function scheduled_refresh() {
        error_log('AI Verify: Refreshing external fact-checks cache');
        AI_Verify_External_Factcheck_Aggregator::clear_cache();
        
        // Pre-cache popular categories
        $categories = array('all', 'politics', 'health', 'climate');
        foreach ($categories as $category) {
            AI_Verify_External_Factcheck_Aggregator::get_all_trending(50, $category);
        }
    }
}