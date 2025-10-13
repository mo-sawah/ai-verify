<?php
/**
 * AJAX Handlers for Fact-Check System
 * CORRECTED: submit_email action now points to handle_email_submission.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Ajax {
    
    public static function init() {
        // AJAX actions
        add_action('wp_ajax_ai_verify_start_factcheck', array(__CLASS__, 'start_factcheck'));
        add_action('wp_ajax_nopriv_ai_verify_start_factcheck', array(__CLASS__, 'start_factcheck'));
        
        // ** THE FIX IS HERE: This now correctly points to the function that saves the user's data. **
        add_action('wp_ajax_ai_verify_submit_email', array(__CLASS__, 'handle_email_submission'));
        add_action('wp_ajax_nopriv_ai_verify_submit_email', array(__CLASS__, 'handle_email_submission'));
        
        add_action('wp_ajax_ai_verify_get_report', array(__CLASS__, 'get_report'));
        add_action('wp_ajax_nopriv_ai_verify_get_report', array(__CLASS__, 'get_report'));
        
        add_action('wp_ajax_ai_verify_export_report', array(__CLASS__, 'export_report'));
        add_action('wp_ajax_nopriv_ai_verify_export_report', array(__CLASS__, 'export_report'));
        
        add_action('wp_ajax_ai_verify_process_factcheck', array(__CLASS__, 'process_factcheck'));
        add_action('wp_ajax_nopriv_ai_verify_process_factcheck', array(__CLASS__, 'process_factcheck'));

        add_action('wp_ajax_ai_verify_check_access', array(__CLASS__, 'check_report_access'));
        add_action('wp_ajax_nopriv_ai_verify_check_access', array(__CLASS__, 'check_report_access'));

        // Register cron action for background processing
        add_action('ai_verify_process_report', array(__CLASS__, 'background_process_report'));
        
        // Status polling
        add_action('wp_ajax_ai_verify_check_status', array(__CLASS__, 'check_processing_status'));
        add_action('wp_ajax_nopriv_ai_verify_check_status', array(__CLASS__, 'check_processing_status'));
        
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
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
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
        // Verify the security nonce
        if (!check_ajax_referer('ai_verify_factcheck_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
            return;
        }
        
        // Sanitize all inputs
        $report_id = sanitize_text_field($_POST['report_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        
        // Validate inputs
        if (empty($report_id) || !is_email($email) || empty($name)) {
            wp_send_json_error(array('message' => 'Please provide a valid name and email address.'));
            return;
        }
        
        // Get user IP for internal records
        $user_ip = self::get_user_ip();
        
        // Grant access in the database (for lead tracking and record-keeping)
        $access_granted = self::grant_report_access($report_id, $email, $name, $user_ip, 'free');
        
        if ($access_granted) {
            // This function updates the main report row with the user's email and name
            AI_Verify_Factcheck_Database::update_user_info($report_id, $email, $name);
            
            error_log("AI Verify: Access granted for report $report_id to $email");
            
            // Send a success response. The JavaScript will handle setting the cookie and revealing the content.
            wp_send_json_success(array(
                'message' => 'Thank you! Access has been granted.',
                'report_id' => $report_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Could not save your information. Please contact support.'));
        }
    }

    
    /**
     * Grant access to a report
     */
    private static function grant_report_access($report_id, $email, $name, $ip, $plan) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_report_access';
        
        // Use INSERT IGNORE to prevent errors on duplicate entries for the same user/report
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table_name (report_id, user_email, user_name, user_ip, plan_type, access_granted_at) VALUES (%s, %s, %s, %s, %s, %s)",
            $report_id,
            $email,
            $name,
            $ip,
            $plan,
            current_time('mysql')
        ));

        // Since INSERT IGNORE returns 0 on duplicate, we check if there's a row now.
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE report_id = %s AND user_email = %s",
            $report_id, $email
        ));

        return $exists > 0;
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
        
        wp_send_json_success(array('has_access' => (bool)$has_access));
    }
    
    /**
     * Check if current user has access to report
     */
    public static function user_has_access($report_id) {
        // With the new system, we only care about the 30-day cookie which is checked client-side.
        // This function can be kept for future server-side checks if needed.
        // For now, it's not critical to the new flow.
        return false;
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
        
        if ($input_type === 'url' && !filter_var($input_value, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Please provide a valid URL'));
        }
        
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
     * Start fact-check processing in background
     */
    public static function process_factcheck() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_POST['report_id']);
        
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found'));
        }
        
        // Update status to processing
        AI_Verify_Factcheck_Database::update_status($report_id, 'processing');
        
        // Schedule background processing immediately
        wp_schedule_single_event(time(), 'ai_verify_process_report', array($report_id));
        
        // Spawn WP Cron to run immediately
        spawn_cron();
        
        error_log("AI Verify: Scheduled background processing for report: $report_id");
        
        // Return success immediately
        wp_send_json_success(array(
            'status' => 'processing',
            'message' => 'Processing started in background'
        ));
    }

    /**
     * Background processor (runs via WP Cron) - UPDATED FOR SINGLE CALL MODES
     */
    public static function background_process_report($report_id) {
        error_log("AI Verify: Background processing started for report: $report_id");
        set_time_limit(0);

        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        if (!$report) {
            error_log("AI Verify: Report not found: $report_id");
            return;
        }

        try {
            // Get the selected provider from settings
            $provider = get_option('ai_verify_factcheck_provider', 'perplexity');
            
            // Step 1: Get content (same as before)
            $content = '';
            $context = '';
            if ($report['input_type'] === 'url') {
                $scraped = AI_Verify_Factcheck_Scraper::scrape_url($report['input_value']);
                if (is_wp_error($scraped)) {
                    throw new Exception($scraped->get_error_message());
                }
                AI_Verify_Factcheck_Database::save_scraped_content($report_id, json_encode($scraped), 'article');
                $content = $scraped['content'];
                $context = $scraped['title'];
            } else {
                $content = $report['input_value'];
                $context = $report['input_value'];
            }

            // Step 2: ROUTING LOGIC - Choose analysis method
            if ($provider === 'single_call_perplexity' || $provider === 'single_call_openrouter') {

                // === NEW "HYBRID" SINGLE CALL WORKFLOW ===
                error_log("AI Verify: Using Hybrid Single Call workflow with provider: $provider");

                // Step A: Check Google Fact-Check API first (This now works because the method is public)
                $google_key = get_option('ai_verify_google_factcheck_key');
                $google_factcheck_context = '';
                if (!empty($google_key)) {
                    $google_result = AI_Verify_Factcheck_Analyzer::check_google_factcheck($context, $google_key);
                    if ($google_result && !empty($google_result['explanation'])) {
                        $google_factcheck_context = "\n\n=== EXISTING FACT-CHECK (Source: Google Fact Check API) ===\nClaim: {$google_result['explanation']}\nRating: {$google_result['rating']}\nSource: {$google_result['source']['name']} ({$google_result['source']['url']})\n---";
                        error_log("AI Verify: Found existing fact-check via Google API.");
                    }
                }

                // Step B: Get real-time web search results from Tavily (with Firecrawl fallback)
                // NOTE: We are making these public in the main analyzer as they are generic utilities.
                $web_results = AI_Verify_Factcheck_Analyzer::search_web_tavily($context);
                if (empty($web_results)) {
                    error_log('AI Verify: Tavily failed. Falling back to Firecrawl Search.');
                    $web_results = AI_Verify_Factcheck_Analyzer::search_web_firecrawl($context);
                }

                $web_search_context = "\n\n=== REAL-TIME WEB SEARCH RESULTS ===\n";
                if (!empty($web_results)) {
                    foreach ($web_results as $idx => $result) {
                        $web_search_context .= "[Source " . ($idx + 1) . "] Title: {$result['title']}\nURL: {$result['url']}\nContent: " . substr($result['content'], 0, 400) . "...\n---\n";
                    }
                } else {
                    $web_search_context .= "No real-time web search results were found.\n";
                    error_log("AI Verify: Warning - No web search results found for single-call analysis.");
                }

                // Step C: Call the appropriate AI from the NEW HYBRID ANALYZER CLASS
                $result_data = null;
                $combined_context = $google_factcheck_context . $web_search_context;

                if ($provider === 'single_call_perplexity') {
                    $result_data = AI_Verify_Factcheck_Hybrid_Analyzer::analyze_with_single_call_perplexity($content, $context, $combined_context);
                } else { // single_call_openrouter
                    $result_data = AI_Verify_Factcheck_Hybrid_Analyzer::analyze_with_single_call_openrouter($content, $context, $combined_context);
                }

                if (is_wp_error($result_data)) {
                    throw new Exception($result_data->get_error_message());
                }
                
                // Step D: Extract and save the data (same as before)
                $factcheck_results = $result_data['factcheck_results'] ?? array();
                $overall_score = $result_data['overall_score'] ?? 50;
                $credibility_rating = $result_data['credibility_rating'] ?? 'Mixed Credibility';
                $propaganda = $result_data['propaganda_techniques'] ?? array();
                
                $sources = array();
                foreach ($factcheck_results as $result) {
                    if (!empty($result['sources'])) {
                        $sources = array_merge($sources, $result['sources']);
                    }
                }
                $unique_sources = array_values(array_unique($sources, SORT_REGULAR));

                AI_Verify_Factcheck_Database::save_results(
                    $report_id,
                    $factcheck_results,
                    $overall_score,
                    $credibility_rating,
                    $unique_sources,
                    $propaganda
                );

            } else {
                
                // === ORIGINAL MULTI-STEP WORKFLOW ===
                error_log("AI Verify: Using Multi-Step workflow with provider: $provider");
                
                $propaganda = AI_Verify_Factcheck_Analyzer::detect_propaganda($content);
                $claims = AI_Verify_Factcheck_Analyzer::extract_claims($content);
                AI_Verify_Factcheck_Database::save_claims($report_id, $claims);
                
                $factcheck_results = AI_Verify_Factcheck_Analyzer::factcheck_claims($claims, $context, $report['input_value']);
                $overall_score = AI_Verify_Factcheck_Analyzer::calculate_overall_score($factcheck_results);
                $credibility_rating = AI_Verify_Factcheck_Analyzer::get_credibility_rating($overall_score);
                
                $sources = array();
                if (is_array($factcheck_results)) {
                    foreach ($factcheck_results as $result) {
                        if (!empty($result['sources'])) {
                            $sources = array_merge($sources, $result['sources']);
                        }
                    }
                }
                $unique_sources = array_values(array_unique($sources, SORT_REGULAR));
                
                AI_Verify_Factcheck_Database::save_results(
                    $report_id,
                    $factcheck_results,
                    $overall_score,
                    $credibility_rating,
                    $unique_sources,
                    $propaganda
                );
            }
            
            error_log("AI Verify: ✅ Completed successfully: $report_id");

        } catch (Exception $e) {
            error_log("AI Verify: ❌ Error processing report $report_id: " . $e->getMessage());
            AI_Verify_Factcheck_Database::update_status($report_id, 'failed');
        }
    }

    /**
     * Check processing status (for polling)
     */
    public static function check_processing_status() {
        check_ajax_referer('ai_verify_factcheck_nonce', 'nonce');
        
        $report_id = sanitize_text_field($_POST['report_id']);
        
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(array('message' => 'Report not found'));
        }
        
        wp_send_json_success(array(
            'status' => $report['status']
        ));
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
            <?php if (!empty($report['factcheck_results']) && is_array($report['factcheck_results'])): ?>
                <?php foreach ($report['factcheck_results'] as $result): ?>
                    <div class="claim">
                        <div class="claim-text"><?php echo esc_html($result['claim']); ?></div>
                        <span class="claim-rating rating-<?php echo strtolower($result['rating']); ?>">
                            <?php echo esc_html($result['rating']); ?>
                        </span>
                        <p><?php echo esc_html($result['explanation']); ?></p>
                        <?php if (!empty($result['sources']) && is_array($result['sources'])): ?>
                            <div class="source">
                                Sources: 
                                <?php 
                                $source_names = array();
                                foreach ($result['sources'] as $source) {
                                    if (!empty($source['name'])) {
                                        $source_names[] = esc_html($source['name']);
                                    }
                                }
                                echo implode(', ', $source_names);
                                ?>
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