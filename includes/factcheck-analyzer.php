<?php
/**
 * Claim Extraction and Analysis Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Analyzer {
    
    /**
     * Extract claims from content
     */
    public static function extract_claims($content) {
        // Split into sentences
        $sentences = self::split_sentences($content);
        
        $claims = array();
        
        foreach ($sentences as $index => $sentence) {
            $score = self::calculate_claim_score($sentence);
            
            // Only keep high-scoring claims (> 0.5)
            if ($score > 0.5) {
                $claims[] = array(
                    'text' => $sentence,
                    'score' => $score,
                    'index' => $index,
                    'type' => self::classify_claim($sentence)
                );
            }
        }
        
        // Sort by score
        usort($claims, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top 10 claims
        return array_slice($claims, 0, 10);
    }
    
    /**
     * Split text into sentences
     */
    private static function split_sentences($text) {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Split by sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text);
        
        $cleaned = array();
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            // Only keep sentences with 10+ words
            if (str_word_count($sentence) >= 10) {
                $cleaned[] = $sentence;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Calculate claim-worthiness score
     */
    private static function calculate_claim_score($sentence) {
        $score = 0.0;
        
        // Check for factual indicators
        $factual_keywords = array(
            'according to', 'said', 'stated', 'announced', 'reported', 
            'study shows', 'research found', 'data shows', 'statistics show',
            'percent', 'million', 'billion', 'trillion', 'thousand',
            'increase', 'decrease', 'rise', 'fall', 'doubled', 'tripled'
        );
        
        foreach ($factual_keywords as $keyword) {
            if (stripos($sentence, $keyword) !== false) {
                $score += 0.2;
            }
        }
        
        // Check for numbers/statistics
        if (preg_match('/\d+/', $sentence)) {
            $score += 0.15;
        }
        
        // Check for quotes
        if (preg_match('/"[^"]*"/', $sentence)) {
            $score += 0.15;
        }
        
        // Check for specific dates
        if (preg_match('/\b(19|20)\d{2}\b/', $sentence)) {
            $score += 0.1;
        }
        
        // Check for comparisons
        $comparison_words = array('more than', 'less than', 'higher than', 'lower than', 'compared to');
        foreach ($comparison_words as $word) {
            if (stripos($sentence, $word) !== false) {
                $score += 0.1;
                break;
            }
        }
        
        // Check for causation
        $causation_words = array('because', 'due to', 'caused by', 'resulted in', 'led to');
        foreach ($causation_words as $word) {
            if (stripos($sentence, $word) !== false) {
                $score += 0.15;
                break;
            }
        }
        
        // Cap at 1.0
        return min($score, 1.0);
    }
    
    /**
     * Classify claim type
     */
    private static function classify_claim($sentence) {
        if (preg_match('/\d+/', $sentence)) {
            return 'statistical';
        }
        if (preg_match('/"[^"]*"/', $sentence)) {
            return 'quote';
        }
        if (stripos($sentence, 'according to') !== false || stripos($sentence, 'said') !== false) {
            return 'attribution';
        }
        return 'general';
    }
    
    /**
     * Fact-check claims using multiple sources
     */
    public static function factcheck_claims($claims, $context = '') {
        $results = array();
        
        $api_key = get_option('ai_verify_google_factcheck_key');
        $openrouter_key = get_option('ai_verify_openrouter_key');
        
        foreach ($claims as $claim) {
            $result = array(
                'claim' => $claim['text'],
                'type' => $claim['type'],
                'score' => $claim['score'],
                'rating' => null,
                'explanation' => null,
                'sources' => array(),
                'confidence' => 0
            );
            
            // Step 1: Check Google Fact Check API
            if (!empty($api_key)) {
                $google_result = self::check_google_factcheck($claim['text'], $api_key);
                if (!empty($google_result)) {
                    $result['rating'] = $google_result['rating'];
                    $result['explanation'] = $google_result['explanation'];
                    $result['sources'][] = $google_result['source'];
                    $result['confidence'] = 0.9; // High confidence for verified fact-checks
                    $results[] = $result;
                    continue;
                }
            }
            
            // Step 2: Use AI with web search
            if (!empty($openrouter_key)) {
                $ai_result = self::check_with_ai($claim['text'], $context, $openrouter_key);
                if (!is_wp_error($ai_result)) {
                    $result['rating'] = $ai_result['rating'];
                    $result['explanation'] = $ai_result['explanation'];
                    $result['sources'] = $ai_result['sources'];
                    $result['confidence'] = $ai_result['confidence'];
                }
            }
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Check claim against Google Fact Check API
     */
    private static function check_google_factcheck($claim, $api_key) {
        // Extract key terms from claim
        $keywords = self::extract_keywords($claim);
        $query = implode(' ', array_slice($keywords, 0, 5));
        
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en'
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['claims'][0]['claimReview'][0])) {
            $review = $body['claims'][0]['claimReview'][0];
            return array(
                'rating' => isset($review['textualRating']) ? $review['textualRating'] : 'Unknown',
                'explanation' => isset($body['claims'][0]['text']) ? $body['claims'][0]['text'] : '',
                'source' => array(
                    'name' => isset($review['publisher']['name']) ? $review['publisher']['name'] : 'Unknown',
                    'url' => isset($review['url']) ? $review['url'] : ''
                )
            );
        }
        
        return null;
    }
    
    /**
     * Check claim with AI + Web Search
     */
    private static function check_with_ai($claim, $context, $api_key) {
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        $system_prompt = "You are a professional fact-checker. Analyze the claim and provide a detailed fact-check with sources. Context: {$context}";
        
        $user_prompt = "Fact-check this claim and provide:\n1. Rating (True/False/Mostly True/Mostly False/Mixture/Unproven)\n2. Detailed explanation\n3. Sources (with URLs if possible)\n\nClaim: {$claim}\n\nProvide response in JSON format: {\"rating\": \"...\", \"explanation\": \"...\", \"sources\": [{\"name\": \"...\", \"url\": \"...\"}], \"confidence\": 0.0-1.0}";
        
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
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $user_prompt)
                ),
                'temperature' => 0.3,
                'max_tokens' => 1000
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('ai_error', 'Invalid AI response');
        }
        
        $content = $body['choices'][0]['message']['content'];
        
        // Try to parse JSON response
        $json_match = array();
        if (preg_match('/\{.*\}/s', $content, $json_match)) {
            $result = json_decode($json_match[0], true);
            if ($result) {
                return $result;
            }
        }
        
        // Fallback: parse text response
        return array(
            'rating' => 'Unknown',
            'explanation' => $content,
            'sources' => array(),
            'confidence' => 0.5
        );
    }
    
    /**
     * Extract keywords from text
     */
    private static function extract_keywords($text) {
        // Remove stop words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those');
        
        $words = preg_split('/\s+/', strtolower($text));
        $keywords = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Calculate overall credibility score
     */
    public static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 0;
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($factcheck_results as $result) {
            $rating = isset($result['rating']) ? strtolower($result['rating']) : 'unknown';
            $confidence = isset($result['confidence']) ? floatval($result['confidence']) : 0.5;
            
            // Convert rating to score
            $score = 0;
            if (strpos($rating, 'true') !== false && strpos($rating, 'false') === false) {
                $score = 1.0;
            } elseif (strpos($rating, 'mostly true') !== false) {
                $score = 0.75;
            } elseif (strpos($rating, 'mixture') !== false || strpos($rating, 'mixed') !== false) {
                $score = 0.5;
            } elseif (strpos($rating, 'mostly false') !== false) {
                $score = 0.25;
            } elseif (strpos($rating, 'false') !== false) {
                $score = 0;
            } else {
                $score = 0.5; // Unknown
            }
            
            $weight = $confidence;
            $total_score += $score * $weight;
            $total_weight += $weight;
        }
        
        if ($total_weight == 0) {
            return 50;
        }
        
        return round(($total_score / $total_weight) * 100, 2);
    }
    
    /**
     * Get credibility rating from score
     */
    public static function get_credibility_rating($score) {
        if ($score >= 80) {
            return 'Highly Credible';
        } elseif ($score >= 60) {
            return 'Mostly Credible';
        } elseif ($score >= 40) {
            return 'Mixed Credibility';
        } elseif ($score >= 20) {
            return 'Low Credibility';
        } else {
            return 'Not Credible';
        }
    }
}