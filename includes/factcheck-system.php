<?php
/**
 * Main Fact-Check System Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_System {
    
    public static function init() {
        // Register shortcodes
        add_shortcode('ai_factcheck_search', array(__CLASS__, 'render_search'));
        add_shortcode('ai_factcheck_results', array(__CLASS__, 'render_results'));
        add_shortcode('ai_factcheck_header_search', array(__CLASS__, 'render_header_search'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        
        // Initialize AJAX handlers
        AI_Verify_Factcheck_Ajax::init();
        
        // Cleanup old reports daily
        add_action('wp_scheduled_cleanup', array('AI_Verify_Factcheck_Database', 'cleanup_old_reports'));
        if (!wp_next_scheduled('wp_scheduled_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_scheduled_cleanup');
        }
    }
    
    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        // Only load on pages with our shortcodes
        global $post;
        if ( !is_a($post, 'WP_Post') || ( !has_shortcode($post->post_content, 'ai_factcheck_search') && !has_shortcode($post->post_content, 'ai_factcheck_results') && !has_shortcode($post->post_content, 'ai_factcheck_header_search') ) ) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ai-verify-factcheck',
            AI_VERIFY_PLUGIN_URL . 'assets/css/factcheck.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'ai-verify-factcheck',
            AI_VERIFY_PLUGIN_URL . 'assets/js/factcheck.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('ai-verify-factcheck', 'aiVerifyFactcheck', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_factcheck_nonce'),
            'results_url' => self::get_results_page_url()
        ));
    }
    
    /**
     * Render search interface
     */
    public static function render_search($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => 'Fact-Check Any Content',
            'subtitle' => 'Enter a URL, article title, or claim to get a comprehensive fact-check report',
            'placeholder' => 'Paste URL or enter text to fact-check...',
            'show_filters' => 'yes',
            'button_text' => 'Analyze'
        ), $atts);
        
        ob_start();
        include AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-search.php';
        return ob_get_clean();
    }

    /**
     * Render header search interface
     */
    public static function render_header_search($atts = array()) {
        ob_start();
        // Assuming you have a template file for the header search
        include AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-header-search.php';
        return ob_get_clean();
    }
    
    /**
     * Render results page
     */
    public static function render_results($atts = array()) {
        $atts = shortcode_atts(array(
            'show_export' => 'yes'
        ), $atts);
        
        ob_start();
        include AI_VERIFY_PLUGIN_DIR . 'templates/factcheck-results.php';
        return ob_get_clean();
    }
    
    /**
     * Get results page URL
     */
    private static function get_results_page_url() {
        $results_page = get_option('ai_verify_results_page_url', '');
        
        if (empty($results_page)) {
            return home_url('/fact-check-results/');
        }
        
        return $results_page;
    }
}