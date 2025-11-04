<?php
/**
 * Web Scraper Engine - IMPROVED with Metadata Extraction
 * Uses ScraperAPI (fast, reliable) or Firecrawl with adaptive filtering
 * IMPROVEMENTS:
 * - Enhanced metadata extraction (title, description, featured_image, author, date)
 * - Uses AI_Verify_Metadata_Extractor for comprehensive metadata parsing
 * - Automatic fallback to Jina if ScraperAPI/Firecrawl fails
 * - Special Daily Mail content filtering (removes recommended articles)
 * - Better error handling and logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Scraper {
    
    public static function scrape_url($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided');
        }

        $service = get_option('ai_verify_scraping_service', 'jina');
        
        // Priority: ScraperAPI > Firecrawl > Jina with automatic fallback
        if ($service === 'scraperapi' && get_option('ai_verify_scraperapi_key')) {
            $result = self::scrape_with_scraperapi($url);
            // If ScraperAPI fails, fallback to Jina
            if (is_wp_error($result)) {
                error_log('AI Verify: ScraperAPI failed (' . $result->get_error_message() . '), falling back to Jina');
                $result = self::scrape_with_jina($url);
            }
        } elseif ($service === 'firecrawl' && get_option('ai_verify_firecrawl_key')) {
            $result = self::scrape_with_firecrawl($url);
            // If Firecrawl fails, fallback to Jina
            if (is_wp_error($result)) {
                error_log('AI Verify: Firecrawl failed (' . $result->get_error_message() . '), falling back to Jina');
                $result = self::scrape_with_jina($url);
            }
        } else {
            $result = self::scrape_with_jina($url);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return self::parse_content($result, $url);
    }

    /**
     * SCRAPERAPI: Fast, reliable, handles JavaScript
     * UPDATED: Now uses AI_Verify_Metadata_Extractor for comprehensive metadata
     */
    private static function scrape_with_scraperapi($url) {
        $api_key = get_option('ai_verify_scraperapi_key');
        if (empty($api_key)) {
            return new WP_Error('scraperapi_error', 'ScraperAPI key not configured.');
        }

        error_log('AI Verify: Using ScraperAPI for: ' . $url);
        
        // ScraperAPI endpoint with render=true for JavaScript sites
        $scraper_url = add_query_arg(array(
            'api_key' => $api_key,
            'url' => $url,
            'render' => 'true', // Render JavaScript
            'country_code' => 'us'
        ), 'https://api.scraperapi.com/');
        
        $response = wp_remote_get($scraper_url, array(
            'timeout' => 60,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify: ScraperAPI error: ' . $response->get_error_message());
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            error_log('AI Verify: ScraperAPI returned status ' . $status);
            return new WP_Error('scraperapi_error', 'ScraperAPI returned status: ' . $status);
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return new WP_Error('scraperapi_error', 'ScraperAPI returned empty content');
        }
        
        // CRITICAL FIX: Extract content FIRST before metadata
        // This ensures the process never gets stuck on metadata extraction
        
        // Convert HTML to clean markdown
        $markdown = self::html_to_markdown($html);
        
        // Apply smart filtering (includes Daily Mail specific filtering)
        $markdown = self::smart_filter($markdown, $url);
        
        // Now extract metadata with full error protection
        $metadata = array(
            'title' => 'Untitled',
            'description' => '',
            'featured_image' => '',
            'author' => '',
            'date' => '',
            'date_modified' => '',
            'domain' => '',
            'favicon' => ''
        );
        
        try {
            if (class_exists('AI_Verify_Metadata_Extractor')) {
                $extracted = @AI_Verify_Metadata_Extractor::extract_metadata($html, $url);
                if (!empty($extracted) && is_array($extracted)) {
                    $metadata = array_merge($metadata, $extracted);
                }
            } else {
                // Fallback to basic extraction
                $metadata['title'] = self::extract_title_from_html($html);
            }
        } catch (Exception $e) {
            error_log('AI Verify: Metadata extraction error: ' . $e->getMessage());
            // Continue with default metadata - never block the process
            $metadata['title'] = self::extract_title_from_html($html);
        }
        
        error_log('AI Verify: ScraperAPI success - Title: "' . substr($metadata['title'], 0, 50) . '", Image: ' . ($metadata['featured_image'] ? 'YES' : 'NO') . ', Desc: ' . ($metadata['description'] ? 'YES' : 'NO'));
        
        return array(
            'title' => $metadata['title'],
            'content' => $markdown,
            'description' => $metadata['description'],
            'featured_image' => $metadata['featured_image'],
            'author' => $metadata['author'],
            'date' => $metadata['date'],
            'date_modified' => $metadata['date_modified'],
            'domain' => $metadata['domain'],
            'favicon' => $metadata['favicon']
        );
    }

    /**
     * Convert HTML to clean markdown (fast and simple)
     */
    private static function html_to_markdown($html) {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html);
        
        // Remove navigation, footer, sidebar, ads
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<aside\b[^>]*>.*?<\/aside>/is', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*(?:ad|advertisement|sidebar|related|trending)[^"]*"[^>]*>.*?<\/div>/is', '', $html);
        
        // Extract main content area (article, main, or div with role="main")
        if (preg_match('/<(?:article|main)[^>]*>(.*?)<\/(?:article|main)>/is', $html, $match)) {
            $html = $match[1];
        } elseif (preg_match('/<div[^>]*role="main"[^>]*>(.*?)<\/div>/is', $html, $match)) {
            $html = $match[1];
        }
        
        // Convert common elements to markdown
        $html = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n# $1\n", $html);
        $html = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n## $1\n", $html);
        $html = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n### $1\n", $html);
        $html = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "\n$1\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', "**$1**", $html);
        $html = preg_replace('/<em[^>]*>(.*?)<\/em>/is', "*$1*", $html);
        
        // Remove all remaining HTML tags
        $html = strip_tags($html);
        
        // Clean up whitespace
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        
        return trim($html);
    }
    
    /**
     * Extract title from HTML (fallback method)
     */
    private static function extract_title_from_html($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            $title = strip_tags($match[1]);
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim($title);
        }
        
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $match)) {
            $title = strip_tags($match[1]);
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim($title);
        }
        
        return 'Untitled';
    }

    /**
     * Firecrawl (keep as backup option) - IMPROVED metadata extraction
     */
    private static function scrape_with_firecrawl($url) {
        $api_key = get_option('ai_verify_firecrawl_key');
        if (empty($api_key)) {
            return new WP_Error('firecrawl_error', 'Firecrawl API key not configured.');
        }

        error_log('AI Verify: Using Firecrawl for: ' . $url);

        // Try v1 API first (has better metadata)
        $api_url = 'https://api.firecrawl.dev/v1/scrape';
        
        $response = wp_remote_post($api_url, array(
            'method'  => 'POST',
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => json_encode(array(
                'url' => $url,
                'formats' => array('markdown', 'html'),
                'onlyMainContent' => true
            )),
        ));

        if (is_wp_error($response)) {
            error_log('AI Verify: Firecrawl v1 failed, trying v0: ' . $response->get_error_message());
            // Fallback to v0
            return self::scrape_with_firecrawl_v0($url, $api_key);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200 || empty($body['data'])) {
            error_log('AI Verify: Firecrawl v1 bad response, trying v0');
            return self::scrape_with_firecrawl_v0($url, $api_key);
        }

        $data = $body['data'];
        $metadata = $data['metadata'] ?? array();
        $markdown = $data['markdown'] ?? '';
        $html = $data['html'] ?? '';
        
        // Extract comprehensive metadata with error protection
        $title = $metadata['title'] ?? $metadata['ogTitle'] ?? '';
        $description = $metadata['description'] ?? $metadata['ogDescription'] ?? '';
        $author = $metadata['author'] ?? $metadata['ogSiteName'] ?? '';
        $date = $metadata['publishedTime'] ?? $metadata['modifiedTime'] ?? '';
        $date_modified = $metadata['modifiedTime'] ?? '';
        
        // Get featured image from multiple sources
        $featured_image = '';
        if (!empty($metadata['ogImage'])) {
            $featured_image = is_array($metadata['ogImage']) ? ($metadata['ogImage'][0]['url'] ?? '') : $metadata['ogImage'];
        }
        if (empty($featured_image) && !empty($html)) {
            // Extract from HTML as fallback using metadata extractor (with error protection)
            try {
                if (class_exists('AI_Verify_Metadata_Extractor')) {
                    $extracted = @AI_Verify_Metadata_Extractor::extract_metadata($html, $url);
                    if (!empty($extracted) && is_array($extracted)) {
                        $featured_image = $extracted['featured_image'] ?? '';
                        if (empty($title)) $title = $extracted['title'] ?? '';
                        if (empty($description)) $description = $extracted['description'] ?? '';
                        if (empty($author)) $author = $extracted['author'] ?? '';
                        if (empty($date)) $date = $extracted['date'] ?? '';
                        if (empty($date_modified)) $date_modified = $extracted['date_modified'] ?? '';
                    }
                }
            } catch (Exception $e) {
                error_log('AI Verify: Firecrawl metadata extraction error: ' . $e->getMessage());
            }
        }
        
        // Clean title
        if (!empty($title)) {
            $title = preg_replace('/[\|\-–—]\s*[^|\-–—]*$/', '', $title);
            $title = trim($title);
        }
        
        // Format date
        if (!empty($date)) {
            $date = date('M j, Y', strtotime($date));
        }
        
        $content = self::smart_filter($markdown, $url);
        
        // Get domain and favicon
        $parsed_url = parse_url($url);
        $domain = isset($parsed_url['host']) ? str_replace('www.', '', $parsed_url['host']) : '';
        $favicon = "https://www.google.com/s2/favicons?domain={$domain}&sz=128";
        
        error_log('AI Verify: Firecrawl success - Title: "' . substr($title, 0, 50) . '", Image: ' . ($featured_image ? 'YES' : 'NO'));

        return array(
            'title'   => $title ?: 'Untitled',
            'content' => $content,
            'description' => $description,
            'featured_image' => $featured_image,
            'author' => $author,
            'date' => $date,
            'date_modified' => $date_modified,
            'domain' => $domain,
            'favicon' => $favicon
        );
    }
    
    /**
     * Firecrawl v0 fallback
     */
    private static function scrape_with_firecrawl_v0($url, $api_key) {
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
                'pageOptions' => array(
                    'onlyMainContent' => true
                )
            )),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200 || empty($body['data']['markdown'])) {
            $error_message = isset($body['error']) ? $body['error'] : 'Firecrawl failed';
            return new WP_Error('firecrawl_error', $error_message);
        }

        $content = $body['data']['markdown'];
        $content = self::smart_filter($content, $url);
        
        // Get domain and favicon
        $parsed_url = parse_url($url);
        $domain = isset($parsed_url['host']) ? str_replace('www.', '', $parsed_url['host']) : '';
        $favicon = "https://www.google.com/s2/favicons?domain={$domain}&sz=128";

        return array(
            'title'   => $body['data']['metadata']['title'] ?? 'Untitled',
            'content' => $content,
            'description' => '',
            'featured_image' => '',
            'author' => '',
            'date' => '',
            'domain' => $domain,
            'favicon' => $favicon
        );
    }
    
    /**
     * Jina Reader (free fallback)
     * UPDATED: Now extracts HTML for metadata when possible
     */
    private static function scrape_with_jina($url) {
        error_log('AI Verify: Using Jina Reader for: ' . $url);
        
        $jina_url = 'https://r.jina.ai/' . $url;
        
        $response = wp_remote_get($jina_url, array(
            'timeout' => 30,
            'headers' => array(
                'X-With-Generated-Alt' => 'true',
                'X-Return-Format' => 'html'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status !== 200 || empty($body)) {
            return new WP_Error('scrape_failed', 'Jina Reader failed. Status: ' . $status);
        }
        
        // Try to extract metadata if we got HTML (with error protection)
        $metadata = array(
            'title' => '',
            'description' => '',
            'featured_image' => '',
            'author' => '',
            'date' => '',
            'date_modified' => '',
            'domain' => '',
            'favicon' => ''
        );
        
        try {
            if (class_exists('AI_Verify_Metadata_Extractor') && strpos($body, '<html') !== false) {
                // Jina returned HTML, extract metadata
                $extracted = @AI_Verify_Metadata_Extractor::extract_metadata($body, $url);
                if (!empty($extracted) && is_array($extracted)) {
                    $metadata = array_merge($metadata, $extracted);
                }
                $body = self::html_to_markdown($body);
            } else {
                // Jina returned markdown, extract title from it
                preg_match('/^# (.+)$/m', $body, $title_match);
                $metadata['title'] = !empty($title_match[1]) ? trim($title_match[1]) : self::extract_title_from_url($url);
                
                // Get domain and favicon
                $parsed_url = parse_url($url);
                $metadata['domain'] = isset($parsed_url['host']) ? str_replace('www.', '', $parsed_url['host']) : '';
                $metadata['favicon'] = "https://www.google.com/s2/favicons?domain={$metadata['domain']}&sz=128";
            }
        } catch (Exception $e) {
            error_log('AI Verify: Jina metadata extraction error: ' . $e->getMessage());
            // Use basic title extraction as fallback
            $metadata['title'] = self::extract_title_from_url($url);
        }

        $body = self::smart_filter($body, $url);

        error_log('AI Verify: Jina success - Title: "' . substr($metadata['title'], 0, 50) . '", Image: ' . ($metadata['featured_image'] ? 'YES' : 'NO'));

        return array(
            'title' => $metadata['title'],
            'content' => $body,
            'description' => $metadata['description'],
            'featured_image' => $metadata['featured_image'],
            'author' => $metadata['author'],
            'date' => $metadata['date'],
            'date_modified' => $metadata['date_modified'],
            'domain' => $metadata['domain'],
            'favicon' => $metadata['favicon']
        );
    }
    
    /**
     * SMART FILTER: Fast and effective for all sites with Daily Mail special handling
     */
    private static function smart_filter($markdown, $url) {
        $lines = explode("\n", $markdown);
        $filtered_lines = array();
        $article_started = false;
        $skip_until_article = true;
        $skip_section = false;
        
        // Quick detection: Is this Daily Mail or similar messy site?
        $domain = parse_url($url, PHP_URL_HOST);
        $is_daily_mail = preg_match('/dailymail\.co\.uk/i', $domain);
        $is_messy_site = preg_match('/(dailymail|nypost|thesun|mirror)\./', $domain);
        
        // For Daily Mail - find the first H1 heading (real article starts there)
        if ($is_daily_mail) {
            $found_h1 = false;
            foreach ($lines as $index => $line) {
                $line_trimmed = trim($line);
                
                // Look for first H1 heading (article title)
                if (!$found_h1 && preg_match('/^#\s+[A-Z]/i', $line_trimmed) && strlen($line_trimmed) > 20) {
                    $found_h1 = true;
                    $article_started = true;
                    $skip_until_article = false;
                    $filtered_lines[] = $line;
                    continue;
                }
                
                // Once H1 found, process normally
                if ($found_h1) {
                    // Skip obvious Daily Mail sidebar/recommended junk
                    if (preg_match('/^(TRENDING|Related|Most Read|Recommended|Share this|SHARE SELECTION|Video Quality)/i', $line_trimmed)) {
                        $skip_section = true;
                        continue;
                    }
                    
                    // Skip short lines that look like UI elements
                    if ($skip_section && strlen($line_trimmed) < 80) {
                        continue;
                    }
                    
                    // Resume if we see proper article content
                    if ($skip_section && strlen($line_trimmed) > 120 && preg_match('/[.!?]/', $line_trimmed)) {
                        $skip_section = false;
                    }
                    
                    // Skip all image captions with viewing numbers
                    if (preg_match('/\d+\.?\d*k?\s*viewing\s*now/i', $line_trimmed)) {
                        continue;
                    }
                    
                    // Keep good content
                    if (!$skip_section && !empty($line_trimmed)) {
                        $filtered_lines[] = $line;
                    }
                }
            }
        } else {
            // Original filtering for other sites
            foreach ($lines as $line) {
                $line_trimmed = trim($line);
                
                // Skip empty lines at start
                if (!$article_started && empty($line_trimmed)) {
                    continue;
                }
                
                // For messy sites, skip until we find article content
                if ($is_messy_site && $skip_until_article) {
                    // Look for first substantial paragraph (150+ chars with article words)
                    if (strlen($line_trimmed) > 150 && 
                        preg_match('/\b(the|a|an|said|told|was|were|has|have)\b/i', $line_trimmed) &&
                        preg_match('/[.!?]/', $line_trimmed)) {
                        $skip_until_article = false;
                        $article_started = true;
                        $filtered_lines[] = $line;
                        continue;
                    }
                    continue;
                }
                
                // Article found, now filter inline junk
                if (!$article_started && strlen($line_trimmed) > 80) {
                    $article_started = true;
                }
                
                if (!$article_started) {
                    continue;
                }
                
                // Detect section headers to skip
                if (preg_match('/^(TRENDING|Related|More Stories|Popular|Recommended|Latest|Breaking News)$/i', $line_trimmed)) {
                    $skip_section = true;
                    continue;
                }
                
                // In skip section
                if ($skip_section) {
                    // Check for "viewing now" or very short lines
                    if (preg_match('/\d+\.?\d*k?\s*(viewing|views)/i', $line_trimmed) || strlen($line_trimmed) < 60) {
                        continue;
                    }
                    
                    // Check if article resumed (long line with proper text)
                    if (strlen($line_trimmed) > 120 && preg_match('/[.!?]/', $line_trimmed)) {
                        $skip_section = false;
                        $filtered_lines[] = $line;
                        continue;
                    }
                    continue;
                }
                
                // Skip obvious UI elements
                if (preg_match('/^(Share|Follow|Subscribe|Play|Pause|View gallery|Loading|Top|Home|\d+p|[0-9]{1,2}:[0-9]{2})$/i', $line_trimmed)) {
                    continue;
                }
                
                // Keep good content
                if (!empty($line_trimmed) || $article_started) {
                    $filtered_lines[] = $line;
                }
            }
        }
        
        $filtered = implode("\n", $filtered_lines);
        $filtered = preg_replace('/\n{3,}/', "\n\n", $filtered);
        
        return trim($filtered);
    }
    
    private static function parse_content($scrape_result, $original_url) {
        $title = $scrape_result['title'] ?? 'Untitled';
        $content = $scrape_result['content'] ?? '';
        $description = $scrape_result['description'] ?? '';
        $featured_image = $scrape_result['featured_image'] ?? '';
        $author = $scrape_result['author'] ?? '';
        $date = $scrape_result['date'] ?? '';
        $date_modified = $scrape_result['date_modified'] ?? '';
        $domain = $scrape_result['domain'] ?? '';
        $favicon = $scrape_result['favicon'] ?? '';
        
        // If no domain/favicon, extract from URL
        if (empty($domain)) {
            $parsed_url = parse_url($original_url);
            $domain = isset($parsed_url['host']) ? str_replace('www.', '', $parsed_url['host']) : '';
            $favicon = "https://www.google.com/s2/favicons?domain={$domain}&sz=128";
        }
        
        // If no author/date from scraper, try to extract from content
        if (empty($author)) {
            $author = self::extract_author($content);
        }
        if (empty($date)) {
            $date = self::extract_date($content);
        }
        
        $clean_content = self::clean_markdown($content);
        $paragraphs = self::extract_paragraphs($clean_content);
        $word_count = str_word_count(strip_tags($clean_content));
        
        // Extract excerpt - prefer description from metadata
        $excerpt = $description;
        if (empty($excerpt) && !empty($paragraphs)) {
            $excerpt = mb_substr($paragraphs[0], 0, 200);
            if (mb_strlen($paragraphs[0]) > 200) {
                $excerpt .= '...';
            }
        }
        
        error_log("AI Verify: Final parse - {$word_count} words, Title: '" . substr($title, 0, 50) . "', Image: " . ($featured_image ? 'YES' : 'NO') . ", Desc: " . ($description ? 'YES' : 'NO'));
        
        return array(
            'success' => true,
            'title' => $title,
            'content' => $clean_content,
            'paragraphs' => $paragraphs,
            'author' => $author,
            'date' => $date,
            'date_modified' => $date_modified,
            'word_count' => $word_count,
            'excerpt' => $excerpt,
            'description' => $description,
            'featured_image' => $featured_image,
            'domain' => $domain,
            'favicon' => $favicon,
            'url' => $original_url,
            'scraped_at' => current_time('mysql')
        );
    }
    
    private static function extract_title_from_url($url) {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '';
        $title = basename($path);
        $title = str_replace(array('-', '_'), ' ', $title);
        return ucwords($title);
    }
    
    private static function extract_author($markdown) {
        $patterns = array('/By ([A-Z][a-z]+ [A-Z][a-z]+)/i', '/Author: ([A-Z][a-z]+ [A-Z][a-z]+)/i');
        foreach ($patterns as $pattern) { 
            if (preg_match($pattern, $markdown, $match)) { 
                return trim($match[1]); 
            } 
        }
        return null;
    }
    
    private static function extract_date($markdown) {
        $patterns = array('/Published: (\d{4}-\d{2}-\d{2})/', '/(\d{1,2}\/\d{1,2}\/\d{4})/', '/(\w+ \d{1,2}, \d{4})/');
        foreach ($patterns as $pattern) { 
            if (preg_match($pattern, $markdown, $match)) { 
                return trim($match[1]); 
            } 
        }
        return null;
    }
    
    private static function clean_markdown($markdown) {
        $markdown = preg_replace('/\[.*?\]\(.*?\)/s', '', $markdown);
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        return trim($markdown);
    }
    
    private static function extract_paragraphs($content) {
        $parts = explode("\n\n", $content);
        $paragraphs = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) > 100 && !preg_match('/^#+/', $part)) {
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