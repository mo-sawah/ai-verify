<?php
/**
 * PROFESSIONAL Claim Extraction and Analysis Engine
 * With ClaimBuster, Perplexity, Source Credibility, Propaganda Detection
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Analyzer {
    
    /**
     * Extract claims using ClaimBuster API or advanced AI
     */
    public static function extract_claims($content) {
        error_log('AI Verify: Starting claim extraction...');
        
        // Try ClaimBuster first (highly accurate)
        $claims = self::extract_with_claimbuster($content);
        
        // Fallback to AI-based extraction if ClaimBuster fails
        if (empty($claims)) {
            error_log('AI Verify: ClaimBuster failed, using AI extraction');
            $claims = self::extract_with_ai($content);
        }
        
        // Fallback to basic extraction if all else fails
        if (empty($claims)) {
            error_log('AI Verify: AI extraction failed, using basic extraction');
            $claims = self::extract_claims_basic($content);
        }
        
        error_log('AI Verify: Extracted ' . count($claims) . ' claims');
        return array_slice($claims, 0, 15); // Max 15 claims
    }
    
    /**
     * Extract claims using ClaimBuster API (FREE and accurate)
     */
    private static function extract_with_claimbuster($content) {
        $sentences = self::split_sentences($content);
        
        if (empty($sentences)) {
            return array();
        }
        
        $all_claims = array();
        $batch_size = 50;
        
        for ($i = 0; $i < count($sentences); $i += $batch_size) {
            $batch = array_slice($sentences, $i, $batch_size);
            $text = implode(' ', $batch);
            
            $response = wp_remote_post('https://idir.uta.edu/claimbuster/api/v2/score/text', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array('text' => $text)),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                error_log('AI Verify: ClaimBuster error: ' . $response->get_error_message());
                continue;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['results'])) {
                foreach ($data['results'] as $result) {
                    if ($result['score'] > 0.5) {
                        $all_claims[] = array(
                            'text' => $result['text'],
                            'score' => $result['score'],
                            'type' => self::classify_claim($result['text']),
                            'index' => count($all_claims)
                        );
                    }
                }
            }
        }
        
        usort($all_claims, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $all_claims;
    }
    
    /**
     * Extract claims using AI (fallback)
     */
    private static function extract_with_ai($content) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            return array();
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        $content = mb_substr($content, 0, 4000);
        
        $prompt = "Extract 10-15 factual claims from this article that can be verified. Return ONLY a JSON array:
[{\"text\": \"claim\", \"score\": 0.9, \"type\": \"statistical\"}]

Article: {$content}";
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'temperature' => 0.3,
                'max_tokens' => 2000
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        
        preg_match('/\[.*\]/s', $text, $matches);
        if (!empty($matches[0])) {
            $claims = json_decode($matches[0], true);
            return is_array($claims) ? $claims : array();
        }
        
        return array();
    }
    
    /**
     * Basic claim extraction (last resort fallback)
     */
    private static function extract_claims_basic($content) {
        $sentences = self::split_sentences($content);
        $claims = array();
        
        foreach ($sentences as $index => $sentence) {
            $score = self::calculate_claim_score($sentence);
            
            if ($score > 0.5) {
                $claims[] = array(
                    'text' => $sentence,
                    'score' => $score,
                    'index' => $index,
                    'type' => self::classify_claim($sentence)
                );
            }
        }
        
        usort($claims, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($claims, 0, 15);
    }

    /**
     * Fact-check claims using multiple methods
     */
    public static function factcheck_claims($claims, $context = '', $url = '') {
        $results = array();
        $api_provider = get_option('ai_verify_factcheck_provider', 'perplexity');
        
        error_log('AI Verify: Fact-checking ' . count($claims) . ' claims using ' . $api_provider);
        
        foreach ($claims as $claim) {
            $result = array(
                'claim' => $claim['text'],
                'type' => $claim['type'] ?? 'general',
                'score' => $claim['score'] ?? 0.5,
                'rating' => null,
                'explanation' => null,
                'sources' => array(),
                'confidence' => 0,
                'evidence_for' => array(),
                'evidence_against' => array(),
                'red_flags' => array(),
                'method' => 'Unknown'
            );
            
            // Step 1: Check Google Fact Check API (existing fact-checks)
            $google_key = get_option('ai_verify_google_factcheck_key');
            if (!empty($google_key)) {
                $google_result = self::check_google_factcheck($claim['text'], $google_key);
                if (!empty($google_result)) {
                    $result['rating'] = $google_result['rating'];
                    $result['explanation'] = $google_result['explanation'];
                    $result['sources'][] = $google_result['source'];
                    $result['confidence'] = 0.95;
                    $result['method'] = 'Google Fact Check API';
                    $results[] = $result;
                    continue;
                }
            }
            
            // Step 2: Use Perplexity or OpenRouter with WEB SEARCH
            if ($api_provider === 'perplexity') {
                $ai_result = self::check_with_perplexity($claim['text'], $context);
            } else {
                $ai_result = self::check_with_openrouter($claim['text'], $context);
            }
            
            if (!is_wp_error($ai_result) && !empty($ai_result)) {
                $result['rating'] = $ai_result['rating'] ?? 'Unknown';
                $result['explanation'] = $ai_result['explanation'] ?? 'No explanation available';
                $result['sources'] = $ai_result['sources'] ?? array();
                $result['confidence'] = $ai_result['confidence'] ?? 0.7;
                $result['evidence_for'] = $ai_result['evidence_for'] ?? array();
                $result['evidence_against'] = $ai_result['evidence_against'] ?? array();
                $result['red_flags'] = $ai_result['red_flags'] ?? array();
                $result['method'] = $api_provider === 'perplexity' ? 'Perplexity AI + Web Search' : 'OpenRouter AI + Web Search';
            } else {
                $result['rating'] = 'Unverified';
                $result['explanation'] = 'Unable to verify this claim with available sources.';
                $result['confidence'] = 0.3;
                $result['method'] = 'No verification available';
            }
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Check claim with Perplexity AI (HAS BUILT-IN WEB SEARCH)
     */
    private static function check_with_perplexity($claim, $context) {
        $api_key = get_option('ai_verify_perplexity_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Perplexity API key not configured');
            return new WP_Error('no_api_key', 'Perplexity API key not configured');
        }
        
        $prompt = "You are a professional fact-checker with access to current web search. Fact-check this claim thoroughly by searching for evidence.

Claim: {$claim}
Context: {$context}

Search the web and provide:
1. Rating: True/False/Mostly True/Mostly False/Misleading/Unproven
2. Detailed explanation with evidence
3. Sources you found (with URLs)
4. Evidence supporting the claim
5. Evidence contradicting the claim
6. Any red flags (propaganda techniques, logical fallacies)

Return ONLY valid JSON:
{
    \"rating\": \"...\",
    \"explanation\": \"...\",
    \"sources\": [{\"name\": \"...\", \"url\": \"...\"}],
    \"evidence_for\": [\"...\"],
    \"evidence_against\": [\"...\"],
    \"red_flags\": [\"...\"],
    \"confidence\": 0.0-1.0
}";
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'llama-3.1-sonar-large-128k-online',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a fact-checker with web search access.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 2000
            )),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: Perplexity API error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        return self::parse_fact_check_response($content);
    }
    
    /**
     * Check claim with OpenRouter AI
     */
    private static function check_with_openrouter($claim, $context) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API key not configured');
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        $prompt = "You are a professional fact-checker. Search the web and verify this claim.

Claim: {$claim}
Context: {$context}

IMPORTANT: You MUST search the web for current, authoritative sources.

Provide:
1. Rating: True/False/Mostly True/Mostly False/Misleading/Unproven
2. Detailed explanation
3. Sources with URLs
4. Evidence for and against
5. Any propaganda techniques or red flags

Return ONLY valid JSON:
{
    \"rating\": \"...\",
    \"explanation\": \"...\",
    \"sources\": [{\"name\": \"...\", \"url\": \"...\"}],
    \"evidence_for\": [\"...\"],
    \"evidence_against\": [\"...\"],
    \"red_flags\": [\"...\"],
    \"confidence\": 0.0-1.0
}";
        
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
                    array('role' => 'system', 'content' => 'You are a fact-checker with web search.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 2000
            )),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        return self::parse_fact_check_response($content);
    }

    /**
     * Parse fact-check response from AI
     */
    private static function parse_fact_check_response($content) {
        // Try to extract JSON
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);
            if ($result) {
                return $result;
            }
        }
        
        // Fallback parsing
        return array(
            'rating' => 'Unknown',
            'explanation' => $content,
            'sources' => array(),
            'evidence_for' => array(),
            'evidence_against' => array(),
            'red_flags' => array(),
            'confidence' => 0.5
        );
    }
    
    /**
     * Check Google Fact Check API
     */
    private static function check_google_factcheck($claim, $api_key) {
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
                'rating' => $review['textualRating'] ?? 'Unknown',
                'explanation' => $body['claims'][0]['text'] ?? '',
                'source' => array(
                    'name' => $review['publisher']['name'] ?? 'Unknown',
                    'url' => $review['url'] ?? ''
                )
            );
        }
        
        return null;
    }
    
    /**
     * Detect propaganda and manipulation techniques
     */
    public static function detect_propaganda($content) {
        $techniques = array();
        $content_lower = strtolower($content);
        
        // Emotional manipulation
        $emotional_words = array('shocking', 'outrageous', 'terrifying', 'urgent', 'breaking', 'horrifying', 'devastating');
        foreach ($emotional_words as $word) {
            if (stripos($content_lower, $word) !== false) {
                $techniques[] = 'Emotional manipulation detected';
                break;
            }
        }
        
        // Cherry-picking
        if (preg_match('/\b(only|just|merely|simply)\b/i', $content) && 
            !preg_match('/\b(however|but|although|while)\b/i', $content)) {
            $techniques[] = 'Possible cherry-picking (one-sided presentation)';
        }
        
        // False dichotomy
        if (preg_match('/\b(either.*or|you\'?re either|only two)\b/i', $content)) {
            $techniques[] = 'False dichotomy detected';
        }
        
        // Ad hominem attacks
        if (preg_match('/\b(stupid|idiot|moron|corrupt|evil|liar)\b/i', $content)) {
            $techniques[] = 'Ad hominem attacks detected';
        }
        
        // Conspiracy language
        if (preg_match('/\b(they don\'?t want you|hidden truth|wake up|sheeple|cover[- ]?up)\b/i', $content)) {
            $techniques[] = 'Conspiracy rhetoric detected';
        }
        
        // Exaggeration
        if (preg_match('/\b(always|never|everyone|nobody|all|none)\b/i', $content)) {
            $techniques[] = 'Possible exaggeration or absolutism';
        }
        
        return $techniques;
    }
    
    /**
     * Check source credibility
     */
    public static function check_source_credibility($url) {
        if (empty($url)) {
            return array('credibility' => 'unknown', 'reason' => 'No URL provided');
        }
        
        $domain = parse_url($url, PHP_URL_HOST);
        
        // Known credible sources
        $credible = array('reuters.com', 'apnews.com', 'bbc.com', 'nytimes.com', 'theguardian.com');
        if (in_array($domain, $credible)) {
            return array('credibility' => 'high', 'reason' => 'Established news organization');
        }
        
        // Known fake news domains
        $fake = array('naturalnews.com', 'infowars.com', 'beforeitsnews.com');
        if (in_array($domain, $fake)) {
            return array('credibility' => 'very_low', 'reason' => 'Known disinformation source');
        }
        
        // Check for .gov, .edu
        if (preg_match('/\.(gov|edu)$/', $domain)) {
            return array('credibility' => 'high', 'reason' => 'Government/educational domain');
        }
        
        return array('credibility' => 'medium', 'reason' => 'Standard web domain');
    }
    
    /**
     * Calculate overall credibility score
     */
    public static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 50;
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($factcheck_results as $result) {
            $rating = strtolower($result['rating'] ?? 'unknown');
            $confidence = floatval($result['confidence'] ?? 0.5);
            
            // Convert rating to score
            $score = 0.5; // default
            if (strpos($rating, 'true') !== false && strpos($rating, 'false') === false) {
                $score = 1.0;
            } elseif (strpos($rating, 'mostly true') !== false) {
                $score = 0.75;
            } elseif (strpos($rating, 'mixture') !== false || strpos($rating, 'misleading') !== false) {
                $score = 0.5;
            } elseif (strpos($rating, 'mostly false') !== false) {
                $score = 0.25;
            } elseif (strpos($rating, 'false') !== false) {
                $score = 0;
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
        if ($score >= 80) return 'Highly Credible';
        if ($score >= 60) return 'Mostly Credible';
        if ($score >= 40) return 'Mixed Credibility';
        if ($score >= 20) return 'Low Credibility';
        return 'Not Credible';
    }
    
    // ============ HELPER FUNCTIONS ============
    
    private static function split_sentences($text) {
        $text = preg_replace('/\s+/', ' ', $text);
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text);
        $cleaned = array();
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (str_word_count($sentence) >= 10) {
                $cleaned[] = $sentence;
            }
        }
        
        return $cleaned;
    }
    
    private static function calculate_claim_score($sentence) {
        $score = 0.0;
        
        $factual_keywords = array('according to', 'said', 'stated', 'study shows', 'research found', 
            'data shows', 'percent', 'million', 'billion', 'increase', 'decrease');
        
        foreach ($factual_keywords as $keyword) {
            if (stripos($sentence, $keyword) !== false) {
                $score += 0.2;
            }
        }
        
        if (preg_match('/\d+/', $sentence)) $score += 0.15;
        if (preg_match('/"[^"]*"/', $sentence)) $score += 0.15;
        if (preg_match('/\b(19|20)\d{2}\b/', $sentence)) $score += 0.1;
        
        return min($score, 1.0);
    }
    
    private static function classify_claim($sentence) {
        if (preg_match('/\d+/', $sentence)) return 'statistical';
        if (preg_match('/"[^"]*"/', $sentence)) return 'quote';
        if (stripos($sentence, 'according to') !== false) return 'attribution';
        return 'general';
    }
    
    private static function extract_keywords($text) {
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be');
        
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
}