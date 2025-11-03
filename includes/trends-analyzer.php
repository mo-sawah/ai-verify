<?php
/**
 * AI-Powered Claim Analysis and Categorization for Trends
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Trends_Analyzer {
    
    /**
     * Categorize and enrich a claim using AI
     */
    public static function analyze_claim($claim_text, $context = '') {
        error_log('AI Verify Trends: Analyzing claim for categorization');
        
        $provider = get_option('ai_verify_factcheck_provider', 'openrouter');
        
        // Use OpenRouter for categorization
        if (strpos($provider, 'openrouter') !== false || $provider === 'openrouter') {
            return self::categorize_with_openrouter($claim_text, $context);
        } else {
            return self::categorize_with_perplexity($claim_text, $context);
        }
    }
    
    /**
     * Categorize claim using OpenRouter
     */
    private static function categorize_with_openrouter($claim_text, $context) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            error_log('AI Verify Trends: OpenRouter API key not configured');
            return self::get_default_categorization($claim_text);
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        $prompt = "Analyze this claim and return ONLY a JSON object with categorization details.

Claim: {$claim_text}
Context: {$context}

Return this exact JSON structure:
{
    \"category\": \"politics|health|climate|technology|economics|crime|education|entertainment|sports|other\",
    \"subcategory\": \"specific topic within category\",
    \"entities\": [
        {\"type\": \"person|organization|location|event\", \"name\": \"entity name\"}
    ],
    \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
    \"sentiment\": \"positive|negative|neutral|mixed\",
    \"urgency\": \"low|medium|high|critical\",
    \"scope\": \"local|national|international|global\"
}

Rules:
- Choose ONE primary category that best fits
- Extract 3-5 key entities (people, organizations, locations)
- Identify 3-7 relevant keywords
- Assess the claim's sentiment and urgency
- Return ONLY the JSON, no other text";
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a data analyst. Return only valid JSON, no other text.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.1,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify Trends: OpenRouter error: ' . $response->get_error_message());
            return self::get_default_categorization($claim_text);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        // Extract JSON from response
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);
            
            if ($result && isset($result['category'])) {
                error_log('AI Verify Trends: Successfully categorized as ' . $result['category']);
                return $result;
            }
        }
        
        error_log('AI Verify Trends: Failed to parse AI response, using defaults');
        return self::get_default_categorization($claim_text);
    }
    
    /**
     * Categorize claim using Perplexity
     */
    private static function categorize_with_perplexity($claim_text, $context) {
        $api_key = get_option('ai_verify_perplexity_key');
        
        if (empty($api_key)) {
            return self::get_default_categorization($claim_text);
        }
        
        $prompt = "Analyze this claim and return ONLY a JSON object with categorization.

Claim: {$claim_text}

Return this JSON:
{
    \"category\": \"politics|health|climate|technology|economics|crime|education|entertainment|sports|other\",
    \"subcategory\": \"specific topic\",
    \"entities\": [{\"type\": \"person|organization|location\", \"name\": \"name\"}],
    \"keywords\": [\"keyword1\", \"keyword2\"],
    \"sentiment\": \"positive|negative|neutral\",
    \"urgency\": \"low|medium|high\"
}";
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'sonar-pro',
                'messages' => array(
                    array('role' => 'system', 'content' => 'Return only valid JSON.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.1,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return self::get_default_categorization($claim_text);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);
            if ($result && isset($result['category'])) {
                return $result;
            }
        }
        
        return self::get_default_categorization($claim_text);
    }
    
    /**
     * Fallback: Use keyword-based categorization
     */
    private static function get_default_categorization($claim_text) {
        $text_lower = strtolower($claim_text);
        
        // Simple keyword matching
        $categories = array(
            'politics' => array('election', 'president', 'government', 'vote', 'politician', 'congress', 'senate', 'law', 'policy'),
            'health' => array('vaccine', 'covid', 'virus', 'disease', 'hospital', 'doctor', 'medicine', 'treatment', 'pandemic'),
            'climate' => array('climate', 'global warming', 'environment', 'pollution', 'carbon', 'emissions', 'weather', 'temperature'),
            'technology' => array('ai', 'artificial intelligence', 'tech', 'computer', 'software', 'hack', 'cyber', 'internet'),
            'economics' => array('economy', 'inflation', 'stock', 'market', 'price', 'dollar', 'tax', 'unemployment', 'jobs'),
            'crime' => array('crime', 'murder', 'robbery', 'arrest', 'police', 'investigation', 'suspect', 'victim'),
            'education' => array('school', 'university', 'education', 'student', 'teacher', 'learning', 'college'),
            'entertainment' => array('celebrity', 'movie', 'music', 'actor', 'singer', 'hollywood', 'film'),
            'sports' => array('sport', 'game', 'player', 'team', 'championship', 'soccer', 'basketball', 'football')
        );
        
        $category_scores = array();
        
        foreach ($categories as $cat => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $score++;
                }
            }
            $category_scores[$cat] = $score;
        }
        
        arsort($category_scores);
        $category = key($category_scores) ?: 'other';
        
        // Extract basic keywords (words > 4 characters, not common words)
        $words = explode(' ', $text_lower);
        $stop_words = array('this', 'that', 'with', 'from', 'have', 'been', 'were', 'said', 'about', 'their');
        $keywords = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (strlen($word) > 4 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        $keywords = array_unique(array_slice($keywords, 0, 5));
        
        return array(
            'category' => $category,
            'subcategory' => 'general',
            'entities' => array(),
            'keywords' => array_values($keywords),
            'sentiment' => 'neutral',
            'urgency' => 'medium',
            'scope' => 'national'
        );
    }
    
    /**
     * Enrich trend data with categorization
     */
    public static function enrich_trend($trend_id) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        $trend = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_trends WHERE id = %d",
            $trend_id
        ), ARRAY_A);
        
        if (!$trend) {
            return false;
        }
        
        // Skip if already categorized
        if (!empty($trend['category']) && $trend['category'] !== 'general') {
            return true;
        }
        
        // Get categorization
        $analysis = self::analyze_claim($trend['claim_text']);
        
        // Update trend
        $wpdb->update(
            $table_trends,
            array(
                'category' => $analysis['category'],
                'subcategory' => $analysis['subcategory'],
                'entities' => json_encode($analysis['entities']),
                'keywords' => json_encode($analysis['keywords']),
                'sentiment' => $analysis['sentiment']
            ),
            array('id' => $trend_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Batch enrich uncategorized trends
     */
    public static function batch_enrich_trends($limit = 10) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table_trends 
             WHERE (category IS NULL OR category = 'general')
             ORDER BY check_count DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        $enriched = 0;
        
        foreach ($trends as $trend) {
            if (self::enrich_trend($trend['id'])) {
                $enriched++;
            }
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
        
        error_log("AI Verify Trends: Enriched {$enriched} trends");
        
        return $enriched;
    }
}