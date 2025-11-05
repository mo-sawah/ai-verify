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
        
        // NEW: Add public-facing AJAX action for the assistant shortcode
        add_action('wp_ajax_ai_verify_public_chat_message', array(__CLASS__, 'handle_public_chat_message'));
        add_action('wp_ajax_nopriv_ai_verify_public_chat_message', array(__CLASS__, 'handle_public_chat_message'));

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
        
        // Create placeholder post immediately so we have a proper URL
        $placeholder_data = array(
            'report_id' => $report_id,
            'input_type' => $input_type,
            'input_value' => $input_value,
            'overall_score' => 0,
            'credibility_rating' => 'Analyzing...',
            'status' => 'pending'
        );
        
        $post_id = AI_Verify_Factcheck_Post_Generator::create_report_post($report_id, $placeholder_data);
        
        // Get the dedicated processing page if set
        $processing_page_id = get_option('ai_verify_processing_page_id');
        if ($processing_page_id) {
            // Use dedicated processing page
            $processing_url = add_query_arg('report', $report_id, get_permalink($processing_page_id));
        } else {
            // Use report post with processing parameter
            $processing_url = add_query_arg('processing', '1', get_permalink($post_id));
        }
        
        wp_send_json_success(array(
            'report_id' => $report_id,
            'report_url' => $processing_url,
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
     * Background processor (runs via WP Cron) - WITH PROGRESS TRACKING
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
            
            // STEP 1: Get content with progress tracking
            AI_Verify_Factcheck_Database::update_progress($report_id, 5, 'Connecting to content source...');
            
            $content = '';
            $context = '';
            if ($report['input_type'] === 'url') {
                AI_Verify_Factcheck_Database::update_progress($report_id, 10, 'Extracting article content...');
                
                $scraped = AI_Verify_Factcheck_Scraper::scrape_url($report['input_value']);
                if (is_wp_error($scraped)) {
                    throw new Exception($scraped->get_error_message());
                }
                // Pass array instead of JSON
                AI_Verify_Factcheck_Database::save_scraped_content($report_id, $scraped, 'article');
                $content = $scraped['content'];
                $context = $scraped['title'];
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 15, 'Content extracted successfully');
            } else {
                $content = $report['input_value'];
                $context = $report['input_value'];
                AI_Verify_Factcheck_Database::update_progress($report_id, 15, 'Text received for analysis');
            }

            // STEP 2: ROUTING LOGIC - Choose analysis method
            if ($provider === 'single_call_perplexity' || $provider === 'single_call_openrouter') {
                // Single call workflow with progress updates
                error_log("AI Verify: Using Hybrid Single Call workflow with provider: $provider");
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 20, 'Searching for existing fact-checks...');

                $google_key = get_option('ai_verify_google_factcheck_key');
                $google_factcheck_context = '';
                if (!empty($google_key)) {
                    $google_result = AI_Verify_Factcheck_Analyzer::check_google_factcheck($context, $google_key);
                    if ($google_result && !empty($google_result['explanation'])) {
                        $google_factcheck_context = "\n\n=== EXISTING FACT-CHECK ===\n{$google_result['explanation']}\n";
                    }
                }

                AI_Verify_Factcheck_Database::update_progress($report_id, 25, 'Gathering reference sources...');
                
                $web_results = AI_Verify_Factcheck_Analyzer::search_web_tavily($context);
                if (empty($web_results)) {
                    $web_results = AI_Verify_Factcheck_Analyzer::search_web_firecrawl($context);
                }

                $web_search_context = "\n\n=== WEB SEARCH RESULTS ===\n";
                if (!empty($web_results)) {
                    foreach ($web_results as $idx => $result) {
                        $web_search_context .= "[Source " . ($idx + 1) . "] {$result['title']}\n{$result['url']}\n";
                    }
                }

                $combined_context = $google_factcheck_context . $web_search_context;

                AI_Verify_Factcheck_Database::update_progress($report_id, 35, 'Starting AI analysis...');
                
                if ($provider === 'single_call_perplexity') {
                    $result_data = AI_Verify_Factcheck_Hybrid_Analyzer::analyze_with_single_call_perplexity($content, $context, $combined_context);
                } else {
                    $result_data = AI_Verify_Factcheck_Hybrid_Analyzer::analyze_with_single_call_openrouter($content, $context, $combined_context);
                }

                if (is_wp_error($result_data)) {
                    throw new Exception($result_data->get_error_message());
                }

                if (empty($result_data) || !isset($result_data['factcheck_results'])) {
                    throw new Exception('AI failed to generate a valid report.');
                }

                AI_Verify_Factcheck_Database::update_progress($report_id, 75, 'Processing analysis results...');
                
                $factcheck_results = $result_data['factcheck_results'];
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

                AI_Verify_Factcheck_Database::update_progress($report_id, 85, 'Finalizing report...');
                
                AI_Verify_Factcheck_Database::save_results(
                    $report_id,
                    $factcheck_results,
                    $overall_score,
                    $credibility_rating,
                    $unique_sources,
                    $propaganda
                );
                
                // STEP 6: Extract metadata separately (AFTER analysis is complete)
                AI_Verify_Factcheck_Database::update_progress($report_id, 90, 'Extracting article metadata...');
                AI_Verify_Factcheck_Database::extract_and_save_metadata($report_id);


            } elseif ($provider === 'openrouter_websearch') {
                // NEW: OpenRouter Native Web Search workflow
                error_log("AI Verify: Using OpenRouter Native Web Search workflow");
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 20, 'Initializing web search analysis...');
                
                // Use the new OpenRouter Web Search analyzer WITH report_id for progress tracking
                $result_data = AI_Verify_Factcheck_OpenRouter_WebSearch::analyze_with_websearch($content, $context, $report['input_value'], $report_id);
                
                if (is_wp_error($result_data)) {
                    throw new Exception($result_data->get_error_message());
                }
                
                if (empty($result_data) || !isset($result_data['factcheck_results'])) {
                    throw new Exception('OpenRouter Web Search failed to generate a valid report.');
                }
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 80, 'Processing analysis results...');
                
                $factcheck_results = $result_data['factcheck_results'];
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
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 85, 'Finalizing report...');
                
                AI_Verify_Factcheck_Database::save_results(
                    $report_id,
                    $factcheck_results,
                    $overall_score,
                    $credibility_rating,
                    $unique_sources,
                    $propaganda
                );
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 90, 'Extracting article metadata...');
                AI_Verify_Factcheck_Database::extract_and_save_metadata($report_id);
            } else {
                // Multi-step workflow with progress tracking
                error_log("AI Verify: Using Multi-Step workflow with provider: $provider");
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 30, 'Analyzing content for bias...');
                $propaganda = AI_Verify_Factcheck_Analyzer::detect_propaganda($content);
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 40, 'Extracting verifiable claims...');
                $claims = AI_Verify_Factcheck_Analyzer::extract_claims($content);
                AI_Verify_Factcheck_Database::save_claims($report_id, $claims);
                
                $total_claims = count($claims);
                error_log("AI Verify: Found {$total_claims} claims to verify");
                
                // Update progress for each claim
                $factcheck_results = array();
                foreach ($claims as $index => $claim) {
                    $claim_num = $index + 1;
                    $claim_text = is_array($claim) ? ($claim['text'] ?? '') : $claim;
                    $progress = 40 + (($index / $total_claims) * 35); // 40-75%
                    
                    AI_Verify_Factcheck_Database::update_progress(
                        $report_id, 
                        $progress, 
                        "Verifying claim {$claim_num} of {$total_claims}...",
                        $claim_text,
                        $claim_num
                    );
                    
                    // Verify this claim
                    $claim_results = AI_Verify_Factcheck_Analyzer::factcheck_claims(array($claim), $context, $report['input_value']);
                    if (!empty($claim_results)) {
                        $factcheck_results = array_merge($factcheck_results, $claim_results);
                    }
                }
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 80, 'Calculating credibility score...');
                
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
                
                AI_Verify_Factcheck_Database::update_progress($report_id, 90, 'Finalizing report...');
                
                AI_Verify_Factcheck_Database::save_results(
                    $report_id,
                    $factcheck_results,
                    $overall_score,
                    $credibility_rating,
                    $unique_sources,
                    $propaganda
                );
                
                // STEP 6: Extract metadata separately (AFTER analysis is complete)
                AI_Verify_Factcheck_Database::update_progress($report_id, 95, 'Extracting article metadata...');
                AI_Verify_Factcheck_Database::extract_and_save_metadata($report_id);
            }
            
            error_log("AI Verify: ✅ Completed successfully: $report_id");
            
            // *** CRITICAL FIX: Trigger trends tracking ***
            $report = AI_Verify_Factcheck_Database::get_report($report_id);
            if ($report && class_exists('AI_Verify_Trends_Database')) {
                error_log("AI Verify Trends: Processing completed report for trends");
                
                // Record each claim in trends
                if (!empty($report['factcheck_results']) && is_array($report['factcheck_results'])) {
                    foreach ($report['factcheck_results'] as $claim_result) {
                        if (empty($claim_result['claim'])) continue;
                        
                        $claim_score = isset($claim_result['confidence']) 
                            ? ($claim_result['confidence'] * 100) 
                            : floatval($report['overall_score']);
                        
                        $metadata = array(
                            'source_url' => $report['input_value'],
                            'input_type' => $report['input_type']
                        );
                        
                        $trend_id = AI_Verify_Trends_Database::record_claim(
                            $claim_result['claim'],
                            $report_id,
                            $claim_score,
                            $metadata
                        );
                        
                        error_log("AI Verify Trends: Recorded claim with trend_id: $trend_id");
                    }
                }
                
                // Also trigger the action hook for other integrations
                do_action('ai_verify_report_completed', $report_id, $report);
            }

        } catch (Exception $e) {
            error_log("AI Verify: ❌ Error processing report $report_id: " . $e->getMessage());
            AI_Verify_Factcheck_Database::update_status($report_id, 'error');
            AI_Verify_Factcheck_Database::update_progress($report_id, 0, $e->getMessage());
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
        
        // Get the post URL for redirect when complete
        $report_url = '';
        $posts = get_posts(array(
            'post_type' => 'fact_check_report',
            'meta_key' => 'report_id',
            'meta_value' => $report_id,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        if (!empty($posts)) {
            $report_url = get_permalink($posts[0]->ID);
        }
        
        wp_send_json_success(array(
            'status' => $report['status'],
            'progress' => isset($report['progress']) ? intval($report['progress']) : 0,
            'message' => isset($report['progress_message']) ? $report['progress_message'] : '',
            'current_claim' => isset($report['current_claim']) ? $report['current_claim'] : null,
            'claim_number' => isset($report['claim_number']) ? intval($report['claim_number']) : null,
            'report_url' => $report_url
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

    /**
     * NEW: Handle public chat message from the assistant shortcode
     * This is a SECURE endpoint with rate-limiting and restricted tool access.
     */
    public static function handle_public_chat_message() {
        check_ajax_referer('ai_verify_public_chat_nonce', 'nonce');

        // 1. Rate Limiting to prevent abuse
        $ip = self::get_user_ip();
        $transient_key = 'ai_verify_chat_limit_' . $ip;
        $request_count = get_transient($transient_key);

        if ($request_count === false) {
            set_transient($transient_key, 1, MINUTE_IN_SECONDS);
        } elseif ($request_count > 15) { // Limit to 15 requests per minute
            wp_send_json_error(array('message' => 'You are sending requests too quickly. Please wait a moment.'));
            return;
        } else {
            set_transient($transient_key, $request_count + 1, MINUTE_IN_SECONDS);
        }

        // 2. Sanitize inputs
        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message cannot be empty.'));
            return;
        }

        // 3. Process with a RESTRICTED toolset
        try {
            $response = self::process_public_chat_message($message);
            wp_send_json_success($response);
        } catch (Exception $e) {
            error_log('AI Verify Public Chat Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'An error occurred while processing your request. Please try again.'));
        }
    }

    /**
     * NEW: Process a message from the public using a safe, limited set of tools.
     */
    private static function process_public_chat_message($user_message) {
        $openrouter_key = get_option('ai_verify_openrouter_key');
        if (empty($openrouter_key)) {
            throw new Exception('AI provider is not configured.');
        }

        $tools_used = [];
        $sources = [];
        $tool_results_context = '';
        
        // Check if the message contains a URL for analysis
        if (preg_match('/https?:\/\/[^\s]+/', $user_message, $matches)) {
            $url = esc_url_raw($matches[0]);
            if (class_exists('AI_Verify_Factcheck_Scraper')) {
                $scraped = AI_Verify_Factcheck_Scraper::scrape_url($url);
                if (!is_wp_error($scraped) && !empty($scraped['content'])) {
                    $tools_used[] = 'URL Analyzer';
                    $sources[] = ['url' => $url, 'title' => $scraped['title'] ?? 'Scraped Content'];
                    $tool_results_context .= "\n\n=== Scraped Content from {$url} ===\n" . mb_substr($scraped['content'], 0, 4000); // Limit context size
                }
            }
        } 
        // Check for a specific, safe database query
        elseif (stripos($user_message, 'viral') !== false || stripos($user_message, 'trending') !== false) {
            if (class_exists('AI_Verify_Trends_Database')) {
                $top_claims = AI_Verify_Trends_Database::get_trending_claims(3, 7); // Get top 3 from last 7 days
                if (!empty($top_claims)) {
                    $tools_used[] = 'Trends Database';
                    $tool_results_context .= "\n\n=== Top Trending Claims from Database ===\n";
                    foreach ($top_claims as $claim) {
                        $tool_results_context .= "- {$claim['claim_text']} (Velocity: {$claim['velocity_status']})\n";
                    }
                }
            }
        }

        // Prepare message for the AI
        $system_prompt = "You are a helpful, public-facing AI fact-checking assistant for our website. Your goal is to provide clear, neutral, and helpful answers. Analyze the provided context if any, but do not mention the context source (like 'Scraped Content'). Just use it to form your answer. Keep responses concise and use markdown for formatting.";
        
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_message . $tool_results_context]
        ];

        // Call OpenRouter API (or your preferred provider)
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $openrouter_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'anthropic/claude-3.5-sonnet',
                'messages' => $messages,
                'max_tokens' => 1024,
            ])
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) throw new Exception($body['error']['message']);

        $assistant_message = $body['choices'][0]['message']['content'] ?? 'I was unable to generate a response.';

        return [
            'response'   => $assistant_message,
            'tools_used' => array_unique($tools_used),
            'sources'    => $sources,
        ];
    }
}