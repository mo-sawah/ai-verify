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
        
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en'
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        error_log('AI Verify: Calling URL: ' . $url);
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: API Error: ' . $response->get_error_message());
            wp_send_json_success(array('factchecks' => array()));
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('AI Verify: API Response: ' . print_r($body, true));
        
        $factchecks = array();
        
        if (isset($body['claims']) && is_array($body['claims'])) {
            foreach (array_slice($body['claims'], 0, 3) as $claim) {
                if (!isset($claim['claimReview'][0])) {
                    continue;
                }
                
                $review = $claim['claimReview'][0];
                
                $factchecks[] = array(
                    'claim' => isset($claim['text']) ? $claim['text'] : 'No claim text',
                    'rating' => isset($review['textualRating']) ? $review['textualRating'] : 'Unknown',
                    'source' => isset($review['publisher']['name']) ? $review['publisher']['name'] : 'Unknown',
                    'url' => isset($review['url']) ? $review['url'] : '#',
                    'date' => isset($review['reviewDate']) ? self::format_date($review['reviewDate']) : ''
                );
            }
        }
        
        error_log('AI Verify: Found ' . count($factchecks) . ' fact-checks');
        
        wp_send_json_success(array('factchecks' => $factchecks));
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
}