<?php
/**
 * Integration hooks to automatically track claims in trends system
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Integration {
    
    public static function init() {
        // Hook into report completion to record trends
        add_action('ai_verify_report_completed', array(__CLASS__, 'process_completed_report'), 10, 2);
        
        // Schedule daily snapshot
        add_action('ai_verify_daily_trends_snapshot', array(__CLASS__, 'create_daily_snapshot'));
        if (!wp_next_scheduled('ai_verify_daily_trends_snapshot')) {
            wp_schedule_event(strtotime('03:00:00'), 'daily', 'ai_verify_daily_trends_snapshot');
        }
        
        // Schedule weekly snapshot
        add_action('ai_verify_weekly_trends_snapshot', array(__CLASS__, 'create_weekly_snapshot'));
        if (!wp_next_scheduled('ai_verify_weekly_trends_snapshot')) {
            wp_schedule_event(strtotime('next monday 03:00:00'), 'weekly', 'ai_verify_weekly_trends_snapshot');
        }
        
        // Background enrichment
        add_action('ai_verify_enrich_trends', array(__CLASS__, 'enrich_uncategorized_trends'));
        if (!wp_next_scheduled('ai_verify_enrich_trends')) {
            wp_schedule_event(time(), 'hourly', 'ai_verify_enrich_trends');
        }
    }
    
    /**
     * Process completed report and record in trends
     */
    public static function process_completed_report($report_id, $report_data) {
        error_log("AI Verify Trends: Processing report {$report_id}");
        
        if (empty($report_data['factcheck_results'])) {
            error_log("AI Verify Trends: No factcheck results found");
            return;
        }
        
        $factcheck_results = $report_data['factcheck_results'];
        $overall_score = isset($report_data['overall_score']) ? floatval($report_data['overall_score']) : 50;
        
        // Metadata for context
        $metadata = array(
            'source_url' => isset($report_data['input_value']) ? $report_data['input_value'] : null,
            'input_type' => isset($report_data['input_type']) ? $report_data['input_type'] : null,
            'user_location' => self::get_user_location()
        );
        
        // Record each claim
        foreach ($factcheck_results as $result) {
            if (empty($result['claim'])) {
                continue;
            }
            
            $claim_score = isset($result['confidence']) ? ($result['confidence'] * 100) : $overall_score;
            
            $trend_id = AI_Verify_Factcheck_Database::record_claim(
                $result['claim'],
                $report_id,
                $claim_score,
                $metadata
            );
            
            // Queue for enrichment if new
            if ($trend_id) {
                wp_schedule_single_event(time() + 60, 'ai_verify_enrich_single_trend', array($trend_id));
            }
        }
        
        // Also update propaganda data if present
        if (!empty($report_data['metadata']['propaganda_techniques'])) {
            self::update_propaganda_data($report_id, $report_data['metadata']['propaganda_techniques']);
        }
        
        error_log("AI Verify Trends: Recorded " . count($factcheck_results) . " claims");
    }
    
    /**
     * Update propaganda techniques for trends
     */
    private static function update_propaganda_data($report_id, $techniques) {
        global $wpdb;
        
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Get trend IDs for this report
        $trend_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT trend_id FROM $table_instances WHERE report_id = %s",
            $report_id
        ));
        
        foreach ($trend_ids as $trend_id) {
            // Get existing propaganda data
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT propaganda_techniques FROM $table_trends WHERE id = %d",
                $trend_id
            ));
            
            $existing_array = !empty($existing) ? json_decode($existing, true) : array();
            
            // Merge with new techniques
            $merged = array_unique(array_merge($existing_array, $techniques));
            
            $wpdb->update(
                $table_trends,
                array('propaganda_techniques' => json_encode($merged)),
                array('id' => $trend_id)
            );
        }
    }
    
    /**
     * Create daily snapshot
     */
    public static function create_daily_snapshot() {
        error_log('AI Verify Trends: Creating daily snapshot');
        AI_Verify_Trends_Database::create_snapshot('daily');
    }
    
    /**
     * Create weekly snapshot
     */
    public static function create_weekly_snapshot() {
        error_log('AI Verify Trends: Creating weekly snapshot');
        AI_Verify_Trends_Database::create_snapshot('weekly');
    }
    
    /**
     * Enrich uncategorized trends
     */
    public static function enrich_uncategorized_trends() {
        error_log('AI Verify Trends: Starting background enrichment');
        AI_Verify_Trends_Analyzer::batch_enrich_trends(5); // Process 5 at a time
    }
    
    /**
     * Enrich single trend
     */
    public static function enrich_single_trend($trend_id) {
        AI_Verify_Trends_Analyzer::enrich_trend($trend_id);
    }
    
    /**
     * Get user location from IP (basic)
     */
    private static function get_user_location() {
        $ip = self::get_user_ip();
        
        // Use free IP geolocation API
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country,city", array('timeout' => 5));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data && isset($data['country'])) {
            return $data['city'] ? "{$data['city']}, {$data['country']}" : $data['country'];
        }
        
        return null;
    }
    
    /**
     * Get user IP
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

// Add the action hook for report completion
add_action('plugins_loaded', function() {
    // Hook into the background processor in factcheck-ajax.php
    // We need to add this action after a report is marked as completed
    
    add_action('ai_verify_factcheck_completed', function($report_id) {
        $report = AI_Verify_Factcheck_Database::get_report($report_id);
        if ($report && $report['status'] === 'completed') {
            do_action('ai_verify_report_completed', $report_id, $report);
        }
    }, 10, 1);
});