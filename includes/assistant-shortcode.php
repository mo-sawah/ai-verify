<?php
/**
 * AI Verify - Frontend AI Assistant Shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Assistant_Shortcode {
    
    public static function init() {
        add_shortcode('ai_verify_assistant', array(__CLASS__, 'render_shortcode'));
    }
    
    public static function render_shortcode($atts) {
        // Enqueue assets specifically for this shortcode
        self::enqueue_assets();
        
        // Load the HTML template
        $template_path = AI_VERIFY_PLUGIN_DIR . 'templates/assistant-shortcode-template.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            return '';
        }
    }
    
    public static function enqueue_assets() {
        // Use a static variable to ensure assets are only enqueued once per page load
        static $assets_enqueued = false;
        if ($assets_enqueued) {
            return;
        }

        wp_enqueue_style(
            'ai-verify-assistant-shortcode-styles',
            AI_VERIFY_PLUGIN_URL . 'assets/css/assistant-shortcode.css',
            array(), // No dependencies needed, it will define its own styles
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-assistant-shortcode-script',
            AI_VERIFY_PLUGIN_URL . 'assets/js/assistant-shortcode.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        // Localize script data for AJAX, including a public-facing nonce
        wp_localize_script('ai-verify-assistant-shortcode-script', 'aiVerifyAssistant', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ai_verify_public_chat_nonce')
        ));
        
        $assets_enqueued = true;
    }
}