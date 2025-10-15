<?php
/**
 * Intelligence Dashboard Main Controller
 * Replaces old trending page system
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Intelligence_Dashboard {
    
    public static function init() {
        // Register shortcode
        add_shortcode('ai_verify_intelligence_dashboard', array(__CLASS__, 'render_dashboard'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_verify_dashboard_refresh', array(__CLASS__, 'ajax_refresh_data'));
        add_action('wp_ajax_nopriv_ai_verify_dashboard_refresh', array(__CLASS__, 'ajax_refresh_data'));
        
        add_action('wp_ajax_ai_verify_dashboard_search', array(__CLASS__, 'ajax_search'));
        add_action('wp_ajax_nopriv_ai_verify_dashboard_search', array(__CLASS__, 'ajax_search'));
        
        add_action('wp_ajax_ai_verify_dashboard_stats', array(__CLASS__, 'ajax_get_stats'));
        add_action('wp_ajax_nopriv_ai_verify_dashboard_stats', array(__CLASS__, 'ajax_get_stats'));
        
        // Schedule velocity calculations (every 15 minutes)
        add_action('ai_verify_calculate_velocity', array(__CLASS__, 'scheduled_velocity_calculation'));
        if (!wp_next_scheduled('ai_verify_calculate_velocity')) {
            wp_schedule_event(time(), 'ai_verify_15min', 'ai_verify_calculate_velocity');
        }
        
        // Schedule data aggregation (every 30 minutes)
        add_action('ai_verify_aggregate_sources', array(__CLASS__, 'scheduled_aggregation'));
        if (!wp_next_scheduled('ai_verify_aggregate_sources')) {
            wp_schedule_event(time(), 'ai_verify_30min', 'ai_verify_aggregate_sources');
        }
    }
    
    /**
     * Enqueue dashboard assets
     */
    public static function enqueue_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_verify_intelligence_dashboard')) {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        
        // Dashboard CSS
        wp_enqueue_style(
            'ai-verify-intelligence-dashboard',
            AI_VERIFY_PLUGIN_URL . 'assets/css/intelligence-dashboard.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        // Dashboard JS
        wp_enqueue_script(
            'ai-verify-intelligence-dashboard',
            AI_VERIFY_PLUGIN_URL . 'assets/js/intelligence-dashboard.js',
            array('jquery', 'chartjs'),
            AI_VERIFY_VERSION,
            true
        );
        
        // Dashboard Charts
        wp_enqueue_script(
            'ai-verify-dashboard-charts',
            AI_VERIFY_PLUGIN_URL . 'assets/js/dashboard-charts.js',
            array('jquery', 'chartjs'),
            AI_VERIFY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('ai-verify-intelligence-dashboard', 'aiVerifyDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_dashboard_nonce'),
            'refresh_interval' => 60000, // 1 minute
            'theme_class' => self::detect_theme_class()
        ));
    }
    
    /**
     * Detect current theme class
     */
    private static function detect_theme_class() {
        // Check body classes or return default
        return 'auto'; // Will be detected by JS
    }
    
    /**
     * Render dashboard
     */
    public static function render_dashboard($atts) {
        $atts = shortcode_atts(array(
            'show_stats' => 'yes',
            'show_charts' => 'yes',
            'show_filters' => 'yes',
            'items_per_page' => 20
        ), $atts);
        
        ob_start();
        include AI_VERIFY_PLUGIN_DIR . 'templates/intelligence-dashboard-page.php';
        return ob_get_clean();
    }
    
    /**
     * Get aggregated dashboard data
     */
    public static function get_dashboard_data($filters = array()) {
        $category = isset($filters['category']) ? $filters['category'] : 'all';
        $platform = isset($filters['platform']) ? $filters['platform'] : 'all';
        $velocity = isset($filters['velocity']) ? $filters['velocity'] : 'all';
        $timeframe = isset($filters['timeframe']) ? $filters['timeframe'] : '7days';
        $search = isset($filters['search']) ? $filters['search'] : '';
        
        // Get internal trends
        $internal_claims = AI_Verify_Velocity_Tracker::get_viral_claims(50);
        
        // Get RSS claims
        if (class_exists('AI_Verify_RSS_Aggregator_Enhanced')) {
            $rss_claims = AI_Verify_RSS_Aggregator_Enhanced::aggregate_all_feeds(5);
        } else {
            $rss_claims = array();
        }
        
        // Get Twitter claims (if API key configured)
        $twitter_claims = array();
        if (get_option('ai_verify_twitter_api_key')) {
            if (class_exists('AI_Verify_Twitter_Monitor')) {
                $twitter_claims = AI_Verify_Twitter_Monitor::monitor_trending_topics();
            }
        }
        
        // Get Google Fact Check claims
        $google_claims = array();
        $google_key = get_option('ai_verify_google_factcheck_key');
        if (!empty($google_key)) {
            $google_claims = self::get_google_factcheck_claims($google_key);
        }
        
        // Merge all sources
        $all_claims = array_merge($internal_claims, $rss_claims, $twitter_claims, $google_claims);
        
        // Apply filters
        if ($category !== 'all') {
            $all_claims = array_filter($all_claims, function($claim) use ($category) {
                return isset($claim['category']) && $claim['category'] === $category;
            });
        }
        
        if ($platform !== 'all') {
            $all_claims = array_filter($all_claims, function($claim) use ($platform) {
                return isset($claim['platform']) && $claim['platform'] === $platform;
            });
        }
        
        if ($velocity !== 'all') {
            $all_claims = array_filter($all_claims, function($claim) use ($velocity) {
                return isset($claim['velocity_status']) && $claim['velocity_status'] === $velocity;
            });
        }
        
        if (!empty($search)) {
            $all_claims = array_filter($all_claims, function($claim) use ($search) {
                $claim_text = isset($claim['claim']) ? $claim['claim'] : ($claim['claim_text'] ?? '');
                return stripos($claim_text, $search) !== false;
            });
        }
        
        // Sort by velocity score (highest first)
        usort($all_claims, function($a, $b) {
            $score_a = isset($a['velocity_score']) ? floatval($a['velocity_score']) : 0;
            $score_b = isset($b['velocity_score']) ? floatval($b['velocity_score']) : 0;
            return $score_b <=> $score_a;
        });
        
        return $all_claims;
    }
    
    /**
     * Get Google Fact Check claims
     */
    private static function get_google_factcheck_claims($api_key, $limit = 20) {
        $queries = array('vaccine', 'election', 'climate', 'covid', 'politics');
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
                        'source' => $review['publisher']['name'] ?? 'Google Fact Check',
                        'url' => $review['url'] ?? '',
                        'date' => $review['reviewDate'] ?? current_time('mysql'),
                        'category' => self::guess_category($claim['text'] ?? ''),
                        'platform' => 'google'
                    );
                }
            }
        }
        
        return array_slice($all_claims, 0, $limit);
    }
    
    /**
     * Guess category from text
     */
    private static function guess_category($text) {
        $text_lower = strtolower($text);
        
        $categories = array(
            'politics' => array('election', 'president', 'vote', 'congress', 'politician'),
            'health' => array('vaccine', 'covid', 'virus', 'disease', 'medical'),
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
     * Get dashboard statistics
     */
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        
        // Total active claims
        $active_claims = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
             WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Viral claims
        $viral_claims = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
             WHERE velocity_status IN ('viral', 'emerging')
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Total verified
        $verified_claims = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
             WHERE avg_credibility_score >= 70"
        );
        
        // Checks per hour (last 24h)
        $checks_24h = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_instances 
             WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $checks_per_hour = round($checks_24h / 24, 1);
        
        // High alert (very low credibility + viral)
        $high_alert = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
             WHERE avg_credibility_score < 30
             AND velocity_status IN ('viral', 'emerging')
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        return array(
            'active_claims' => intval($active_claims),
            'viral_claims' => intval($viral_claims),
            'verified_claims' => intval($verified_claims),
            'checks_per_hour' => floatval($checks_per_hour),
            'high_alert' => intval($high_alert)
        );
    }
    
    /**
     * AJAX: Refresh dashboard data
     */
    public static function ajax_refresh_data() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $filters = array(
            'category' => sanitize_text_field($_POST['category'] ?? 'all'),
            'platform' => sanitize_text_field($_POST['platform'] ?? 'all'),
            'velocity' => sanitize_text_field($_POST['velocity'] ?? 'all'),
            'timeframe' => sanitize_text_field($_POST['timeframe'] ?? '7days'),
            'search' => sanitize_text_field($_POST['search'] ?? '')
        );
        
        $claims = self::get_dashboard_data($filters);
        
        wp_send_json_success(array(
            'claims' => array_slice($claims, 0, 20),
            'total' => count($claims)
        ));
    }
    
    /**
     * AJAX: Search
     */
    public static function ajax_search() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $filters = array('search' => $search);
        $claims = self::get_dashboard_data($filters);
        
        wp_send_json_success(array(
            'claims' => array_slice($claims, 0, 20),
            'total' => count($claims)
        ));
    }
    
    /**
     * AJAX: Get stats
     */
    public static function ajax_get_stats() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $stats = self::get_dashboard_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Scheduled: Calculate velocity
     */
    public static function scheduled_velocity_calculation() {
        if (class_exists('AI_Verify_Velocity_Tracker')) {
            AI_Verify_Velocity_Tracker::batch_calculate_velocity();
        }
    }
    
    /**
     * Scheduled: Aggregate sources
     */
    public static function scheduled_aggregation() {
        // Clear caches to force refresh
        if (class_exists('AI_Verify_RSS_Aggregator_Enhanced')) {
            AI_Verify_RSS_Aggregator_Enhanced::clear_cache();
        }
        
        error_log('AI Verify: Scheduled aggregation complete');
    }
}

// Add custom cron schedules
add_filter('cron_schedules', function($schedules) {
    $schedules['ai_verify_15min'] = array(
        'interval' => 900,
        'display' => 'Every 15 Minutes'
    );
    $schedules['ai_verify_30min'] = array(
        'interval' => 1800,
        'display' => 'Every 30 Minutes'
    );
    return $schedules;
});