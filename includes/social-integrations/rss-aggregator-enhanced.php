<?php
/**
 * Enhanced RSS Aggregator - 15+ Fact-Checking Sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_RSS_Aggregator_Enhanced {
    
    private static $cache_duration = 1800; // 30 minutes
    
    /**
     * All RSS feed sources
     */
    private static function get_feed_sources() {
        return array(
            // Original sources
            'politifact' => array(
                'name' => 'PolitiFact',
                'url' => 'https://www.politifact.com/rss/all/',
                'category' => 'politics'
            ),
            'snopes' => array(
                'name' => 'Snopes',
                'url' => 'https://www.snopes.com/feed/',
                'category' => 'general'
            ),
            'factcheck_org' => array(
                'name' => 'FactCheck.org',
                'url' => 'https://www.factcheck.org/feed/',
                'category' => 'politics'
            ),
            'afp' => array(
                'name' => 'AFP Fact Check',
                'url' => 'https://factcheck.afp.com/afp-fact-check/list/rss',
                'category' => 'general'
            ),
            'fullfact' => array(
                'name' => 'Full Fact',
                'url' => 'https://fullfact.org/feed/',
                'category' => 'general'
            ),
            
            // NEW SOURCES
            'reuters' => array(
                'name' => 'Reuters Fact Check',
                'url' => 'https://www.reuters.com/news/archive/factcheck',
                'category' => 'general'
            ),
            'apnews' => array(
                'name' => 'AP Fact Check',
                'url' => 'https://apnews.com/hub/ap-fact-check',
                'category' => 'general'
            ),
            'usatoday' => array(
                'name' => 'USA Today Fact Check',
                'url' => 'https://www.usatoday.com/news/factcheck/',
                'category' => 'general'
            ),
            'washington_post' => array(
                'name' => 'Washington Post Fact Checker',
                'url' => 'https://www.washingtonpost.com/news/fact-checker/feed/',
                'category' => 'politics'
            ),
            'lead_stories' => array(
                'name' => 'Lead Stories',
                'url' => 'https://leadstories.com/rss/',
                'category' => 'general'
            ),
            'science_feedback' => array(
                'name' => 'Science Feedback',
                'url' => 'https://sciencefeedback.co/feed/',
                'category' => 'health'
            ),
            'health_feedback' => array(
                'name' => 'Health Feedback',
                'url' => 'https://healthfeedback.org/feed/',
                'category' => 'health'
            ),
            'climate_feedback' => array(
                'name' => 'Climate Feedback',
                'url' => 'https://climatefeedback.org/feed/',
                'category' => 'climate'
            ),
            'check_your_fact' => array(
                'name' => 'Check Your Fact',
                'url' => 'https://checkyourfact.com/feed/',
                'category' => 'general'
            ),
            'truth_or_fiction' => array(
                'name' => 'Truth or Fiction',
                'url' => 'https://www.truthorfiction.com/feed/',
                'category' => 'general'
            )
        );
    }
    
    /**
     * Aggregate all RSS feeds
     */
    public static function aggregate_all_feeds($limit_per_source = 5) {
        $cache_key = 'ai_verify_rss_enhanced_all';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $all_claims = array();
        $sources = self::get_feed_sources();
        
        foreach ($sources as $source_key => $source_data) {
            $feed_claims = self::parse_feed($source_data['url'], $source_data['name'], $source_data['category']);
            
            if (!empty($feed_claims)) {
                $all_claims = array_merge($all_claims, array_slice($feed_claims, 0, $limit_per_source));
            }
            
            // Small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }
        
        // Sort by date (newest first)
        usort($all_claims, function($a, $b) {
            $time_a = isset($a['date']) ? strtotime($a['date']) : 0;
            $time_b = isset($b['date']) ? strtotime($b['date']) : 0;
            return $time_b - $time_a;
        });
        
        // Remove duplicates by claim hash
        $unique_claims = array();
        $seen_hashes = array();
        
        foreach ($all_claims as $claim) {
            $hash = md5(strtolower($claim['claim']));
            if (!isset($seen_hashes[$hash])) {
                $unique_claims[] = $claim;
                $seen_hashes[$hash] = true;
            }
        }
        
        // Cache for 30 minutes
        set_transient($cache_key, $unique_claims, self::$cache_duration);
        
        error_log('AI Verify: Aggregated ' . count($unique_claims) . ' unique claims from ' . count($sources) . ' RSS sources');
        
        return $unique_claims;
    }
    
    /**
     * Parse individual RSS feed
     */
    private static function parse_feed($feed_url, $source_name, $category) {
        $response = wp_remote_get($feed_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            error_log('AI Verify RSS: Failed to fetch ' . $source_name . ': ' . $response->get_error_message());
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if (!$xml) {
            error_log('AI Verify RSS: Failed to parse XML from ' . $source_name);
            return array();
        }
        
        $items = array();
        
        // Handle both RSS 2.0 and Atom feeds
        if (isset($xml->channel->item)) {
            // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $items[] = self::parse_rss_item($item, $source_name, $category);
            }
        } elseif (isset($xml->entry)) {
            // Atom
            foreach ($xml->entry as $entry) {
                $items[] = self::parse_atom_entry($entry, $source_name, $category);
            }
        }
        
        return array_filter($items); // Remove empty items
    }
    
    /**
     * Parse RSS 2.0 item
     */
    private static function parse_rss_item($item, $source_name, $category) {
        $title = (string) $item->title;
        $link = (string) $item->link;
        $description = (string) $item->description;
        $pubDate = (string) $item->pubDate;
        
        if (empty($title)) {
            return null;
        }
        
        $rating = self::extract_rating($title . ' ' . $description);
        
        return array(
            'claim' => strip_tags($title),
            'rating' => $rating,
            'source' => $source_name,
            'url' => $link,
            'date' => date('Y-m-d H:i:s', strtotime($pubDate)),
            'category' => $category,
            'description' => wp_trim_words(strip_tags($description), 30),
            'platform' => 'rss'
        );
    }
    
    /**
     * Parse Atom entry
     */
    private static function parse_atom_entry($entry, $source_name, $category) {
        $title = (string) $entry->title;
        $link = (string) $entry->link['href'];
        $summary = (string) $entry->summary;
        $published = (string) $entry->published;
        
        if (empty($title)) {
            return null;
        }
        
        $rating = self::extract_rating($title . ' ' . $summary);
        
        return array(
            'claim' => strip_tags($title),
            'rating' => $rating,
            'source' => $source_name,
            'url' => $link,
            'date' => date('Y-m-d H:i:s', strtotime($published)),
            'category' => $category,
            'description' => wp_trim_words(strip_tags($summary), 30),
            'platform' => 'rss'
        );
    }
    
    /**
     * Extract rating from text
     */
    private static function extract_rating($text) {
        $text_lower = strtolower($text);
        
        // Specific ratings first
        if (strpos($text_lower, 'pants on fire') !== false) return 'False';
        if (strpos($text_lower, 'mostly true') !== false) return 'Mostly True';
        if (strpos($text_lower, 'mostly false') !== false) return 'Mostly False';
        if (strpos($text_lower, 'half true') !== false) return 'Mixed';
        
        // General ratings
        if (strpos($text_lower, 'false') !== false) return 'False';
        if (strpos($text_lower, 'true') !== false) return 'True';
        if (strpos($text_lower, 'misleading') !== false) return 'Misleading';
        if (strpos($text_lower, 'mixture') !== false) return 'Mixed';
        if (strpos($text_lower, 'unproven') !== false) return 'Unproven';
        if (strpos($text_lower, 'unverified') !== false) return 'Unverified';
        
        return 'Unknown';
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        delete_transient('ai_verify_rss_enhanced_all');
        error_log('AI Verify: Cleared RSS aggregator cache');
    }
}