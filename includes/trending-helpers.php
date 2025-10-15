<?php
/**
 * Helper Functions for Trending Page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize rating for CSS class
 */
if (!function_exists('ai_verify_sanitize_rating')) {
    function ai_verify_sanitize_rating($rating) {
        $rating_lower = strtolower($rating);
        if (strpos($rating_lower, 'false') !== false && strpos($rating_lower, 'mostly') === false) return 'false';
        if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'mostly') === false) return 'true';
        if (strpos($rating_lower, 'mostly true') !== false) return 'mostly-true';
        if (strpos($rating_lower, 'mostly false') !== false) return 'mostly-false';
        if (strpos($rating_lower, 'misleading') !== false || strpos($rating_lower, 'mixture') !== false) return 'misleading';
        return 'unknown';
    }
}

/**
 * Get rating icon emoji
 */
if (!function_exists('ai_verify_get_rating_icon')) {
    function ai_verify_get_rating_icon($rating) {
        $rating_lower = strtolower($rating);
        if (strpos($rating_lower, 'false') !== false && strpos($rating_lower, 'mostly') === false) return '❌';
        if (strpos($rating_lower, 'true') !== false && strpos($rating_lower, 'mostly') === false) return '✅';
        if (strpos($rating_lower, 'mostly true') !== false) return '✓';
        if (strpos($rating_lower, 'mostly false') !== false) return '✗';
        if (strpos($rating_lower, 'misleading') !== false || strpos($rating_lower, 'mixture') !== false) return '⚠️';
        return '❓';
    }
}