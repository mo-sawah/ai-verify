<?php
/**
 * Metadata Extractor for Fact-Check System
 * Extracts title, description, featured image, author, date, and favicon from HTML
 * Uses native PHP parsing - no external dependencies
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Metadata_Extractor {
    
    /**
     * Extract all metadata from HTML content
     * @param string $html The HTML content
     * @param string $url The source URL
     * @return array Metadata array
     */
    public static function extract_metadata($html, $url) {
        $metadata = array(
            'title' => '',
            'description' => '',
            'featured_image' => '',
            'author' => '',
            'date' => '',
            'date_modified' => '',
            'favicon' => '',
            'domain' => '',
            'site_name' => ''
        );
        
        // Parse URL for domain and favicon
        $parsed_url = parse_url($url);
        if (isset($parsed_url['host'])) {
            $metadata['domain'] = str_replace('www.', '', $parsed_url['host']);
            $metadata['favicon'] = "https://www.google.com/s2/favicons?domain=" . $metadata['domain'] . "&sz=128";
        }
        
        // Extract OpenGraph and Twitter Card metadata
        $og_data = self::extract_open_graph($html);
        $twitter_data = self::extract_twitter_card($html);
        $json_ld = self::extract_json_ld($html);
        
        // Priority order: OpenGraph > Twitter Card > Standard HTML > JSON-LD
        
        // Title
        $metadata['title'] = $og_data['title'] 
            ?: $twitter_data['title'] 
            ?: self::extract_html_title($html)
            ?: $json_ld['headline']
            ?: 'Untitled';
        
        // Description
        $metadata['description'] = $og_data['description']
            ?: $twitter_data['description']
            ?: self::extract_meta_description($html)
            ?: $json_ld['description']
            ?: '';
        
        // Featured Image
        $metadata['featured_image'] = $og_data['image']
            ?: $twitter_data['image']
            ?: $json_ld['image']
            ?: '';
        
        // Make image URL absolute if relative
        if (!empty($metadata['featured_image']) && !preg_match('/^https?:\/\//i', $metadata['featured_image'])) {
            $metadata['featured_image'] = self::make_absolute_url($metadata['featured_image'], $url);
        }
        
        // Author
        $metadata['author'] = $og_data['author']
            ?: self::extract_author($html)
            ?: $json_ld['author']
            ?: '';
        
        // Publication Date
        $metadata['date'] = $og_data['published_time']
            ?: $json_ld['datePublished']
            ?: self::extract_date($html)
            ?: '';
        
        // Modified Date
        $metadata['date_modified'] = $og_data['modified_time']
            ?: $json_ld['dateModified']
            ?: '';
        
        // Site Name
        $metadata['site_name'] = $og_data['site_name']
            ?: $metadata['domain']
            ?: '';
        
        error_log("AI Verify Metadata: Title='" . substr($metadata['title'], 0, 50) . "', Image=" . ($metadata['featured_image'] ? 'YES' : 'NO') . ", Desc=" . ($metadata['description'] ? 'YES' : 'NO'));
        
        return $metadata;
    }
    
    /**
     * Extract OpenGraph metadata
     */
    private static function extract_open_graph($html) {
        $data = array(
            'title' => '',
            'description' => '',
            'image' => '',
            'author' => '',
            'published_time' => '',
            'modified_time' => '',
            'site_name' => ''
        );
        
        // Match og:title
        if (preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['title'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Match og:description
        if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['description'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Match og:image
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['image'] = $match[1];
        }
        
        // Match article:author
        if (preg_match('/<meta[^>]*property=["\']article:author["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['author'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Match article:published_time
        if (preg_match('/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['published_time'] = $match[1];
        }
        
        // Match article:modified_time
        if (preg_match('/<meta[^>]*property=["\']article:modified_time["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['modified_time'] = $match[1];
        }
        
        // Match og:site_name
        if (preg_match('/<meta[^>]*property=["\']og:site_name["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['site_name'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return $data;
    }
    
    /**
     * Extract Twitter Card metadata
     */
    private static function extract_twitter_card($html) {
        $data = array(
            'title' => '',
            'description' => '',
            'image' => ''
        );
        
        // Match twitter:title
        if (preg_match('/<meta[^>]*name=["\']twitter:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['title'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Match twitter:description
        if (preg_match('/<meta[^>]*name=["\']twitter:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['description'] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Match twitter:image
        if (preg_match('/<meta[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $data['image'] = $match[1];
        }
        
        return $data;
    }
    
    /**
     * Extract JSON-LD structured data
     */
    private static function extract_json_ld($html) {
        $data = array(
            'headline' => '',
            'description' => '',
            'image' => '',
            'author' => '',
            'datePublished' => '',
            'dateModified' => ''
        );
        
        // Find all JSON-LD scripts
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json_string) {
                $json = json_decode($json_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    // Handle @graph arrays
                    $items = array();
                    if (isset($json['@graph']) && is_array($json['@graph'])) {
                        $items = $json['@graph'];
                    } else {
                        $items = array($json);
                    }
                    
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        
                        $type = $item['@type'] ?? '';
                        
                        // Look for Article, NewsArticle, BlogPosting types
                        if (in_array($type, array('Article', 'NewsArticle', 'BlogPosting', 'WebPage'))) {
                            if (empty($data['headline']) && isset($item['headline'])) {
                                $data['headline'] = $item['headline'];
                            }
                            if (empty($data['description']) && isset($item['description'])) {
                                $data['description'] = $item['description'];
                            }
                            if (empty($data['image']) && isset($item['image'])) {
                                $image = $item['image'];
                                if (is_array($image)) {
                                    $data['image'] = $image['url'] ?? $image[0] ?? '';
                                } else {
                                    $data['image'] = $image;
                                }
                            }
                            if (empty($data['author']) && isset($item['author'])) {
                                $author = $item['author'];
                                if (is_array($author)) {
                                    $data['author'] = $author['name'] ?? '';
                                } else {
                                    $data['author'] = $author;
                                }
                            }
                            if (empty($data['datePublished']) && isset($item['datePublished'])) {
                                $data['datePublished'] = $item['datePublished'];
                            }
                            if (empty($data['dateModified']) && isset($item['dateModified'])) {
                                $data['dateModified'] = $item['dateModified'];
                            }
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Extract title from HTML <title> tag
     */
    private static function extract_html_title($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
    
    /**
     * Extract description from meta tag
     */
    private static function extract_meta_description($html) {
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
    
    /**
     * Extract author from meta tags or content
     */
    private static function extract_author($html) {
        // Try meta author tag
        if (preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Try rel="author" link
        if (preg_match('/<a[^>]*rel=["\']author["\'][^>]*>(.*?)<\/a>/i', $html, $match)) {
            return html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // Try byline patterns
        if (preg_match('/By\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i', $html, $match)) {
            return trim($match[1]);
        }
        
        return '';
    }
    
    /**
     * Extract publication date
     */
    private static function extract_date($html) {
        // Try time tag with datetime attribute
        if (preg_match('/<time[^>]*datetime=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            return $match[1];
        }
        
        // Try meta date tags
        $date_metas = array('datePublished', 'publishdate', 'publish_date', 'date');
        foreach ($date_metas as $meta) {
            if (preg_match('/<meta[^>]*(?:name|property)=["\']' . $meta . '["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
                return $match[1];
            }
        }
        
        return '';
    }
    
    /**
     * Convert relative URL to absolute
     */
    private static function make_absolute_url($relative_url, $base_url) {
        // If it's already absolute, return it
        if (preg_match('/^https?:\/\//i', $relative_url)) {
            return $relative_url;
        }
        
        $parsed = parse_url($base_url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        
        // Protocol-relative URL
        if (substr($relative_url, 0, 2) === '//') {
            return $scheme . ':' . $relative_url;
        }
        
        // Absolute path
        if (substr($relative_url, 0, 1) === '/') {
            return $scheme . '://' . $host . $relative_url;
        }
        
        // Relative path
        $path = $parsed['path'] ?? '';
        $path = substr($path, 0, strrpos($path, '/') + 1);
        
        return $scheme . '://' . $host . $path . $relative_url;
    }
}