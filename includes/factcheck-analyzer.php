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
     * Search web using Firecrawl Search API
     */
    private static function search_web_firecrawl($query) {
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
            'body' => json_encode(array(
                'query' => $query,
                'limit' => 5, // Get top 5 results
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
        
        error_log('AI Verify: Firecrawl search response code: ' . $status_code);
        
        if ($status_code !== 200) {
            error_log('AI Verify: Firecrawl search failed - Status ' . $status_code . ': ' . $body);
            return array();
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            error_log('AI Verify: No Firecrawl search results');
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
        
        error_log('AI Verify: Found ' . count($results) . ' Firecrawl search results');
        
        return $results;
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
     * Check claim with Perplexity AI + Firecrawl Search (ULTIMATE COMBO)
     */
    private static function check_with_perplexity($claim, $context) {
        $api_key = get_option('ai_verify_perplexity_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Perplexity API key not configured');
            // Fallback to Firecrawl search + OpenRouter
            return self::check_with_openrouter($claim, $context);
        }
        
        error_log('AI Verify: Checking with Perplexity: ' . substr($claim, 0, 100));
        
        // Optional: Pre-search with Firecrawl for additional context
        $firecrawl_results = self::search_web_firecrawl($claim);
        $firecrawl_context = '';
        
        if (!empty($firecrawl_results)) {
            $firecrawl_context = "\n\nAdditional web context:\n";
            foreach ($firecrawl_results as $result) {
                $firecrawl_context .= "- {$result['title']}: " . substr($result['content'], 0, 200) . "...\n";
            }
        }
        
        $prompt = "Fact-check this claim by searching the web thoroughly.

    Claim: {$claim}
    Context: {$context}
    {$firecrawl_context}

    Provide a comprehensive fact-check in JSON:
    {
        \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unproven\",
        \"explanation\": \"detailed explanation with evidence\",
        \"sources\": [{\"name\": \"source\", \"url\": \"https://...\"}],
        \"evidence_for\": [\"supporting evidence\"],
        \"evidence_against\": [\"contradicting evidence\"],
        \"red_flags\": [\"propaganda techniques\"],
        \"confidence\": 0.8
    }";
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'llama-3.1-sonar-large-128k-online',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a fact-checker. Always return valid JSON.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 2000
            )),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: Perplexity error, falling back to OpenRouter + Firecrawl');
            return self::check_with_openrouter($claim, $context);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            error_log('AI Verify: Perplexity failed - Status ' . $status_code);
            return self::check_with_openrouter($claim, $context);
        }
        
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        return self::parse_fact_check_response($content);
    }
    
    /**
     * Check claim with OpenRouter AI + Firecrawl Search
     */
    private static function check_with_openrouter($claim, $context) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API key not configured');
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        error_log('AI Verify: Checking with OpenRouter + Firecrawl Search: ' . substr($claim, 0, 100));
        
        // Step 1: Search the web using Firecrawl
        $web_results = self::search_web_firecrawl($claim);
        
        if (empty($web_results)) {
            error_log('AI Verify: No Firecrawl search results found');
        }
        
        // Step 2: Build context from web results
        $web_context = '';
        if (!empty($web_results)) {
            $web_context = "\n\nWeb Search Results:\n";
            foreach ($web_results as $idx => $result) {
                $content_preview = substr($result['content'], 0, 500);
                $web_context .= "\n[Source " . ($idx + 1) . "]\n";
                $web_context .= "Title: {$result['title']}\n";
                $web_context .= "URL: {$result['url']}\n";
                $web_context .= "Content: {$content_preview}...\n";
            }
        }
        
        // Step 3: Ask AI to analyze with web results
        $prompt = "You are a professional fact-checker. Analyze this claim using the web search results provided.

    Claim: {$claim}
    Original Context: {$context}
    {$web_context}

    Based on the web search results above, provide a detailed fact-check in JSON format:
    {
        \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unproven\",
        \"explanation\": \"detailed explanation citing the sources\",
        \"sources\": [{\"name\": \"source name\", \"url\": \"https://...\"}],
        \"evidence_for\": [\"evidence supporting the claim\"],
        \"evidence_against\": [\"evidence contradicting the claim\"],
        \"red_flags\": [\"any propaganda techniques or logical fallacies detected\"],
        \"confidence\": 0.85
    }

    IMPORTANT: 
    - Base your rating on the web search results provided
    - Cite specific sources in your explanation
    - If web results contradict the claim, rate it False
    - Include propaganda techniques if detected";
        
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
                    array('role' => 'system', 'content' => 'You are a fact-checker. Always return valid JSON with evidence-based ratings.'),
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
                'explanation' => 'API connection error: ' . $response->get_error_message(),
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_text = wp_remote_retrieve_body($response);
        
        error_log('AI Verify: OpenRouter response code: ' . $status_code);
        
        if ($status_code !== 200) {
            error_log('AI Verify: OpenRouter API error - Status ' . $status_code);
            return array(
                'rating' => 'Unverified',
                'explanation' => 'API error (Status ' . $status_code . '). Check your API key and credits.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        $body = json_decode($body_text, true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            return array(
                'rating' => 'Unverified',
                'explanation' => 'Empty response from AI',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        error_log('AI Verify: OpenRouter response preview: ' . substr($content, 0, 200));
        
        $result = self::parse_fact_check_response($content);
        
        // Add Firecrawl sources if not included by AI
        if (empty($result['sources']) && !empty($web_results)) {
            $result['sources'] = array_map(function($r) {
                return array('name' => $r['title'], 'url' => $r['url']);
            }, array_slice($web_results, 0, 3));
        }
        
        return $result;
    }

    /**
     * Search web using Tavily API
     */
    private static function search_web_tavily($query) {
        $api_key = get_option('ai_verify_tavily_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Tavily API key not configured');
            return array();
        }
        
        error_log('AI Verify: Searching web with Tavily: ' . $query);
        
        $response = wp_remote_post('https://api.tavily.com/search', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'api_key' => $api_key,
                'query' => $query,
                'search_depth' => 'advanced',
                'include_answer' => true,
                'max_results' => 5
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
        
        error_log('AI Verify: Found ' . count($results) . ' web results');
        
        return $results;
    }

    /**
     * Parse fact-check response from AI (IMPROVED)
     */
    private static function parse_fact_check_response($content) {
        // Try to extract JSON
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);
            
            if ($result && isset($result['rating'])) {
                error_log('AI Verify: Successfully parsed JSON result');
                
                // Ensure all fields exist
                return array(
                    'rating' => $result['rating'] ?? 'Unknown',
                    'explanation' => $result['explanation'] ?? 'No explanation provided',
                    'sources' => $result['sources'] ?? array(),
                    'evidence_for' => $result['evidence_for'] ?? array(),
                    'evidence_against' => $result['evidence_against'] ?? array(),
                    'red_flags' => $result['red_flags'] ?? array(),
                    'confidence' => isset($result['confidence']) ? floatval($result['confidence']) : 0.5
                );
            }
        }
        
        error_log('AI Verify: Failed to parse JSON, using text fallback');
        
        // Fallback: Try to extract rating from text
        $rating = 'Unknown';
        if (preg_match('/(true|false|mostly true|mostly false|misleading|unverified)/i', $content, $rating_match)) {
            $rating = ucwords($rating_match[1]);
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
     * Calculate overall credibility score (IMPROVED - Penalties for Unproven)
     */
    public static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 50;
        }
        
        $total_score = 0;
        $total_weight = 0;
        $unproven_count = 0;
        $total_claims = count($factcheck_results);
        
        foreach ($factcheck_results as $result) {
            $rating = strtolower($result['rating'] ?? 'unknown');
            $confidence = floatval($result['confidence'] ?? 0.5);
            
            // Convert rating to score
            $score = 0.5; // default
            
            if (strpos($rating, 'true') !== false && strpos($rating, 'false') === false) {
                $score = 1.0; // True
            } elseif (strpos($rating, 'mostly true') !== false) {
                $score = 0.75; // Mostly True
            } elseif (strpos($rating, 'mixture') !== false || strpos($rating, 'misleading') !== false) {
                $score = 0.3; // Misleading
            } elseif (strpos($rating, 'mostly false') !== false) {
                $score = 0.15; // Mostly False
            } elseif (strpos($rating, 'false') !== false) {
                $score = 0; // False
            } elseif (strpos($rating, 'unproven') !== false || strpos($rating, 'unverified') !== false || strpos($rating, 'unknown') !== false) {
                $unproven_count++;
                // Unproven with HIGH confidence means we're CONFIDENT it's unproven
                // This should LOWER the score significantly
                if ($confidence > 0.8) {
                    $score = 0.2; // High confidence it's unproven = very low score
                } else {
                    $score = 0.4; // Low confidence unproven = slightly better
                }
            }
            
            $weight = max($confidence, 0.3); // Minimum weight of 0.3
            $total_score += $score * $weight;
            $total_weight += $weight;
        }
        
        if ($total_weight == 0) {
            return 30; // Default low score if no data
        }
        
        $calculated_score = ($total_score / $total_weight) * 100;
        
        // Heavy penalty if most claims are unproven
        $unproven_ratio = $unproven_count / $total_claims;
        if ($unproven_ratio > 0.5) {
            // More than 50% unproven = significant penalty
            $penalty = ($unproven_ratio - 0.5) * 40; // Up to 20% penalty
            $calculated_score = max(20, $calculated_score - $penalty);
        }
        
        return round($calculated_score, 2);
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