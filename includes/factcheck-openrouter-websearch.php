<?php
/**
 * OpenRouter Native Web Search Analyzer
 * NEW MODE: Uses OpenRouter's built-in web search capabilities
 * This is separate from the Tavily-based multistep mode
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_OpenRouter_WebSearch {
    
    /**
     * Analyze content using OpenRouter with native web search
     * This is a multistep process but uses OpenRouter's search instead of Tavily
     */
    public static function analyze_with_websearch($content, $context, $url = '') {
        error_log('AI Verify: Starting OpenRouter Native Web Search Analysis');
        
        // Step 1: Extract claims (same as before)
        $claims = self::extract_claims($content);
        
        if (empty($claims)) {
            return new WP_Error('no_claims', 'Could not extract verifiable claims from content');
        }
        
        error_log('AI Verify: Extracted ' . count($claims) . ' claims for OpenRouter web search');
        
        // Step 2: Fact-check each claim using OpenRouter's web search
        $factcheck_results = self::factcheck_claims_with_websearch($claims, $context, $url);
        
        // Step 3: Detect propaganda
        $propaganda = self::detect_propaganda($content);
        
        // Step 4: Calculate scores
        $overall_score = self::calculate_overall_score($factcheck_results);
        $credibility_rating = self::get_credibility_rating($overall_score);
        
        return array(
            'factcheck_results' => $factcheck_results,
            'overall_score' => $overall_score,
            'credibility_rating' => $credibility_rating,
            'propaganda_techniques' => $propaganda,
            'method' => 'OpenRouter Native Web Search'
        );
    }
    
    /**
     * Extract claims using OpenRouter
     */
    private static function extract_claims($content) {
        $api_key = get_option('ai_verify_openrouter_key');
        if (empty($api_key)) {
            return array();
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        $content = mb_substr($content, 0, 6000);
        
        $prompt = "Extract 8-12 verifiable factual claims from this article. Focus on statistics, names, dates, locations, events, cause-effect relationships, policy claims, scientific claims.

Return ONLY a JSON array:
[{\"text\": \"claim\", \"score\": 0.9, \"type\": \"statistical\"}]

Types: \"statistical\", \"quote\", \"causal\", \"policy\", \"scientific\", \"general\"

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
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.3,
                'max_tokens' => 2000
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: OpenRouter claim extraction failed: ' . $response->get_error_message());
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        
        preg_match('/\[.*\]/s', $text, $matches);
        if (!empty($matches[0])) {
            $claims = json_decode($matches[0], true);
            if (is_array($claims)) {
                error_log('AI Verify: Successfully extracted ' . count($claims) . ' claims');
                return $claims;
            }
        }
        
        error_log('AI Verify: Failed to parse claims JSON');
        return array();
    }
    
    /**
     * Fact-check claims using OpenRouter's web search capabilities
     * This makes multiple search calls per claim
     */
    private static function factcheck_claims_with_websearch($claims, $context, $url) {
        $results = array();
        $api_key = get_option('ai_verify_openrouter_key');
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        // Check if Google Fact Check is available
        $google_key = get_option('ai_verify_google_factcheck_key');
        
        foreach ($claims as $index => $claim) {
            error_log('AI Verify: Checking claim ' . ($index + 1) . '/' . count($claims) . ' with OpenRouter web search');
            
            $claim_text = $claim['text'];
            
            $result = array(
                'claim' => $claim_text,
                'type' => $claim['type'] ?? 'general',
                'score' => $claim['score'] ?? 0.5,
                'rating' => 'Unverified',
                'explanation' => '',
                'sources' => array(),
                'confidence' => 0,
                'evidence_for' => array(),
                'evidence_against' => array(),
                'red_flags' => array(),
                'method' => 'OpenRouter Web Search'
            );
            
            // Try Google Fact Check first
            if (!empty($google_key)) {
                $google_result = self::check_google_factcheck($claim_text, $google_key);
                if (!empty($google_result)) {
                    $result['rating'] = $google_result['rating'];
                    $result['explanation'] = $google_result['explanation'];
                    $result['sources'][] = $google_result['source'];
                    $result['confidence'] = 0.95;
                    $result['method'] = 'Google Fact Check';
                    $results[] = $result;
                    continue;
                }
            }
            
            // Use OpenRouter with web search
            $check_result = self::check_claim_with_websearch($claim_text, $context, $url, $api_key, $model);
            
            if (!is_wp_error($check_result) && !empty($check_result)) {
                $result['rating'] = $check_result['rating'] ?? 'Unverified';
                $result['explanation'] = $check_result['explanation'] ?? 'No analysis available';
                $result['sources'] = $check_result['sources'] ?? array();
                $result['confidence'] = $check_result['confidence'] ?? 0.6;
                $result['evidence_for'] = $check_result['evidence_for'] ?? array();
                $result['evidence_against'] = $check_result['evidence_against'] ?? array();
                $result['red_flags'] = $check_result['red_flags'] ?? array();
            } else {
                $result['explanation'] = 'Unable to verify this claim with web sources.';
                $result['confidence'] = 0.3;
            }
            
            $results[] = $result;
            
            // Small delay between claims to avoid rate limiting
            if ($index < count($claims) - 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        return $results;
    }
    
    /**
     * Check a single claim using OpenRouter's web search
     * This method uses OpenRouter's search capabilities via their API
     */
    private static function check_claim_with_websearch($claim, $context, $url, $api_key, $model) {
        error_log('AI Verify: Analyzing claim with OpenRouter web search: ' . substr($claim, 0, 100));
        
        // Build the search-enhanced prompt
        $prompt = "You are a professional fact-checker with web search capabilities. Your task is to verify this claim by searching the web and analyzing sources.

CLAIM TO VERIFY:
{$claim}

ARTICLE CONTEXT:
{$context}
" . (!empty($url) ? "SOURCE URL: {$url}\n\n" : "") . "

INSTRUCTIONS:
1. Search the web for information about this specific claim
2. Focus on authoritative sources (news agencies, academic papers, government sites, fact-checking organizations)
3. Analyze what you find and compare it to the claim
4. Provide a detailed fact-check with proper citations

Return your analysis as a JSON object with this EXACT structure:
{
    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
    \"explanation\": \"Detailed 3-5 paragraph analysis. Start by explaining what the claim states, then what your web searches found, then your conclusion.\",
    \"sources\": [{\"name\": \"Source Name\", \"url\": \"https://example.com\"}],
    \"evidence_for\": [\"Evidence supporting the claim\"],
    \"evidence_against\": [\"Evidence contradicting the claim\"],
    \"red_flags\": [\"Any propaganda techniques or misleading elements\"],
    \"confidence\": 0.85
}

RATING GUIDELINES:
- \"True\": Web sources strongly confirm the claim
- \"False\": Web sources strongly contradict the claim  
- \"Mostly True\": Claim is largely accurate but missing context
- \"Mostly False\": Claim has some truth but is largely inaccurate
- \"Misleading\": Technically true but presented deceptively
- \"Unverified\": Cannot find sufficient reliable sources

CRITICAL: Include ALL sources you found in your search. Cite them like [Source 1], [Source 2] in your explanation.";

        // Prepare the API request with search capabilities
        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional fact-checker with web search capabilities. Search the web thoroughly to verify claims and cite all sources you find. Always return valid JSON.'
                ),
                array(
                    'role' => 'user', 
                    'content' => $prompt
                )
            ),
            'temperature' => 0.2,
            'max_tokens' => 3000
        );
        
        // Add web search parameters if the model supports it
        // Note: This depends on OpenRouter's specific implementation
        // Some models may support a 'tools' parameter for web search
        if (self::model_supports_websearch($model)) {
            $body['tools'] = array(
                array(
                    'type' => 'web_search',
                    'max_results' => 5
                )
            );
        }
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: OpenRouter websearch API error: ' . $response->get_error_message());
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body_text = wp_remote_retrieve_body($response);
            error_log('AI Verify: OpenRouter websearch returned status ' . $status_code . ': ' . substr($body_text, 0, 500));
            return new WP_Error('api_error', 'API returned status ' . $status_code);
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Verify: Failed to parse OpenRouter response JSON');
            return new WP_Error('json_error', 'Invalid JSON response');
        }
        
        // Extract the content from the response
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            error_log('AI Verify: Empty content in OpenRouter response');
            return new WP_Error('empty_response', 'No content in response');
        }
        
        // Parse the fact-check result from the content
        $result = self::parse_factcheck_response($content);
        
        // Log the search sources if available in the response metadata
        if (!empty($data['citations'])) {
            error_log('AI Verify: Found ' . count($data['citations']) . ' citations in response');
            // Merge citations into sources if available
            foreach ($data['citations'] as $citation) {
                $result['sources'][] = array(
                    'name' => $citation['title'] ?? $citation['url'] ?? 'Unknown',
                    'url' => $citation['url'] ?? ''
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Check if a model supports web search
     */
    private static function model_supports_websearch($model) {
        // Models that natively support web search on OpenRouter
        $search_models = array(
            'perplexity/',
            'openai/gpt-4-turbo-preview',
            'anthropic/claude-3-opus',
            'anthropic/claude-3.5-sonnet',
            'anthropic/claude-3-sonnet'
        );
        
        foreach ($search_models as $prefix) {
            if (strpos($model, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse the fact-check response from OpenRouter
     */
    private static function parse_factcheck_response($content) {
        error_log('AI Verify: Parsing OpenRouter websearch response (first 300 chars): ' . substr($content, 0, 300));
        
        // Try to extract JSON from the response
        // Handle markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $json_str = $matches[1];
        } elseif (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
            $json_str = $matches[0];
        } else {
            error_log('AI Verify: No JSON found in response, creating fallback');
            return self::create_fallback_result($content);
        }
        
        // Clean the JSON string
        $json_str = trim($json_str);
        
        // Decode JSON
        $result = json_decode($json_str, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Verify: JSON decode failed - ' . json_last_error_msg());
            return self::create_fallback_result($content);
        }
        
        // Validate structure
        if (!is_array($result) || !isset($result['rating'])) {
            error_log('AI Verify: Invalid result structure');
            return self::create_fallback_result($content);
        }
        
        // Ensure all required fields exist
        $result['rating'] = $result['rating'] ?? 'Unverified';
        $result['explanation'] = $result['explanation'] ?? 'No explanation provided';
        $result['sources'] = $result['sources'] ?? array();
        $result['evidence_for'] = $result['evidence_for'] ?? array();
        $result['evidence_against'] = $result['evidence_against'] ?? array();
        $result['red_flags'] = $result['red_flags'] ?? array();
        $result['confidence'] = isset($result['confidence']) ? floatval($result['confidence']) : 0.6;
        
        error_log('AI Verify: Successfully parsed result with rating: ' . $result['rating']);
        
        return $result;
    }
    
    /**
     * Create fallback result when parsing fails
     */
    private static function create_fallback_result($content) {
        $rating = 'Unverified';
        
        // Try to extract rating
        if (preg_match('/(true|false|mostly true|mostly false|misleading|unverified)/i', $content, $match)) {
            $rating = ucwords(strtolower($match[1]));
        }
        
        // Extract explanation text
        $explanation = strip_tags($content);
        $explanation = preg_replace('/\{[^}]*\}/s', '', $explanation);
        $explanation = preg_replace('/\[[^\]]*\]/s', '', $explanation);
        $explanation = trim($explanation);
        
        if (empty($explanation) || strlen($explanation) < 50) {
            $explanation = 'Unable to provide detailed analysis due to response format issues.';
        }
        
        return array(
            'rating' => $rating,
            'explanation' => $explanation,
            'sources' => array(),
            'confidence' => 0.4,
            'evidence_for' => array(),
            'evidence_against' => array(),
            'red_flags' => array()
        );
    }
    
    /**
     * Check Google Fact Check API
     */
    private static function check_google_factcheck($claim, $api_key) {
        $response = wp_remote_get(
            add_query_arg(
                array('query' => urlencode($claim), 'key' => $api_key),
                'https://factchecktools.googleapis.com/v1alpha1/claims:search'
            ),
            array('timeout' => 15)
        );
        
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
     * Detect propaganda techniques
     */
    private static function detect_propaganda($content) {
        $techniques = array();
        $content_lower = strtolower($content);
        
        $emotional_words = array('shocking', 'outrageous', 'terrifying', 'urgent', 'breaking', 'horrifying');
        $emotional_count = 0;
        foreach ($emotional_words as $word) {
            if (stripos($content_lower, $word) !== false) {
                $emotional_count++;
            }
        }
        if ($emotional_count >= 3) {
            $techniques[] = 'Heavy emotional manipulation detected';
        }
        
        if (preg_match('/\b(stupid|idiot|corrupt|evil|liar)\b/i', $content)) {
            $techniques[] = 'Ad hominem attacks detected';
        }
        
        if (preg_match('/\b(deep state|wake up|sheeple|cover[- ]?up)\b/i', $content)) {
            $techniques[] = 'Conspiracy rhetoric detected';
        }
        
        return $techniques;
    }
    
    /**
     * Calculate overall credibility score
     */
    private static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 50;
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($factcheck_results as $result) {
            $rating = strtolower($result['rating'] ?? 'unverified');
            $confidence = floatval($result['confidence'] ?? 0.5);
            
            $score = 0.5; // Neutral for unverified
            
            if (strpos($rating, 'true') !== false && strpos($rating, 'false') === false) {
                $score = 1.0;
            } elseif (strpos($rating, 'mostly true') !== false) {
                $score = 0.75;
            } elseif (strpos($rating, 'mixture') !== false || strpos($rating, 'misleading') !== false) {
                $score = 0.5;
            } elseif (strpos($rating, 'mostly false') !== false) {
                $score = 0.25;
            } elseif (strpos($rating, 'false') !== false) {
                $score = 0.0;
            }
            
            $weight = max($confidence, 0.3);
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
    private static function get_credibility_rating($score) {
        if ($score >= 85) return 'Highly Credible';
        if ($score >= 70) return 'Mostly Credible';
        if ($score >= 50) return 'Mixed Credibility';
        if ($score >= 30) return 'Low Credibility';
        return 'Not Credible';
    }
}