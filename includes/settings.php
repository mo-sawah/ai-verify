<?php
/**
 * Settings Page for AI Verify
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Settings {
    
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    
    public static function add_settings_page() {
        add_options_page(
            'AI Verify Settings',
            'AI Verify',
            'manage_options',
            'ai-verify-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    public static function register_settings() {
        // General Settings
        register_setting('ai_verify_settings', 'ai_verify_auto_add');
        register_setting('ai_verify_settings', 'ai_verify_openrouter_key');
        register_setting('ai_verify_settings', 'ai_verify_openrouter_model');
        register_setting('ai_verify_settings', 'ai_verify_google_factcheck_key');
        register_setting('ai_verify_settings', 'ai_verify_factcheck_max_age');
        register_setting('ai_verify_settings', 'ai_verify_cta_title');
        register_setting('ai_verify_settings', 'ai_verify_cta_description');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_1_text');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_1_url');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_2_text');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_2_url');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_3_text');
        register_setting('ai_verify_settings', 'ai_verify_cta_button_3_url');
    }
    
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['ai_verify_save_settings'])) {
            check_admin_referer('ai_verify_settings_nonce');
            
            update_option('ai_verify_auto_add', sanitize_text_field($_POST['ai_verify_auto_add']));
            update_option('ai_verify_openrouter_key', sanitize_text_field($_POST['ai_verify_openrouter_key']));
            update_option('ai_verify_openrouter_model', sanitize_text_field($_POST['ai_verify_openrouter_model']));
            update_option('ai_verify_google_factcheck_key', sanitize_text_field($_POST['ai_verify_google_factcheck_key']));
            update_option('ai_verify_factcheck_max_age', intval($_POST['ai_verify_factcheck_max_age']));
            update_option('ai_verify_cta_title', sanitize_text_field($_POST['ai_verify_cta_title']));
            update_option('ai_verify_cta_description', sanitize_textarea_field($_POST['ai_verify_cta_description']));
            update_option('ai_verify_cta_button_1_text', sanitize_text_field($_POST['ai_verify_cta_button_1_text']));
            update_option('ai_verify_cta_button_1_url', esc_url_raw($_POST['ai_verify_cta_button_1_url']));
            update_option('ai_verify_cta_button_2_text', sanitize_text_field($_POST['ai_verify_cta_button_2_text']));
            update_option('ai_verify_cta_button_2_url', esc_url_raw($_POST['ai_verify_cta_button_2_url']));
            update_option('ai_verify_cta_button_3_text', sanitize_text_field($_POST['ai_verify_cta_button_3_text']));
            update_option('ai_verify_cta_button_3_url', esc_url_raw($_POST['ai_verify_cta_button_3_url']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $auto_add = get_option('ai_verify_auto_add', 'no');
        $openrouter_key = get_option('ai_verify_openrouter_key', '');
        $openrouter_model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        $google_key = get_option('ai_verify_google_factcheck_key', '');
        $factcheck_max_age = get_option('ai_verify_factcheck_max_age', 2);
        $cta_title = get_option('ai_verify_cta_title', 'Want More Verification Tools?');
        $cta_description = get_option('ai_verify_cta_description', 'Access our full suite of professional disinformation monitoring and investigation tools');
        $cta_btn_1_text = get_option('ai_verify_cta_button_1_text', '🔍 OSINT Search');
        $cta_btn_1_url = get_option('ai_verify_cta_button_1_url', 'https://disinformationcommission.com');
        $cta_btn_2_text = get_option('ai_verify_cta_button_2_text', '🌐 Web Monitor');
        $cta_btn_2_url = get_option('ai_verify_cta_button_2_url', 'https://disinformationcommission.com');
        $cta_btn_3_text = get_option('ai_verify_cta_button_3_text', '🛡️ All Tools');
        $cta_btn_3_url = get_option('ai_verify_cta_button_3_url', 'https://disinformationcommission.com');
        ?>
        
        <div class="wrap">
            <h1>🔍 AI Verify Settings</h1>
            <p>Configure your fact-check verification tools</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('ai_verify_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2"><h2>General Settings</h2></th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_auto_add">Auto-add to Posts</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_verify_auto_add" id="ai_verify_auto_add" value="yes" <?php checked($auto_add, 'yes'); ?>>
                                Automatically add verification tools to the end of all posts
                            </label>
                            <p class="description">Or use shortcode: <code>[ai_verify]</code></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>🤖 AI Chatbot Settings</h2></th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_openrouter_key">OpenRouter API Key</label>
                        </th>
                        <td>
                            <input type="text" name="ai_verify_openrouter_key" id="ai_verify_openrouter_key" value="<?php echo esc_attr($openrouter_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter.ai</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_openrouter_model">AI Model</label>
                        </th>
                        <td>
                            <select name="ai_verify_openrouter_model" id="ai_verify_openrouter_model">
                                <option value="anthropic/claude-3.5-sonnet" <?php selected($openrouter_model, 'anthropic/claude-3.5-sonnet'); ?>>Claude 3.5 Sonnet (Recommended)</option>
                                <option value="openai/gpt-4o" <?php selected($openrouter_model, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="google/gemini-pro-1.5" <?php selected($openrouter_model, 'google/gemini-pro-1.5'); ?>>Gemini Pro 1.5</option>
                                <option value="meta-llama/llama-3.1-70b-instruct" <?php selected($openrouter_model, 'meta-llama/llama-3.1-70b-instruct'); ?>>Llama 3.1 70B</option>
                            </select>
                            <p class="description">Choose the AI model for the chatbot</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>📰 Fact-Check API Settings</h2></th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_google_factcheck_key">Google Fact Check API Key</label>
                        </th>
                        <td>
                            <input type="text" name="ai_verify_google_factcheck_key" id="ai_verify_google_factcheck_key" value="<?php echo esc_attr($google_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> (Free)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_factcheck_max_age">Show Fact-Checks From</label>
                        </th>
                        <td>
                            <select name="ai_verify_factcheck_max_age" id="ai_verify_factcheck_max_age">
                                <option value="1" <?php selected($factcheck_max_age, 1); ?>>Last year only</option>
                                <option value="2" <?php selected($factcheck_max_age, 2); ?>>Last 2 years</option>
                                <option value="3" <?php selected($factcheck_max_age, 3); ?>>Last 3 years</option>
                                <option value="5" <?php selected($factcheck_max_age, 5); ?>>Last 5 years</option>
                                <option value="999" <?php selected($factcheck_max_age, 999); ?>>All time (no filter)</option>
                            </select>
                            <p class="description">Filter out old fact-checks to show only recent ones</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2"><h2>🎯 Call-to-Action Settings</h2></th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_cta_title">CTA Title</label>
                        </th>
                        <td>
                            <input type="text" name="ai_verify_cta_title" id="ai_verify_cta_title" value="<?php echo esc_attr($cta_title); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ai_verify_cta_description">CTA Description</label>
                        </th>
                        <td>
                            <textarea name="ai_verify_cta_description" id="ai_verify_cta_description" class="large-text" rows="3"><?php echo esc_textarea($cta_description); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Button 1</th>
                        <td>
                            <input type="text" name="ai_verify_cta_button_1_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_1_text); ?>" class="regular-text" style="margin-bottom: 5px;">
                            <br>
                            <input type="url" name="ai_verify_cta_button_1_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_1_url); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Button 2</th>
                        <td>
                            <input type="text" name="ai_verify_cta_button_2_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_2_text); ?>" class="regular-text" style="margin-bottom: 5px;">
                            <br>
                            <input type="url" name="ai_verify_cta_button_2_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_2_url); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Button 3</th>
                        <td>
                            <input type="text" name="ai_verify_cta_button_3_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_3_text); ?>" class="regular-text" style="margin-bottom: 5px;">
                            <br>
                            <input type="url" name="ai_verify_cta_button_3_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_3_url); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="ai_verify_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <div class="card" style="max-width: 800px; margin-top: 30px;">
                <h2>📖 How to Use</h2>
                <ol>
                    <li><strong>OpenRouter API:</strong> Sign up at OpenRouter.ai and add credits ($5 = ~500-1000 conversations)</li>
                    <li><strong>Google Fact Check API:</strong> Enable "Fact Check Tools API" in Google Cloud Console (Free, no billing required)</li>
                    <li><strong>Shortcode:</strong> Use <code>[ai_verify]</code> anywhere in your posts</li>
                    <li><strong>Auto-add:</strong> Check the box above to add tools automatically to all posts</li>
                </ol>
            </div>
        </div>
        <?php
    }
}