<?php
/**
 * Background Data Aggregation System
 * Runs via WP-Cron to prevent page load timeouts
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Background_Aggregator {
    
    /**
     * Initialize background jobs
     */
    public static function init() {
        // Schedule all background jobs
        add_action('ai_verify_aggregate_rss', array(__CLASS__, 'aggregate_rss_feeds'));
        add_action('ai_verify_aggregate_google', array(__CLASS__, 'aggregate_google_factcheck'));
        add_action('ai_verify_aggregate_twitter', array(__CLASS__, 'aggregate_twitter_data'));
        add_action('ai_verify_calculate_velocity', array(__CLASS__, 'calculate_velocity'));
        add_action('ai_verify_cleanup_old_data', array(__CLASS__, 'cleanup_old_data'));
        
        // Register schedules if not already scheduled
        if (!wp_next_scheduled('ai_verify_aggregate_rss')) {
            wp_schedule_event(time(), 'ai_verify_30min', 'ai_verify_aggregate_rss');
        }
        
        if (!wp_next_scheduled('ai_verify_aggregate_google')) {
            wp_schedule_event(time() + 300, 'ai_verify_30min', 'ai_verify_aggregate_google'); // Offset by 5 min
        }
        
        if (!wp_next_scheduled('ai_verify_aggregate_twitter')) {
            wp_schedule_event(time() + 600, 'hourly', 'ai_verify_aggregate_twitter'); // Offset by 10 min
        }
        
        if (!wp_next_scheduled('ai_verify_calculate_velocity')) {
            wp_schedule_event(time() + 150, 'ai_verify_15min', 'ai_verify_calculate_velocity'); // Every 15 min
        }
        
        if (!wp_next_scheduled('ai_verify_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'ai_verify_cleanup_old_data');
        }
        
        error_log('AI Verify: Background aggregator initialized');
    }
    
    /**
     * Aggregate RSS Feeds (Runs every 30 min) - IMPROVED ERROR HANDLING
     */
    public static function aggregate_rss_feeds() {
        error_log('AI Verify: [CRON] Starting RSS aggregation...');
        $start_time = microtime(true);
        
        if (!class_exists('AI_Verify_RSS_Aggregator_Enhanced')) {
            error_log('AI Verify: RSS Aggregator class not found');
            return;
        }
        
        // Clear cache to force fresh fetch
        AI_Verify_RSS_Aggregator_Enhanced::clear_cache();
        
        // Fetch fresh RSS data with error handling
        try {
            $claims = AI_Verify_RSS_Aggregator_Enhanced::aggregate_all_feeds(3); // Reduced from 5 to 3
        } catch (Exception $e) {
            error_log('AI Verify: RSS aggregation exception: ' . $e->getMessage());
            $claims = array();
        }
        
        if (empty($claims)) {
            error_log('AI Verify: [CRON] No RSS claims fetched, skipping storage');
            update_option('ai_verify_last_rss_run', current_time('mysql'));
            return;
        }
        
        // Store in database for quick retrieval
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        $stored_count = 0;
        foreach ($claims as $claim) {
            // Skip if missing required fields
            if (empty($claim['url']) || empty($claim['claim'])) {
                continue;
            }
            
            // Check if already exists (last 7 days)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_sources WHERE source_url = %s AND scraped_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $claim['url']
            ));
            
            if (!$exists) {
                // Store new claim
                $insert_result = $wpdb->insert(
                    $table_sources,
                    array(
                        'trend_id' => 0,
                        'platform' => 'rss',
                        'source_url' => $claim['url'],
                        'source_title' => substr($claim['claim'], 0, 500), // Limit length
                        'author_handle' => $claim['source'],
                        'posted_at' => $claim['date'],
                        'scraped_at' => current_time('mysql'),
                        'metadata' => json_encode(array(
                            'rating' => $claim['rating'] ?? 'Unknown',
                            'category' => $claim['category'] ?? 'general',
                            'description' => isset($claim['description']) ? substr($claim['description'], 0, 500) : ''
                        ))
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($insert_result) {
                    $stored_count++;
                } else {
                    error_log('AI Verify: Failed to store RSS claim: ' . $wpdb->last_error);
                }
            }
        }
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: [CRON] RSS aggregation complete: {$stored_count} new claims stored in {$elapsed}s (from " . count($claims) . " fetched)");
        
        // Update last run timestamp
        update_option('ai_verify_last_rss_run', current_time('mysql'));
        update_option('ai_verify_last_rss_count', $stored_count);
    }
    
    /**
     * Aggregate Google Fact Check API (Runs every 30 min, offset)
     */
    public static function aggregate_google_factcheck() {
        error_log('AI Verify: [CRON] Starting Google Fact Check aggregation...');
        $start_time = microtime(true);
        
        $api_key = get_option('ai_verify_google_factcheck_key');
        if (empty($api_key)) {
            error_log('AI Verify: Google API key not configured, skipping');
            return;
        }
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        // Search for trending topics
        $queries = array('vaccine', 'election', 'climate', 'politics', 'health');
        $stored_count = 0;
        
        foreach ($queries as $query) {
            $url = add_query_arg(array(
                'key' => $api_key,
                'query' => $query,
                'languageCode' => 'en',
                'pageSize' => 5
            ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
            
            $response = wp_remote_get($url, array('timeout' => 10));
            
            if (is_wp_error($response)) {
                error_log('AI Verify: Google API error: ' . $response->get_error_message());
                continue;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                error_log('AI Verify: Google API error: ' . $body['error']['message']);
                continue;
            }
            
            if (isset($body['claims']) && is_array($body['claims'])) {
                foreach ($body['claims'] as $claim) {
                    if (!isset($claim['claimReview'][0])) continue;
                    
                    $review = $claim['claimReview'][0];
                    $claim_url = $review['url'] ?? '';
                    
                    // Check if already exists
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_sources WHERE source_url = %s",
                        $claim_url
                    ));
                    
                    if (!$exists && !empty($claim_url)) {
                        $wpdb->insert(
                            $table_sources,
                            array(
                                'trend_id' => 0,
                                'platform' => 'google',
                                'source_url' => $claim_url,
                                'source_title' => $claim['text'] ?? '',
                                'author_handle' => $review['publisher']['name'] ?? 'Google Fact Check',
                                'posted_at' => $review['reviewDate'] ?? current_time('mysql'),
                                'scraped_at' => current_time('mysql'),
                                'metadata' => json_encode(array(
                                    'rating' => $review['textualRating'] ?? 'Unknown',
                                    'category' => self::guess_category($claim['text'] ?? '')
                                ))
                            ),
                            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                        );
                        $stored_count++;
                    }
                }
            }
            
            // Rate limiting - wait between queries
            usleep(500000); // 0.5 seconds
        }
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: [CRON] Google aggregation complete: {$stored_count} new claims stored in {$elapsed}s");
        
        update_option('ai_verify_last_google_run', current_time('mysql'));
    }
    
    /**
     * Aggregate Twitter Data (Runs hourly, if configured)
     */
    public static function aggregate_twitter_data() {
        $api_key = get_option('ai_verify_twitter_api_key');
        if (empty($api_key)) {
            error_log('AI Verify: Twitter API key not configured, skipping');
            return;
        }
        
        error_log('AI Verify: [CRON] Starting Twitter aggregation...');
        $start_time = microtime(true);
        
        if (!class_exists('AI_Verify_Twitter_Monitor')) {
            error_log('AI Verify: Twitter Monitor class not found');
            return;
        }
        
        // Monitor trending topics
        $tweets = AI_Verify_Twitter_Monitor::monitor_trending_topics();
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        $stored_count = 0;
        foreach ($tweets as $tweet) {
            // Check if already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_sources WHERE source_url = %s",
                $tweet['url']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $table_sources,
                    array(
                        'trend_id' => 0,
                        'platform' => 'twitter',
                        'source_url' => $tweet['url'],
                        'source_title' => $tweet['claim'],
                        'engagement_count' => $tweet['engagement'],
                        'posted_at' => $tweet['date'],
                        'scraped_at' => current_time('mysql'),
                        'metadata' => json_encode($tweet['metadata'] ?? array())
                    ),
                    array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
                );
                $stored_count++;
            }
        }
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: [CRON] Twitter aggregation complete: {$stored_count} new tweets stored in {$elapsed}s");
        
        update_option('ai_verify_last_twitter_run', current_time('mysql'));
    }
    
    /**
     * Calculate Velocity (Runs every 15 min)
     */
    public static function calculate_velocity() {
        error_log('AI Verify: [CRON] Starting velocity calculations...');
        $start_time = microtime(true);
        
        if (class_exists('AI_Verify_Velocity_Tracker')) {
            $count = AI_Verify_Velocity_Tracker::batch_calculate_velocity();
            $elapsed = round(microtime(true) - $start_time, 2);
            error_log("AI Verify: [CRON] Velocity calculated for {$count} trends in {$elapsed}s");
        }
        
        update_option('ai_verify_last_velocity_run', current_time('mysql'));
    }
    
    /**
     * Cleanup Old Data (Runs daily)
     */
    public static function cleanup_old_data() {
        error_log('AI Verify: [CRON] Starting cleanup...');
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        
        // Delete sources older than 30 days
        $deleted_sources = $wpdb->query(
            "DELETE FROM $table_sources WHERE scraped_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Delete velocity snapshots older than 30 days
        $deleted_velocity = $wpdb->query(
            "DELETE FROM $table_velocity WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        error_log("AI Verify: [CRON] Cleanup complete: {$deleted_sources} sources, {$deleted_velocity} snapshots deleted");
        
        update_option('ai_verify_last_cleanup_run', current_time('mysql'));
    }
    
    /**
     * Guess category from text
     */
    private static function guess_category($text) {
        $text_lower = strtolower($text);
        
        $categories = array(
            'politics' => array('election', 'president', 'vote', 'congress'),
            'health' => array('vaccine', 'covid', 'virus', 'disease'),
            'climate' => array('climate', 'global warming', 'environment'),
            'technology' => array('ai', 'tech', 'facebook', 'twitter'),
            'crime' => array('crime', 'murder', 'police'),
            'economy' => array('economy', 'inflation', 'jobs')
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
     * Manual trigger for testing
     */
    public static function run_all_now() {
        error_log('AI Verify: Manual aggregation triggered');
        
        self::aggregate_rss_feeds();
        self::aggregate_google_factcheck();
        self::aggregate_twitter_data();
        self::calculate_velocity();
        
        return array(
            'status' => 'success',
            'message' => 'All background jobs completed',
            'last_runs' => array(
                'rss' => get_option('ai_verify_last_rss_run'),
                'google' => get_option('ai_verify_last_google_run'),
                'twitter' => get_option('ai_verify_last_twitter_run'),
                'velocity' => get_option('ai_verify_last_velocity_run')
            )
        );
    }
}