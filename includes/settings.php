<?php
/**
 * Settings Page for AI Verify
 * Updated with Perplexity API and Fact-Check Provider Selection
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
        register_setting('ai_verify_settings', 'ai_verify_perplexity_key');
        register_setting('ai_verify_settings', 'ai_verify_factcheck_provider');
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
        register_setting('ai_verify_settings', 'ai_verify_results_page_url');
        register_setting('ai_verify_settings', 'ai_verify_firecrawl_key');
        register_setting('ai_verify_settings', 'ai_verify_scraping_service');
        register_setting('ai_verify_settings', 'ai_verify_tavily_key');
    }
    
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['ai_verify_save_settings'])) {
            check_admin_referer('ai_verify_settings_nonce');
            
            update_option('ai_verify_auto_add', isset($_POST['ai_verify_auto_add']) ? 'yes' : 'no');
            update_option('ai_verify_openrouter_key', sanitize_text_field($_POST['ai_verify_openrouter_key']));
            update_option('ai_verify_openrouter_model', sanitize_text_field($_POST['ai_verify_openrouter_model']));
            update_option('ai_verify_perplexity_key', sanitize_text_field($_POST['ai_verify_perplexity_key']));
            update_option('ai_verify_factcheck_provider', sanitize_text_field($_POST['ai_verify_factcheck_provider']));
            update_option('ai_verify_google_factcheck_key', sanitize_text_field($_POST['ai_verify_google_factcheck_key']));
            update_option('ai_verify_factcheck_max_age', intval($_POST['ai_verify_factcheck_max_age']));
            update_option('ai_verify_results_page_url', esc_url_raw($_POST['ai_verify_results_page_url']));
            update_option('ai_verify_cta_title', sanitize_text_field($_POST['ai_verify_cta_title']));
            update_option('ai_verify_cta_description', sanitize_textarea_field($_POST['ai_verify_cta_description']));
            update_option('ai_verify_cta_button_1_text', sanitize_text_field($_POST['ai_verify_cta_button_1_text']));
            update_option('ai_verify_cta_button_1_url', esc_url_raw($_POST['ai_verify_cta_button_1_url']));
            update_option('ai_verify_cta_button_2_text', sanitize_text_field($_POST['ai_verify_cta_button_2_text']));
            update_option('ai_verify_cta_button_2_url', esc_url_raw($_POST['ai_verify_cta_button_2_url']));
            update_option('ai_verify_cta_button_3_text', sanitize_text_field($_POST['ai_verify_cta_button_3_text']));
            update_option('ai_verify_cta_button_3_url', esc_url_raw($_POST['ai_verify_cta_button_3_url']));
            update_option('ai_verify_firecrawl_key', sanitize_text_field($_POST['ai_verify_firecrawl_key']));
            update_option('ai_verify_scraping_service', sanitize_text_field($_POST['ai_verify_scraping_service']));

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        // Get all options
        $auto_add = get_option('ai_verify_auto_add', 'no');
        $openrouter_key = get_option('ai_verify_openrouter_key', '');
        $openrouter_model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        $perplexity_key = get_option('ai_verify_perplexity_key', '');
        $factcheck_provider = get_option('ai_verify_factcheck_provider', 'perplexity');
        $google_key = get_option('ai_verify_google_factcheck_key', '');
        $factcheck_max_age = get_option('ai_verify_factcheck_max_age', 2);
        $results_page_url = get_option('ai_verify_results_page_url', '');
        $cta_title = get_option('ai_verify_cta_title', 'Want More Verification Tools?');
        $cta_description = get_option('ai_verify_cta_description', 'Access our full suite of professional disinformation monitoring and investigation tools');
        $cta_btn_1_text = get_option('ai_verify_cta_button_1_text', '🔍 OSINT Search');
        $cta_btn_1_url = get_option('ai_verify_cta_button_1_url', 'https://disinformationcommission.com');
        $cta_btn_2_text = get_option('ai_verify_cta_button_2_text', '🌐 Web Monitor');
        $cta_btn_2_url = get_option('ai_verify_cta_button_2_url', 'https://disinformationcommission.com');
        $cta_btn_3_text = get_option('ai_verify_cta_button_3_text', '🛡️ All Tools');
        $cta_btn_3_url = get_option('ai_verify_cta_button_3_url', 'https://disinformationcommission.com');
        $firecrawl_key = get_option('ai_verify_firecrawl_key', '');
        $scraping_service = get_option('ai_verify_scraping_service', 'jina');
        ?>
        
        <div class="wrap">
            <h1>🔍 AI Verify Settings</h1>
            <p>Configure your professional fact-check verification system</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('ai_verify_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr><th colspan="2"><h2>General Settings</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_auto_add">Auto-add to Posts</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_verify_auto_add" id="ai_verify_auto_add" value="yes" <?php checked($auto_add, 'yes'); ?>>
                                Automatically add verification tools to the end of all posts
                            </label>
                            <p class="description">Or use shortcode: <code>[ai_verify]</code></p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h2>⚙️ Scraping Service Settings</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_scraping_service">Scraping Service</label></th>
                        <td>
                            <select name="ai_verify_scraping_service" id="ai_verify_scraping_service">
                                <option value="jina" <?php selected($scraping_service, 'jina'); ?>>Jina Reader API (Free)</option>
                                <option value="firecrawl" <?php selected($scraping_service, 'firecrawl'); ?>>Firecrawl API (Recommended)</option>
                            </select>
                            <p class="description">Choose the service to scrape web content. Firecrawl is highly recommended for reliability.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_firecrawl_key">Firecrawl API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_firecrawl_key" id="ai_verify_firecrawl_key" value="<?php echo esc_attr($firecrawl_key); ?>" class="regular-text">
                            <p class="description">Required if using Firecrawl API. Get your key from <a href="https://firecrawl.dev" target="_blank">Firecrawl.dev</a>.</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>🔬 Fact-Check AI Provider</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_factcheck_provider">Fact-Check Provider</label></th>
                        <td>
                            <select name="ai_verify_factcheck_provider" id="ai_verify_factcheck_provider">
                                <option value="perplexity" <?php selected($factcheck_provider, 'perplexity'); ?>>Perplexity AI (Recommended - Has Built-in Web Search)</option>
                                <option value="openrouter" <?php selected($factcheck_provider, 'openrouter'); ?>>OpenRouter (Claude/GPT)</option>
                            </select>
                            <p class="description"><strong>Recommended:</strong> Perplexity has built-in web search and is specifically designed for fact-checking.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="ai_verify_perplexity_key">Perplexity API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_perplexity_key" id="ai_verify_perplexity_key" value="<?php echo esc_attr($perplexity_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://www.perplexity.ai/settings/api" target="_blank">Perplexity.ai</a> (~$5 for 1000 searches)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="ai_verify_tavily_key">Tavily API Key (For OpenRouter Web Search)</label></th>
                        <td>
                            <input type="text" name="ai_verify_tavily_key" id="ai_verify_tavily_key" value="<?php echo esc_attr(get_option('ai_verify_tavily_key', '')); ?>" class="regular-text">
                            <p class="description">Required if using OpenRouter. Get FREE key from <a href="https://tavily.com" target="_blank">Tavily.com</a> (1000 searches/month free)</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>🤖 AI Chatbot Settings (Optional)</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_openrouter_key">OpenRouter API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_openrouter_key" id="ai_verify_openrouter_key" value="<?php echo esc_attr($openrouter_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter.ai</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_openrouter_model">AI Model</label></th>
                        <td>
                            <select name="ai_verify_openrouter_model" id="ai_verify_openrouter_model">
                                <option value="anthropic/claude-3.5-sonnet" <?php selected($openrouter_model, 'anthropic/claude-3.5-sonnet'); ?>>Claude 3.5 Sonnet (Recommended)</option>
                                <option value="openai/gpt-4o" <?php selected($openrouter_model, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="google/gemini-pro-1.5" <?php selected($openrouter_model, 'google/gemini-pro-1.5'); ?>>Gemini Pro 1.5</option>
                                <option value="meta-llama/llama-3.1-70b-instruct" <?php selected($openrouter_model, 'meta-llama/llama-3.1-70b-instruct'); ?>>Llama 3.1 70B</option>
                            </select>
                            <p class="description">Choose the AI model for chatbot and fallback fact-checking</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>📰 Google Fact Check API (Optional)</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_google_factcheck_key">Google Fact Check API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_google_factcheck_key" id="ai_verify_google_factcheck_key" value="<?php echo esc_attr($google_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> (Free - checks existing fact-checks)</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>📊 Fact-Check System Settings</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_results_page_url">Results Page URL</label></th>
                        <td>
                            <input type="url" name="ai_verify_results_page_url" id="ai_verify_results_page_url" value="<?php echo esc_attr($results_page_url); ?>" class="regular-text">
                            <p class="description">Enter the full URL of the page with the <code>[ai_factcheck_results]</code> shortcode.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_factcheck_max_age">Show Fact-Checks From</label></th>
                        <td>
                            <select name="ai_verify_factcheck_max_age" id="ai_verify_factcheck_max_age">
                                <option value="1" <?php selected($factcheck_max_age, 1); ?>>Last year only</option>
                                <option value="2" <?php selected($factcheck_max_age, 2); ?>>Last 2 years</option>
                                <option value="5" <?php selected($factcheck_max_age, 5); ?>>Last 5 years</option>
                                <option value="999" <?php selected($factcheck_max_age, 999); ?>>All time (no filter)</option>
                            </select>
                            <p class="description">Filter out old fact-checks to show only recent ones</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>🎯 Call-to-Action Settings</h2></th></tr>
                    <tr><th scope="row"><label for="ai_verify_cta_title">CTA Title</label></th><td><input type="text" name="ai_verify_cta_title" id="ai_verify_cta_title" value="<?php echo esc_attr($cta_title); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label for="ai_verify_cta_description">CTA Description</label></th><td><textarea name="ai_verify_cta_description" id="ai_verify_cta_description" class="large-text" rows="3"><?php echo esc_textarea($cta_description); ?></textarea></td></tr>
                    <tr><th scope="row">Button 1</th><td><input type="text" name="ai_verify_cta_button_1_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_1_text); ?>" class="regular-text" style="margin-bottom: 5px;"><br><input type="url" name="ai_verify_cta_button_1_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_1_url); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">Button 2</th><td><input type="text" name="ai_verify_cta_button_2_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_2_text); ?>" class="regular-text" style="margin-bottom: 5px;"><br><input type="url" name="ai_verify_cta_button_2_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_2_url); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">Button 3</th><td><input type="text" name="ai_verify_cta_button_3_text" placeholder="Button Text" value="<?php echo esc_attr($cta_btn_3_text); ?>" class="regular-text" style="margin-bottom: 5px;"><br><input type="url" name="ai_verify_cta_button_3_url" placeholder="https://example.com" value="<?php echo esc_url($cta_btn_3_url); ?>" class="regular-text"></td></tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="ai_verify_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <div class="card" style="max-width: 900px; margin-top: 30px;">
                <h2>📖 Setup Guide</h2>
                <ol>
                    <li><strong>Scraping API:</strong> Get Firecrawl API key (recommended) from <a href="https://firecrawl.dev" target="_blank">Firecrawl.dev</a></li>
                    <li><strong>Perplexity API (Recommended):</strong> Sign up at <a href="https://www.perplexity.ai/settings/api" target="_blank">Perplexity.ai</a> (~$5 for 1000 fact-checks with web search)</li>
                    <li><strong>OR OpenRouter API:</strong> Sign up at <a href="https://openrouter.ai" target="_blank">OpenRouter.ai</a> and add credits ($5 = ~500 fact-checks)</li>
                    <li><strong>Google Fact Check API (Optional):</strong> Enable "Fact Check Tools API" in Google Cloud Console (Free, checks existing fact-checks)</li>
                    <li><strong>Create Results Page:</strong> Create a new page and add shortcode: <code>[ai_factcheck_results]</code></li>
                    <li><strong>Add to Posts:</strong> Use shortcode <code>[ai_verify]</code> or enable "Auto-add to Posts"</li>
                </ol>
                
                <h3>💡 Why Perplexity is Recommended:</h3>
                <ul>
                    <li>✅ Built-in web search (no additional configuration needed)</li>
                    <li>✅ Specifically designed for research and fact-checking</li>
                    <li>✅ Returns sources with every answer</li>
                    <li>✅ More accurate than GPT for factual queries</li>
                    <li>✅ Cost-effective (~$0.005 per fact-check)</li>
                </ul>
                
                <h3>📊 How It Works:</h3>
                <p><strong>Step 1:</strong> Scrape article with Firecrawl/Jina<br>
                <strong>Step 2:</strong> Extract claims with ClaimBuster (FREE) or AI<br>
                <strong>Step 3:</strong> Check Google Fact Check API for existing fact-checks<br>
                <strong>Step 4:</strong> Use Perplexity/OpenRouter with WEB SEARCH to verify new claims<br>
                <strong>Step 5:</strong> Detect propaganda techniques<br>
                <strong>Step 6:</strong> Calculate credibility score and generate professional report</p>
            </div>
        </div>
        <?php
    }
}