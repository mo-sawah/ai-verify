<?php
/**
 * Web Scraper using Jina Reader API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Scraper {
    
    const JINA_API_URL = 'https://r.jina.ai/';
    
    /**
     * Scrape URL using Jina Reader
     */
    public static function scrape_url($url) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided');
        }
        
        // Build Jina Reader URL
        $jina_url = self::JINA_API_URL . urlencode($url);
        
        // Make request
        $response = wp_remote_get($jina_url, array(
            'timeout' => 30,
            'headers' => array(
                'X-Return-Format' => 'markdown',
                'X-Timeout' => '30'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status !== 200) {
            return new WP_Error('scrape_failed', 'Failed to scrape URL. Status: ' . $status);
        }
        
        if (empty($body)) {
            return new WP_Error('empty_content', 'No content retrieved from URL');
        }
        
        // Parse and clean content
        $parsed = self::parse_content($body, $url);
        
        return $parsed;
    }
    
    /**
     * Parse scraped content
     */
    private static function parse_content($markdown, $original_url) {
        // Extract title (first H1 or H2)
        preg_match('/^#+ (.+)$/m', $markdown, $title_match);
        $title = !empty($title_match[1]) ? trim($title_match[1]) : self::extract_title_from_url($original_url);
        
        // Extract metadata if present
        $author = self::extract_author($markdown);
        $date = self::extract_date($markdown);
        
        // Clean markdown content
        $content = self::clean_markdown($markdown);
        
        // Extract main text paragraphs
        $paragraphs = self::extract_paragraphs($content);
        
        // Get word count
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
    
    /**
     * Extract title from URL
     */
    private static function extract_title_from_url($url) {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '';
        $title = basename($path);
        $title = str_replace(array('-', '_'), ' ', $title);
        return ucwords($title);
    }
    
    /**
     * Extract author from markdown
     */
    private static function extract_author($markdown) {
        // Look for common author patterns
        $patterns = array(
            '/By ([A-Z][a-z]+ [A-Z][a-z]+)/i',
            '/Author: ([A-Z][a-z]+ [A-Z][a-z]+)/i',
            '/Written by ([A-Z][a-z]+ [A-Z][a-z]+)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $markdown, $match)) {
                return trim($match[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Extract publication date
     */
    private static function extract_date($markdown) {
        // Look for date patterns
        $patterns = array(
            '/Published: (\d{4}-\d{2}-\d{2})/',
            '/Date: (\d{4}-\d{2}-\d{2})/',
            '/(\d{1,2}\/\d{1,2}\/\d{4})/',
            '/(\w+ \d{1,2}, \d{4})/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $markdown, $match)) {
                return trim($match[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Clean markdown content
     */
    private static function clean_markdown($markdown) {
        // Remove navigation and footer content
        $markdown = preg_replace('/\[.*?\]\(.*?\)/s', '', $markdown); // Remove links
        
        // Remove excessive newlines
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        
        // Remove HTML comments
        $markdown = preg_replace('/<!--.*?-->/s', '', $markdown);
        
        return trim($markdown);
    }
    
    /**
     * Extract text paragraphs
     */
    private static function extract_paragraphs($content) {
        // Split by double newlines
        $parts = explode("\n\n", $content);
        
        $paragraphs = array();
        foreach ($parts as $part) {
            $part = trim($part);
            // Only keep substantial paragraphs (more than 50 characters)
            if (strlen($part) > 50 && !preg_match('/^#+/', $part)) {
                $paragraphs[] = $part;
            }
        }
        
        return $paragraphs;
    }
    
    /**
     * Search web for phrase/title
     */
    public static function search_phrase($phrase) {
        // Use Google Fact Check API first
        $api_key = get_option('ai_verify_google_factcheck_key');
        
        if (!empty($api_key)) {
            $factchecks = self::search_google_factcheck($phrase, $api_key);
            if (!empty($factchecks)) {
                return array(
                    'success' => true,
                    'source' => 'google_factcheck',
                    'results' => $factchecks,
                    'phrase' => $phrase
                );
            }
        }
        
        // If no results, use web search through Claude
        return array(
            'success' => true,
            'source' => 'web_search',
            'phrase' => $phrase,
            'requires_ai' => true
        );
    }
    
    /**
     * Search Google Fact Check API
     */
    private static function search_google_factcheck($query, $api_key) {
        $url = add_query_arg(array(
            'key' => $api_key,
            'query' => urlencode($query),
            'languageCode' => 'en',
            'pageSize' => 10
        ), 'https://factchecktools.googleapis.com/v1alpha1/claims:search');
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $factchecks = array();
        
        if (isset($body['claims']) && is_array($body['claims'])) {
            foreach ($body['claims'] as $claim) {
                if (!isset($claim['claimReview'][0])) {
                    continue;
                }
                
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