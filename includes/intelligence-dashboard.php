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

        // Add this in the init() method, around line 30:
        add_action('wp_ajax_ai_verify_get_chart_data', array(__CLASS__, 'ajax_get_chart_data'));
        add_action('wp_ajax_nopriv_ai_verify_get_chart_data', array(__CLASS__, 'ajax_get_chart_data'));

        // *** MAKE SURE THESE ARE HERE: ***
        add_action('wp_ajax_ai_verify_dashboard_refresh', array(__CLASS__, 'ajax_refresh_dashboard'));
        add_action('wp_ajax_ai_verify_dashboard_stats', array(__CLASS__, 'ajax_get_stats'));
        add_action('wp_ajax_nopriv_ai_verify_dashboard_refresh', array(__CLASS__, 'ajax_refresh_dashboard'));

        // Add this in the init() method, around line 35
        add_action('wp_ajax_ai_verify_get_propaganda_data', array(__CLASS__, 'ajax_get_propaganda_data'));
        add_action('wp_ajax_nopriv_ai_verify_get_propaganda_data', array(__CLASS__, 'ajax_get_propaganda_data'));

        
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
        
        // Check if this page has our shortcode
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_verify_intelligence_dashboard')) {
            return;
        }
        
        // STEP 1: Load Chart.js library from CDN
        wp_enqueue_script(
            'chartjs-library',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            array(),
            '4.4.0',
            true
        );
        
        // STEP 2: Load OUR Chart.js wrapper (depends on Chart.js library)
        wp_enqueue_script(
            'ai-verify-charts',
            AI_VERIFY_PLUGIN_URL . 'assets/js/Chart.js',
            array('jquery', 'chartjs-library'),
            AI_VERIFY_VERSION,
            true
        );
        
        // STEP 3: Load Dashboard JS (depends on our Chart wrapper)
        wp_enqueue_script(
            'ai-verify-intelligence-dashboard',
            AI_VERIFY_PLUGIN_URL . 'assets/js/intelligence-dashboard.js',
            array('jquery', 'ai-verify-charts'),
            AI_VERIFY_VERSION,
            true
        );
        
        // STEP 4: Load Chat Assistant JS (depends on dashboard)
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/js/chat-assistant.js')) {
            wp_enqueue_script(
                'ai-verify-chat-assistant',
                AI_VERIFY_PLUGIN_URL . 'assets/js/chat-assistant.js',
                array('jquery', 'ai-verify-intelligence-dashboard'),
                AI_VERIFY_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('ai-verify-intelligence-dashboard', 'aiVerifyDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_dashboard_nonce'),
            'plugin_url' => AI_VERIFY_PLUGIN_URL,
            'refresh_interval' => 60000,
            'theme_class' => self::detect_theme_class()
        ));
        
        // Dashboard CSS
        wp_enqueue_style(
            'ai-verify-intelligence-dashboard',
            AI_VERIFY_PLUGIN_URL . 'assets/css/intelligence-dashboard.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        // Chat Assistant CSS
        if (file_exists(AI_VERIFY_PLUGIN_DIR . 'assets/css/chat-assistant.css')) {
            wp_enqueue_style(
                'ai-verify-chat-assistant',
                AI_VERIFY_PLUGIN_URL . 'assets/css/chat-assistant.css',
                array('ai-verify-intelligence-dashboard'),
                AI_VERIFY_VERSION
            );
        }
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
     * Get aggregated dashboard data (OPTIMIZED - reads from background cache)
     */
    public static function get_dashboard_data($filters = array()) {
        error_log('AI Verify: Loading dashboard from background cache...');
        $start_time = microtime(true);
        
        $category = isset($filters['category']) ? $filters['category'] : 'all';
        $platform = isset($filters['platform']) ? $filters['platform'] : 'all';
        $velocity = isset($filters['velocity']) ? $filters['velocity'] : 'all';
        $timeframe = isset($filters['timeframe']) ? $filters['timeframe'] : '7days';
        $search = isset($filters['search']) ? $filters['search'] : '';
        
        global $wpdb;
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        $all_claims = array();
        
        // 1. Get internal trends (fast - from database)
        $internal_query = "SELECT * FROM $table_trends WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        if ($velocity !== 'all') {
            $internal_query .= $wpdb->prepare(" AND velocity_status = %s", $velocity);
        }
        
        if ($category !== 'all') {
            $internal_query .= $wpdb->prepare(" AND category = %s", $category);
        }
        
        $internal_query .= " ORDER BY velocity_score DESC, check_count DESC LIMIT 50";
        
        $internal_claims = $wpdb->get_results($internal_query, ARRAY_A);
        
        // Format internal claims
        foreach ($internal_claims as &$claim) {
            $claim['platform'] = 'internal';
            $claim['claim'] = $claim['claim_text'];
            $claim['date'] = $claim['last_seen'];
            $claim['source'] = 'User Checks';
            $claim['rating'] = self::calculate_rating_from_score($claim['avg_credibility_score']);
        }
        
        // 2. Get external sources (from background-aggregated data)
        $sources_query = "SELECT * FROM $table_sources WHERE scraped_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        if ($platform !== 'all') {
            $sources_query .= $wpdb->prepare(" AND platform = %s", $platform);
        }
        
        $sources_query .= " ORDER BY scraped_at DESC LIMIT 100";
        
        $external_sources = $wpdb->get_results($sources_query, ARRAY_A);
        
        // Format external sources
        $external_claims = array();
        foreach ($external_sources as $source) {
            $metadata = json_decode($source['metadata'], true);
            
            $external_claims[] = array(
                'claim' => $source['source_title'],
                'platform' => $source['platform'],
                'source' => $source['author_handle'],
                'url' => $source['source_url'],
                'date' => $source['posted_at'],
                'category' => $metadata['category'] ?? 'general',
                'rating' => $metadata['rating'] ?? 'Unknown',
                'description' => $metadata['description'] ?? '',
                'engagement_count' => $source['engagement_count'],
                'velocity_status' => 'external',
                'velocity_score' => $source['engagement_count'] / 10 // Simple calculation
            );
        }
        
        // Merge internal and external
        $all_claims = array_merge($internal_claims, $external_claims);
        
        // Apply search filter
        if (!empty($search)) {
            $all_claims = array_filter($all_claims, function($claim) use ($search) {
                return stripos($claim['claim'], $search) !== false;
            });
        }
        
        // Sort by velocity score
        usort($all_claims, function($a, $b) {
            $score_a = isset($a['velocity_score']) ? floatval($a['velocity_score']) : 0;
            $score_b = isset($b['velocity_score']) ? floatval($b['velocity_score']) : 0;
            return $score_b <=> $score_a;
        });
        
        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("AI Verify: Dashboard loaded in {$elapsed}s (" . count($all_claims) . " claims)");
        
        return $all_claims;
    }

    /**
     * Calculate rating from credibility score
     */
    private static function calculate_rating_from_score($score) {
        if ($score >= 80) return 'True';
        if ($score >= 60) return 'Mostly True';
        if ($score >= 40) return 'Mixed';
        if ($score >= 20) return 'Mostly False';
        return 'False';
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
    
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        
        // FIXED: Active claims (internal + external, last 7 days)
        $internal_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        $external_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT source_url) FROM $table_sources 
            WHERE scraped_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        $active_claims = intval($internal_count) + intval($external_count);
        
        // FIXED: Viral claims (velocity_score >= 20 OR velocity_status = 'viral')
        $viral_claims = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
            WHERE (velocity_score >= 20 OR velocity_status IN ('viral', 'emerging'))
            AND last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // If still 0, check if ANY trends exist with velocity data
        if ($viral_claims == 0) {
            $viral_claims = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table_trends 
                WHERE velocity_score > 0
                AND last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY velocity_score DESC
                LIMIT 10"
            );
        }
        
        // Verified claims
        $verified_claims = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
            WHERE avg_credibility_score >= 70"
        );
        
        // Checks per hour (last 24h)
        $checks_24h = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_instances 
            WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $checks_per_hour = $checks_24h > 0 ? round($checks_24h / 24, 1) : 0;
        
        // FIXED: High alert (low credibility OR high velocity)
        $high_alert = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_trends 
            WHERE (
                avg_credibility_score < 30 
                OR (velocity_score >= 20 AND avg_credibility_score < 50)
            )
            AND last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // If still 0, show count of low credibility claims
        if ($high_alert == 0) {
            $high_alert = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table_trends 
                WHERE avg_credibility_score < 40
                AND last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        }
        
        error_log("AI Verify Stats: Active={$active_claims}, Viral={$viral_claims}, High Alert={$high_alert}");
        
        return array(
            'active_claims' => intval($active_claims),
            'viral_claims' => intval($viral_claims),
            'verified_claims' => intval($verified_claims),
            'checks_per_hour' => floatval($checks_per_hour),
            'high_alert' => intval($high_alert)
        );
    }
    
    /**
     * AJAX: Refresh dashboard data (WITH PAGINATION)
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
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        
        // Get ALL claims first
        $all_claims = self::get_dashboard_data($filters);
        $total = count($all_claims);
        
        // Paginate
        $offset = ($page - 1) * $per_page;
        $claims = array_slice($all_claims, $offset, $per_page);
        
        error_log("AI Verify: Returning page {$page}, {$per_page} items (total: {$total})");
        
        wp_send_json_success(array(
            'claims' => $claims,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'has_more' => ($offset + $per_page) < $total
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

    /**
     * AJAX: Get chart data (WITH NEW CHARTS)
     */
    public static function ajax_get_chart_data() {
        error_log('AI Verify: ajax_get_chart_data called');
        
        $nonce_check = check_ajax_referer('ai_verify_dashboard_nonce', 'nonce', false);
        error_log('AI Verify: Nonce check: ' . ($nonce_check ? 'PASSED' : 'FAILED'));
        
        if (!$nonce_check) {
            error_log('AI Verify: Nonce verification failed!');
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '7days');
        error_log('AI Verify: Timeframe: ' . $timeframe);
        
        global $wpdb;
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        // Get timeline data
        $timeline = $wpdb->get_results("
            SELECT 
                DATE(checked_at) as date,
                COUNT(*) as count
            FROM $table_instances
            WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(checked_at)
            ORDER BY date ASC
        ", ARRAY_A);
        
        error_log('AI Verify: Timeline query returned ' . count($timeline) . ' rows');
        
        // Get category breakdown
        $categories = $wpdb->get_results("
            SELECT 
                category,
                COUNT(*) as count
            FROM $table_trends
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY category
            ORDER BY count DESC
        ", ARRAY_A);
        
        error_log('AI Verify: Categories query returned ' . count($categories) . ' rows');
        
        // Get velocity data
        $velocity = $wpdb->get_results("
            SELECT 
                claim_text,
                velocity_score
            FROM $table_trends
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY velocity_score DESC
            LIMIT 5
        ", ARRAY_A);
        
        error_log('AI Verify: Velocity query returned ' . count($velocity) . ' rows');
        
        // Get platform data
        $platforms = $wpdb->get_results("
            SELECT 
                platform,
                COUNT(*) as count
            FROM $table_sources
            WHERE scraped_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY platform
            ORDER BY count DESC
        ", ARRAY_A);
        
        error_log('AI Verify: Platforms query returned ' . count($platforms) . ' rows');
        
        // *** NEW: Get top sources ***
        $top_sources = $wpdb->get_results("
            SELECT 
                author_handle as source,
                COUNT(*) as count
            FROM $table_sources
            WHERE scraped_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND author_handle IS NOT NULL
            AND author_handle != ''
            GROUP BY author_handle
            ORDER BY count DESC
            LIMIT 10
        ", ARRAY_A);
        
        error_log('AI Verify: Top sources query returned ' . count($top_sources) . ' rows');
        
        // *** NEW: Get credibility distribution ***
        $credibility = $wpdb->get_results("
            SELECT 
                CASE 
                    WHEN avg_credibility_score >= 80 THEN 'Highly Credible (80-100)'
                    WHEN avg_credibility_score >= 60 THEN 'Mostly Credible (60-79)'
                    WHEN avg_credibility_score >= 40 THEN 'Mixed (40-59)'
                    WHEN avg_credibility_score >= 20 THEN 'Low Credibility (20-39)'
                    ELSE 'Not Credible (0-19)'
                END as credibility_range,
                COUNT(*) as count
            FROM $table_trends
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND avg_credibility_score IS NOT NULL
            GROUP BY credibility_range
            ORDER BY MIN(avg_credibility_score) DESC
        ", ARRAY_A);
        
        error_log('AI Verify: Credibility query returned ' . count($credibility) . ' rows');
        
        $data = array(
            'timeline' => $timeline,
            'categories' => $categories,
            'velocity' => $velocity,
            'platforms' => $platforms,
            'top_sources' => $top_sources,      // NEW
            'credibility' => $credibility        // NEW
        );
        
        error_log('AI Verify: Sending chart data - ' . json_encode(array(
            'timeline_count' => count($timeline),
            'categories_count' => count($categories),
            'velocity_count' => count($velocity),
            'platforms_count' => count($platforms),
            'top_sources_count' => count($top_sources),
            'credibility_count' => count($credibility)
        )));
        
        wp_send_json_success($data);
    }

   /**
     * AJAX: Get propaganda analysis data (STATIC FAKE DATA for development)
     */
    public static function ajax_get_propaganda_data() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');

        // --- Hardcoded Static Data ---

        // 1. Define the main stats
        $propaganda_percentage = 65;
        $propaganda_claims_count = 13;

        // 2. Define the top techniques and their counts (must be sorted high to low)
        $top_techniques = [
            'Ad Hominem' => 5,
            'Appeal to Emotion' => 3,
            'Strawman' => 3,
            'Loaded Language' => 1,
            'Bandwagon' => 1
        ];
        // Note: The sum of these counts (5+3+3+1+1) is 13, matching $propaganda_claims_count.

        // 3. Define a fixed list of example claims with propaganda
        $claims_with_propaganda = [
            [
                'claim' => "We can't trust the senator's new policy because he's a known flip-flopper with questionable friends.",
                'techniques' => ['Ad Hominem'], // Keep it simple with one technique for display
            ],
            [
                'claim' => "Think of the innocent children who will suffer if we don't pass this law immediately.",
                'techniques' => ['Appeal to Emotion'],
            ],
            [
                'claim' => "The opposition wants to leave our borders wide open for anyone to just walk in, which is a ridiculous security risk.",
                'techniques' => ['Strawman'],
            ]
        ];
        
        // 4. Definitions can remain as they are static already
        $definitions = [
            'Ad Hominem' => 'Attacks the person making the argument, instead of the argument itself.',
            'Appeal to Emotion' => 'Manipulates an emotional response in place of a valid or compelling argument.',
            'Strawman' => 'Misrepresents someone\'s argument to make it easier to attack.',
            'Loaded Language' => 'Uses words with strong positive or negative connotations to influence the audience.',
            'Bandwagon' => 'Appeals to popularity or the fact that many people do something as an attempted form of validation.',
            'Appeal to Fear' => 'Uses fear or threats to persuade audience',
            'Appeal to Authority' => 'Claims something is true because an authority says so',
            'Black-and-White Fallacy' => 'Presents only two options when more exist',
            'Doubt' => 'Questions credibility without evidence',
            'Flag-Waving' => 'Appeals to patriotism or group identity',
            'Name Calling/Labeling' => 'Gives negative labels to discredit',
            'Red Herring' => 'Introduces irrelevant information',
            'Whataboutism' => 'Deflects by pointing to others\' wrongdoing'
        ];

        // --- Prepare the final data payload ---
        $data = [
            'propaganda_percentage' => $propaganda_percentage,
            'propaganda_claims' => $propaganda_claims_count,
            'top_techniques' => $top_techniques,
            'claims_with_propaganda' => $claims_with_propaganda,
            'definitions' => $definitions,
        ];
        
        wp_send_json_success($data);
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