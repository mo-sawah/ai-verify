<?php
/**
 * Hybrid Single-Call Fact-Check Analysis Engine
 * This class is dedicated to the faster, single-call workflows,
 * keeping it isolated from the multi-step analyzer.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Hybrid_Analyzer {

    /**
     * Analyze entire content with a single Perplexity call
     */
    public static function analyze_with_single_call_perplexity($content, $context, $combined_context) {
        $api_key = get_option('ai_verify_perplexity_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Perplexity API key not configured.');
        }

        error_log('AI Verify (Hybrid): Starting Single Call analysis with Perplexity.');

        $prompt = "You are a professional, unbiased fact-checking journalist. Your task is to analyze the following article content using the provided context from existing fact-checks and real-time web searches.

        Article Context (Title/URL): {$context}
        Article Content: {$content}
        {$combined_context}

        Your mission is to return a complete fact-check report in a single, valid JSON object. The JSON should follow this exact structure:
        {
            \"factcheck_results\": [
                {
                    \"claim\": \"The first verifiable claim you identified.\",
                    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
                    \"explanation\": \"A detailed explanation for your rating, citing web sources.\",
                    \"sources\": [{\"name\": \"Source Name\", \"url\": \"https://source.url\"}],
                    \"confidence\": 0.9
                }
            ],
            \"overall_score\": 85.5,
            \"credibility_rating\": \"Highly Credible\",
            \"propaganda_techniques\": [\"Technique 1\", \"Technique 2\"]
        }

        CRITICAL INSTRUCTIONS:
        1.  Identify 5-10 of the most significant, verifiable claims from the article.
        2.  For each claim, perform a thorough web search to find high-quality, primary sources (e.g., news agencies, scientific studies, official reports).
        3.  Provide a clear rating and a detailed explanation for each claim.
        4.  Calculate an overall credibility score (0-100) based on the accuracy and verifiability of the claims.
        5.  Determine a final credibility rating ('Highly Credible', 'Mostly Credible', 'Mixed Credibility', 'Low Credibility', 'Not Credible').
        6.  Identify any propaganda techniques or logical fallacies present.
        7.  Ensure the final output is ONLY the JSON object, with no other text before or after it.";

        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'model' => 'llama-3.1-sonar-large-128k-online',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are an expert fact-checker. Return only a valid JSON object based on your web search and analysis.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.1,
                'max_tokens' => 4000
            )),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            error_log('AI Verify (Hybrid): Perplexity single-call error: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        preg_match('/\{.*\}/s', $body, $matches);
        if (empty($matches[0])) {
            error_log('AI Verify (Hybrid): Perplexity single-call failed to return valid JSON. Body: ' . $body);
            return new WP_Error('json_error', 'AI failed to produce a valid JSON report.');
        }

        return json_decode($matches[0], true);
    }

    /**
     * Analyze entire content with a single OpenRouter call
     */
    public static function analyze_with_single_call_openrouter($content, $context, $combined_context) {
        $api_key = get_option('ai_verify_openrouter_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API key not configured.');
        }

        $model = get_option('ai_verify_openrouter_model', 'anthropic/claude-3.5-sonnet');
        error_log('AI Verify (Hybrid): Starting Single Call analysis with OpenRouter.');

        $prompt = "You are a professional, unbiased fact-checking journalist. Your task is to analyze the following article content using ONLY the context provided from existing fact-checks and real-time web searches.

        Article Context (Title/URL): {$context}
        Article Content: {$content}
        {$combined_context}

        Your mission is to return a complete fact-check report in a single, valid JSON object. The JSON should follow this exact structure:
        {
            \"factcheck_results\": [
                {
                    \"claim\": \"The first verifiable claim you identified.\",
                    \"rating\": \"True/False/Mostly True/Mostly False/Misleading/Unverified\",
                    \"explanation\": \"A detailed explanation for your rating, citing the provided web sources by [Source X].\",
                    \"sources\": [{\"name\": \"Source Name\", \"url\": \"https://source.url\"}],
                    \"confidence\": 0.9
                }
            ],
            \"overall_score\": 85.5,
            \"credibility_rating\": \"Highly Credible\",
            \"propaganda_techniques\": [\"Technique 1\", \"Technique 2\"]
        }

        CRITICAL INSTRUCTIONS:
        1.  Base your entire analysis STRICTLY on the web search results and context provided.
        2.  Identify 5-10 of the most significant, verifiable claims from the article.
        3.  Provide a clear rating and detailed explanation for each claim, citing sources like [Source 1], [Source 2], etc.
        4.  Populate the 'sources' array for each claim with the URLs from the web results you used.
        5.  Calculate an overall credibility score (0-100) and a final rating.
        6.  Identify any propaganda techniques or logical fallacies.
        7.  Ensure the final output is ONLY the JSON object.";

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are an expert fact-checker. Return only a valid JSON object based on the provided context.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.1,
                'max_tokens' => 4000
            )),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            error_log('AI Verify (Hybrid): OpenRouter single-call error: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        preg_match('/\{.*\}/s', $body, $matches);
        if (empty($matches[0])) {
            error_log('AI Verify (Hybrid): OpenRouter single-call failed to return valid JSON. Body: ' . $body);
            return new WP_Error('json_error', 'AI failed to produce a valid JSON report.');
        }

        return json_decode($matches[0], true);
    }
}