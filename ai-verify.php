<?php
/**
 * Plugin Name: AI Verify
 * Plugin URI: https://sawahsolutions.com
 * Description: Professional fact-check verification tools with AI chatbot, reverse image search, and related fact-checks
 * Version: 1.0.2
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
define('AI_VERIFY_VERSION', '1.0.2');
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
    }
    
    public function enqueue_assets() {
        // Only load on singular posts
        if (!is_singular('post')) {
            return;
        }
        
        wp_enqueue_style(
            'ai-verify-styles',
            AI_VERIFY_PLUGIN_URL . 'assets/css/ai-verify.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-script',
            AI_VERIFY_PLUGIN_URL . 'assets/js/ai-verify.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-script', 'aiVerifyData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_nonce'),
            'post_id' => get_the_ID()
        ));
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

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('ai_verify_auto_add', 'no');
    add_option('ai_verify_openrouter_key', '');
    add_option('ai_verify_google_factcheck_key', '');
    add_option('ai_verify_cta_title', 'Want More Verification Tools?');
    add_option('ai_verify_cta_description', 'Access our full suite of professional disinformation monitoring and investigation tools');
    add_option('ai_verify_cta_buttons', json_encode(array(
        array('text' => '🔍 OSINT Search', 'url' => 'https://disinformationcommission.com'),
        array('text' => '🌐 Web Monitor', 'url' => 'https://disinformationcommission.com'),
        array('text' => '🛡️ All Tools', 'url' => 'https://disinformationcommission.com')
    )));
});