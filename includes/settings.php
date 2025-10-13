<?php
/**
 * Settings Page for AI Verify
 * UPDATED: Added Tavily API integration for web search
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
        register_setting('ai_verify_settings', 'ai_verify_tavily_key'); // NEW
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
            update_option('ai_verify_tavily_key', sanitize_text_field($_POST['ai_verify_tavily_key'])); // NEW

            echo '<div class="notice notice-success"><p><strong>✓ Settings saved successfully!</strong></p></div>';
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
        $tavily_key = get_option('ai_verify_tavily_key', ''); // NEW
        ?>
        
        <div class="wrap">
            <h1>🔍 AI Verify Settings</h1>
            <p>Configure your professional fact-check verification system</p>
            
            <form method="post" action="" style="max-width: 1200px;">
                <?php wp_nonce_field('ai_verify_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr><th colspan="2"><h2>⚙️ General Settings</h2></th></tr>
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

                    <tr><th colspan="2"><h2>🌐 Web Scraping Service</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_scraping_service">Scraping Service</label></th>
                        <td>
                            <select name="ai_verify_scraping_service" id="ai_verify_scraping_service">
                                <option value="jina" <?php selected($scraping_service, 'jina'); ?>>Jina Reader API (Free)</option>
                                <option value="firecrawl" <?php selected($scraping_service, 'firecrawl'); ?>>Firecrawl API (Recommended)</option>
                            </select>
                            <p class="description">Choose the service to scrape web content. Firecrawl is recommended for reliability.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_firecrawl_key">Firecrawl API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_firecrawl_key" id="ai_verify_firecrawl_key" value="<?php echo esc_attr($firecrawl_key); ?>" class="regular-text">
                            <p class="description">Required if using Firecrawl. Get your key from <a href="https://firecrawl.dev" target="_blank">Firecrawl.dev</a></p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>🔬 Fact-Check AI Provider</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_factcheck_provider">Primary Fact-Check Provider</label></th>
                        <td>
                            <select name="ai_verify_factcheck_provider" id="ai_verify_factcheck_provider">
                                <option value="perplexity" <?php selected($factcheck_provider, 'perplexity'); ?>>✨ Perplexity AI (RECOMMENDED - Built-in Web Search)</option>
                                <option value="openrouter" <?php selected($factcheck_provider, 'openrouter'); ?>>OpenRouter (Claude/GPT) + External Search</option>
                            </select>
                            <p class="description"><strong>💡 Recommended:</strong> Perplexity has built-in real-time web search and is specifically optimized for fact-checking. OpenRouter requires Tavily/Firecrawl for web search.</p>
                        </td>
                    </tr>
                    
                    <tr style="background: #f0fdf4;">
                        <th scope="row"><label for="ai_verify_perplexity_key">✨ Perplexity API Key (Primary)</label></th>
                        <td>
                            <input type="text" name="ai_verify_perplexity_key" id="ai_verify_perplexity_key" value="<?php echo esc_attr($perplexity_key); ?>" class="regular-text">
                            <p class="description">
                                <strong>BEST OPTION:</strong> Get your API key from <a href="https://www.perplexity.ai/settings/api" target="_blank">Perplexity.ai</a><br>
                                💰 Cost: ~$5 for 1000 fact-checks | Uses: <code>llama-3.1-sonar-large-128k-online</code><br>
                                ✓ Built-in web search | ✓ Source citations | ✓ Optimized for factual queries
                            </p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h2>🔍 Web Search APIs (For OpenRouter Fallback)</h2></th></tr>
                    <tr style="background: #fffbeb;">
                        <th scope="row"><label for="ai_verify_tavily_key">⭐ Tavily API Key (Recommended)</label></th>
                        <td>
                            <input type="text" name="ai_verify_tavily_key" id="ai_verify_tavily_key" value="<?php echo esc_attr($tavily_key); ?>" class="regular-text">
                            <p class="description">
                                <strong>RECOMMENDED FOR OPENROUTER:</strong> Get FREE key from <a href="https://tavily.com" target="_blank">Tavily.com</a><br>
                                💰 FREE: 1000 searches/month | AI-optimized search for fact-checking<br>
                                Used when OpenRouter is selected as fact-check provider
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="ai_verify_openrouter_key">OpenRouter API Key (Fallback)</label></th>
                        <td>
                            <input type="text" name="ai_verify_openrouter_key" id="ai_verify_openrouter_key" value="<?php echo esc_attr($openrouter_key); ?>" class="regular-text">
                            <p class="description">
                                Fallback AI provider. Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter.ai</a><br>
                                💰 Cost varies by model | Requires Tavily or Firecrawl for web search
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_openrouter_model">OpenRouter Model</label></th>
                        <td>
                            <select name="ai_verify_openrouter_model" id="ai_verify_openrouter_model">
                                <option value="anthropic/claude-3.5-sonnet" <?php selected($openrouter_model, 'anthropic/claude-3.5-sonnet'); ?>>Claude 3.5 Sonnet (Best Quality)</option>
                                <option value="openai/gpt-4o" <?php selected($openrouter_model, 'openai/gpt-4o'); ?>>GPT-4o</option>
                                <option value="google/gemini-pro-1.5" <?php selected($openrouter_model, 'google/gemini-pro-1.5'); ?>>Gemini Pro 1.5</option>
                                <option value="meta-llama/llama-3.1-70b-instruct" <?php selected($openrouter_model, 'meta-llama/llama-3.1-70b-instruct'); ?>>Llama 3.1 70B</option>
                            </select>
                            <p class="description">Used when OpenRouter is selected as fact-check provider</p>
                        </td>
                    </tr>
                    
                    <tr><th colspan="2"><h2>📰 Google Fact Check API (Optional)</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="ai_verify_google_factcheck_key">Google Fact Check API Key</label></th>
                        <td>
                            <input type="text" name="ai_verify_google_factcheck_key" id="ai_verify_google_factcheck_key" value="<?php echo esc_attr($google_key); ?>" class="regular-text">
                            <p class="description">
                                Checks existing fact-checks from trusted sources. Get FREE key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a><br>
                                Enable "Fact Check Tools API" in your Google Cloud project
                            </p>
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
                    <input type="submit" name="ai_verify_save_settings" class="button button-primary" value="💾 Save All Settings">
                </p>
            </form>
            
            <div class="card" style="max-width: 1200px; margin-top: 30px;">
                <h2>📖 UPDATED Setup Guide (v2.0)</h2>
                
                <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; color: #059669;">✨ Recommended Setup (Best Results)</h3>
                    <ol style="margin: 0;">
                        <li><strong>Scraping:</strong> Firecrawl API ($) - <a href="https://firecrawl.dev" target="_blank">Get Key</a></li>
                        <li><strong>Fact-Checking:</strong> Perplexity API (~$5 for 1000 checks) - <a href="https://www.perplexity.ai/settings/api" target="_blank">Get Key</a></li>
                        <li><strong>Google Fact Check:</strong> (Optional, FREE) - <a href="https://console.cloud.google.com" target="_blank">Get Key</a></li>
                    </ol>
                    <p style="margin-bottom: 0;"><strong>Why this works best:</strong> Perplexity has built-in real-time web search specifically optimized for factual queries. No additional configuration needed!</p>
                </div>

                <div style="background: #fffbeb; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; color: #d97706;">💰 Budget Setup (Free/Cheap Options)</h3>
                    <ol style="margin: 0;">
                        <li><strong>Scraping:</strong> Jina Reader (FREE)</li>
                        <li><strong>Web Search:</strong> Tavily API (FREE - 1000/month) - <a href="https://tavily.com" target="_blank">Get Key</a></li>
                        <li><strong>Fact-Checking:</strong> OpenRouter (~$5-10 for 500-1000 checks) - <a href="https://openrouter.ai" target="_blank">Get Key</a></li>
                    </ol>
                    <p style="margin-bottom: 0;"><strong>Note:</strong> This requires Tavily for web search. Works well but requires more configuration.</p>
                </div>
                
                <h3>🔄 How the NEW System Works:</h3>
                <ol>
                    <li><strong>Step 1:</strong> Scrape article content (Firecrawl or Jina)</li>
                    <li><strong>Step 2:</strong> Extract verifiable claims (ClaimBuster API - FREE)</li>
                    <li><strong>Step 3:</strong> Check Google Fact Check API for existing fact-checks (if key provided)</li>
                    <li><strong>Step 4:</strong> PRIMARY METHOD:
                        <ul>
                            <li><strong>If Perplexity selected:</strong> Direct call to Perplexity (built-in web search) ✨</li>
                            <li><strong>If OpenRouter selected:</strong> Search web with Tavily → Send results to OpenRouter AI</li>
                        </ul>
                    </li>
                    <li><strong>Step 5:</strong> Detect propaganda techniques</li>
                    <li><strong>Step 6:</strong> Calculate credibility score (IMPROVED SCORING)</li>
                    <li><strong>Step 7:</strong> Generate professional report with sources</li>
                </ol>
                
                <h3>🎯 What Changed in v2.0:</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <li>✅ <strong>Perplexity optimization:</strong> No redundant Firecrawl calls - uses built-in search</li>
                    <li>✅ <strong>Tavily integration:</strong> AI-optimized search API (FREE tier available)</li>
                    <li>✅ <strong>Better scoring:</strong> "Unverified" = neutral (0.5), not penalty</li>
                    <li>✅ <strong>Paywall UX:</strong> Shows AFTER report generation (not before)</li>
                    <li>✅ <strong>Cookie fix:</strong> Only sets after successful AJAX submission</li>
                    <li>✅ <strong>Smarter fallbacks:</strong> Tries best method first, falls back gracefully</li>
                </ul>

                <h3>💡 API Comparison:</h3>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <tr style="background: #f3f4f6;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #e5e7eb;">Service</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #e5e7eb;">Cost</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #e5e7eb;">Best For</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #e5e7eb;">Web Search?</th>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>Perplexity</strong></td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">~$0.005/check</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Primary fact-checking</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">✅ Built-in</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>Tavily</strong></td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">FREE (1000/mo)</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Web search for OpenRouter</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">✅ Yes</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>Firecrawl</strong></td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Paid plans</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Scraping + search (fallback)</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">✅ Yes</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>OpenRouter</strong></td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Varies by model</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Fallback AI provider</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">❌ Needs external</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>Google Fact Check</strong></td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">FREE</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">Check existing fact-checks</td>
                        <td style="padding: 10px; border: 1px solid #e5e7eb;">N/A</td>
                    </tr>
                </table>

                <div style="background: #fef2f2; padding: 20px; border-radius: 8px; border-left: 4px solid #ef4444; margin-top: 20px;">
                    <h3 style="margin-top: 0; color: #dc2626;">⚠️ Important Notes:</h3>
                    <ul style="margin: 0;">
                        <li>Create a results page with shortcode: <code>[ai_factcheck_results]</code></li>
                        <li>Set the results page URL in settings above</li>
                        <li>Test with a small API budget first to verify everything works</li>
                        <li>Monitor your API usage through each provider's dashboard</li>
                        <li>Paywall now shows AFTER report is generated for better UX</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
            .form-table th { width: 280px; }
            .form-table code { 
                background: #f3f4f6; 
                padding: 2px 6px; 
                border-radius: 3px;
                font-size: 13px;
            }
            .card h3 { color: #1f2937; margin-top: 20px; }
            .card ul { line-height: 1.8; }
        </style>
        <?php
    }
}