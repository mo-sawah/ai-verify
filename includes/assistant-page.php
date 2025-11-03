<?php
/**
 * AI Verify - Dedicated AI Fact-Check Assistant Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Assistant_Page {
    
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_assistant_page'));
    }
    
    public static function add_assistant_page() {
        add_menu_page(
            'AI Assistant',          // Page Title
            'AI Assistant',          // Menu Title
            'manage_options',        // Capability
            'ai-verify-assistant',   // Menu Slug
            array(__CLASS__, 'render_assistant_page'), // Callback function
            'dashicons-superhero',   // Icon
            3                        // Position (just below Dashboard)
        );
    }
    
    public static function render_assistant_page() {
        // This function loads the HTML template for the page.
        $template_path = AI_VERIFY_PLUGIN_DIR . 'templates/assistant-page-template.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h2>Error: Assistant template file not found.</h2></div>';
        }
    }
}