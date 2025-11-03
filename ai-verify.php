<?php
/**
 * Plugin Name: AI Verify
 * Plugin URI: https://sawahsolutions.com
 * Description: Professional fact-check verification tools with AI chatbot, reverse image search, and related fact-checks
 * Version: 2.2.5
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: ai-verify
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_VERIFY_VERSION', '2.2.5');
define('AI_VERIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_VERIFY_PLUGIN_URL', plugin_dir_url(__FILE__));

class AI_Verify {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Check if required files exist
        if (!file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/settings.php')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>AI Verify Error: Missing includes/settings.php file</p></div>';
            });
            return;
        }
        
        if (!file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/ajax-handlers.php')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>AI Verify Error: Missing includes/ajax-handlers.php file</p></div>';
            });
            return;
        }
        
        
        // Load required files
        require_once AI_VERIFY_PLUGIN_DIR . 'includes/settings.php';
        require_once AI_VERIFY_PLUGIN_DIR . 'includes/ajax-handlers.php';
        
        // Load widget files
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/misinfo-widget.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/misinfo-widget.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/misinfo-ajax.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/misinfo-ajax.php';
        }
        
        // Load fact-check system files
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-database.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-database.php';
        }
        
        // NEW: Load fact-check post generator (creates WordPress posts for completed reports)
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-post-generator.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-post-generator.php';
        }
        
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-scraper.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-scraper.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-analyzer.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-analyzer.php';
        }
        // NEW: Load the hybrid single-call analyzer
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-hybrid-analyzer.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-hybrid-analyzer.php';
        }
        
        // NEW: Load fact-check AJAX handler (database-backed access tracking)
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-ajax.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-ajax.php';
        }
        
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-system.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-system.php';
        }
        
        // Load leads admin (only in admin)
        if (is_admin() && file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-leads-admin.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/factcheck-leads-admin.php';
        }

        // Load trending helpers (must load before template)
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trending-helpers.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trending-helpers.php';
        }

        // Load Intelligence Dashboard (Phase 1 & 2)
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-database-upgrade.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-database-upgrade.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-velocity.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-velocity.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/social-integrations/rss-aggregator-enhanced.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/social-integrations/rss-aggregator-enhanced.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/social-integrations/twitter-monitor.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/social-integrations/twitter-monitor.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/intelligence-dashboard.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/intelligence-dashboard.php';
        }

        // Load chat assistant
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/chat-assistant.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/chat-assistant.php';
        }
        
        // Load propaganda analyzer
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/propaganda-analyzer.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/propaganda-analyzer.php';
        }

        // Load background aggregator
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/background-aggregator.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/background-aggregator.php';
        }
        
        // Load trends system files
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-database.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-database.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-analyzer.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-analyzer.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-integration.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-integration.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-admin.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-admin.php';
        }
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/trends-widget.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/trends-widget.php';
        }

        if (is_admin() && file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/assistant-page.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/assistant-page.php';
        }

        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/assistant-shortcode.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/assistant-shortcode.php';
        }

        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'includes/deepfake-detector.php')) {
            require_once AI_VERIFY_PLUGIN_DIR . 'includes/deepfake-detector.php';
        }
        
        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add shortcode
        add_shortcode('ai_verify', array($this, 'render_verification_tools'));
        
        // Auto-add to posts if enabled
        add_filter('the_content', array($this, 'auto_add_to_content'));
        
        // Initialize settings
        if (class_exists('AI_Verify_Settings')) {
            AI_Verify_Settings::init();
        }
        
        // Initialize AJAX handlers
        if (class_exists('AI_Verify_Ajax')) {
            AI_Verify_Ajax::init();
        }
        
        // Initialize fact-check system
        if (class_exists('AI_Verify_Factcheck_System')) {
            AI_Verify_Factcheck_System::init();
        }

        // Initialize Background Aggregator
        if (class_exists('AI_Verify_Background_Aggregator')) {
            AI_Verify_Background_Aggregator::init();
        }
        
        // Initialize trends system
        if (class_exists('AI_Verify_Trends_Integration')) {
            AI_Verify_Trends_Integration::init();
        }
        if (class_exists('AI_Verify_Trends_Admin')) {
            AI_Verify_Trends_Admin::init();
        }
        if (class_exists('AI_Verify_Trends_Widget')) {
            AI_Verify_Trends_Widget::init();
        }
        
        // Initialize fact-check AJAX handler
        if (class_exists('AI_Verify_Factcheck_Ajax')) {
            AI_Verify_Factcheck_Ajax::init();
        }
        
        // Initialize assistant shortcode
        if (class_exists('AI_Verify_Assistant_Shortcode')) {
            AI_Verify_Assistant_Shortcode::init();
        }

        if (class_exists('AI_Verify_Chat_Assistant')) {
            AI_Verify_Chat_Assistant::init();
        }
    }
    
    public function enqueue_assets() {
        // Prevent duplicate enqueues
        static $enqueued = false;
        
        if ($enqueued) {
            return;
        }
        
        // Enqueue widget CSS
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/css/misinfo-widget.css')) {
            wp_enqueue_style(
                'ai-verify-misinfo-widget',
                AI_VERIFY_PLUGIN_URL . 'assets/css/misinfo-widget.css',
                array(),
                AI_VERIFY_VERSION
            );
        }
        
        // Enqueue widget JS
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/js/misinfo-widget.js')) {
            wp_enqueue_script(
                'ai-verify-misinfo-widget',
                AI_VERIFY_PLUGIN_URL . 'assets/js/misinfo-widget.js',
                array('jquery'),
                AI_VERIFY_VERSION,
                true
            );
            
            wp_localize_script('ai-verify-misinfo-widget', 'aiVerifyMisinfo', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_verify_misinfo_nonce')
            ));
        }
        
        // Enqueue fact-check CSS and JS
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/css/factcheck.css')) {
            wp_enqueue_style(
                'ai-verify-factcheck',
                AI_VERIFY_PLUGIN_URL . 'assets/css/factcheck.css',
                array(),
                AI_VERIFY_VERSION
            );
        }
        
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/js/factcheck.js')) {
            wp_enqueue_script(
                'ai-verify-factcheck',
                AI_VERIFY_PLUGIN_URL . 'assets/js/factcheck.js',
                array('jquery'),
                AI_VERIFY_VERSION,
                true
            );
            
            // Localize with fact-check specific data
            $results_url = get_option('ai_verify_results_page_url', '');
            
            wp_localize_script('ai-verify-factcheck', 'aiVerifyFactcheck', array(
                'ajax_url' => admin_url('admin-ajax.php', 'https'),
                'nonce' => wp_create_nonce('ai_verify_factcheck_nonce'),
                'results_url' => $results_url
            ));
        }
        
        $enqueued = true;
    }
    
    public function enqueue_admin_assets($hook) {
        if ('settings_page_ai-verify-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'ai-verify-admin',
            AI_VERIFY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_VERIFY_VERSION
        );

        // Enqueue assets for dedicated assistant page
        if ('toplevel_page_ai-verify-assistant' === $hook) {
            wp_enqueue_style(
                'ai-verify-assistant-page',
                AI_VERIFY_PLUGIN_URL . 'assets/css/assistant-page.css',
                array(),
                AI_VERIFY_VERSION
            );

            wp_enqueue_script(
                'ai-verify-assistant-page',
                AI_VERIFY_PLUGIN_URL . 'assets/js/assistant-page.js',
                array('jquery'),
                AI_VERIFY_VERSION,
                true
            );

            wp_localize_script('ai-verify-assistant-page', 'aiVerifyDashboard', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'assistant_nonce' => wp_create_nonce('ai_verify_assistant_nonce')
            ));
        }
    }
    
    public function render_verification_tools($atts = array()) {
        // Prevent rendering during content filtering
        if (doing_filter('get_the_excerpt')) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'show_image_search' => 'yes',
            'show_ai_chat' => 'yes',
            'show_fact_checks' => 'yes',
            'show_cta' => 'yes'
        ), $atts);
        
        $featured_image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
        $post_title = get_the_title();
        $post_excerpt = '';
        
        $template_path = AI_VERIFY_PLUGIN_DIR . 'templates/verification-tools.php';
        
        if (!file_exists($template_path)) {
            return '<!-- AI Verify: Template file not found -->';
        }
        
        ob_start();
        include $template_path;
        $output = ob_get_clean();
        
        return $output;
    }
    
    public function auto_add_to_content($content) {
        // Prevent infinite loops
        static $is_processing = false;
        
        if ($is_processing) {
            return $content;
        }
        
        if (!is_singular('post') || !is_main_query() || !in_the_loop()) {
            return $content;
        }
        
        $auto_add = get_option('ai_verify_auto_add', 'no');
        
        if ($auto_add === 'yes' && strpos($content, 'ai-verify-tools') === false) {
            $is_processing = true;
            $content .= $this->render_verification_tools();
            $is_processing = false;
        }
        
        return $content;
    }
}

// Initialize plugin
function ai_verify_init() {
    return AI_Verify::get_instance();
}
add_action('plugins_loaded', 'ai_verify_init');

// Activation hook - UPDATED FOR REPORT POST TYPE
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('ai_verify_auto_add', 'no');
    add_option('ai_verify_openrouter_key', '');
    add_option('ai_verify_perplexity_key', '');
    add_option('ai_verify_tavily_key', '');
    add_option('ai_verify_firecrawl_key', '');
    add_option('ai_verify_google_factcheck_key', '');
    add_option('ai_verify_factcheck_provider', 'perplexity');
    add_option('ai_verify_scraping_service', 'jina');
    add_option('ai_verify_cta_title', 'Want More Verification Tools?');
    add_option('ai_verify_cta_description', 'Access our full suite of professional disinformation monitoring and investigation tools');
    add_option('ai_verify_reality_defender_key', '');
    add_option('ai_verify_cta_buttons', json_encode(array(
        array('text' => '🔍 OSINT Search', 'url' => 'https://disinformationcommission.com'),
        array('text' => '🌐 Web Monitor', 'url' => 'https://disinformationcommission.com'),
        array('text' => '🛡️ All Tools', 'url' => 'https://disinformationcommission.com')
    )));
    
    // Create database tables for access tracking
    if (class_exists('AI_Verify_Factcheck_Ajax')) {
        AI_Verify_Factcheck_Ajax::init();
    }
    
    // Create fact-check report tables
    if (class_exists('AI_Verify_Factcheck_Database')) {
        AI_Verify_Factcheck_Database::create_tables();
    }
    
    // NEW: Register the Reports custom post type (must be done before flushing rewrite rules)
    if (class_exists('AI_Verify_Factcheck_Post_Generator')) {
        AI_Verify_Factcheck_Post_Generator::register_post_type();
    }

    // Upgrade database for Intelligence Dashboard
    if (class_exists('AI_Verify_Trends_Database_Upgrade')) {
        AI_Verify_Trends_Database_Upgrade::upgrade_to_v2();
    }
    
    // Create initial velocity snapshots
    if (class_exists('AI_Verify_Velocity_Tracker')) {
        wp_schedule_single_event(time() + 60, 'ai_verify_calculate_velocity');
    }
    
    // NEW: Flush rewrite rules to register the custom post type URLs
    flush_rewrite_rules();
    
    error_log('AI Verify: Plugin activated with Reports custom post type');
});

// Deactivation hook - NEW
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
    
    error_log('AI Verify: Plugin deactivated');
});