<?php
/**
 * OPTIMIZED Claim Extraction and Analysis Engine
 * * IMPROVEMENTS:
 * - Perplexity uses built-in search (no redundant Firecrawl calls)
 * - Added Tavily integration as alternative to Firecrawl
 * - Improved OpenRouter web search strategy
 * - Fixed scoring logic (unverified = neutral, not penalty)
 * - Better error handling and fallbacks
 * - More accurate source attribution
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Analyzer {
    
    /**
     * Safe JSON encode that never returns boolean
     */
    private static function safe_json_encode($data) {
        $json = json_encode($data);
        if ($json === false) {
            error_log('AI Verify: json_encode failed - ' . json_last_error_msg());
            // Return empty JSON object as fallback
            return '{}';
        }
        return $json;
    }
    
    /**
     * Extract claims using AI (ClaimBuster removed - not working)
     */
    public static function extract_claims($content) {
        error_log('AI Verify: Starting AI-based claim extraction...');
        
        // Use AI-based extraction as primary method
        $claims = self::extract_with_ai($content);
        
        // Fallback to basic extraction if AI fails
        if (empty($claims)) {
            error_log('AI Verify: AI extraction failed, using basic extraction');
            $claims = self::extract_claims_basic($content);
        }
        
        error_log('AI Verify: Extracted ' . count($claims) . ' claims');
        return array_slice($claims, 0, 15); // Max 15 claims
    }

    /**
     * OPTIMIZED: Fact-check claims using best available method
     * * Priority:
     * 1. Google Fact Check API (existing fact-checks)
     * 2. Perplexity AI with built-in search (recommended)
     * 3. OpenRouter + Tavily/Firecrawl search (fallback)
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
            
            // STEP 1: Check Google Fact Check API (fastest, checks existing fact-checks)
            $google_key = get_option('ai_verify_google_factcheck_key');
            if (!empty($google_key)) {
                $google_result = self::check_google_factcheck($claim['text'], $google_key);
                if (!empty($google_result)) {
                    $result['rating'] = $google_result['rating'];
                    $result['explanation'] = $google_result['explanation'];
                    $result['sources'][] = $google_result['source'];
                    $result['confidence'] = 0.95;
                    $result['method'] = 'Google Fact Check';
                    $results[] = $result;
                    continue; // Skip to next claim
                }
            }
            
            // STEP 2: Use Perplexity or OpenRouter with web search
            if ($api_provider === 'perplexity') {
                $ai_result = self::check_with_perplexity_optimized($claim['text'], $context);
            } else {
                $ai_result = self::check_with_openrouter_optimized($claim['text'], $context);
            }
            
            if (!is_wp_error($ai_result) && !empty($ai_result)) {
                $result['rating'] = $ai_result['rating'] ?? 'Unverified';
                $result['explanation'] = $ai_result['explanation'] ?? 'No explanation available';
                $result['sources'] = $ai_result['sources'] ?? array();
                $result['confidence'] = $ai_result['confidence'] ?? 0.6;
                $result['evidence_for'] = $ai_result['evidence_for'] ?? array();
                $result['evidence_against'] = $ai_result['evidence_against'] ?? array();
                $result['red_flags'] = $ai_result['red_flags'] ?? array();
                $result['method'] = $api_provider === 'perplexity' 
                    ? 'Perplexity AI Analysis' 
                    : 'AI Analysis';
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
     * OPTIMIZED: Check claim with Perplexity AI (NO redundant search)
     * * Perplexity's sonar models have BUILT-IN web search, so we don't need
     * to call Firecrawl separately. Just call Perplexity directly.
     */
    private static function check_with_perplexity_optimized($claim, $context) {
        $api_key = get_option('ai_verify_perplexity_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Perplexity API key not configured');
            return self::check_with_openrouter_optimized($claim, $context);
        }
        
        error_log('AI Verify: Checking with Perplexity (built-in search): ' . substr($claim, 0, 100));
        
        // Direct prompt - Perplexity will search the web automatically
        $prompt = "You are a professional fact-checker. Verify this claim by searching the web thoroughly.

Claim: {$claim}
Context: {$context}

Search the web for credible sources and provide a comprehensive fact-check in JSON format:
{
    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
    \"explanation\": \"detailed explanation citing specific sources\",
    \"sources\": [{\"name\": \"source name\", \"url\": \"https://...\"}],
    \"evidence_for\": [\"evidence supporting the claim\"],
    \"evidence_against\": [\"evidence contradicting the claim\"],
    \"red_flags\": [\"any propaganda techniques or logical fallacies\"],
    \"confidence\": 0.85
}

IMPORTANT:
- Rate as \"True\" only if strong evidence supports it
- Rate as \"False\" if evidence contradicts it  
- Rate as \"Unverified\" only if truly no reliable sources found
- Always cite specific URLs in sources array
- Confidence should reflect quality of sources (0.9+ for government/academic, 0.7+ for news)";
        
        $request_body = array(
            'model' => 'sonar-pro', // Built-in web search
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a fact-checker. Always search the web and return valid JSON with evidence-based ratings.'),
                array('role' => 'user', 'content' => $prompt)
            ),
            'temperature' => 0.2,
            'max_tokens' => 2000
        );
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => self::safe_json_encode($request_body),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: Perplexity error: ' . $response->get_error_message());
            return self::check_with_openrouter_optimized($claim, $context);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            // More detailed error logging
            error_log('AI Verify: Perplexity failed - Status ' . $status_code . ' | Response: ' . $body);
            return self::check_with_openrouter_optimized($claim, $context);
        }
        
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        $result = self::parse_fact_check_response($content);
        
        // Manually find sources from the explanation text if possible, as citations metadata is not available this way
        if (empty($result['sources'])) {
            $found_sources = [];
            preg_match_all('/https?:\/\/[^\s)]+/', $result['explanation'], $matches);
            if (!empty($matches[0])) {
                foreach (array_slice(array_unique($matches[0]), 0, 5) as $url) {
                    $found_sources[] = [
                        'name' => parse_url($url, PHP_URL_HOST) ?: 'Web Source',
                        'url' => $url
                    ];
                }
            }
            $result['sources'] = $found_sources;
        }

        return $result;
    }
    
    /**
     * OPTIMIZED: Check claim with OpenRouter + Smart Web Search
     * * Strategy: Try Tavily first (AI-optimized), fallback to Firecrawl
     */
    private static function check_with_openrouter_optimized($claim, $context) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API key not configured');
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        error_log('AI Verify: Using OpenRouter + Web Search: ' . substr($claim, 0, 100));
        
        // STEP 1: Try Tavily first (recommended for AI fact-checking)
        $web_results = self::search_web_tavily($claim);
        
        // Fallback to Firecrawl if Tavily not available/failed
        if (empty($web_results)) {
            error_log('AI Verify: Tavily unavailable, trying Firecrawl');
            $web_results = self::search_web_firecrawl($claim);
        }
        
        if (empty($web_results)) {
            error_log('AI Verify: No web search results found');
            return array(
                'rating' => 'Unverified',
                'explanation' => 'Could not find web sources to verify this claim.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        // STEP 2: Build rich context from search results
        $web_context = "\n\n=== WEB SEARCH RESULTS ===\n";
        foreach ($web_results as $idx => $result) {
            $content_preview = substr($result['content'], 0, 600);
            $web_context .= "\n[Source " . ($idx + 1) . "]\n";
            $web_context .= "Title: {$result['title']}\n";
            $web_context .= "URL: {$result['url']}\n";
            $web_context .= "Content: {$content_preview}...\n";
            $web_context .= "---\n";
        }
        
        // STEP 3: Ask AI to analyze with web context
        $prompt = "You are a professional fact-checker. Analyze this claim using the web search results provided.

Claim: {$claim}
Original Context: {$context}
{$web_context}

Based on the web search results above, provide a detailed fact-check in JSON format:
{
    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
    \"explanation\": \"detailed explanation citing specific sources by number\",
    \"sources\": [{\"name\": \"source name\", \"url\": \"https://...\"}],
    \"evidence_for\": [\"evidence supporting the claim from sources\"],
    \"evidence_against\": [\"evidence contradicting the claim from sources\"],
    \"red_flags\": [\"propaganda techniques or logical fallacies detected\"],
    \"confidence\": 0.85
}

CRITICAL INSTRUCTIONS:
- Base rating ONLY on the web search results provided
- Cite sources by [Source X] in your explanation
- If search results strongly support claim → \"True\" or \"Mostly True\"
- If search results contradict claim → \"False\" or \"Mostly False\"
- If results are mixed → \"Misleading\" or \"Mixture\"
- ONLY use \"Unverified\" if search results don't address the claim
- Confidence based on source quality: 0.9+ (govt/academic), 0.7-0.9 (major news), 0.5-0.7 (other)
- Always include the most relevant source URLs from above";
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => self::safe_json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a fact-checker. Always return valid JSON with evidence-based ratings using provided search results.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 2000
            )),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: OpenRouter error: ' . $response->get_error_message());
            return array(
                'rating' => 'Unverified',
                'explanation' => 'API connection error.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_text = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            error_log('AI Verify: OpenRouter API error - Status ' . $status_code . ' | Body: ' . $body_text);
            return array(
                'rating' => 'Unverified',
                'explanation' => 'API error. Check API key and credits.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        $body = json_decode($body_text, true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        $result = self::parse_fact_check_response($content);
        
        // Ensure we have sources from search results
        if (empty($result['sources'])) {
            $result['sources'] = array_map(function($r) {
                return array('name' => $r['title'], 'url' => $r['url']);
            }, array_slice($web_results, 0, 3));
        }
        
        return $result;
    }

    /**
     * Search web using Tavily API (RECOMMENDED for AI fact-checking)
     */
    public static function search_web_tavily($query) {
        $api_key = get_option('ai_verify_tavily_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Tavily API key not configured');
            return array();
        }
        
        error_log('AI Verify: Searching with Tavily: ' . $query);
        
        $response = wp_remote_post('https://api.tavily.com/search', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => self::safe_json_encode(array(
                'api_key' => $api_key,
                'query' => $query,
                'search_depth' => 'advanced', // Deep search for fact-checking
                'include_answer' => true,
                'include_raw_content' => false,
                'max_results' => 5,
                'include_domains' => array(), // Can whitelist trusted domains
                'exclude_domains' => array() // Can blacklist unreliable sources
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: Tavily error: ' . $response->get_error_message());
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['results'])) {
            error_log('AI Verify: No Tavily results');
            return array();
        }
        
        $results = array();
        foreach ($body['results'] as $result) {
            $results[] = array(
                'title' => $result['title'] ?? '',
                'url' => $result['url'] ?? '',
                'content' => $result['content'] ?? ''
            );
        }
        
        error_log('AI Verify: Found ' . count($results) . ' Tavily results');
        return $results;
    }

    /**
     * Search web using Firecrawl Search API (fallback)
     */
    public static function search_web_firecrawl($query) {
        $api_key = get_option('ai_verify_firecrawl_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Firecrawl API key not configured');
            return array();
        }
        
        error_log('AI Verify: Searching with Firecrawl: ' . $query);
        
        $response = wp_remote_post('https://api.firecrawl.dev/v1/search', array(
            'method'  => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => self::safe_json_encode(array(
                'query' => $query,
                'limit' => 5,
                'lang' => 'en',
                'format' => 'markdown'
            )),
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: Firecrawl search error: ' . $response->get_error_message());
            return array();
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            error_log('AI Verify: Firecrawl search failed - Status ' . $status_code);
            return array();
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            return array();
        }
        
        $results = array();
        foreach ($data['data'] as $item) {
            $results[] = array(
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'content' => $item['markdown'] ?? ($item['content'] ?? '')
            );
        }
        
        error_log('AI Verify: Found ' . count($results) . ' Firecrawl results');
        return $results;
    }

    /**
     * Parse fact-check response from AI
     */
    private static function parse_fact_check_response($content) {
        // Try to extract JSON
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);
            
            if ($result && isset($result['rating'])) {
                error_log('AI Verify: Successfully parsed JSON result');
                
                return array(
                    'rating' => $result['rating'] ?? 'Unverified',
                    'explanation' => $result['explanation'] ?? 'No explanation provided',
                    'sources' => $result['sources'] ?? array(),
                    'evidence_for' => $result['evidence_for'] ?? array(),
                    'evidence_against' => $result['evidence_against'] ?? array(),
                    'red_flags' => $result['red_flags'] ?? array(),
                    'confidence' => isset($result['confidence']) ? floatval($result['confidence']) : 0.6
                );
            }
        }
        
        error_log('AI Verify: Failed to parse JSON, using text fallback');
        
        // Fallback: extract rating from text
        $rating = 'Unverified';
        if (preg_match('/(true|false|mostly true|mostly false|misleading|unverified|mixture)/i', $content, $rating_match)) {
            $rating = ucwords(strtolower($rating_match[1]));
        }
        
        return array(
            'rating' => $rating,
            'explanation' => strip_tags($content),
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
    public static function check_google_factcheck($claim, $api_key) {
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
        $emotional_words = array('shocking', 'outrageous', 'terrifying', 'urgent', 'breaking', 'horrifying', 'devastating', 'explosive');
        $emotional_count = 0;
        foreach ($emotional_words as $word) {
            if (stripos($content_lower, $word) !== false) {
                $emotional_count++;
            }
        }
        if ($emotional_count >= 3) {
            $techniques[] = 'Heavy emotional manipulation detected (' . $emotional_count . ' trigger words)';
        } elseif ($emotional_count >= 1) {
            $techniques[] = 'Emotional language detected';
        }
        
        // Cherry-picking
        if (preg_match('/\b(only|just|merely|simply)\b/i', $content) && 
            !preg_match('/\b(however|but|although|while|yet|nevertheless)\b/i', $content)) {
            $techniques[] = 'Possible cherry-picking (one-sided presentation)';
        }
        
        // False dichotomy
        if (preg_match('/\b(either.*or|you\'?re (either|with)|only two (options|choices))\b/i', $content)) {
            $techniques[] = 'False dichotomy (oversimplified choice)';
        }
        
        // Ad hominem
        if (preg_match('/\b(stupid|idiot|moron|corrupt|evil|liar|crooked)\b/i', $content)) {
            $techniques[] = 'Ad hominem attacks detected';
        }
        
        // Conspiracy rhetoric
        if (preg_match('/\b(they don\'?t want you|hidden truth|wake up|sheeple|cover[- ]?up|deep state)\b/i', $content)) {
            $techniques[] = 'Conspiracy rhetoric detected';
        }
        
        // Absolutism
        $absolute_count = preg_match_all('/\b(always|never|everyone|nobody|all|none|every|no one)\b/i', $content);
        if ($absolute_count >= 3) {
            $techniques[] = 'Excessive absolutism (black-and-white thinking)';
        }
        
        // Appeal to fear
        if (preg_match('/\b(threat|danger|risk|warning|alert|crisis)\b/i', $content)) {
            $fear_count = preg_match_all('/\b(threat|danger|risk|warning|alert|crisis)\b/i', $content);
            if ($fear_count >= 3) {
                $techniques[] = 'Appeal to fear tactics';
            }
        }
        
        return $techniques;
    }
    
    /**
     * FIXED: Calculate overall credibility score
     * * CHANGES:
     * - "Unverified" now = 0.5 (neutral), not penalized
     * - Better weighting based on confidence
     * - More balanced scoring
     */
    public static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 50; // Neutral if no data
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($factcheck_results as $result) {
            $rating = strtolower($result['rating'] ?? 'unverified');
            $confidence = floatval($result['confidence'] ?? 0.5);
            
            // Convert rating to score (0.0 to 1.0)
            $score = 0.5; // default neutral
            
            if (strpos($rating, 'true') !== false && strpos($rating, 'false') === false) {
                $score = 1.0; // True
            } elseif (strpos($rating, 'mostly true') !== false) {
                $score = 0.75; // Mostly True
            } elseif (strpos($rating, 'mixture') !== false || strpos($rating, 'misleading') !== false || strpos($rating, 'mixed') !== false) {
                $score = 0.5; // Misleading/Mixed
            } elseif (strpos($rating, 'mostly false') !== false) {
                $score = 0.25; // Mostly False
            } elseif (strpos($rating, 'false') !== false) {
                $score = 0.0; // False
            } elseif (strpos($rating, 'unverified') !== false || strpos($rating, 'unknown') !== false) {
                $score = 0.5; // CHANGED: Neutral, not penalty
            }
            
            // Weight by confidence (higher confidence = more weight)
            $weight = max($confidence, 0.3); // Minimum weight
            
            $total_score += $score * $weight;
            $total_weight += $weight;
        }
        
        if ($total_weight == 0) {
            return 50; // Neutral
        }
        
        $calculated_score = ($total_score / $total_weight) * 100;
        
        // Round to 2 decimals
        return round($calculated_score, 2);
    }
    
    /**
     * Get credibility rating from score
     */
    public static function get_credibility_rating($score) {
        if ($score >= 85) return 'Highly Credible';
        if ($score >= 70) return 'Mostly Credible';
        if ($score >= 50) return 'Mixed Credibility';
        if ($score >= 30) return 'Low Credibility';
        return 'Not Credible';
    }
    
    // ============ HELPER FUNCTIONS ============
    
    
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
        
        $prompt = "Extract 10-15 verifiable factual claims from this article. Focus on specific statements that can be fact-checked. Return ONLY a JSON array:
[{\"text\": \"claim\", \"score\": 0.9, \"type\": \"statistical\"}]

Article: {$content}";
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => self::safe_json_encode(array(
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
     * Basic claim extraction (last resort)
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
            'data shows', 'percent', 'million', 'billion', 'increase', 'decrease', 'reported');
        
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