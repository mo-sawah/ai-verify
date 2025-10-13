<?php
/**
 * AJAX Handlers for Fact-Check System
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Ajax {
    
    public static function init() {
        // AJAX actions
        add_action('wp_ajax_ai_verify_start_factcheck', array(__CLASS__, 'start_factcheck'));
        add_action('wp_ajax_nopriv_ai_verify_start_factcheck', array(__CLASS__, 'start_factcheck'));
        
        add_action('wp_ajax_ai_verify_submit_email', array(__CLASS__, 'submit_email'));
        add_action('wp_ajax_nopriv_ai_verify_submit_email', array(__CLASS__, 'submit_email'));
        
        add_action('wp_ajax_ai_verify_get_report', array(__CLASS__, 'get_report'));
        add_action('wp_ajax_nopriv_ai_verify_get_report', array(__CLASS__, 'get_report'));
        
        add_action('wp_ajax_ai_verify_export_report', array(__CLASS__, 'export_report'));
        add_action('wp_ajax_nopriv_ai_verify_export_report', array(__CLASS__, 'export_report'));
        
        add_action('wp_ajax_ai_verify_process_factcheck', array(__CLASS__, 'process_factcheck'));
        add_action('wp_ajax_nopriv_ai_verify_process_factcheck', array(__CLASS__, 'process_factcheck'));

        add_action('wp_ajax_ai_verify_check_access', array(__CLASS__, 'check_report_access'));
        add_action('wp_ajax_nopriv_ai_verify_check_access', array(__CLASS__, 'check_report_access'));
        
        // Create table on init if doesn't exist
        self::maybe_create_access_table();
    }

    /**
     * Create table to track report access
     */
    private static function maybe_create_access_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_report_access';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                report_id varchar(255) NOT NULL,
                user_email varchar(255) NOT NULL,
                user_name varchar(255) DEFAULT NULL,
                user_ip varchar(45) DEFAULT NULL,
                plan_type varchar(20) DEFAULT 'free',
                access_granted_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY report_id (report_id),
                KEY user_email (user_email),
                KEY user_ip (user_ip),
                UNIQUE KEY report_email (report_id, user_email)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            error_log('AI Verify: Created report_access table');
        }
    }
    
    /**
     * Handle email submission and grant access
     */
    public static function handle_email_submission() {
        // Verify nonce
        if (!check_ajax_referer('ai_verify_factcheck_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $plan = sanitize_text_field($_POST['plan'] ?? 'free');
        
        if (empty($report_id) || empty($email) || empty($name)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        // Get user IP
        $user_ip = self::get_user_ip();
        
        // Grant access in database
        $access_granted = self::grant_report_access($report_id, $email, $name, $user_ip, $plan);
        
        if ($access_granted) {
            // Update report with user info
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
            
            $wpdb->update(
                $table_name,
                array(
                    'user_email' => $email,
                    'user_name' => $name
                ),
                array('report_id' => $report_id)
            );
            
            error_log("AI Verify: Access granted for report $report_id to $email");
            
            wp_send_json_success(array(
                'message' => 'Access granted',
                'report_id' => $report_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to grant access'));
        }
    }
    
    /**
     * Grant access to a report
     */
    private static function grant_report_access($report_id, $email, $name, $ip, $plan) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_report_access';
        
        // Check if already has access
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE report_id = %s AND user_email = %s",
            $report_id,
            $email
        ));
        
        if ($existing) {
            error_log("AI Verify: User $email already has access to $report_id");
            return true; // Already has access
        }
        
        // Grant new access
        $result = $wpdb->insert(
            $table_name,
            array(
                'report_id' => $report_id,
                'user_email' => $email,
                'user_name' => $name,
                'user_ip' => $ip,
                'plan_type' => $plan,
                'access_granted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Check if user has access to report
     */
    public static function check_report_access() {
        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        
        if (empty($report_id)) {
            wp_send_json_error(array('message' => 'No report ID'));
        }
        
        $has_access = self::user_has_access($report_id);
        
        if ($has_access) {
            wp_send_json_success(array(
                'has_access' => true,
                'method' => $has_access['method']
            ));
        } else {
            wp_send_json_success(array(
                'has_access' => false
            ));
        }
    }
    
    /**
     * Check if current user has access to report
     * Checks: 1. Database by IP, 2. Database by email cookie, 3. Pro plan cookie
     */
    public static function user_has_access($report_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_report_access';
        $user_ip = self::get_user_ip();
        
        // Method 1: Check by IP address
        $access = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE report_id = %s AND user_ip = %s",
            $report_id,
            $user_ip
        ), ARRAY_A);
        
        if ($access) {
            error_log("AI Verify: Access found by IP for report $report_id");
            return array('method' => 'ip', 'data' => $access);
        }
        
        // Method 2: Check by email cookie
        if (isset($_COOKIE['ai_verify_user_email'])) {
            $email = sanitize_email($_COOKIE['ai_verify_user_email']);
            
            $access = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE report_id = %s AND user_email = %s",
                $report_id,
                $email
            ), ARRAY_A);
            
            if ($access) {
                error_log("AI Verify: Access found by email for report $report_id");
                return array('method' => 'email', 'data' => $access);
            }
        }
        
        // Method 3: Check for Pro subscription
        if (isset($_COOKIE['ai_verify_pro']) && $_COOKIE['ai_verify_pro'] === 'true') {
            error_log("AI Verify: Pro user accessing report $report_id");
            return array('method' => 'pro', 'data' => array('plan_type' => 'pro'));
        }
        
        // Method 4: Check usage within free limit
        $usage_data = self::get_usage_data();
        if ($usage_data['count'] < 5) {
            // Within free limit - but still need to check if THIS specific report was accessed
            $report_cookie = 'ai_verify_report_' . $report_id;
            if (isset($_COOKIE[$report_cookie])) {
                error_log("AI Verify: Report $report_id previously accessed (within free limit)");
                return array('method' => 'free', 'data' => array('plan_type' => 'free'));
            }
        }
        
        error_log("AI Verify: No access found for report $report_id");
        return false;
    }
    
    /**
     * Get usage data from cookie
     */
    private static function get_usage_data() {
        if (!isset($_COOKIE['ai_verify_usage'])) {
            return array('count' => 0, 'expires' => null);
        }
        
        $data = json_decode(stripslashes($_COOKIE['ai_verify_usage']), true);
        
        if (!$data || !isset($data['count'])) {
            return array('count' => 0, 'expires' => null);
        }
        
        // Check if expired
        if (isset($data['expires']) && strtotime($data['expires']) < time()) {
            return array('count' => 0, 'expires' => null);
        }
        
        return $data;
    }
    
    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * Start fact-check process
     */
    public static function start_factcheck() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $input_type = sanitize_text_field($_POST['input_type']);
        $input_value = sanitize_text_field($_POST['input_value']);
        
        if (empty($input_value)) {
            wp_send_json_error(array('message' => 'Please provide input'));
        }
        
        // Validate input based on type
        if ($input_type === 'url' && !filter_var($input_value, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Please provide a valid URL'));
        }
        
        // Create report record
        $report_id = AI_Verify_Factcheck_Database::create_report($input_type, $input_value);
        
        if (!$report_id) {
            wp_send_json_error(array('message' => 'Failed to create report'));
        }
        
        wp_send_json_success(array(
            'report_id' => $report_id,
            'message' => 'Report created successfully'
        ));
    }
    
    /**
     * Submit email and start processing
     */
    public static function submit_email() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_POST['report_id']);
        $email = sanitize_email($_POST['email']);
        $name = sanitize_text_field($_POST['name']);
        $terms_accepted = isset($_POST['terms_accepted']) && $_POST['terms_accepted'] === 'true';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Please provide a valid email'));
        }
        
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Please provide your name'));
        }
        
        if (!$terms_accepted) {
            wp_send_json_error(array('message' => 'Please accept the terms of use'));
        }
        
        // Update report with user info
        AI_Verify_Factcheck_Database::update_user_info($report_id, $email, $name);
        
        // Start processing
        AI_Verify_Factcheck_Database::update_status($report_id, 'processing');
        
        wp_send_json_success(array(
            'message' => 'Processing started',
            'report_id' => $report_id
        ));
    }
    
    /**
     * Process fact-check (main processing with propaganda detection)
     */
    public static function process_factcheck() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_POST['report_id']);
        
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found'));
        }
        
        try {
            $input_type = $report['input_type'];
            $input_value = $report['input_value'];
            
            // Step 1: Scrape or search
            if ($input_type === 'url') {
                $scraped = AI_Verify_Factcheck_Scraper::scrape_url($input_value);
                
                if (is_wp_error($scraped)) {
                    throw new Exception($scraped->get_error_message());
                }
                
                AI_Verify_Factcheck_Database::save_scraped_content(
                    $report_id,
                    json_encode($scraped),
                    'article'
                );
                
                $content = $scraped['content'];
                $context = $scraped['title'];
                
            } else { // title or phrase
                $search_result = AI_Verify_Factcheck_Scraper::search_phrase($input_value);
                
                if ($search_result['source'] === 'google_factcheck' && !empty($search_result['results'])) {
                    // Already has fact-checks
                    $factcheck_results = $search_result['results'];
                    $score = 70;
                    $rating = 'See fact-checks below';
                    
                    AI_Verify_Factcheck_Database::save_results(
                        $report_id,
                        $factcheck_results,
                        $score,
                        $rating,
                        array(),
                        array() // empty propaganda
                    );
                    
                    wp_send_json_success(array(
                        'status' => 'completed',
                        'report_id' => $report_id
                    ));
                    return;
                }
                
                $content = $input_value;
                $context = $input_value;
            }
            
            // Step 2: Detect propaganda techniques
            $propaganda = AI_Verify_Factcheck_Analyzer::detect_propaganda($content);
            
            // Step 3: Extract claims
            $claims = AI_Verify_Factcheck_Analyzer::extract_claims($content);
            
            AI_Verify_Factcheck_Database::save_claims($report_id, $claims);
            
            // Step 4: Fact-check claims
            $factcheck_results = AI_Verify_Factcheck_Analyzer::factcheck_claims($claims, $context, $input_value);
            
            // Step 5: Calculate overall score
            $overall_score = AI_Verify_Factcheck_Analyzer::calculate_overall_score($factcheck_results);
            $credibility_rating = AI_Verify_Factcheck_Analyzer::get_credibility_rating($overall_score);
            
            // Step 6: Collect sources
            $sources = array();
            foreach ($factcheck_results as $result) {
                if (!empty($result['sources'])) {
                    $sources = array_merge($sources, $result['sources']);
                }
            }
            
            // Remove duplicate sources
            $unique_sources = array();
            $seen = array();
            foreach ($sources as $source) {
                $key = $source['url'] ?? $source['name'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique_sources[] = $source;
                }
            }
            
            // Step 7: Save results with propaganda detection
            AI_Verify_Factcheck_Database::save_results(
                $report_id,
                $factcheck_results,
                $overall_score,
                $credibility_rating,
                $unique_sources,
                $propaganda
            );
            
            wp_send_json_success(array(
                'status' => 'completed',
                'report_id' => $report_id,
                'score' => $overall_score,
                'rating' => $credibility_rating
            ));
            
        } catch (Exception $e) {
            AI_Verify_Factcheck_Database::update_status($report_id, 'failed');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Get report data
     */
    public static function get_report() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_POST['report_id']);
        
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found'));
        }
        
        // Remove sensitive data
        unset($report['user_ip']);
        
        wp_send_json_success(array('report' => $report));
    }
    
    /**
     * Export report as PDF/JSON
     */
    public static function export_report() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_GET['report_id']);
        $format = sanitize_text_field($_GET['format']);
        
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        
        if (!$report) {
            wp_die('Report not found');
        }
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="factcheck-report-' . $report_id . '.json"');
            echo json_encode($report, JSON_PRETTY_PRINT);
            exit;
        }
        
        if ($format === 'html') {
            // Generate HTML report
            $html = self::generate_html_report($report);
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="factcheck-report-' . $report_id . '.html"');
            echo $html;
            exit;
        }
        
        wp_die('Invalid export format');
    }
    
    /**
     * Generate HTML report
     */
    private static function generate_html_report($report) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Fact-Check Report - <?php echo esc_html($report['report_id']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                h1 { color: #2d3748; border-bottom: 3px solid #acd2bf; padding-bottom: 10px; }
                .score { font-size: 48px; font-weight: bold; color: #acd2bf; text-align: center; margin: 30px 0; }
                .rating { text-align: center; font-size: 24px; margin-bottom: 40px; }
                .claim { background: #f7fafc; padding: 15px; margin: 15px 0; border-left: 4px solid #acd2bf; }
                .claim-text { font-weight: bold; margin-bottom: 10px; }
                .claim-rating { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; }
                .rating-true { background: #e6ffed; color: #22543d; }
                .rating-false { background: #fee; color: #c53030; }
                .rating-mixture { background: #fff9e6; color: #744210; }
                .source { color: #718096; font-size: 14px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <h1>Fact-Check Report</h1>
            <p><strong>Report ID:</strong> <?php echo esc_html($report['report_id']); ?></p>
            <p><strong>Input:</strong> <?php echo esc_html($report['input_value']); ?></p>
            <p><strong>Date:</strong> <?php echo esc_html($report['created_at']); ?></p>
            
            <div class="score"><?php echo number_format($report['overall_score'], 1); ?>%</div>
            <div class="rating"><?php echo esc_html($report['credibility_rating']); ?></div>
            
            <h2>Claims Analysis</h2>
            <?php if (!empty($report['factcheck_results'])): ?>
                <?php foreach ($report['factcheck_results'] as $result): ?>
                    <div class="claim">
                        <div class="claim-text"><?php echo esc_html($result['claim']); ?></div>
                        <span class="claim-rating rating-<?php echo strtolower($result['rating']); ?>">
                            <?php echo esc_html($result['rating']); ?>
                        </span>
                        <p><?php echo esc_html($result['explanation']); ?></p>
                        <?php if (!empty($result['sources'])): ?>
                            <div class="source">
                                Sources: 
                                <?php foreach ($result['sources'] as $source): ?>
                                    <?php echo esc_html($source['name']); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <p style="margin-top: 40px; font-size: 12px; color: #718096; text-align: center;">
                Generated by AI Verify Fact-Check System
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize
AI_Verify_Factcheck_Ajax::init();