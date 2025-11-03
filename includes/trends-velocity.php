<?php
/**
 * Velocity Tracking System
 * Calculates viral velocity and trending status
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Velocity_Tracker {
    
    /**
     * Calculate velocity for a claim
     */
    public static function calculate_velocity($trend_id) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        
        // Get current trend data
        $trend = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_trends WHERE id = %d",
            $trend_id
        ), ARRAY_A);
        
        if (!$trend) {
            return false;
        }
        
        $current_count = intval($trend['check_count']);
        $now = current_time('mysql');
        
        // Get snapshots from last 24h
        $snapshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_velocity 
             WHERE trend_id = %d 
             AND timestamp >= DATE_SUB(%s, INTERVAL 24 HOUR)
             ORDER BY timestamp DESC",
            $trend_id,
            $now
        ), ARRAY_A);
        
        // Calculate velocity metrics
        $velocity_1h = 0;
        $velocity_6h = 0;
        $velocity_24h = 0;
        
        $snapshot_1h = self::get_snapshot_at_time($snapshots, $now, 1);
        $snapshot_6h = self::get_snapshot_at_time($snapshots, $now, 6);
        $snapshot_24h = self::get_snapshot_at_time($snapshots, $now, 24);
        
        if ($snapshot_1h) {
            $velocity_1h = ($current_count - $snapshot_1h['check_count_snapshot']) / 1;
        }
        
        if ($snapshot_6h) {
            $velocity_6h = ($current_count - $snapshot_6h['check_count_snapshot']) / 6;
        }
        
        if ($snapshot_24h) {
            $velocity_24h = ($current_count - $snapshot_24h['check_count_snapshot']) / 24;
        }
        
        // Calculate overall velocity score (weighted average)
        $velocity_score = ($velocity_1h * 0.5) + ($velocity_6h * 0.3) + ($velocity_24h * 0.2);
        
        // Calculate shares per hour (average)
        $shares_per_hour = $velocity_6h > 0 ? $velocity_6h : $velocity_24h;
        
        // Determine status
        $status = self::determine_status($velocity_score, $shares_per_hour);
        
        // Save snapshot
        $wpdb->insert(
            $table_velocity,
            array(
                'trend_id' => $trend_id,
                'timestamp' => $now,
                'check_count_snapshot' => $current_count,
                'velocity_1h' => round($velocity_1h, 2),
                'velocity_6h' => round($velocity_6h, 2),
                'velocity_24h' => round($velocity_24h, 2),
                'platform_data' => json_encode(array())
            ),
            array('%d', '%s', '%d', '%f', '%f', '%f', '%s')
        );
        
        // Update trend with velocity data
        $wpdb->update(
            $table_trends,
            array(
                'velocity_status' => $status,
                'velocity_score' => round($velocity_score, 2),
                'shares_per_hour' => round($shares_per_hour, 2)
            ),
            array('id' => $trend_id),
            array('%s', '%f', '%f'),
            array('%d')
        );
        
        return array(
            'status' => $status,
            'velocity_score' => round($velocity_score, 2),
            'shares_per_hour' => round($shares_per_hour, 2),
            'velocity_1h' => round($velocity_1h, 2),
            'velocity_6h' => round($velocity_6h, 2),
            'velocity_24h' => round($velocity_24h, 2)
        );
    }
    
    /**
     * Get snapshot closest to X hours ago
     */
    private static function get_snapshot_at_time($snapshots, $current_time, $hours_ago) {
        $target_time = strtotime("-{$hours_ago} hours", strtotime($current_time));
        
        $closest = null;
        $smallest_diff = PHP_INT_MAX;
        
        foreach ($snapshots as $snapshot) {
            $snapshot_time = strtotime($snapshot['timestamp']);
            $diff = abs($snapshot_time - $target_time);
            
            if ($diff < $smallest_diff) {
                $smallest_diff = $diff;
                $closest = $snapshot;
            }
        }
        
        return $closest;
    }
    
    /**
     * Determine viral status
     */
    private static function determine_status($velocity_score, $shares_per_hour) {
        if ($velocity_score >= 50) {
            return 'viral';
        } elseif ($velocity_score >= 20) {
            return 'emerging';
        } elseif ($velocity_score >= 5) {
            return 'active';
        } elseif ($velocity_score > 0) {
            return 'slow';
        } else {
            return 'dormant';
        }
    }
    
    /**
     * Batch calculate velocity for all active trends
     */
    public static function batch_calculate_velocity() {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Get trends updated in last 48 hours
        $trends = $wpdb->get_results(
            "SELECT id FROM $table_trends 
             WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
             ORDER BY check_count DESC
             LIMIT 100",
            ARRAY_A
        );
        
        $count = 0;
        foreach ($trends as $trend) {
            self::calculate_velocity($trend['id']);
            $count++;
        }
        
        error_log("AI Verify: Calculated velocity for {$count} trends");
        
        return $count;
    }
    
    /**
     * Get trending claims (sorted by velocity)
     */
    public static function get_viral_claims($limit = 20) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        $claims = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_trends 
             WHERE velocity_status IN ('viral', 'emerging', 'active')
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY velocity_score DESC, shares_per_hour DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $claims;
    }
    
    /**
     * Cleanup old snapshots (keep 30 days)
     */
    public static function cleanup_old_snapshots() {
        global $wpdb;
        
        $table_velocity = $wpdb->prefix . 'ai_verify_velocity_snapshots';
        
        $deleted = $wpdb->query(
            "DELETE FROM $table_velocity 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        error_log("AI Verify: Cleaned up {$deleted} old velocity snapshots");
    }
}