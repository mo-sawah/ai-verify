<?php
/**
 * Misinformation Widget AJAX Handler - FIXED
 * Added caching to prevent API calls on every page load
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Misinfo_Ajax {
    
    private static $cache_duration = 1800; // 30 minutes
    
    public static function init() {
        add_action('wp_ajax_ai_verify_get_misinformation', array(__CLASS__, 'get_misinformation'));
        add_action('wp_ajax_nopriv_ai_verify_get_misinformation', array(__CLASS__, 'get_misinformation'));
    }
    
    public static function get_misinformation() {
        check_ajax_referer('ai_verify_misinfo_nonce', 'nonce');
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        // ADDED: Check cache first
        $cache_key = 'ai_verify_misinfo_data_' . $limit;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            wp_send_json_success(array('items' => $cached_data, 'from_cache' => true));
            return;
        }
        
        $api_key = get_option('ai_verify_google_factcheck_key');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key not configured'));
            return;
        }
        
        $search_topics = self::get_search_topics();
        $all_items = array();
        
        foreach ($search_topics as $topic) {
            $items = self::search_misinformation($api_key, $topic);
            $all_items = array_merge($all_items, $items);
            
            if (count($all_items) >= $limit) {
                break;
            }
        }
        
        $unique_items = self::remove_duplicates($all_items);
        
        usort($unique_items, function($a, $b) {
            return strcmp($b['date_raw'], $a['date_raw']);
        });
        
        $unique_items = array_slice($unique_items, 0, $limit);
        
        // ADDED: Store in cache for 30 minutes
        set_transient($cache_key, $unique_items, self::$cache_duration);
        
        wp_send_json_success(array('items' => $unique_items, 'from_cache' => false));
    }
    
    private static function get_search_topics() {
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
        
        shuffle($topics);
        return array_slice($topics, 0, 5);
    }
    
    private static function search_misinformation($api_key, $query) {
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en',
            'pageSize' => 10
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
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
                
                if (!self::is_false_or_misleading($rating)) {
                    continue;
                }
                
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

AI_Verify_Misinfo_Ajax::init();