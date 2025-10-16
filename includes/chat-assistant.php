<?php
/**
 * AI Chat Assistant System
 * Smart context-aware assistant with tool calling
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Chat_Assistant {
    
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_ai_verify_chat_message', array(__CLASS__, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_ai_verify_chat_message', array(__CLASS__, 'handle_chat_message'));
        
        add_action('wp_ajax_ai_verify_chat_history', array(__CLASS__, 'get_chat_history'));
        add_action('wp_ajax_nopriv_ai_verify_chat_history', array(__CLASS__, 'get_chat_history'));

        // --- ADD THIS NEW ACTION FOR THE DEDICATED PAGE ---
       add_action('wp_ajax_ai_verify_assistant_page_message', array(__CLASS__, 'handle_assistant_page_message'));
    }
    
    /**
     * Handle chat message
     */
    public static function handle_chat_message() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is required'));
        }
        
        if (empty($session_id)) {
            $session_id = uniqid('chat_', true);
        }
        
        try {
            $response = self::process_chat_message($message, $session_id);
            
            wp_send_json_success(array(
                'response' => $response['message'],
                'tools_used' => $response['tools_used'],
                'sources' => $response['sources'],
                'session_id' => $session_id
            ));
        } catch (Exception $e) {
            error_log('AI Verify Chat Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to process message: ' . $e->getMessage()));
        }
    }
    
    /**
     * Process chat message with smart tool calling
     */
    private static function process_chat_message($message, $session_id) {
        $openrouter_key = get_option('ai_verify_openrouter_key');
        
        if (empty($openrouter_key)) {
            throw new Exception('OpenRouter API key not configured');
        }
        
        // Build system context
        $context = self::build_system_context();
        
        // Get conversation history
        $history = self::get_conversation_history($session_id);
        
        // Determine if tools are needed
        $needs_tools = self::analyze_tool_requirements($message);
        
        $tools_used = array();
        $sources = array();
        $tool_results = '';
        
        // Execute tools if needed
        if ($needs_tools['search']) {
            $search_results = self::search_with_tavily($message);
            $tools_used[] = 'tavily';
            $sources = array_merge($sources, $search_results['sources']);
            $tool_results .= "\n\n=== Web Search Results ===\n" . $search_results['content'];
        }
        
        if ($needs_tools['scrape'] && !empty($needs_tools['url'])) {
            $scraped = self::scrape_with_firecrawl($needs_tools['url']);
            $tools_used[] = 'firecrawl';
            $sources[] = array('url' => $needs_tools['url'], 'title' => 'Scraped Content');
            $tool_results .= "\n\n=== Scraped Content ===\n" . $scraped;
        }
        
        if ($needs_tools['database']) {
            $db_results = self::query_database($message);
            $tools_used[] = 'database';
            $tool_results .= "\n\n=== Database Results ===\n" . $db_results;
        }
        
        // Build messages array
        $messages = array(
            array(
                'role' => 'system',
                'content' => $context
            )
        );
        
        // Add history
        foreach ($history as $msg) {
            $messages[] = array(
                'role' => $msg['role'],
                'content' => $msg['content']
            );
        }
        
        // Add current message with tool results
        $user_message = $message;
        if (!empty($tool_results)) {
            $user_message .= $tool_results;
        }
        
        $messages[] = array(
            'role' => 'user',
            'content' => $user_message
        );
        
        // Call OpenRouter
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $openrouter_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode(array(
                'model' => 'meta-llama/llama-3.1-70b-instruct',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000
            ))
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'OpenRouter API error');
        }
        
        $assistant_message = $body['choices'][0]['message']['content'] ?? '';
        
        // Save to history
        self::save_to_history($session_id, 'user', $message);
        self::save_to_history($session_id, 'assistant', $assistant_message);
        
        return array(
            'message' => $assistant_message,
            'tools_used' => $tools_used,
            'sources' => $sources
        );
    }
    
    /**
     * Build system context
     */
    private static function build_system_context() {
        global $wpdb;
        
        $site_name = get_bloginfo('name');
        $current_date = current_time('F j, Y');
        $current_time = current_time('g:i A');
        
        // Get database stats
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $total_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends");
        $viral_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends WHERE velocity_status IN ('viral', 'emerging')");
        
        // Get recent top claims
        $recent_claims = $wpdb->get_results("
            SELECT claim_text, avg_credibility_score, velocity_status, category
            FROM $table_trends
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY velocity_score DESC
            LIMIT 10
        ", ARRAY_A);
        
        $claims_summary = '';
        foreach ($recent_claims as $claim) {
            $claims_summary .= "- {$claim['claim_text']} (Score: {$claim['avg_credibility_score']}, Status: {$claim['velocity_status']}, Category: {$claim['category']})\n";
        }
        
        $context = <<<CONTEXT
You are an AI fact-checking assistant for {$site_name}, a professional misinformation intelligence platform.

**Current Date & Time**: {$current_date} at {$current_time}

**Your Capabilities**:
1. **Database Access**: You have access to {$total_claims} fact-checked claims, including {$viral_claims} currently viral/emerging claims
2. **Web Search**: You can search the web via Tavily when you need current information
3. **Web Scraping**: You can scrape specific URLs via Firecrawl when users provide links
4. **Analysis**: You can analyze propaganda techniques, credibility scores, and trend patterns

**Recent Top Claims in Database**:
{$claims_summary}

**Instructions**:
- Be concise, professional, and accurate
- When referencing database claims, cite the claim text and credibility score
- If asked about current events NOT in the database, acknowledge you'll need to search
- If given a URL, offer to analyze it with Firecrawl
- Explain propaganda techniques in simple terms
- Use markdown formatting for clarity
- Never make up information - if you don't know, say so

**Response Style**:
- Keep answers under 200 words unless detailed analysis is requested
- Use bullet points for clarity
- Bold important facts
- Include credibility scores when discussing claims
CONTEXT;
        
        return $context;
    }
    
    /**
     * Analyze if tools are needed
     */
    private static function analyze_tool_requirements($message) {
        $message_lower = strtolower($message);
        
        $needs = array(
            'search' => false,
            'scrape' => false,
            'database' => false,
            'url' => null
        );
        
        // Check for URL
        if (preg_match('/https?:\/\/[^\s]+/', $message, $matches)) {
            $needs['scrape'] = true;
            $needs['url'] = $matches[0];
        }
        
        // Keywords that suggest web search needed
        $search_keywords = array('latest', 'recent', 'current', 'today', 'news', 'breaking', 'happened', 'what is happening');
        foreach ($search_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $needs['search'] = true;
                break;
            }
        }
        
        // Keywords that suggest database query
        $db_keywords = array('claims about', 'fact check', 'credibility', 'propaganda', 'trends', 'viral', 'misinformation about');
        foreach ($db_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $needs['database'] = true;
                break;
            }
        }
        
        // If asking about specific topic in database
        if (preg_match('/(what|show|find|search).*(claim|fact|check|propaganda)/i', $message)) {
            $needs['database'] = true;
        }
        
        return $needs;
    }
    
    /**
     * Search with Tavily
     */
    private static function search_with_tavily($query) {
        $tavily_key = get_option('ai_verify_tavily_key');
        
        if (empty($tavily_key)) {
            return array('content' => '', 'sources' => array());
        }
        
        $response = wp_remote_post('https://api.tavily.com/search', array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'api_key' => $tavily_key,
                'query' => $query,
                'search_depth' => 'basic',
                'max_results' => 5
            ))
        ));
        
        if (is_wp_error($response)) {
            return array('content' => '', 'sources' => array());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $results = $body['results'] ?? array();
        
        $content = '';
        $sources = array();
        
        foreach ($results as $result) {
            $content .= "**{$result['title']}**\n{$result['content']}\nURL: {$result['url']}\n\n";
            $sources[] = array('url' => $result['url'], 'title' => $result['title']);
        }
        
        return array('content' => $content, 'sources' => $sources);
    }
    
    /**
     * Scrape with Firecrawl
     */
    private static function scrape_with_firecrawl($url) {
        $scraped = AI_Verify_Factcheck_Scraper::scrape_url($url);
        return is_array($scraped) && isset($scraped['content']) ? $scraped['content'] : '';
    }
    
    /**
     * Query database
     */
    private static function query_database($message) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Extract potential search terms
        $search_terms = self::extract_search_terms($message);
        
        if (empty($search_terms)) {
            // Return general stats
            $stats = AI_Verify_Intelligence_Dashboard::get_dashboard_stats();
            return "Database Overview:\n- Total Claims: {$stats['active_claims']}\n- Viral Claims: {$stats['viral_claims']}\n- High Alert: {$stats['high_alert']}";
        }
        
        // Search claims
        $like_clause = '%' . $wpdb->esc_like($search_terms) . '%';
        $claims = $wpdb->get_results($wpdb->prepare("
            SELECT claim_text, avg_credibility_score, velocity_status, category, check_count, metadata
            FROM $table_trends
            WHERE claim_text LIKE %s OR category LIKE %s
            ORDER BY velocity_score DESC
            LIMIT 10
        ", $like_clause, $like_clause), ARRAY_A);
        
        if (empty($claims)) {
            return "No claims found matching '{$search_terms}' in our database.";
        }
        
        $result = "Found " . count($claims) . " claims:\n\n";
        foreach ($claims as $claim) {
            $metadata = json_decode($claim['metadata'], true);
            $propaganda = isset($metadata['propaganda_techniques']) ? implode(', ', $metadata['propaganda_techniques']) : 'None';
            
            $result .= "**Claim**: {$claim['claim_text']}\n";
            $result .= "- Credibility: {$claim['avg_credibility_score']}/100\n";
            $result .= "- Status: {$claim['velocity_status']}\n";
            $result .= "- Category: {$claim['category']}\n";
            $result .= "- Checks: {$claim['check_count']}\n";
            $result .= "- Propaganda: {$propaganda}\n\n";
        }
        
        return $result;
    }
    
    /**
     * Extract search terms from message
     */
    private static function extract_search_terms($message) {
        // Remove common question words
        $cleaned = preg_replace('/\b(what|how|why|when|where|who|is|are|about|the|a|an|in|on|at|for|to|of)\b/i', '', $message);
        
        // Get meaningful words
        $words = preg_split('/\s+/', trim($cleaned));
        $words = array_filter($words, function($word) {
            return strlen($word) > 3;
        });
        
        return implode(' ', array_slice($words, 0, 3));
    }
    
    /**
     * Save to conversation history
     */
    private static function save_to_history($session_id, $role, $content) {
        $history = get_transient('ai_verify_chat_' . $session_id) ?: array();
        
        $history[] = array(
            'role' => $role,
            'content' => $content,
            'timestamp' => current_time('mysql')
        );
        
        // Keep last 20 messages
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        
        set_transient('ai_verify_chat_' . $session_id, $history, 3600); // 1 hour
    }
    
    /**
     * Get conversation history
     */
    private static function get_conversation_history($session_id) {
        return get_transient('ai_verify_chat_' . $session_id) ?: array();
    }
    
    /**
     * AJAX: Get chat history
     */
    public static function get_chat_history() {
        check_ajax_referer('ai_verify_dashboard_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_success(array('history' => array()));
        }
        
        $history = self::get_conversation_history($session_id);
        
        wp_send_json_success(array('history' => $history));
    }

    /**
     * NEW: Handle advanced chat message from the dedicated assistant page
     */
    public static function handle_assistant_page_message() {
        check_ajax_referer('ai_verify_assistant_nonce', 'nonce'); // Use a new nonce for security

        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? uniqid('chat_', true));
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : [];
        
        try {
            // This is where the magic for multi-step tool use happens
            $response = self::process_advanced_chat_message($message, $session_id, $history);
            
            wp_send_json_success(array(
                'response' => $response['message'],
                'tools_used' => $response['tools_used'],
                'sources' => $response['sources'],
                'session_id' => $session_id
            ));
        } catch (Exception $e) {
            error_log('AI Verify Assistant Page Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to process message: ' . $e->getMessage()));
        }
    }

    /**
     * NEW: Process advanced chat message with multi-step tool calling
     */
    private static function process_advanced_chat_message($user_message, $session_id, $history) {
        $openrouter_key = get_option('ai_verify_openrouter_key');
        if (empty($openrouter_key)) {
            throw new Exception('OpenRouter API key not configured');
        }

        $system_context = self::build_system_context(); // You can enhance this context further
        $tools_used = [];
        $sources = [];
        
        // Construct messages array from provided history
        $messages = [['role' => 'system', 'content' => $system_context]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $user_message];

        // This is a simplified example of a tool-calling loop.
        // A real implementation would involve multiple calls to the AI.
        // 1. First call to determine which tools to use.
        // 2. Execute tools.
        // 3. Second call to AI with tool results to get the final answer.
        
        // For now, we'll use a more advanced version of the original logic.
        $needs = self::analyze_tool_requirements($user_message);
        $tool_results_context = '';
        
        if ($needs['search']) {
            $search_results = self::search_with_tavily($user_message);
            $tools_used[] = 'Tavily Search';
            $sources = array_merge($sources, $search_results['sources']);
            $tool_results_context .= "\n\n=== Web Search Results ===\n" . $search_results['content'];
        }
        
        if ($needs['scrape'] && !empty($needs['url'])) {
            $scraped = self::scrape_with_firecrawl($needs['url']);
            $tools_used[] = 'Firecrawl Scrape';
            $sources[] = ['url' => $needs['url'], 'title' => 'Scraped Content'];
            $tool_results_context .= "\n\n=== Scraped Content from {$needs['url']} ===\n" . $scraped;
        }
        
        if ($needs['database']) {
            $db_results = self::query_database($user_message); // You can make this function much smarter
            $tools_used[] = 'Database Query';
            $tool_results_context .= "\n\n=== Internal Database Results ===\n" . $db_results;
        }

        // Add tool results to the last user message for context
        if (!empty($tool_results_context)) {
            $messages[count($messages) - 1]['content'] .= $tool_results_context;
        }
        
        // Final call to OpenRouter with all context
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 120, // Longer timeout for complex tasks
            'headers' => [ /* ... same as before ... */ ],
            'body' => json_encode([
                'model' => 'anthropic/claude-3.5-sonnet', // Use a powerful model
                'messages' => $messages,
                'temperature' => 0.5,
                'max_tokens' => 2048,
            ])
        ));
        
        // ... handle response and errors same as process_chat_message ...
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $assistant_message = $body['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';

        return [
            'message' => $assistant_message,
            'tools_used' => array_unique($tools_used),
            'sources' => $sources,
        ];
    }
}

AI_Verify_Chat_Assistant::init();