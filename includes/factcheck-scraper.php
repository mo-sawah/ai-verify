<?php
/**
 * Web Scraper Engine
 * Integrates multiple scraping services.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Scraper {
    
    /**
     * Main scrape function - acts as a controller.
     *
     * @param string $url The URL to scrape.
     * @return array|WP_Error The scraped content or an error.
     */
    public static function scrape_url($url) {
        // Validate URL first
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided');
        }

        $service = get_option('ai_verify_scraping_service', 'jina');
        
        if ($service === 'firecrawl' && get_option('ai_verify_firecrawl_key')) {
            $result = self::scrape_with_firecrawl($url);
        } else {
            $result = self::scrape_with_jina($url);
        }

        // If the primary scraper fails, you could add a fallback here if desired
        if (is_wp_error($result)) {
            return $result;
        }

        // Return the parsed content from the successful scraper
        return self::parse_content($result['content'], $result['title'], $url);
    }

    /**
     * Scrapes a URL using the Firecrawl API.
     *
     * @param string $url The URL to scrape.
     * @return array|WP_Error An array with raw 'title' and 'content' or an error.
     */
    private static function scrape_with_firecrawl($url) {
        $api_key = get_option('ai_verify_firecrawl_key');
        if (empty($api_key)) {
            return new WP_Error('firecrawl_error', 'Firecrawl API key is not configured.');
        }

        $api_url = 'https://api.firecrawl.dev/v0/scrape';
        
        $response = wp_remote_post($api_url, array(
            'method'  => 'POST',
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => json_encode(array(
                'url' => $url,
                'pageOptions' => array('from' => 'jina') // Use Jina Reader via Firecrawl
            )),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200 || empty($body['data']['markdown'])) {
            $error_message = isset($body['error']) ? $body['error'] : 'Failed to retrieve content from Firecrawl.';
            return new WP_Error('firecrawl_error', $error_message);
        }

        return array(
            'title'   => $body['data']['metadata']['title'] ?? 'Untitled',
            'content' => $body['data']['markdown']
        );
    }
    
    /**
     * Scrape URL using Jina Reader API (your original method).
     *
     * @param string $url The URL to scrape.
     * @return array|WP_Error An array with raw 'title' and 'content' or an error.
     */
    private static function scrape_with_jina($url) {
        $jina_url = 'https://r.jina.ai/' . $url;
        
        $response = wp_remote_get($jina_url, array(
            'timeout' => 60,
            'headers' => array(
                'X-Return-Format' => 'markdown',
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status !== 200 || empty($body)) {
            return new WP_Error('scrape_failed', 'Failed to scrape URL with Jina. Status: ' . $status);
        }
        
        // Extract title from the markdown itself
        preg_match('/^# (.+)$/m', $body, $title_match);
        $title = !empty($title_match[1]) ? trim($title_match[1]) : self::extract_title_from_url($url);

        return array(
            'title' => $title,
            'content' => $body
        );
    }
    
    /**
     * Parse scraped content (this is your original function).
     */
    private static function parse_content($markdown, $title, $original_url) {
        $author = self::extract_author($markdown);
        $date = self::extract_date($markdown);
        $content = self::clean_markdown($markdown);
        $paragraphs = self::extract_paragraphs($content);
        $word_count = str_word_count(strip_tags($content));
        
        return array(
            'success' => true,
            'title' => $title,
            'content' => $content,
            'paragraphs' => $paragraphs,
            'author' => $author,
            'date' => $date,
            'word_count' => $word_count,
            'url' => $original_url,
            'scraped_at' => current_time('mysql')
        );
    }
    
    // --- ALL YOUR ORIGINAL HELPER FUNCTIONS REMAIN UNCHANGED ---

    private static function extract_title_from_url($url) {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '';
        $title = basename($path);
        $title = str_replace(array('-', '_'), ' ', $title);
        return ucwords($title);
    }
    
    private static function extract_author($markdown) {
        $patterns = array('/By ([A-Z][a-z]+ [A-Z][a-z]+)/i', '/Author: ([A-Z][a-z]+ [A-Z][a-z]+)/i', '/Written by ([A-Z][a-z]+ [A-Z][a-z]+)/i');
        foreach ($patterns as $pattern) { if (preg_match($pattern, $markdown, $match)) { return trim($match[1]); } }
        return null;
    }
    
    private static function extract_date($markdown) {
        $patterns = array('/Published: (\d{4}-\d{2}-\d{2})/', '/Date: (\d{4}-\d{2}-\d{2})/', '/(\d{1,2}\/\d{1,2}\/\d{4})/', '/(\w+ \d{1,2}, \d{4})/');
        foreach ($patterns as $pattern) { if (preg_match($pattern, $markdown, $match)) { return trim($match[1]); } }
        return null;
    }
    
    private static function clean_markdown($markdown) {
        $markdown = preg_replace('/\[.*?\]\(.*?\)/s', '', $markdown);
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = preg_replace('/<!--.*?-->/s', '', $markdown);
        return trim($markdown);
    }
    
    private static function extract_paragraphs($content) {
        $parts = explode("\n\n", $content);
        $paragraphs = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) > 50 && !preg_match('/^#+/', $part)) {
                $paragraphs[] = $part;
            }
        }
        return $paragraphs;
    }
    
    public static function search_phrase($phrase) {
        $api_key = get_option('ai_verify_google_factcheck_key');
        if (!empty($api_key)) {
            $factchecks = self::search_google_factcheck($phrase, $api_key);
            if (!empty($factchecks)) {
                return array('success' => true, 'source' => 'google_factcheck', 'results' => $factchecks, 'phrase' => $phrase);
            }
        }
        return array('success' => true, 'source' => 'web_search', 'phrase' => $phrase, 'requires_ai' => true);
    }
    
    private static function search_google_factcheck($query, $api_key) {
        $url = add_query_arg(array('key' => $api_key, 'query' => urlencode($query), 'languageCode' => 'en', 'pageSize' => 10), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        $response = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($response)) { return array(); }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $factchecks = array();
        if (isset($body['claims']) && is_array($body['claims'])) {
            foreach ($body['claims'] as $claim) {
                if (!isset($claim['claimReview'][0])) { continue; }
                $review = $claim['claimReview'][0];
                $factchecks[] = array(
                    'claim' => isset($claim['text']) ? $claim['text'] : '',
                    'rating' => isset($review['textualRating']) ? $review['textualRating'] : '',
                    'source' => isset($review['publisher']['name']) ? $review['publisher']['name'] : '',
                    'url' => isset($review['url']) ? $review['url'] : '',
                    'date' => isset($review['reviewDate']) ? $review['reviewDate'] : ''
                );
            }
        }
        return $factchecks;
    }
}

