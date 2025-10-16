<?php
/**
 * Database Operations for Fact-Check System
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Database {
    
    private static $table_name = 'ai_verify_factcheck_reports';
    
    /**
     * Create database table on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            report_id varchar(64) NOT NULL,
            input_type varchar(20) NOT NULL,
            input_value text NOT NULL,
            user_email varchar(255) DEFAULT NULL,
            user_name varchar(255) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            status varchar(20) DEFAULT 'processing',
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
    
    /**
     * Create new report record
     */
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
    
    /**
     * Update report with user info
     */
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

    /**
     * Update processing progress
     */
    public static function update_progress($report_id, $progress, $message = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $wpdb->update(
            $table,
            array(
                'progress' => $progress,
                'progress_message' => $message
            ),
            array('report_id' => $report_id),
            array('%d', '%s'),
            array('%s')
        );
    }
    
    public static function update_status($report_id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $wpdb->update(
            $table,
            array('status' => $status),
            array('report_id' => $report_id),
            array('%s'),
            array('%s')
        );
    }
    
    /**
     * Save scraped content
     */
    public static function save_scraped_content($report_id, $content, $content_type = 'article') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $wpdb->update(
            $table_name,
            array(
                'scraped_content' => $content,
                'content_type' => sanitize_text_field($content_type),
                'status' => 'scraped'
            ),
            array('report_id' => $report_id)
        );
    }
    
    /**
     * Save extracted claims
     */
    public static function save_claims($report_id, $claims) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $wpdb->update(
            $table_name,
            array(
                'claims' => json_encode($claims),
                'status' => 'claims_extracted'
            ),
            array('report_id' => $report_id)
        );
    }
    
    /**
     * Save fact-check results (UPDATED - NOW SAVES PROPAGANDA)
     */
    public static function save_results($report_id, $results, $overall_score, $rating, $sources = array(), $propaganda = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        // Build metadata with propaganda
        $metadata = array();
        if (!empty($propaganda) && is_array($propaganda)) {
            $metadata['propaganda_techniques'] = $propaganda;
            error_log("AI Verify: Saving " . count($propaganda) . " propaganda techniques: " . json_encode($propaganda));
        } else {
            error_log("AI Verify: No propaganda techniques to save");
        }
        
        $update_data = array(
            'status' => 'completed',
            'factcheck_results' => json_encode($results),
            'overall_score' => $overall_score,
            'credibility_rating' => $rating,
            'sources' => json_encode($sources),
            'metadata' => json_encode($metadata),
            'completed_at' => current_time('mysql')
        );
        
        $format = array('%s', '%s', '%f', '%s', '%s', '%s', '%s');
        
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            array('report_id' => $report_id),
            $format,
            array('%s')
        );
        
        if ($updated === false) {
            error_log('AI Verify: Database update failed: ' . $wpdb->last_error);
            return false;
        }
        
        error_log("AI Verify: Successfully saved results for {$report_id}");
        
        return true;
    }
    
    /**
     * Get report by ID (updated to include metadata)
     */
    public static function get_report($report_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $report = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE report_id = %s", $report_id),
            ARRAY_A
        );
        
        if ($report) {
            // Decode JSON fields
            if (!empty($report['claims'])) {
                $report['claims'] = json_decode($report['claims'], true);
            }
            if (!empty($report['factcheck_results'])) {
                $report['factcheck_results'] = json_decode($report['factcheck_results'], true);
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
    
    /**
     * Get recent reports by email
     */
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
    
    /**
     * Check if report exists and is accessible
     */
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
    
    /**
     * Generate unique report ID
     */
    private static function generate_report_id() {
        return 'fc_' . uniqid() . '_' . substr(md5(time() . rand()), 0, 8);
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
     * Clean old pending reports (older than 24 hours)
     */
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