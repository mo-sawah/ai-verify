<?php
/**
 * Database Schema Upgrade for Intelligence Dashboard v2
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Database_Upgrade {
    
    /**
     * Upgrade to v2 schema
     */
    public static function upgrade_to_v2() {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if upgrade needed
        $column_check = $wpdb->get_results("SHOW COLUMNS FROM $table_trends LIKE 'velocity_status'");
        
        if (empty($column_check)) {
            error_log('AI Verify: Starting database upgrade to v2...');
            
            // Add new columns to existing trends table
            $wpdb->query("ALTER TABLE $table_trends 
                ADD COLUMN velocity_status VARCHAR(20) DEFAULT 'dormant',
                ADD COLUMN shares_per_hour DECIMAL(10,2) DEFAULT 0,
                ADD COLUMN velocity_score DECIMAL(10,2) DEFAULT 0,
                ADD COLUMN platform_breakdown JSON,
                ADD COLUMN geographic_spread JSON,
                ADD COLUMN sentiment VARCHAR(20),
                ADD COLUMN entities JSON,
                ADD COLUMN viral_peak_time DATETIME,
                ADD COLUMN twitter_mentions INT DEFAULT 0,
                ADD COLUMN rss_mentions INT DEFAULT 0
            ");
            
            error_log('AI Verify: Added new columns to trends table');
        }
        
        // Create velocity snapshots table
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        
        $sql_velocity = "CREATE TABLE IF NOT EXISTS $table_velocity (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            trend_id BIGINT NOT NULL,
            timestamp DATETIME NOT NULL,
            check_count_snapshot INT,
            velocity_1h DECIMAL(10,2),
            velocity_6h DECIMAL(10,2),
            velocity_24h DECIMAL(10,2),
            platform_data JSON,
            INDEX idx_trend_time (trend_id, timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_velocity);
        
        // Create claim sources table
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        $sql_sources = "CREATE TABLE IF NOT EXISTS $table_sources (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            trend_id BIGINT NOT NULL,
            platform VARCHAR(50),
            source_url TEXT,
            source_title TEXT,
            author_handle VARCHAR(255),
            engagement_count INT DEFAULT 0,
            posted_at DATETIME,
            scraped_at DATETIME,
            metadata JSON,
            INDEX idx_trend (trend_id),
            INDEX idx_platform (platform)
        ) $charset_collate;";
        
        dbDelta($sql_sources);
        
        error_log('AI Verify: Database upgrade to v2 complete');
        
        return true;
    }
    
    /**
     * Rollback to v1 (if needed)
     */
    public static function rollback_to_v1() {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        $table_sources = $wpdb->prefix . 'ai_verify_claim_sources';
        
        // Drop new tables
        $wpdb->query("DROP TABLE IF EXISTS $table_velocity");
        $wpdb->query("DROP TABLE IF EXISTS $table_sources");
        
        // Remove new columns
        $wpdb->query("ALTER TABLE $table_trends 
            DROP COLUMN IF EXISTS velocity_status,
            DROP COLUMN IF EXISTS shares_per_hour,
            DROP COLUMN IF EXISTS velocity_score,
            DROP COLUMN IF EXISTS platform_breakdown,
            DROP COLUMN IF EXISTS geographic_spread,
            DROP COLUMN IF EXISTS sentiment,
            DROP COLUMN IF EXISTS entities,
            DROP COLUMN IF EXISTS viral_peak_time,
            DROP COLUMN IF EXISTS twitter_mentions,
            DROP COLUMN IF EXISTS rss_mentions
        ");
        
        error_log('AI Verify: Rolled back to v1 schema');
    }
}