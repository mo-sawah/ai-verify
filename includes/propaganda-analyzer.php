<?php
/**
 * Propaganda Analysis for Intelligence Dashboard
 * Aggregates and displays propaganda techniques from database
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Propaganda_Analyzer {
    
    public static function init() {
        // AJAX handler for propaganda data
        add_action('wp_ajax_ai_verify_get_propaganda_data', array(__CLASS__, 'get_propaganda_data'));
        add_action('wp_ajax_nopriv_ai_verify_get_propaganda_data', array(__CLASS__, 'get_propaganda_data'));
    }
    
    /**
     * Get propaganda data from database
     */
    public static function get_propaganda_data() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '7days');
        $category = sanitize_text_field($_POST['category'] ?? 'all');
        
        $data = self::analyze_propaganda_trends($timeframe, $category);
        
        wp_send_json_success($data);
    }
    
    /**
     * Analyze propaganda trends
     */
    public static function analyze_propaganda_trends($timeframe = '7days', $category = 'all') {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Convert timeframe to days
        $days_map = array(
            '1day' => 1,
            '3days' => 3,
            '7days' => 7,
            '30days' => 30
        );
        $days = $days_map[$timeframe] ?? 7;
        
        // Build query
        $where = "WHERE last_seen >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        
        if ($category !== 'all') {
            $where .= $wpdb->prepare(" AND category = %s", $category);
        }
        
        // Get all claims with propaganda data
        $claims = $wpdb->get_results("
            SELECT claim_text, metadata, avg_credibility_score, velocity_status, category, check_count
            FROM $table_trends
            {$where}
            AND metadata IS NOT NULL
            ORDER BY last_seen DESC
        ", ARRAY_A);
        
        // Aggregate propaganda techniques
        $techniques_count = array();
        $techniques_by_category = array();
        $claims_with_propaganda = array();
        $total_propaganda_claims = 0;
        
        foreach ($claims as $claim) {
            $metadata = json_decode($claim['metadata'], true);
            
            if (empty($metadata['propaganda_techniques'])) {
                continue;
            }
            
            $techniques = $metadata['propaganda_techniques'];
            $total_propaganda_claims++;
            
            // Count techniques
            foreach ($techniques as $technique) {
                if (!isset($techniques_count[$technique])) {
                    $techniques_count[$technique] = 0;
                }
                $techniques_count[$technique]++;
                
                // By category
                $cat = $claim['category'];
                if (!isset($techniques_by_category[$cat])) {
                    $techniques_by_category[$cat] = array();
                }
                if (!isset($techniques_by_category[$cat][$technique])) {
                    $techniques_by_category[$cat][$technique] = 0;
                }
                $techniques_by_category[$cat][$technique]++;
            }
            
            // Store claim with propaganda
            $claims_with_propaganda[] = array(
                'claim' => $claim['claim_text'],
                'techniques' => $techniques,
                'credibility' => $claim['avg_credibility_score'],
                'velocity' => $claim['velocity_status'],
                'category' => $claim['category'],
                'checks' => $claim['check_count']
            );
        }
        
        // Sort by count
        arsort($techniques_count);
        
        // Get top techniques
        $top_techniques = array_slice($techniques_count, 0, 10, true);
        
        // Calculate percentages
        $total_claims = count($claims);
        $propaganda_percentage = $total_claims > 0 ? round(($total_propaganda_claims / $total_claims) * 100, 1) : 0;
        
        // Get technique definitions
        $technique_definitions = self::get_propaganda_definitions();
        
        return array(
            'total_claims' => $total_claims,
            'propaganda_claims' => $total_propaganda_claims,
            'propaganda_percentage' => $propaganda_percentage,
            'top_techniques' => $top_techniques,
            'techniques_by_category' => $techniques_by_category,
            'claims_with_propaganda' => array_slice($claims_with_propaganda, 0, 20),
            'definitions' => $technique_definitions
        );
    }
    
    /**
     * Get propaganda technique definitions
     */
    private static function get_propaganda_definitions() {
        return array(
            'Appeal to Fear' => 'Uses fear or threats to persuade audience',
            'Appeal to Authority' => 'Claims something is true because an authority says so',
            'Bandwagon' => 'Appeals to desire to follow the crowd',
            'Black-and-White Fallacy' => 'Presents only two options when more exist',
            'Causal Oversimplification' => 'Assumes single cause for complex issue',
            'Doubt' => 'Questions credibility without evidence',
            'Exaggeration/Minimization' => 'Makes things bigger or smaller than reality',
            'Flag-Waving' => 'Appeals to patriotism or group identity',
            'Loaded Language' => 'Uses emotionally charged words',
            'Name Calling/Labeling' => 'Gives negative labels to discredit',
            'Obfuscation' => 'Uses confusing or vague language',
            'Red Herring' => 'Introduces irrelevant information',
            'Reductio ad Hitlerum' => 'Compares opponent to Hitler/Nazis',
            'Repetition' => 'Repeats message to make it seem true',
            'Slogans' => 'Uses catchy phrases instead of reasoning',
            'Straw Man' => 'Misrepresents opponent\'s argument',
            'Thought-Terminating Cliché' => 'Uses clichés to stop critical thinking',
            'Whataboutism' => 'Deflects by pointing to others\' wrongdoing'
        );
    }
    
    /**
     * Get propaganda severity level
     */
    public static function get_severity_level($technique_count) {
        if ($technique_count >= 5) {
            return array('level' => 'critical', 'color' => '#ef4444', 'label' => 'Critical');
        } elseif ($technique_count >= 3) {
            return array('level' => 'high', 'color' => '#f59e0b', 'label' => 'High');
        } elseif ($technique_count >= 1) {
            return array('level' => 'moderate', 'color' => '#eab308', 'label' => 'Moderate');
        } else {
            return array('level' => 'low', 'color' => '#10b981', 'label' => 'Low');
        }
    }
}

AI_Verify_Propaganda_Analyzer::init();