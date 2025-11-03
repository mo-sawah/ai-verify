<?php
/**
 * Database Operations for Misinformation Trends System
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Database {
    
    /**
     * Create trends tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main trends table
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        $sql_trends = "CREATE TABLE $table_trends (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            claim_hash varchar(64) NOT NULL,
            claim_text text NOT NULL,
            normalized_text text NOT NULL,
            category varchar(50) DEFAULT 'general',
            subcategory varchar(50) DEFAULT NULL,
            entities longtext DEFAULT NULL,
            keywords longtext DEFAULT NULL,
            sentiment varchar(20) DEFAULT 'neutral',
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            check_count int(11) DEFAULT 1,
            avg_credibility_score decimal(5,2) DEFAULT NULL,
            min_credibility_score decimal(5,2) DEFAULT NULL,
            max_credibility_score decimal(5,2) DEFAULT NULL,
            trending_score decimal(10,2) DEFAULT 0,
            velocity_score decimal(10,2) DEFAULT 0,
            geographic_data longtext DEFAULT NULL,
            source_domains longtext DEFAULT NULL,
            propaganda_techniques longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY claim_hash (claim_hash),
            KEY category (category),
            KEY trending_score (trending_score DESC),
            KEY velocity_score (velocity_score DESC),
            KEY first_seen (first_seen),
            KEY last_seen (last_seen)
        ) $charset_collate;";
        
        // Claim instances table (tracks each individual check)
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        
        $sql_instances = "CREATE TABLE $table_instances (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trend_id bigint(20) NOT NULL,
            report_id varchar(255) NOT NULL,
            checked_at datetime NOT NULL,
            credibility_score decimal(5,2) DEFAULT NULL,
            source_url text DEFAULT NULL,
            source_domain varchar(255) DEFAULT NULL,
            user_location varchar(100) DEFAULT NULL,
            input_type varchar(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY trend_id (trend_id),
            KEY report_id (report_id),
            KEY checked_at (checked_at),
            KEY source_domain (source_domain)
        ) $charset_collate;";
        
        // Trending snapshots (daily/weekly aggregations)
        $table_snapshots = $wpdb->prefix . 'ai_verify_trending_snapshots';
        
        $sql_snapshots = "CREATE TABLE $table_snapshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_date date NOT NULL,
            snapshot_type varchar(20) NOT NULL,
            trending_claims longtext NOT NULL,
            category_breakdown longtext NOT NULL,
            top_domains longtext NOT NULL,
            avg_credibility decimal(5,2) DEFAULT NULL,
            total_checks int(11) DEFAULT 0,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY snapshot_unique (snapshot_date, snapshot_type),
            KEY snapshot_date (snapshot_date DESC)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_trends);
        dbDelta($sql_instances);
        dbDelta($sql_snapshots);
        
        error_log('AI Verify: Created trends database tables');
    }
    
    /**
     * Record a claim instance
     */
    public static function record_claim($claim_text, $report_id, $credibility_score, $metadata = array()) {
        global $wpdb;
        
        // Normalize claim text
        $normalized = self::normalize_claim($claim_text);
        $claim_hash = md5($normalized);
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        
        // Check if trend exists
        $trend = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_trends WHERE claim_hash = %s",
            $claim_hash
        ), ARRAY_A);
        
        if ($trend) {
            // Update existing trend
            $trend_id = $trend['id'];
            
            // Calculate new averages
            $new_count = $trend['check_count'] + 1;
            $new_avg = (($trend['avg_credibility_score'] * $trend['check_count']) + $credibility_score) / $new_count;
            $new_min = min($trend['min_credibility_score'], $credibility_score);
            $new_max = max($trend['max_credibility_score'], $credibility_score);
            
            // Calculate velocity (checks per day since first seen)
            $days_active = max(1, (strtotime('now') - strtotime($trend['first_seen'])) / 86400);
            $velocity = $new_count / $days_active;
            
            // Calculate trending score (weighted by recency and volume)
            $recency_weight = self::calculate_recency_weight($trend['last_seen']);
            $trending_score = ($velocity * 10) + ($new_count * 5) + ($recency_weight * 20);
            
            $wpdb->update(
                $table_trends,
                array(
                    'last_seen' => current_time('mysql'),
                    'check_count' => $new_count,
                    'avg_credibility_score' => round($new_avg, 2),
                    'min_credibility_score' => round($new_min, 2),
                    'max_credibility_score' => round($new_max, 2),
                    'velocity_score' => round($velocity, 2),
                    'trending_score' => round($trending_score, 2)
                ),
                array('id' => $trend_id),
                array('%s', '%d', '%f', '%f', '%f', '%f', '%f'),
                array('%d')
            );
            
        } else {
            // Create new trend
            $wpdb->insert(
                $table_trends,
                array(
                    'claim_hash' => $claim_hash,
                    'claim_text' => $claim_text,
                    'normalized_text' => $normalized,
                    'first_seen' => current_time('mysql'),
                    'last_seen' => current_time('mysql'),
                    'check_count' => 1,
                    'avg_credibility_score' => round($credibility_score, 2),
                    'min_credibility_score' => round($credibility_score, 2),
                    'max_credibility_score' => round($credibility_score, 2),
                    'velocity_score' => 1.0,
                    'trending_score' => 25.0 // Initial boost for new claims
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f')
            );
            
            $trend_id = $wpdb->insert_id;
        }
        
        // Record this specific instance
        $source_url = isset($metadata['source_url']) ? $metadata['source_url'] : null;
        $source_domain = $source_url ? parse_url($source_url, PHP_URL_HOST) : null;
        
        $wpdb->insert(
            $table_instances,
            array(
                'trend_id' => $trend_id,
                'report_id' => $report_id,
                'checked_at' => current_time('mysql'),
                'credibility_score' => round($credibility_score, 2),
                'source_url' => $source_url,
                'source_domain' => $source_domain,
                'user_location' => isset($metadata['user_location']) ? $metadata['user_location'] : null,
                'input_type' => isset($metadata['input_type']) ? $metadata['input_type'] : null
            ),
            array('%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
        
        return $trend_id;
    }
    
    /**
     * Get trending claims
     */
    public static function get_trending_claims($limit = 10, $category = null, $timeframe = '7days') {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Calculate date threshold
        $date_threshold = self::get_date_threshold($timeframe);
        
        $where = array("last_seen >= %s");
        $params = array($date_threshold);
        
        if ($category && $category !== 'all') {
            $where[] = "category = %s";
            $params[] = $category;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_trends 
             WHERE $where_sql 
             ORDER BY trending_score DESC, check_count DESC 
             LIMIT %d",
            array_merge($params, array($limit))
        );
        
        $trends = $wpdb->get_results($query, ARRAY_A);
        
        // Enrich with metadata
        foreach ($trends as &$trend) {
            $trend['entities'] = !empty($trend['entities']) ? json_decode($trend['entities'], true) : array();
            $trend['keywords'] = !empty($trend['keywords']) ? json_decode($trend['keywords'], true) : array();
            $trend['propaganda_techniques'] = !empty($trend['propaganda_techniques']) ? json_decode($trend['propaganda_techniques'], true) : array();
        }
        
        return $trends;
    }
    
    /**
     * Get category breakdown
     */
    public static function get_category_breakdown($timeframe = '7days') {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $date_threshold = self::get_date_threshold($timeframe);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT category, 
                    COUNT(*) as count,
                    AVG(avg_credibility_score) as avg_score,
                    SUM(check_count) as total_checks
             FROM $table_trends 
             WHERE last_seen >= %s 
             GROUP BY category 
             ORDER BY total_checks DESC",
            $date_threshold
        ), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get top source domains
     */
    public static function get_top_domains($limit = 10, $timeframe = '7days') {
        global $wpdb;
        
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $date_threshold = self::get_date_threshold($timeframe);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT source_domain, 
                    COUNT(*) as check_count,
                    AVG(credibility_score) as avg_credibility
             FROM $table_instances 
             WHERE checked_at >= %s 
               AND source_domain IS NOT NULL 
             GROUP BY source_domain 
             ORDER BY check_count DESC 
             LIMIT %d",
            $date_threshold,
            $limit
        ), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get credibility timeline
     */
    public static function get_credibility_timeline($days = 30) {
        global $wpdb;
        
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(checked_at) as date,
                    AVG(credibility_score) as avg_score,
                    COUNT(*) as check_count
             FROM $table_instances 
             WHERE checked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(checked_at)
             ORDER BY date ASC",
            $days
        ), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get propaganda technique heatmap
     */
    public static function get_propaganda_heatmap($timeframe = '7days') {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $date_threshold = self::get_date_threshold($timeframe);
        
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT propaganda_techniques, check_count 
             FROM $table_trends 
             WHERE last_seen >= %s 
               AND propaganda_techniques IS NOT NULL 
               AND propaganda_techniques != 'null'",
            $date_threshold
        ), ARRAY_A);
        
        $technique_counts = array();
        
        foreach ($trends as $trend) {
            $techniques = json_decode($trend['propaganda_techniques'], true);
            if (is_array($techniques)) {
                foreach ($techniques as $technique) {
                    if (!isset($technique_counts[$technique])) {
                        $technique_counts[$technique] = 0;
                    }
                    $technique_counts[$technique] += $trend['check_count'];
                }
            }
        }
        
        arsort($technique_counts);
        
        return $technique_counts;
    }
    
    /**
     * Create daily snapshot
     */
    public static function create_snapshot($type = 'daily') {
        global $wpdb;
        
        $table_snapshots = $wpdb->prefix . 'ai_verify_trending_snapshots';
        
        $timeframe = $type === 'daily' ? '1day' : '7days';
        
        $snapshot = array(
            'snapshot_date' => current_time('Y-m-d'),
            'snapshot_type' => $type,
            'trending_claims' => json_encode(self::get_trending_claims(20, null, $timeframe)),
            'category_breakdown' => json_encode(self::get_category_breakdown($timeframe)),
            'top_domains' => json_encode(self::get_top_domains(20, $timeframe)),
            'avg_credibility' => self::get_avg_credibility($timeframe),
            'total_checks' => self::get_total_checks($timeframe)
        );
        
        // Try to update if exists, otherwise insert
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_snapshots WHERE snapshot_date = %s AND snapshot_type = %s",
            $snapshot['snapshot_date'],
            $type
        ));
        
        if ($existing) {
            $wpdb->update(
                $table_snapshots,
                $snapshot,
                array('id' => $existing)
            );
        } else {
            $wpdb->insert($table_snapshots, $snapshot);
        }
    }
    
    // === HELPER FUNCTIONS ===
    
    private static function normalize_claim($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    private static function calculate_recency_weight($last_seen) {
        $hours_ago = (strtotime('now') - strtotime($last_seen)) / 3600;
        
        if ($hours_ago < 24) return 1.0;
        if ($hours_ago < 72) return 0.7;
        if ($hours_ago < 168) return 0.4;
        return 0.1;
    }
    
    private static function get_date_threshold($timeframe) {
        switch ($timeframe) {
            case '1day':
            case '24hours':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case '3days':
                return date('Y-m-d H:i:s', strtotime('-3 days'));
            case '7days':
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days':
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default:
                return date('Y-m-d H:i:s', strtotime('-7 days'));
        }
    }
    
    private static function get_avg_credibility($timeframe) {
        global $wpdb;
        
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $date_threshold = self::get_date_threshold($timeframe);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(credibility_score) FROM $table_instances WHERE checked_at >= %s",
            $date_threshold
        ));
    }
    
    private static function get_total_checks($timeframe) {
        global $wpdb;
        
        $table_instances = $wpdb->prefix . 'ai_verify_claim_instances';
        $date_threshold = self::get_date_threshold($timeframe);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_instances WHERE checked_at >= %s",
            $date_threshold
        ));
    }
}