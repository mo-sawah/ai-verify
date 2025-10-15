<?php
/**
 * External Fact-Check Aggregator
 * Pulls trending misinformation from top fact-checking organizations
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_External_Factcheck_Aggregator {
    
    private static $cache_duration = 3600; // 1 hour cache
    
    /**
     * Get all trending fact-checks from external sources
     */
    public static function get_all_trending($limit = 20, $category = null) {
        $cache_key = 'ai_verify_external_trending_' . $category . '_' . $limit;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $all_factchecks = array();
        
        // Aggregate from multiple sources
        $sources = array(
            'google_factcheck' => self::get_google_factcheck_claims($limit),
            'politifact_rss' => self::get_politifact_rss(),
            'snopes_rss' => self::get_snopes_rss(),
            'factcheckorg_rss' => self::get_factcheckorg_rss(),
            'afp_rss' => self::get_afp_factcheck_rss(),
            'fullfact_rss' => self::get_fullfact_rss()
        );
        
        foreach ($sources as $source_name => $factchecks) {
            if (!is_wp_error($factchecks) && !empty($factchecks)) {
                foreach ($factchecks as $factcheck) {
                    $factcheck['source_name'] = $source_name;
                    $all_factchecks[] = $factcheck;
                }
            }
        }
        
        // Sort by date (newest first)
        usort($all_factchecks, function($a, $b) {
            $time_a = isset($a['date']) ? strtotime($a['date']) : 0;
            $time_b = isset($b['date']) ? strtotime($b['date']) : 0;
            return $time_b - $time_a;
        });
        
        // Filter by category if specified
        if ($category && $category !== 'all') {
            $all_factchecks = array_filter($all_factchecks, function($item) use ($category) {
                return isset($item['category']) && strtolower($item['category']) === strtolower($category);
            });
        }
        
        // Limit results
        $all_factchecks = array_slice($all_factchecks, 0, $limit);
        
        // Cache for 1 hour
        set_transient($cache_key, $all_factchecks, self::$cache_duration);
        
        return $all_factchecks;
    }
    
    /**
     * Google Fact Check API
     */
    private static function get_google_factcheck_claims($limit = 10) {
        $api_key = get_option('ai_verify_google_factcheck_key');
        
        if (empty($api_key)) {
            return array();
        }
        
        // Get recent claims (search for common topics)
        $queries = array('covid', 'vaccine', 'election', 'climate', 'politics');
        $all_claims = array();
        
        foreach ($queries as $query) {
            $url = add_query_arg(array(
                'key' => $api_key,
                'query' => $query,
                'languageCode' => 'en',
                'pageSize' => 5
            ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
            
            $response = wp_remote_get($url, array('timeout' => 10));
            
            if (is_wp_error($response)) {
                continue;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['claims']) && is_array($body['claims'])) {
                foreach ($body['claims'] as $claim) {
                    if (!isset($claim['claimReview'][0])) continue;
                    
                    $review = $claim['claimReview'][0];
                    
                    $all_claims[] = array(
                        'claim' => $claim['text'] ?? '',
                        'rating' => $review['textualRating'] ?? 'Unknown',
                        'source' => $review['publisher']['name'] ?? 'Unknown',
                        'url' => $review['url'] ?? '',
                        'date' => $review['reviewDate'] ?? '',
                        'category' => self::guess_category($claim['text'] ?? ''),
                        'claimant' => $claim['claimant'] ?? null
                    );
                }
            }
        }
        
        // Remove duplicates
        $unique_claims = array();
        $seen = array();
        
        foreach ($all_claims as $claim) {
            $hash = md5($claim['claim']);
            if (!isset($seen[$hash])) {
                $unique_claims[] = $claim;
                $seen[$hash] = true;
            }
        }
        
        return array_slice($unique_claims, 0, $limit);
    }
    
    /**
     * PolitiFact RSS Feed
     */
    private static function get_politifact_rss() {
        $feed_url = 'https://www.politifact.com/rss/all/';
        return self::parse_rss_feed($feed_url, 'PolitiFact');
    }
    
    /**
     * Snopes RSS Feed
     */
    private static function get_snopes_rss() {
        $feed_url = 'https://www.snopes.com/feed/';
        return self::parse_rss_feed($feed_url, 'Snopes');
    }
    
    /**
     * FactCheck.org RSS Feed
     */
    private static function get_factcheckorg_rss() {
        $feed_url = 'https://www.factcheck.org/feed/';
        return self::parse_rss_feed($feed_url, 'FactCheck.org');
    }
    
    /**
     * AFP Fact Check RSS Feed
     */
    private static function get_afp_factcheck_rss() {
        $feed_url = 'https://factcheck.afp.com/afp-fact-check/list/rss';
        return self::parse_rss_feed($feed_url, 'AFP Fact Check');
    }
    
    /**
     * Full Fact RSS Feed
     */
    private static function get_fullfact_rss() {
        $feed_url = 'https://fullfact.org/feed/';
        return self::parse_rss_feed($feed_url, 'Full Fact');
    }
    
    /**
     * Generic RSS Parser
     */
    private static function parse_rss_feed($feed_url, $source_name) {
        $response = wp_remote_get($feed_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if (!$xml) {
            return array();
        }
        
        $items = array();
        
        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            $link = (string) $item->link;
            $description = (string) $item->description;
            $pubDate = (string) $item->pubDate;
            
            // Extract rating from title or description
            $rating = self::extract_rating($title . ' ' . $description);
            
            $items[] = array(
                'claim' => strip_tags($title),
                'rating' => $rating,
                'source' => $source_name,
                'url' => $link,
                'date' => date('Y-m-d', strtotime($pubDate)),
                'category' => self::guess_category($title),
                'description' => wp_trim_words(strip_tags($description), 30)
            );
        }
        
        return array_slice($items, 0, 5);
    }
    
    /**
     * Extract rating from text
     */
    private static function extract_rating($text) {
        $text_lower = strtolower($text);
        
        if (strpos($text_lower, 'false') !== false) return 'False';
        if (strpos($text_lower, 'true') !== false) return 'True';
        if (strpos($text_lower, 'misleading') !== false) return 'Misleading';
        if (strpos($text_lower, 'mixture') !== false) return 'Mixture';
        if (strpos($text_lower, 'mostly true') !== false) return 'Mostly True';
        if (strpos($text_lower, 'mostly false') !== false) return 'Mostly False';
        if (strpos($text_lower, 'pants on fire') !== false) return 'False';
        if (strpos($text_lower, 'unproven') !== false) return 'Unproven';
        
        return 'Unknown';
    }
    
    /**
     * Guess category from claim text
     */
    private static function guess_category($text) {
        $text_lower = strtolower($text);
        
        $categories = array(
            'politics' => array('trump', 'biden', 'president', 'election', 'congress', 'senate', 'vote'),
            'health' => array('vaccine', 'covid', 'virus', 'disease', 'medical', 'doctor', 'health'),
            'climate' => array('climate', 'global warming', 'environment', 'carbon', 'emissions'),
            'technology' => array('ai', 'artificial intelligence', 'tech', 'facebook', 'twitter', 'social media'),
            'crime' => array('murder', 'crime', 'police', 'arrest', 'shooting'),
            'immigration' => array('immigration', 'border', 'immigrant', 'refugee'),
            'economy' => array('economy', 'inflation', 'jobs', 'unemployment', 'tax')
        );
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_verify_external_trending_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_verify_external_trending_%'");
    }
}