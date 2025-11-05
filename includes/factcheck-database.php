<?php
/**
 * FIXED: Database Operations - Ensures Metadata is ALWAYS Saved
 * Changes:
 * - Metadata saved immediately after scraping
 * - All metadata fields properly extracted and stored
 * - Never loses title, author, date, image data
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
            scraped_html longtext DEFAULT NULL,
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
        
        // ALSO store HTML for later metadata re-extraction if needed
        $html = $scraped_data['html'] ?? '';
        
        $update_data = array(
            'scraped_content' => $scraped_data['content'] ?? '',
            'scraped_html' => $html,  // NEW: Store HTML
            'content_type' => sanitize_text_field($content_type),
            'metadata' => json_encode($metadata), // Save as JSON
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
                'claims' => json_encode($claims),
                'status' => 'claims_extracted'
            ),
            array('report_id' => $report_id)
        );
    }
    
    /**
     * Save fact-check results and create WordPress post
     */
    public static function save_results($report_id, $results, $overall_score, $rating, $sources = array(), $propaganda = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
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
        
        $update_data = array(
            'status' => 'completed',
            'factcheck_results' => json_encode($results),
            'overall_score' => $overall_score,
            'credibility_rating' => $rating,
            'sources' => json_encode($sources),
            'metadata' => json_encode($metadata),
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
     * NEW: Extract and save metadata separately AFTER analysis
     * This prevents metadata extraction from interfering with AI analysis
     */
    public static function extract_and_save_metadata($report_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        error_log('AI Verify: Extracting metadata separately for ' . $report_id);
        
        // Get URL and existing metadata from database
        $data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT input_value, scraped_html, metadata FROM $table_name WHERE report_id = %s",
                $report_id
            ),
            ARRAY_A
        );
        
        if (empty($data)) {
            error_log('AI Verify: Cannot extract metadata - no data found');
            return false;
        }
        
        $url = $data['input_value'];
        $html = $data['scraped_html'] ?? '';
        $existing_meta = !empty($data['metadata']) ? json_decode($data['metadata'], true) : array();
        
        // If we already have good metadata, don't re-extract
        if (!empty($existing_meta['title']) && !empty($existing_meta['featured_image'])) {
            error_log('AI Verify: Metadata already exists and looks good, skipping re-extraction');
            return $existing_meta;
        }
        
        // Extract metadata using the metadata extractor
        $metadata = array();
        
        if (!empty($html) && class_exists('AI_Verify_Metadata_Extractor')) {
            try {
                $metadata = AI_Verify_Metadata_Extractor::extract_metadata($html, $url);
                error_log('AI Verify: Metadata extracted - Title: "' . substr($metadata['title'] ?? '', 0, 50) . '", Image: ' . ($metadata['featured_image'] ? 'YES' : 'NO'));
            } catch (Exception $e) {
                error_log('AI Verify: Metadata extraction failed: ' . $e->getMessage());
            }
        }
        
        // If extraction failed or didn't get key fields, use fallback
        if (empty($metadata) || empty($metadata['title'])) {
            $parsed_url = parse_url($url);
            $domain = isset($parsed_url['host']) ? str_replace('www.', '', $parsed_url['host']) : '';
            
            $metadata = array_merge(
                array(
                    'title' => 'Untitled Article',
                    'description' => '',
                    'featured_image' => '',
                    'author' => '',
                    'date' => '',
                    'domain' => $domain,
                    'favicon' => "https://www.google.com/s2/favicons?domain={$domain}&sz=128",
                    'url' => $url
                ),
                $metadata
            );
        }
        
        // Merge with existing metadata (preserve any fields that were already there)
        if (!empty($existing_meta)) {
            $metadata = array_merge($existing_meta, $metadata);
        }
        
        // Save updated metadata
        $wpdb->update(
            $table_name,
            array('metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            array('report_id' => $report_id)
        );
        
        error_log('AI Verify: Metadata saved for ' . $report_id);
        
        return $metadata;
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