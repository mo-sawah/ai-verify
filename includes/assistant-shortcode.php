<?php
/**
 * AI Verify - Advanced Frontend Chat Assistant Shortcode
 * Professional multi-tool AI assistant with streaming responses
 * 
 * Features:
 * - Multi-turn conversations with full context
 * - Tool calling: Web search (Tavily), Web scraping (Firecrawl), Database queries, Fact-check access
 * - Streaming responses for real-time feedback
 * - Conversation history with export
 * - Professional UI with dark mode support
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Assistant_Shortcode {
    
    public static function init() {
        add_shortcode('ai_verify_assistant', array(__CLASS__, 'render_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_ai_verify_assistant_chat', array(__CLASS__, 'handle_chat'));
        add_action('wp_ajax_nopriv_ai_verify_assistant_chat', array(__CLASS__, 'handle_chat'));
        
        add_action('wp_ajax_ai_verify_assistant_get_history', array(__CLASS__, 'get_history'));
        add_action('wp_ajax_nopriv_ai_verify_assistant_get_history', array(__CLASS__, 'get_history'));
    }
    
    /**
     * Render shortcode
     */
    public static function render_shortcode($atts) {
        self::enqueue_assets();
        
        $template_path = AI_VERIFY_PLUGIN_DIR . 'templates/assistant-shortcode-template.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        return '<p>Chat assistant template not found.</p>';
    }
    
    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        static $assets_enqueued = false;
        if ($assets_enqueued) return;

        wp_enqueue_style(
            'ai-verify-assistant-shortcode',
            AI_VERIFY_PLUGIN_URL . 'assets/css/assistant-shortcode.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        wp_enqueue_script(
            'ai-verify-assistant-shortcode',
            AI_VERIFY_PLUGIN_URL . 'assets/js/assistant-shortcode.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-assistant-shortcode', 'aiVerifyAssistant', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_assistant_nonce'),
            'max_history' => 20, // Keep last 20 messages for context
            'stream_enabled' => true
        ));
        
        $assets_enqueued = true;
    }
    
    /**
     * Handle chat message with tool calling
     */
    public static function handle_chat() {
        // Rate limiting
        $ip = self::get_user_ip();
        $rate_key = 'ai_verify_chat_rate_' . md5($ip);
        $requests = get_transient($rate_key);
        
        if ($requests === false) {
            set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        } elseif ($requests > 20) { // 20 requests per minute
            wp_send_json_error(array(
                'message' => 'Rate limit exceeded. Please wait a moment.'
            ));
        } else {
            set_transient($rate_key, $requests + 1, MINUTE_IN_SECONDS);
        }
        
        check_ajax_referer('ai_verify_assistant_nonce', 'nonce');
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message cannot be empty'));
        }
        
        // Validate history structure
        if (!is_array($history)) {
            $history = array();
        }
        
        try {
            $response = self::process_chat_with_tools($message, $history, $session_id);
            wp_send_json_success($response);
        } catch (Exception $e) {
            error_log('AI Verify Chat Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to process message. Please try again.'
            ));
        }
    }
    
    /**
     * Process chat with intelligent tool calling
     */
    private static function process_chat_with_tools($message, $history, $session_id) {
        $openrouter_key = get_option('ai_verify_openrouter_key');
        
        if (empty($openrouter_key)) {
            throw new Exception('AI provider not configured');
        }
        
        // Build system context with available tools
        $system_context = self::build_system_context();
        
        // Prepare conversation history
        $messages = array(
            array('role' => 'system', 'content' => $system_context)
        );
        
        // Add conversation history (last 20 messages for context)
        $recent_history = array_slice($history, -20);
        foreach ($recent_history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Determine which tools to use
        $tool_calls = self::determine_tool_calls($message);
        $tool_results = array();
        $tools_used = array();
        
        // Execute tools
        if (!empty($tool_calls)) {
            foreach ($tool_calls as $tool_call) {
                $result = self::execute_tool($tool_call['tool'], $tool_call['params']);
                if ($result) {
                    $tool_results[] = $result;
                    $tools_used[] = $tool_call['tool'];
                }
            }
        }
        
        // If tools were used, add their results to context
        if (!empty($tool_results)) {
            $tool_context = "\n\n=== TOOL RESULTS ===\n";
            foreach ($tool_results as $result) {
                $tool_context .= $result . "\n\n";
            }
            
            $messages[count($messages) - 1]['content'] .= $tool_context;
        }
        
        // Call OpenRouter API with Claude 3.5 Haiku (fast & cheap: $0.25/$1.25 per 1M tokens)
        $api_response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $openrouter_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode(array(
                'model' => 'anthropic/claude-3.5-haiku', // Fast, cheap, powerful
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2048,
                'stream' => false // We'll implement streaming in JavaScript
            ))
        ));
        
        if (is_wp_error($api_response)) {
            throw new Exception($api_response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'API error');
        }
        
        $assistant_message = $body['choices'][0]['message']['content'] ?? '';
        
        // Extract sources from tool results
        $sources = array();
        foreach ($tool_results as $result_data) {
            if (isset($result_data['sources'])) {
                $sources = array_merge($sources, $result_data['sources']);
            }
        }
        
        return array(
            'message' => $assistant_message,
            'tools_used' => array_unique($tools_used),
            'sources' => $sources,
            'session_id' => $session_id,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Build comprehensive system context
     */
    private static function build_system_context() {
        global $wpdb;
        
        $site_name = get_bloginfo('name');
        $current_date = current_time('F j, Y g:i A');
        
        // Get database stats
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        $total_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends") ?: 0;
        $viral_claims = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends WHERE velocity_status IN ('viral', 'emerging')") ?: 0;
        
        // Get recent posts
        $recent_posts = get_posts(array(
            'numberposts' => 10,
            'post_status' => 'publish'
        ));
        
        $posts_summary = "Recent articles:\n";
        foreach ($recent_posts as $post) {
            $posts_summary .= "- {$post->post_title} (ID: {$post->ID})\n";
        }
        
        $context = <<<CONTEXT
You are an advanced AI fact-checking assistant for {$site_name}, a professional misinformation intelligence platform.

**Current Date & Time**: {$current_date}

**Your Capabilities**:

1. **Web Search (Tavily API)**: Search the internet for current information, news, and fact-checks
2. **Web Scraping (Firecrawl)**: Analyze any URL the user provides in depth
3. **Database Access**: Query our database of {$total_claims} fact-checked claims ({$viral_claims} currently viral/emerging)
4. **Post Database**: Access to all published articles and content on this website
5. **Fact-Check Analysis**: Provide credibility scores and propaganda detection

{$posts_summary}

**Instructions**:
- Be conversational, helpful, and accurate
- When you use tools, explain what you're doing ("Let me search for that...")
- Cite sources when providing factual information
- If you find misinformation, explain why it's false with evidence
- Use markdown formatting for clarity (bold, lists, etc.)
- Keep responses under 500 words unless detailed analysis is requested
- If you don't know something, say so and offer to search
- Be skeptical of unverified claims

**Response Format**:
- Use **bold** for key findings
- Use bullet points for lists
- Include source citations when relevant
- Provide actionable insights when possible

**Propaganda Techniques You Can Detect**:
- Ad Hominem, Strawman, False Dilemma, Appeal to Emotion, Loaded Language, Bandwagon, Cherry Picking, and more

Remember: You have real-time access to the web and our entire database. Use these tools proactively to give accurate, helpful responses.
CONTEXT;
        
        return $context;
    }
    
    /**
     * Determine which tools to call based on user message
     */
    private static function determine_tool_calls($message) {
        $message_lower = strtolower($message);
        $tools = array();
        
        // Check for URL (always scrape URLs)
        if (preg_match('/https?:\/\/[^\s]+/', $message, $matches)) {
            $tools[] = array(
                'tool' => 'web_scrape',
                'params' => array('url' => $matches[0])
            );
        }
        
        // Check for search intent
        $search_keywords = array('search', 'find', 'what is', 'who is', 'latest', 'recent', 'current', 'news', 'today');
        foreach ($search_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $tools[] = array(
                    'tool' => 'web_search',
                    'params' => array('query' => $message)
                );
                break;
            }
        }
        
        // Check for database query intent
        $db_keywords = array('claims about', 'fact check', 'database', 'trending', 'viral', 'propaganda', 'misinformation about');
        foreach ($db_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $tools[] = array(
                    'tool' => 'database_query',
                    'params' => array('query' => $message)
                );
                break;
            }
        }
        
        // Check for post search intent
        $post_keywords = array('article', 'post', 'blog', 'wrote', 'published');
        foreach ($post_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                $tools[] = array(
                    'tool' => 'post_search',
                    'params' => array('query' => $message)
                );
                break;
            }
        }
        
        return $tools;
    }
    
    /**
     * Execute a tool call
     */
    private static function execute_tool($tool, $params) {
        switch ($tool) {
            case 'web_search':
                return self::tool_web_search($params['query']);
            
            case 'web_scrape':
                return self::tool_web_scrape($params['url']);
            
            case 'database_query':
                return self::tool_database_query($params['query']);
            
            case 'post_search':
                return self::tool_post_search($params['query']);
            
            default:
                return null;
        }
    }
    
    /**
     * Tool: Web Search via Tavily
     */
    private static function tool_web_search($query) {
        $tavily_key = get_option('ai_verify_tavily_key');
        
        if (empty($tavily_key)) {
            return null;
        }
        
        $response = wp_remote_post('https://api.tavily.com/search', array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'api_key' => $tavily_key,
                'query' => $query,
                'search_depth' => 'basic',
                'max_results' => 5,
                'include_answer' => true
            ))
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $results = $body['results'] ?? array();
        $answer = $body['answer'] ?? '';
        
        $content = "**Web Search Results**:\n";
        if (!empty($answer)) {
            $content .= "Summary: {$answer}\n\n";
        }
        
        $sources = array();
        foreach ($results as $result) {
            $content .= "- **{$result['title']}**: {$result['content']}\n  Source: {$result['url']}\n\n";
            $sources[] = array(
                'title' => $result['title'],
                'url' => $result['url']
            );
        }
        
        return array(
            'content' => $content,
            'sources' => $sources
        );
    }
    
    /**
     * Tool: Web Scraping via Firecrawl
     */
    private static function tool_web_scrape($url) {
        if (!class_exists('AI_Verify_Factcheck_Scraper')) {
            return null;
        }
        
        $scraped = AI_Verify_Factcheck_Scraper::scrape_url($url);
        
        if (is_wp_error($scraped) || empty($scraped['content'])) {
            return null;
        }
        
        $content = "**Scraped Content from {$url}**:\n\n";
        $content .= "Title: {$scraped['title']}\n\n";
        $content .= substr($scraped['content'], 0, 2000); // Limit to 2000 chars
        
        return array(
            'content' => $content,
            'sources' => array(
                array('title' => $scraped['title'], 'url' => $url)
            )
        );
    }
    
    /**
     * Tool: Database Query
     */
    private static function tool_database_query($query) {
        global $wpdb;
        
        $table_trends = $wpdb->prefix . 'ai_verify_claim_trends';
        
        // Extract search terms
        $search_terms = self::extract_search_terms($query);
        
        if (empty($search_terms)) {
            // Return general stats
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends");
            $viral = $wpdb->get_var("SELECT COUNT(*) FROM $table_trends WHERE velocity_status IN ('viral', 'emerging')");
            
            return "**Database Stats**: {$total} total claims, {$viral} currently viral/emerging.";
        }
        
        // Search claims
        $like = '%' . $wpdb->esc_like($search_terms) . '%';
        $claims = $wpdb->get_results($wpdb->prepare("
            SELECT claim_text, avg_credibility_score, velocity_status, category, check_count, metadata
            FROM $table_trends
            WHERE claim_text LIKE %s OR category LIKE %s
            ORDER BY velocity_score DESC
            LIMIT 10
        ", $like, $like), ARRAY_A);
        
        if (empty($claims)) {
            return "No claims found matching '{$search_terms}' in our database.";
        }
        
        $content = "**Database Results** ({$search_terms}):\n\n";
        foreach ($claims as $claim) {
            $metadata = json_decode($claim['metadata'], true);
            $propaganda = !empty($metadata['propaganda_techniques']) ? implode(', ', $metadata['propaganda_techniques']) : 'None';
            
            $content .= "- **{$claim['claim_text']}**\n";
            $content .= "  Credibility: {$claim['avg_credibility_score']}/100 | Status: {$claim['velocity_status']} | Category: {$claim['category']}\n";
            $content .= "  Propaganda: {$propaganda}\n\n";
        }
        
        return $content;
    }
    
    /**
     * Tool: Post Search
     */
    private static function tool_post_search($query) {
        $search_terms = self::extract_search_terms($query);
        
        $posts = get_posts(array(
            's' => $search_terms,
            'numberposts' => 10,
            'post_status' => 'publish'
        ));
        
        if (empty($posts)) {
            return "No articles found matching '{$search_terms}'.";
        }
        
        $content = "**Articles Found** ({$search_terms}):\n\n";
        foreach ($posts as $post) {
            $excerpt = wp_trim_words($post->post_content, 30);
            $content .= "- **{$post->post_title}**\n  {$excerpt}\n  Link: " . get_permalink($post->ID) . "\n\n";
        }
        
        return $content;
    }
    
    /**
     * Extract search terms from query
     */
    private static function extract_search_terms($query) {
        // Remove common words
        $stopwords = array('what', 'how', 'why', 'when', 'where', 'who', 'is', 'are', 'about', 'the', 'a', 'an', 'in', 'on', 'at', 'for', 'to', 'of', 'show', 'me', 'find', 'search', 'claims', 'fact', 'check');
        
        $words = preg_split('/\s+/', strtolower($query));
        $words = array_filter($words, function($word) use ($stopwords) {
            return strlen($word) > 2 && !in_array($word, $stopwords);
        });
        
        return implode(' ', array_slice($words, 0, 3));
    }
    
    /**
     * Get conversation history
     */
    public static function get_history() {
        check_ajax_referer('ai_verify_assistant_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_success(array('history' => array()));
        }
        
        // In a real implementation, you'd store this in the database
        // For now, we rely on client-side localStorage
        wp_send_json_success(array('history' => array()));
    }
    
    /**
     * Get user IP
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

AI_Verify_Assistant_Shortcode::init();