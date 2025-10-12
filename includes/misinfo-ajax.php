<?php
/**
 * Misinformation Widget AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Misinfo_Ajax {
    
    public static function init() {
        add_action('wp_ajax_ai_verify_get_misinformation', array(__CLASS__, 'get_misinformation'));
        add_action('wp_ajax_nopriv_ai_verify_get_misinformation', array(__CLASS__, 'get_misinformation'));
    }
    
    public static function get_misinformation() {
        check_ajax_referer('ai_verify_misinfo_nonce', 'nonce');
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $api_key = get_option('ai_verify_google_factcheck_key');
        
        if (empty($api_key)) {
            error_log('AI Verify Misinfo: No Google API key configured');
            wp_send_json_error(array('message' => 'API key not configured'));
            return;
        }
        
        // Get popular topics to search
        $search_topics = self::get_search_topics();
        
        $all_items = array();
        
        // Search multiple topics to gather enough misinformation
        foreach ($search_topics as $topic) {
            $items = self::search_misinformation($api_key, $topic);
            $all_items = array_merge($all_items, $items);
            
            // Stop if we have enough items
            if (count($all_items) >= $limit) {
                break;
            }
        }
        
        // Remove duplicates by claim text
        $unique_items = self::remove_duplicates($all_items);
        
        // Sort by date (newest first)
        usort($unique_items, function($a, $b) {
            return strcmp($b['date_raw'], $a['date_raw']);
        });
        
        // Limit results
        $unique_items = array_slice($unique_items, 0, $limit);
        
        error_log('AI Verify Misinfo: Returning ' . count($unique_items) . ' items');
        
        wp_send_json_success(array('items' => $unique_items));
    }
    
    private static function get_search_topics() {
        // Common topics that usually have misinformation
        $topics = array(
            'vaccine',
            'covid',
            'election',
            'climate',
            'immigration',
            'trump',
            'biden',
            'health',
            'politics',
            'science'
        );
        
        // Randomize to get different results each time
        shuffle($topics);
        
        return array_slice($topics, 0, 5); // Search top 5 topics
    }
    
    private static function search_misinformation($api_key, $query) {
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en',
            'pageSize' => 10
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        error_log('AI Verify Misinfo: Searching for: ' . $query);
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            error_log('AI Verify Misinfo: API Error: ' . $response->get_error_message());
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $items = array();
        $current_year = date('Y');
        $max_age = get_option('ai_verify_factcheck_max_age', 2);
        $min_year = $current_year - $max_age;
        
        if (isset($body['claims']) && is_array($body['claims'])) {
            foreach ($body['claims'] as $claim) {
                if (!isset($claim['claimReview'][0])) {
                    continue;
                }
                
                $review = $claim['claimReview'][0];
                $rating = isset($review['textualRating']) ? $review['textualRating'] : '';
                
                // Only include False or Misleading claims
                if (!self::is_false_or_misleading($rating)) {
                    continue;
                }
                
                // Check date
                $date_raw = isset($review['reviewDate']) ? $review['reviewDate'] : '';
                if ($max_age < 999 && !empty($date_raw)) {
                    $review_year = (int) date('Y', strtotime($date_raw));
                    if ($review_year < $min_year) {
                        continue;
                    }
                }
                
                $items[] = array(
                    'claim' => isset($claim['text']) ? $claim['text'] : 'No claim text',
                    'rating' => $rating,
                    'source' => isset($review['publisher']['name']) ? $review['publisher']['name'] : 'Unknown',
                    'url' => isset($review['url']) ? $review['url'] : '#',
                    'date' => !empty($date_raw) ? self::format_date($date_raw) : 'Unknown',
                    'date_raw' => $date_raw
                );
            }
        }
        
        return $items;
    }
    
    private static function is_false_or_misleading($rating) {
        $rating_lower = strtolower($rating);
        
        $false_keywords = array(
            'false',
            'incorrect',
            'fake',
            'misleading',
            'mostly false',
            'pants on fire',
            'four pinocchios',
            'mostly incorrect',
            'bogus',
            'wrong',
            'untrue',
            'fabricated',
            'debunked'
        );
        
        foreach ($false_keywords as $keyword) {
            if (strpos($rating_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function remove_duplicates($items) {
        $seen = array();
        $unique = array();
        
        foreach ($items as $item) {
            $claim_key = strtolower(trim($item['claim']));
            
            if (!isset($seen[$claim_key])) {
                $seen[$claim_key] = true;
                $unique[] = $item;
            }
        }
        
        return $unique;
    }
    
    private static function format_date($date_string) {
        $timestamp = strtotime($date_string);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;
        
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
        } elseif ($diff < 172800) {
            return 'Yesterday';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks != 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}

// Initialize
AI_Verify_Misinfo_Ajax::init();