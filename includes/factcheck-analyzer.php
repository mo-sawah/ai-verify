<?php
/**
 * FIXED: Fact-Check Analyzer
 * ALL ISSUES RESOLVED:
 * - UTF-8 encoding fixed
 * - Explanations properly parsed (no more JSON display)
 * - Method label shows "AI Analysis" only
 * - Enhanced prompts for detailed analysis
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Analyzer {
    
    /**
     * FIXED: Safe JSON encode that cleans UTF-8 BEFORE encoding
     */
    private static function safe_json_encode($data) {
        $data = self::clean_utf8_recursive($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            error_log('AI Verify: json_encode failed - ' . json_last_error_msg());
            $json = json_encode($data);
            if ($json === false) {
                return '{}';
            }
        }
        
        return $json;
    }
    
    /**
     * Clean UTF-8 recursively
     */
    private static function clean_utf8_recursive($data) {
        if (is_string($data)) {
            // 1. Force conversion to UTF-8, ignoring invalid characters.
            // This is much stronger than mb_convert_encoding.
            $data = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            
            // 2. Fallback just in case iconv isn't available
            if ($data === false) {
                $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }

            // 3. Aggressively strip all non-printable ASCII control characters
            // (Note: the 'u' flag is removed to operate on raw bytes)
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            
            return $data;
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::clean_utf8_recursive($value);
            }
        }
        
        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = self::clean_utf8_recursive($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Extract claims with better AI prompt
     */
    public static function extract_claims($content) {
        error_log('AI Verify: Starting AI-based claim extraction...');
        
        $claims = self::extract_with_ai_enhanced($content);
        
        if (empty($claims)) {
            error_log('AI Verify: AI extraction failed, using basic extraction');
            $claims = self::extract_claims_basic($content);
        }
        
        error_log('AI Verify: Extracted ' . count($claims) . ' claims');
        return array_slice($claims, 0, 10);
    }

    /**
     * Fact-check claims
     */
    public static function factcheck_claims($claims, $context = '', $url = '') {
        $results = array();
        $api_provider = get_option('ai_verify_factcheck_provider', 'openrouter');
        
        error_log('AI Verify: Fact-checking ' . count($claims) . ' claims using ' . $api_provider);
        
        foreach ($claims as $index => $claim) {
            error_log('AI Verify: Checking claim ' . ($index + 1) . '/' . count($claims));
            
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
                'method' => 'AI Analysis' // FIXED: Simplified label
            );
            
            // Check Google Fact Check API first
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
                    continue;
                }
            }
            
            // Use Perplexity or OpenRouter with web search
            if ($api_provider === 'perplexity') {
                $ai_result = self::check_with_perplexity_optimized($claim['text'], $context);
            } else {
                $ai_result = self::check_with_openrouter_enhanced($claim['text'], $context);
            }
            
            if (!is_wp_error($ai_result) && !empty($ai_result)) {
                $result['rating'] = $ai_result['rating'] ?? 'Unverified';
                $result['explanation'] = $ai_result['explanation'] ?? 'No explanation available';
                $result['sources'] = $ai_result['sources'] ?? array();
                $result['confidence'] = $ai_result['confidence'] ?? 0.6;
                $result['evidence_for'] = $ai_result['evidence_for'] ?? array();
                $result['evidence_against'] = $ai_result['evidence_against'] ?? array();
                $result['red_flags'] = $ai_result['red_flags'] ?? array();
                // Keep method as 'AI Analysis'
            } else {
                $result['rating'] = 'Unverified';
                $result['explanation'] = 'Unable to verify this claim with available sources.';
                $result['confidence'] = 0.3;
            }
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Check with Perplexity
     */
    private static function check_with_perplexity_optimized($claim, $context) {
        $api_key = get_option('ai_verify_perplexity_key');
        
        if (empty($api_key)) {
            return self::check_with_openrouter_enhanced($claim, $context);
        }
        
        error_log('AI Verify: Checking with Perplexity: ' . substr($claim, 0, 100));
        
        $prompt = "You are a professional fact-checker. Verify this claim by searching the web thoroughly.

Claim: {$claim}
Context: {$context}

Provide a comprehensive fact-check in JSON format:
{
    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
    \"explanation\": \"3-5 paragraph detailed explanation citing all sources you found\",
    \"sources\": [{\"name\": \"source name\", \"url\": \"https://...\"}],
    \"evidence_for\": [\"evidence supporting\"],
    \"evidence_against\": [\"evidence contradicting\"],
    \"red_flags\": [\"propaganda techniques\"],
    \"confidence\": 0.85
}

Requirements:
- 3-5 paragraphs minimum
- Cite ALL sources (minimum 3-5)
- Explain what article claims vs what sources say";
        
        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => self::safe_json_encode(array(
                'model' => 'sonar-pro',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a fact-checker. Return valid JSON with comprehensive analysis.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 3000
            )),
            'timeout' => 90
        ));
        
        if (is_wp_error($response)) {
            return self::check_with_openrouter_enhanced($claim, $context);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return self::check_with_openrouter_enhanced($claim, $context);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        return self::parse_fact_check_response($content);
    }
    
    /**
     * ENHANCED: Check with OpenRouter + Detailed Analysis
     */
    private static function check_with_openrouter_enhanced($claim, $context) {
        $api_key = get_option('ai_verify_openrouter_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API key not configured');
        }
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        
        error_log('AI Verify: Using OpenRouter + Web Search: ' . substr($claim, 0, 100));
        
        // Get web search results from Tavily
        $web_results = self::search_web_tavily($claim);
        
        if (empty($web_results)) {
            $web_results = self::search_web_firecrawl($claim);
        }
        
        if (empty($web_results)) {
            return array(
                'rating' => 'Unverified',
                'explanation' => 'Could not find web sources to verify this claim.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        // Build comprehensive context
        $web_context = "\n\n=== WEB SEARCH RESULTS ===\n";
        foreach ($web_results as $idx => $result) {
            $web_context .= "\n[Source " . ($idx + 1) . "]\n";
            $web_context .= "Title: {$result['title']}\n";
            $web_context .= "URL: {$result['url']}\n";
            $web_context .= "Content: {$result['content']}\n---\n\n";
        }
        
        // ENHANCED PROMPT
        $prompt = "You are a professional fact-checker. Analyze this claim using ALL web search results.

Claim: {$claim}
Context: {$context}

{$web_context}

Provide comprehensive fact-check in valid JSON:
{
    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
    \"explanation\": \"DETAILED 3-5 paragraphs\",
    \"sources\": [{\"name\": \"source name\", \"url\": \"https://...\"}],
    \"evidence_for\": [\"evidence supporting\"],
    \"evidence_against\": [\"evidence contradicting\"],
    \"red_flags\": [\"propaganda techniques\"],
    \"confidence\": 0.85
}

EXPLANATION STRUCTURE (3-5 paragraphs):

Paragraph 1: WHAT THE CLAIM SAYS
- State what the article is claiming
- Identify specific facts being asserted

Paragraphs 2-4: WHAT SOURCES SAY
- Analyze each source [Source 1], [Source 2], etc.
- Explain what each says about the claim
- Note agreements/contradictions
- Evaluate source credibility

Final Paragraph: CONCLUSION
- Synthesize all evidence
- Explain why this rating
- Note nuances/caveats

RATING GUIDELINES:
- \"True\" - Strong evidence from multiple sources confirms
- \"False\" - Strong evidence contradicts
- \"Misleading\" - Technically true but misleading context
- \"Unverified\" - Insufficient evidence

CRITICAL: Include ALL source URLs in sources array. Cite sources by number [Source X].";
        
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
                    array('role' => 'system', 'content' => 'You are a fact-checker. Provide comprehensive multi-paragraph analysis. Always return valid JSON.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.2,
                'max_tokens' => 3000
            )),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
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
            error_log('AI Verify: OpenRouter error - Status ' . $status_code);
            return array(
                'rating' => 'Unverified',
                'explanation' => 'API error.',
                'sources' => array(),
                'confidence' => 0.3
            );
        }
        
        $body = json_decode($body_text, true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        
        $result = self::parse_fact_check_response($content);
        
        // Ensure sources from search
        if (empty($result['sources']) || count($result['sources']) < count($web_results)) {
            $result['sources'] = array_map(function($r) {
                return array(
                    'name' => !empty($r['title']) ? $r['title'] : parse_url($r['url'], PHP_URL_HOST),
                    'url' => $r['url']
                );
            }, $web_results);
        }
        
        return $result;
    }

    /**
     * FIXED: Parse response - ensures explanation is always plain text, not JSON
     */
    private static function parse_fact_check_response($content) {
        // Extract JSON from response
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $result = json_decode($matches[0], true);

            // --- START FIX ---
            // Clean the JSON string for any invalid UTF-8 characters BEFORE decoding
            $json_string = self::clean_utf8_recursive($matches[0]);
            $result = json_decode($json_string, true);
                
            // Log an error if decoding still fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('AI Verify: JSON decode failed - ' . json_last_error_msg());
                $result = null; // Force fallback
            }
            // --- END FIX ---
            
            if ($result && isset($result['rating'])) {
                // CRITICAL FIX: Ensure explanation is plain text string
                $explanation = $result['explanation'] ?? 'No explanation provided';
                
                // If explanation is somehow still an array/object, convert to string
                if (is_array($explanation) || is_object($explanation)) {
                    $explanation = 'No explanation available';
                }
                
                error_log('AI Verify: Parsed - Explanation length: ' . strlen($explanation));
                
                return array(
                    'rating' => $result['rating'] ?? 'Unverified',
                    'explanation' => $explanation, // ALWAYS a string
                    'sources' => $result['sources'] ?? array(),
                    'evidence_for' => $result['evidence_for'] ?? array(),
                    'evidence_against' => $result['evidence_against'] ?? array(),
                    'red_flags' => $result['red_flags'] ?? array(),
                    'confidence' => isset($result['confidence']) ? floatval($result['confidence']) : 0.6
                );
            }
        }
        
        // Fallback: extract rating from text
        $rating = 'Unverified';
        if (preg_match('/(true|false|mostly true|mostly false|misleading|unverified|mixture)/i', $content, $rating_match)) {
            $rating = ucwords(strtolower($rating_match[1]));
        }
        
        return array(
            'rating' => $rating,
            'explanation' => 'A processing error occurred. The AI analyzer returned a response that could not be parsed, which may be due to invalid characters in the source text.',
            'sources' => array(),
            'evidence_for' => array(),
            'evidence_against' => array(),
            'red_flags' => array(),
            'confidence' => 0.5
        );
    }
    
    /**
     * Search with Tavily (UTF-8 cleaned)
     */
    public static function search_web_tavily($query) {
        $api_key = get_option('ai_verify_tavily_key');
        
        if (empty($api_key)) {
            return array();
        }
        
        error_log('AI Verify: Searching with Tavily: ' . $query);
        
        $response = wp_remote_post('https://api.tavily.com/search', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => self::safe_json_encode(array(
                'api_key' => $api_key,
                'query' => $query,
                'search_depth' => 'advanced',
                'include_answer' => true,
                'include_raw_content' => false,
                'max_results' => 5
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['results'])) {
            return array();
        }
        
        $results = array();
        foreach ($body['results'] as $result) {
            $results[] = array(
                'title' => self::clean_utf8_recursive($result['title'] ?? ''),
                'url' => $result['url'] ?? '',
                'content' => self::clean_utf8_recursive($result['content'] ?? '')
            );
        }
        
        error_log('AI Verify: Found ' . count($results) . ' Tavily results');
        return $results;
    }

    /**
     * Search with Firecrawl (fallback)
     */
    public static function search_web_firecrawl($query) {
        $api_key = get_option('ai_verify_firecrawl_key');
        
        if (empty($api_key)) {
            return array();
        }
        
        $response = wp_remote_post('https://api.firecrawl.dev/v1/search', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => self::safe_json_encode(array(
                'query' => $query,
                'limit' => 5,
                'lang' => 'en',
                'format' => 'markdown'
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['data'])) {
            return array();
        }
        
        $results = array();
        foreach ($data['data'] as $item) {
            $results[] = array(
                'title' => self::clean_utf8_recursive($item['title'] ?? ''),
                'url' => $item['url'] ?? '',
                'content' => self::clean_utf8_recursive($item['markdown'] ?? ($item['content'] ?? ''))
            );
        }
        
        return $results;
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
     * Detect propaganda
     */
    public static function detect_propaganda($content) {
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
    
    public static function calculate_overall_score($factcheck_results) {
        if (empty($factcheck_results)) {
            return 50;
        }
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($factcheck_results as $result) {
            $rating = strtolower($result['rating'] ?? 'unverified');
            $confidence = floatval($result['confidence'] ?? 0.5);
            
            $score = 0.5;
            
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
    
    public static function get_credibility_rating($score) {
        if ($score >= 85) return 'Highly Credible';
        if ($score >= 70) return 'Mostly Credible';
        if ($score >= 50) return 'Mixed Credibility';
        if ($score >= 30) return 'Low Credibility';
        return 'Not Credible';
    }
    
    // Helper functions
    
    private static function extract_with_ai_enhanced($content) {
        $api_key = get_option('ai_verify_openrouter_key');
        if (empty($api_key)) return array();
        
        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        $content = mb_substr($content, 0, 6000);
        
        $prompt = "Extract 8-12 verifiable factual claims from this article. Focus on statistics, names, dates, locations, events, cause-effect relationships, policy claims, scientific claims, direct quotes.

Return ONLY JSON array:
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
            'body' => self::safe_json_encode(array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'temperature' => 0.3,
                'max_tokens' => 2000
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) return array();
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        
        preg_match('/\[.*\]/s', $text, $matches);
        if (!empty($matches[0])) {
            $claims = json_decode($matches[0], true);
            return is_array($claims) ? $claims : array();
        }
        
        return array();
    }
    
    private static function extract_claims_basic($content) {
        $sentences = self::split_sentences($content);
        $claims = array();
        
        foreach ($sentences as $index => $sentence) {
            $score = self::calculate_claim_score($sentence);
            if ($score > 0.5) {
                $claims[] = array(
                    'text' => $sentence,
                    'score' => $score,
                    'type' => self::classify_claim($sentence)
                );
            }
        }
        
        usort($claims, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($claims, 0, 10);
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
        $keywords = array('according to', 'said', 'stated', 'study shows', 'data shows', 'percent', 'million', 'billion');
        
        foreach ($keywords as $keyword) {
            if (stripos($sentence, $keyword) !== false) {
                $score += 0.2;
            }
        }
        
        if (preg_match('/\d+/', $sentence)) $score += 0.15;
        if (preg_match('/"[^"]*"/', $sentence)) $score += 0.15;
        
        return min($score, 1.0);
    }
    
    private static function classify_claim($sentence) {
        if (preg_match('/\d+/', $sentence)) return 'statistical';
        if (preg_match('/"[^"]*"/', $sentence)) return 'quote';
        return 'general';
    }
    
    private static function extract_keywords($text) {
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were');
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