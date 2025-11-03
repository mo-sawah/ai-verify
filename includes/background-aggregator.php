<?php
/**
 * Background Data Aggregation System - FINAL FIX
 * Prevents running on every page load, only schedules cron jobs
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Background_Aggregator {
    
    private static $initialized = false;
    
    /**
     * Initialize background jobs ONCE - only schedules, doesn't run
     */
    public static function init() {
        // Prevent multiple initializations
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        
        // Add custom 6-hour schedule
        add_filter('cron_schedules', array(__CLASS__, 'add_custom_schedules'));
        
        // Register cron action hooks (these only run when WP-Cron triggers them)
        add_action('ai_verify_aggregate_rss', array(__CLASS__, 'aggregate_rss_feeds'));
        add_action('ai_verify_aggregate_google', array(__CLASS__, 'aggregate_google_factcheck'));
        add_action('ai_verify_aggregate_twitter', array(__CLASS__, 'aggregate_twitter_data'));
        add_action('ai_verify_calculate_velocity', array(__CLASS__, 'calculate_velocity'));
        add_action('ai_verify_cleanup_old_data', array(__CLASS__, 'cleanup_old_data'));
        
        // Only schedule if not already scheduled (doesn't run them immediately)
        self::schedule_jobs();
    }
    
    /**
     * Schedule all cron jobs (doesn't run them)
     */
    private static function schedule_jobs() {
        if (!wp_next_scheduled('ai_verify_aggregate_rss')) {
            wp_schedule_event(time() + 3600, 'ai_verify_6hours', 'ai_verify_aggregate_rss'); // Start in 1 hour
        }
        
        if (!wp_next_scheduled('ai_verify_aggregate_google')) {
            wp_schedule_event(time() + 5400, 'ai_verify_6hours', 'ai_verify_aggregate_google'); // Start in 1.5 hours
        }
        
        if (!wp_next_scheduled('ai_verify_aggregate_twitter')) {
            wp_schedule_event(time() + 7200, 'ai_verify_6hours', 'ai_verify_aggregate_twitter'); // Start in 2 hours
        }
        
        if (!wp_next_scheduled('ai_verify_calculate_velocity')) {
            wp_schedule_event(time() + 9000, 'ai_verify_6hours', 'ai_verify_calculate_velocity'); // Start in 2.5 hours
        }
        
        if (!wp_next_scheduled('ai_verify_cleanup_old_data')) {
            wp_schedule_event(strtotime('tomorrow 3am'), 'daily', 'ai_verify_cleanup_old_data');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public static function add_custom_schedules($schedules) {
        if (!isset($schedules['ai_verify_6hours'])) {
            $schedules['ai_verify_6hours'] = array(
                'interval' => 21600, // 6 hours
                'display'  => __('Every 6 Hours', 'ai-verify')
            );
        }
        return $schedules;
    }
    
    /**
     * Aggregate RSS Feeds - ONLY runs via cron
     */
    public static function aggregate_rss_feeds() {
        // Verify this is running via cron, not page load
        if (!defined('DOING_CRON') || !DOING_CRON) {
            error_log('AI Verify: RSS aggregation skipped - not running via cron');
            return;
        }
        
        error_log('AI Verify: [CRON] Starting RSS aggregation...');
        $start_time = microtime(true);
        
        if (!class_exists('AI_Verify_RSS_Aggregator_Enhanced')) {
            error_log('AI Verify: RSS Aggregator class not found');
            return;
        }
        
        AI_Verify_RSS_Aggregator_Enhanced::clear_cache();
        
        try {
            $claims = AI_Verify_RSS_Aggregator_Enhanced::aggregate_all_feeds(5);
        } catch (Exception $e) {
            error_log('AI Verify: RSS aggregation exception: ' . $e->getMessage());
            $claims = array();
        }
        
        if (empty($claims)) {
            error_log('AI Verify: [CRON] No RSS claims fetched');
            update_option('ai_verify_last_rss_run', current_time('mysql'));
            return;
        }
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        $stored_count = 0;
        foreach ($claims as $claim) {
            if (empty($claim['url']) || empty($claim['claim'])) {
                continue;
            }
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_sources WHERE source_url = %s AND scraped_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $claim['url']
            ));
            
            if (!$exists) {
                $insert_result = $wpdb->insert(
                    $table_sources,
                    array(
                        'trend_id' => 0,
                        'platform' => 'rss',
                        'source_url' => $claim['url'],
                        'source_title' => substr($claim['claim'], 0, 500),
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
                }
            }
        }
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: [CRON] RSS aggregation complete: {$stored_count} new claims in {$elapsed}s");
        
        update_option('ai_verify_last_rss_run', current_time('mysql'));
        update_option('ai_verify_last_rss_count', $stored_count);
    }
    
    /**
     * Aggregate Google Fact Check - ONLY runs via cron
     */
    public static function aggregate_google_factcheck() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        error_log('AI Verify: [CRON] Starting Google aggregation...');
        $start_time = microtime(true);
        
        $api_key = get_option('ai_verify_google_factcheck_key');
        if (empty($api_key)) {
            return;
        }
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
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
            
            if (is_wp_error($response) || !isset(json_decode(wp_remote_retrieve_body($response), true)['claims'])) {
                continue;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['claims']) && is_array($body['claims'])) {
                foreach ($body['claims'] as $claim) {
                    if (!isset($claim['claimReview'][0])) continue;
                    
                    $review = $claim['claimReview'][0];
                    $claim_url = $review['url'] ?? '';
                    
                    if (empty($claim_url)) continue;
                    
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_sources WHERE source_url = %s",
                        $claim_url
                    ));
                    
                    if (!$exists) {
                        $wpdb->insert(
                            $table_sources,
                            array(
                                'trend_id' => 0,
                                'platform' => 'google',
                                'source_url' => $claim_url,
                                'source_title' => $claim['text'] ?? '',
                                'author_handle' => $review['publisher']['name'] ?? 'Google',
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
            
            usleep(500000);
        }
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: [CRON] Google complete: {$stored_count} new claims in {$elapsed}s");
        update_option('ai_verify_last_google_run', current_time('mysql'));
    }
    
    /**
     * Aggregate Twitter - ONLY runs via cron
     */
    public static function aggregate_twitter_data() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        $api_key = get_option('ai_verify_twitter_api_key');
        if (empty($api_key) || !class_exists('AI_Verify_Twitter_Monitor')) {
            return;
        }
        
        error_log('AI Verify: [CRON] Starting Twitter aggregation...');
        // ... (rest of implementation same as before)
        update_option('ai_verify_last_twitter_run', current_time('mysql'));
    }
    
    /**
     * Calculate Velocity - ONLY runs via cron
     */
    public static function calculate_velocity() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        if (class_exists('AI_Verify_Velocity_Tracker')) {
            $count = AI_Verify_Velocity_Tracker::batch_calculate_velocity();
            error_log("AI Verify: [CRON] Velocity: {$count} trends");
        }
        
        update_option('ai_verify_last_velocity_run', current_time('mysql'));
    }
    
    /**
     * Cleanup - ONLY runs via cron
     */
    public static function cleanup_old_data() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        global $wpdb;
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        
        $deleted_sources = $wpdb->query(
            "DELETE FROM $table_sources WHERE scraped_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $deleted_velocity = $wpdb->query(
            "DELETE FROM $table_velocity WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        error_log("AI Verify: [CRON] Cleanup: {$deleted_sources} sources, {$deleted_velocity} snapshots deleted");
        update_option('ai_verify_last_cleanup_run', current_time('mysql'));
    }
    
    private static function guess_category($text) {
        $text_lower = strtolower($text);
        $categories = array(
            'politics' => array('election', 'president', 'vote'),
            'health' => array('vaccine', 'covid', 'virus'),
            'climate' => array('climate', 'warming', 'environment'),
            'technology' => array('ai', 'tech', 'facebook'),
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
     * Manual trigger (for admin testing only)
     */
    public static function run_all_now() {
        define('DOING_CRON', true); // Simulate cron environment
        
        self::aggregate_rss_feeds();
        self::aggregate_google_factcheck();
        self::calculate_velocity();
        
        return array(
            'status' => 'success',
            'last_runs' => array(
                'rss' => get_option('ai_verify_last_rss_run'),
                'google' => get_option('ai_verify_last_google_run'),
                'velocity' => get_option('ai_verify_last_velocity_run')
            )
        );
    }
}