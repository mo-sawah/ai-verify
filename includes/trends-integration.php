<?php
/**
 * Trends Integration - FINAL FIX
 * Prevents running enrichment on every page load
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Integration {
    
    private static $initialized = false;
    
    public static function init() {
        // Prevent multiple initializations
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        
        // Hook into report completion
        add_action('ai_verify_report_completed', array(__CLASS__, 'process_completed_report'), 10, 2);
        
        // Schedule snapshots and enrichment (doesn't run them immediately)
        self::schedule_tasks();
        
        // Register cron hooks (only run when triggered by WP-Cron)
        add_action('ai_verify_daily_trends_snapshot', array(__CLASS__, 'create_daily_snapshot'));
        add_action('ai_verify_weekly_trends_snapshot', array(__CLASS__, 'create_weekly_snapshot'));
        add_action('ai_verify_enrich_trends', array(__CLASS__, 'enrich_uncategorized_trends'));
    }
    
    /**
     * Schedule tasks (doesn't run them)
     */
    private static function schedule_tasks() {
        if (!wp_next_scheduled('ai_verify_daily_trends_snapshot')) {
            wp_schedule_event(strtotime('tomorrow 3am'), 'daily', 'ai_verify_daily_trends_snapshot');
        }
        
        if (!wp_next_scheduled('ai_verify_weekly_trends_snapshot')) {
            wp_schedule_event(strtotime('next monday 3am'), 'weekly', 'ai_verify_weekly_trends_snapshot');
        }
        
        if (!wp_next_scheduled('ai_verify_enrich_trends')) {
            wp_schedule_event(time() + 3600, 'ai_verify_6hours', 'ai_verify_enrich_trends'); // Start in 1 hour
        }
    }
    
    /**
     * Process completed report (runs immediately when report completes)
     */
    public static function process_completed_report($report_id, $report) {
        if (!$report || $report['status'] !== 'completed') {
            return;
        }
        
        $factcheck_results = $report['factcheck_results'];
        
        if (empty($factcheck_results) || !is_array($factcheck_results)) {
            return;
        }
        
        $metadata_array = is_string($report['metadata']) ? json_decode($report['metadata'], true) : $report['metadata'];
        $propaganda_techniques = [];
        
        if (isset($metadata_array['propaganda_techniques']) && is_array($metadata_array['propaganda_techniques'])) {
            $propaganda_techniques = $metadata_array['propaganda_techniques'];
        }
        
        foreach ($factcheck_results as $result) {
            if (empty($result['claim'])) {
                continue;
            }
            
            $claim_score = isset($result['confidence']) 
                ? ($result['confidence'] * 100) 
                : floatval($report['overall_score']);
            
            $claim_metadata = array(
                'source_url' => $report['input_value'],
                'input_type' => $report['input_type'],
                'report_id' => $report_id
            );
            
            if (!empty($propaganda_techniques)) {
                $claim_metadata['propaganda_techniques'] = $propaganda_techniques;
            }
            
            if (class_exists('AI_Verify_Trends_Database')) {
                AI_Verify_Trends_Database::record_claim(
                    $result['claim'],
                    $report_id,
                    $claim_score,
                    $claim_metadata
                );
            }
        }
    }
    
    /**
     * Create daily snapshot - ONLY runs via cron
     */
    public static function create_daily_snapshot() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        if (class_exists('AI_Verify_Trends_Database')) {
            AI_Verify_Trends_Database::create_snapshot('daily');
        }
    }
    
    /**
     * Create weekly snapshot - ONLY runs via cron
     */
    public static function create_weekly_snapshot() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        if (class_exists('AI_Verify_Trends_Database')) {
            AI_Verify_Trends_Database::create_snapshot('weekly');
        }
    }
    
    /**
     * Enrich trends - ONLY runs via cron
     */
    public static function enrich_uncategorized_trends() {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }
        
        if (class_exists('AI_Verify_Trends_Analyzer')) {
            AI_Verify_Trends_Analyzer::batch_enrich_trends(5);
        }
    }
}

add_action('plugins_loaded', function() {
    add_action('ai_verify_factcheck_completed', function($report_id) {
        if (class_exists('AI_Verify_Factcheck_Database')) {
            $report = AI_Verify_Factcheck_Database::get_report($report_id);
            if ($report && $report['status'] === 'completed') {
                do_action('ai_verify_report_completed', $report_id, $report);
            }
        }
    }, 10, 1);
});