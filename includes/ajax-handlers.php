<?php
/**
 * AJAX Handlers for AI Verify
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Ajax {
    
    public static function init() {
        add_action('wp_ajax_ai_verify_chat', array(__CLASS__, 'handle_chat'));
        add_action('wp_ajax_nopriv_ai_verify_chat', array(__CLASS__, 'handle_chat'));
        
        add_action('wp_ajax_ai_verify_get_factchecks', array(__CLASS__, 'get_fact_checks'));
        add_action('wp_ajax_nopriv_ai_verify_get_factchecks', array(__CLASS__, 'get_fact_checks'));
        
        // NEW: Background jobs handler
        add_action('wp_ajax_ai_verify_run_background_jobs', array(__CLASS__, 'run_background_jobs'));
    }
    
    public static function handle_chat() {
        check_ajax_referer('ai_verify_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message']);
        $post_id = intval($_POST['post_id']);
        $context = sanitize_textarea_field($_POST['context']);
        
        $api_key = get_option('ai_verify_openrouter_key');
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'OpenRouter API key not configured. Please contact site administrator.'));
        }
        
        $post_title = get_the_title($post_id);
        $post_content = get_post_field('post_content', $post_id);
        $post_excerpt = get_the_excerpt($post_id);
        
        $system_prompt = "You are a helpful fact-checking assistant. You help users understand fact-checks and verify claims. Be concise, accurate, and cite sources when possible. The user is reading an article titled: '{$post_title}'. Context: {$context}";
        
        $response = self::call_openrouter_api($api_key, $model, $message, $system_prompt);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success(array('message' => $response));
    }
    
    private static function call_openrouter_api($api_key, $model, $message, $system_prompt) {
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        
        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $message
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 500
        );
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to connect to AI service');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('api_error', 'Invalid response from AI service');
        }
        
        return $body['choices'][0]['message']['content'];
    }
    
    public static function get_fact_checks() {
        check_ajax_referer('ai_verify_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        $api_key = get_option('ai_verify_google_factcheck_key');
        
        // Debug logging
        error_log('AI Verify: Searching fact-checks for: ' . $query);
        error_log('AI Verify: API Key configured: ' . (!empty($api_key) ? 'Yes' : 'No'));
        
        if (empty($api_key)) {
            error_log('AI Verify: No Google API key configured');
            wp_send_json_success(array('factchecks' => array()));
            return;
        }
        
        // Try the search
        $factchecks = self::search_factchecks($api_key, $query);
        
        // If no results and query has multiple words, try with just first 2-3 key words
        if (empty($factchecks) && str_word_count($query) > 3) {
            $words = explode(' ', $query);
            $shorter_query = implode(' ', array_slice($words, 0, 3));
            error_log('AI Verify: Trying shorter query: ' . $shorter_query);
            $factchecks = self::search_factchecks($api_key, $shorter_query);
        }
        
        // If still no results, try just the first significant word
        if (empty($factchecks) && str_word_count($query) > 1) {
            $words = explode(' ', $query);
            $single_word = $words[0];
            error_log('AI Verify: Trying single word: ' . $single_word);
            $factchecks = self::search_factchecks($api_key, $single_word);
        }
        
        error_log('AI Verify: Final count: ' . count($factchecks) . ' fact-checks');
        
        wp_send_json_success(array('factchecks' => $factchecks));
    }
    
    private static function search_factchecks($api_key, $query) {
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en',
            'pageSize' => 10
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        error_log('AI Verify: Calling URL: ' . $url);
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: API Error: ' . $response->get_error_message());
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('AI Verify: API Response: ' . print_r($body, true));
        
        $factchecks = array();
        $current_year = date('Y');
        $max_age = get_option('ai_verify_factcheck_max_age', 2);
        $min_year = $current_year - $max_age;
        
        if (isset($body['claims']) && is_array($body['claims'])) {
            foreach ($body['claims'] as $claim) {
                if (!isset($claim['claimReview'][0])) {
                    continue;
                }
                
                $review = $claim['claimReview'][0];
                
                // Skip old fact-checks based on settings
                if ($max_age < 999 && isset($review['reviewDate'])) {
                    $review_year = (int) date('Y', strtotime($review['reviewDate']));
                    if ($review_year < $min_year) {
                        error_log('AI Verify: Skipping old fact-check from ' . $review_year);
                        continue;
                    }
                }
                
                $factchecks[] = array(
                    'claim' => isset($claim['text']) ? $claim['text'] : 'No claim text',
                    'rating' => isset($review['textualRating']) ? $review['textualRating'] : 'Unknown',
                    'source' => isset($review['publisher']['name']) ? $review['publisher']['name'] : 'Unknown',
                    'url' => isset($review['url']) ? $review['url'] : '#',
                    'date' => isset($review['reviewDate']) ? self::format_date($review['reviewDate']) : ''
                );
                
                // Stop when we have 3 recent fact-checks
                if (count($factchecks) >= 3) {
                    break;
                }
            }
        }
        
        return $factchecks;
    }
    
    private static function format_date($date_string) {
        $timestamp = strtotime($date_string);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;
        
        if ($diff < 86400) {
            return 'Today';
        } elseif ($diff < 172800) {
            return 'Yesterday';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' days ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 604800) . ' weeks ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
    
    /**
     * Run background jobs manually (NEW METHOD)
     */
    public static function run_background_jobs() {
        check_ajax_referer('ai_verify_background', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        if (class_exists('AI_Verify_Background_Aggregator')) {
            $result = AI_Verify_Background_Aggregator::run_all_now();
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Background Aggregator class not found');
        }
    }
}