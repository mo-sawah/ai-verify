<?php
/**
 * FIXED: Database Operations - Ensures Metadata AND Explanations are ALWAYS Saved Properly
 * Changes:
 * - Better JSON encoding with proper flags (JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES)
 * - Data cleaning before storage to remove control characters
 * - Validation after saving to ensure data integrity
 * - Never loses title, author, date, image data, OR explanations
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Database {
    
    private static $table_name = 'ai_verify_factcheck_reports';
    
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_id varchar(64) NOT NULL,
            input_type varchar(20) NOT NULL,
            input_value text NOT NULL,
            user_email varchar(255) DEFAULT NULL,
            user_name varchar(255) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            status varchar(20) DEFAULT 'processing',
            progress int(3) DEFAULT 0,
            progress_message text DEFAULT NULL,
            current_claim text DEFAULT NULL,
            claim_number int DEFAULT 0,
            content_type varchar(50) DEFAULT NULL,
            scraped_content longtext DEFAULT NULL,
            claims longtext DEFAULT NULL,
            factcheck_results longtext DEFAULT NULL,
            overall_score decimal(5,2) DEFAULT NULL,
            credibility_rating varchar(20) DEFAULT NULL,
            sources longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY report_id (report_id),
            KEY status (status),
            KEY user_email (user_email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function create_report($input_type, $input_value) {
        global $wpdb;
        
        $report_id = self::generate_report_id();
        $table_name = $wpdb->prefix . self::$table_name;
        
        $data = array(
            'report_id' => $report_id,
            'input_type' => sanitize_text_field($input_type),
            'input_value' => sanitize_text_field($input_value),
            'user_ip' => self::get_user_ip(),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $data);
        
        return $report_id;
    }
    
    public static function update_user_info($report_id, $email, $name) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $wpdb->update(
            $table_name,
            array(
                'user_email' => sanitize_email($email),
                'user_name' => sanitize_text_field($name)
            ),
            array('report_id' => $report_id)
        );
    }

    public static function update_progress($report_id, $progress, $message = '', $current_claim = null, $claim_number = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $update_data = array(
            'progress' => $progress,
            'progress_message' => $message
        );
        
        if ($current_claim !== null) {
            $update_data['current_claim'] = $current_claim;
        }
        
        if ($claim_number !== null) {
            $update_data['claim_number'] = $claim_number;
        }
        
        $wpdb->update(
            $table,
            $update_data,
            array('report_id' => $report_id)
        );
    }
    
    public static function update_status($report_id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $wpdb->update(
            $table,
            array('status' => $status),
            array('report_id' => $report_id)
        );
    }
    
    /**
     * FIXED: Save scraped content with COMPREHENSIVE metadata
     * Ensures ALL metadata fields are captured and saved
     */
    public static function save_scraped_content($report_id, $scraped_data, $content_type = 'article') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // CRITICAL: Build complete metadata array with ALL fields
        $metadata = array(
            'title' => $scraped_data['title'] ?? '',
            'author' => $scraped_data['author'] ?? '',
            'date' => $scraped_data['date'] ?? '',
            'date_modified' => $scraped_data['date_modified'] ?? '',
            'word_count' => $scraped_data['word_count'] ?? 0,
            'excerpt' => $scraped_data['excerpt'] ?? '',
            'description' => $scraped_data['description'] ?? '',
            'featured_image' => $scraped_data['featured_image'] ?? '',
            'domain' => $scraped_data['domain'] ?? '',
            'favicon' => $scraped_data['favicon'] ?? '',
            'url' => $scraped_data['url'] ?? $scraped_data['source_url'] ?? ''
        );
        
        // Remove empty values to save space
        $metadata = array_filter($metadata, function($value) {
            return !empty($value);
        });
        
        error_log('AI Verify: Saving metadata - ' . json_encode(array(
            'title' => substr($metadata['title'] ?? '', 0, 50),
            'author' => $metadata['author'] ?? 'none',
            'date' => $metadata['date'] ?? 'none',
            'image' => !empty($metadata['featured_image']) ? 'YES' : 'NO'
        )));
        
        $update_data = array(
            'scraped_content' => $scraped_data['content'] ?? '',
            'content_type' => sanitize_text_field($content_type),
            'metadata' => self::safe_json_encode($metadata),
            'status' => 'scraped'
        );
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('report_id' => $report_id)
        );
        
        error_log("AI Verify: Saved scraped content + metadata for {$report_id}");
    }
    
    public static function save_claims($report_id, $claims) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $wpdb->update(
            $table_name,
            array(
                'claims' => self::safe_json_encode($claims),
                'status' => 'claims_extracted'
            ),
            array('report_id' => $report_id)
        );
    }
    
    /**
     * Save fact-check results and create WordPress post
     * IMPROVED: Better JSON encoding to prevent explanation corruption
     */
    public static function save_results($report_id, $results, $overall_score, $rating, $sources = array(), $propaganda = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        // CRITICAL: Clean all text data before encoding to prevent corruption
        $results = self::clean_data_for_storage($results);
        $sources = self::clean_data_for_storage($sources);
        
        $metadata = array();
        if (!empty($propaganda) && is_array($propaganda)) {
            $metadata['propaganda_techniques'] = $propaganda;
        }
        
        // Get existing metadata and merge
        $existing_meta = $wpdb->get_var(
            $wpdb->prepare("SELECT metadata FROM $table_name WHERE report_id = %s", $report_id)
        );
        
        if (!empty($existing_meta)) {
            $existing_metadata = json_decode($existing_meta, true);
            if (is_array($existing_metadata)) {
                $metadata = array_merge($existing_metadata, $metadata);
            }
        }
        
        // IMPROVED: Use proper JSON encoding flags to prevent corruption
        $results_json = self::safe_json_encode($results);
        $sources_json = self::safe_json_encode($sources);
        $metadata_json = self::safe_json_encode($metadata);
        
        // Validate JSON encoding worked
        if ($results_json === false || $results_json === '{}' || $results_json === 'null') {
            error_log('AI Verify: Failed to encode results for ' . $report_id);
            error_log('AI Verify: Results data: ' . print_r($results, true));
            return false;
        }
        
        error_log('AI Verify: Encoded results length: ' . strlen($results_json) . ' bytes');
        error_log('AI Verify: First result explanation length: ' . (isset($results[0]['explanation']) ? strlen($results[0]['explanation']) : 0) . ' chars');
        
        $update_data = array(
            'status' => 'completed',
            'factcheck_results' => $results_json,
            'overall_score' => $overall_score,
            'credibility_rating' => $rating,
            'sources' => $sources_json,
            'metadata' => $metadata_json,
            'completed_at' => current_time('mysql')
        );
        
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            array('report_id' => $report_id)
        );
        
        if ($updated === false) {
            error_log('AI Verify: Database update failed: ' . $wpdb->last_error);
            return false;
        }
        
        error_log("AI Verify: Successfully saved results for {$report_id}");
        
        // Verify data integrity immediately
        $verify = $wpdb->get_var(
            $wpdb->prepare("SELECT factcheck_results FROM $table_name WHERE report_id = %s", $report_id)
        );
        $verify_decoded = json_decode($verify, true);
        if (empty($verify_decoded) || !is_array($verify_decoded)) {
            error_log('AI Verify: WARNING - Saved data cannot be decoded properly!');
        } else {
            error_log('AI Verify: Data integrity verified - ' . count($verify_decoded) . ' results');
            if (isset($verify_decoded[0]['explanation'])) {
                error_log('AI Verify: First explanation retrieved: ' . substr($verify_decoded[0]['explanation'], 0, 100));
            }
        }
        
        // Create WordPress post
        $report_data = self::get_report($report_id);
        
        if (class_exists('AI_Verify_Factcheck_Post_Generator') && !empty($report_data)) {
            $post_id = AI_Verify_Factcheck_Post_Generator::create_report_post($report_id, $report_data);
            if ($post_id) {
                error_log("AI Verify: Created WordPress post {$post_id} for report {$report_id}");
            }
        }
        
        return true;
    }
    
    /**
     * Clean data recursively to remove problematic characters before JSON encoding
     */
    private static function clean_data_for_storage($data) {
        if (is_string($data)) {
            // Ensure valid UTF-8
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Remove control characters except newlines and tabs
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            return $data;
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::clean_data_for_storage($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Safe JSON encode with proper flags to prevent corruption
     */
    private static function safe_json_encode($data) {
        // Use flags that prevent issues with special characters
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
        
        if ($json === false) {
            error_log('AI Verify: JSON encoding failed - ' . json_last_error_msg());
            // Try with default flags as fallback
            $json = json_encode($data);
            if ($json === false) {
                error_log('AI Verify: JSON encoding failed even with default flags');
                return '{}';
            }
        }
        
        return $json;
    }
    
    /**
     * Get report with decoded JSON fields
     */
    public static function get_report($report_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $report = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE report_id = %s", $report_id),
            ARRAY_A
        );
        
        if ($report) {
            if (!empty($report['claims'])) {
                $report['claims'] = json_decode($report['claims'], true);
            }
            if (!empty($report['factcheck_results'])) {
                $decoded_results = json_decode($report['factcheck_results'], true);
                if (is_array($decoded_results)) {
                    $report['factcheck_results'] = $decoded_results;
                    // Log first explanation for debugging
                    if (isset($decoded_results[0]['explanation'])) {
                        error_log('AI Verify: Retrieved explanation (first 100 chars): ' . substr($decoded_results[0]['explanation'], 0, 100));
                    }
                } else {
                    error_log('AI Verify: Failed to decode factcheck_results');
                    $report['factcheck_results'] = array();
                }
            }
            if (!empty($report['sources'])) {
                $report['sources'] = json_decode($report['sources'], true);
            }
            if (!empty($report['metadata'])) {
                $report['metadata'] = json_decode($report['metadata'], true);
            }
        }
        
        return $report;
    }
    
    public static function get_user_reports($email, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_email = %s ORDER BY created_at DESC LIMIT %d",
                sanitize_email($email),
                intval($limit)
            ),
            ARRAY_A
        );
    }
    
    public static function can_access_report($report_id, $email = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        if ($email) {
            $report = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE report_id = %s AND user_email = %s",
                    $report_id,
                    sanitize_email($email)
                )
            );
        } else {
            $report = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE report_id = %s",
                    $report_id
                )
            );
        }
        
        return !empty($report);
    }
    
    private static function generate_report_id() {
        return 'fc_' . uniqid() . '_' . substr(md5(time() . rand()), 0, 8);
    }
    
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
    
    public static function cleanup_old_reports() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $wpdb->query(
            "DELETE FROM $table_name 
             WHERE status IN ('pending', 'processing') 
             AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}