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
        7.  Ensure the final output is ONLY the JSON object, with no other text, markdown formatting, or explanations before or after it.";

        $response = wp_remote_post('https://api.perplexity.ai/chat/completions', array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'model' => 'sonar-pro',
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

        // **IMPROVED VALIDATION AND LOGGING**
        if (empty($matches[0])) {
            // Log the entire raw response for debugging
            error_log('AI Verify (Hybrid): Perplexity single-call FAILED to find a JSON object. Raw response body: ' . $body);
            return new WP_Error('json_extraction_failed', 'AI response did not contain a JSON object.');
        }

        $result = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Verify (Hybrid): Perplexity single-call FAILED to parse JSON. JSON content: ' . $matches[0]);
            return new WP_Error('json_decode_failed', 'AI response contained invalid JSON.');
        }

        return $result;
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

        $body_string = wp_remote_retrieve_body($response);
        $response_data = json_decode($body_string, true);

        // First, check if the main API response is valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Verify (Hybrid): OpenRouter single-call FAILED to decode main API response. Raw Body: ' . $body_string);
            return new WP_Error('json_decode_failed_outer', 'Could not decode the main API response from OpenRouter.');
        }

        // Now, safely navigate the nested structure to get the AI's content string
        if (empty($response_data['choices'][0]['message']['content'])) {
            error_log('AI Verify (Hybrid): OpenRouter response is missing the content block. Full response: ' . print_r($response_data, true));
            return new WP_Error('missing_content_block', 'AI response was valid but did not contain the expected content.');
        }

        $content_string = $response_data['choices'][0]['message']['content'];

        // Finally, decode the inner JSON string which contains our report
        $report_data = json_decode($content_string, true);

        // If decoding fails, it might be because the AI added text around the JSON.
        // Try one last time to extract it with regex as a fallback.
        if (json_last_error() !== JSON_ERROR_NONE) {
            preg_match('/\{.*\}/s', $content_string, $matches);
            if (!empty($matches[0])) {
                $report_data = json_decode($matches[0], true);
                // If it's valid now, return it
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $report_data;
                }
            }

            // If it still fails, log the error and return.
            error_log('AI Verify (Hybrid): OpenRouter single-call FAILED to decode the inner report JSON. Content string was: ' . $content_string);
            return new WP_Error('json_decode_failed_inner', 'The content from the AI was not a valid JSON report.');
        }

        return $report_data;
    }
}