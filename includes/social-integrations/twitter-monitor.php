<?php
/**
 * Twitter Monitoring via TwitterAPI.io
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Twitter_Monitor {
    
    private static $cache_duration = 900; // 15 minutes
    
    /**
     * Search Twitter for claims
     */
    public static function search_claims($keywords, $limit = 20) {
        $api_key = get_option('ai_verify_twitter_api_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: TwitterAPI.io key not configured');
            return array();
        }
        
        $cache_key = 'ai_verify_twitter_search_' . md5($keywords);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // TwitterAPI.io endpoint
        $url = 'https://api.twitterapi.io/v2/search';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'query' => $keywords,
                'max_results' => $limit,
                'tweet_fields' => 'created_at,public_metrics,entities'
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Verify Twitter: ' . $response->get_error_message());
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']) || !is_array($body['data'])) {
            return array();
        }
        
        $tweets = array();
        
        foreach ($body['data'] as $tweet) {
            $tweets[] = array(
                'claim' => $tweet['text'] ?? '',
                'source' => 'Twitter',
                'url' => 'https://twitter.com/i/web/status/' . $tweet['id'],
                'date' => $tweet['created_at'] ?? '',
                'engagement' => $tweet['public_metrics']['retweet_count'] ?? 0,
                'platform' => 'twitter',
                'metadata' => array(
                    'likes' => $tweet['public_metrics']['like_count'] ?? 0,
                    'retweets' => $tweet['public_metrics']['retweet_count'] ?? 0,
                    'replies' => $tweet['public_metrics']['reply_count'] ?? 0
                )
            );
        }
        
        set_transient($cache_key, $tweets, self::$cache_duration);
        
        return $tweets;
    }
    
    /**
     * Monitor trending misinfo topics on Twitter
     */
    public static function monitor_trending_topics() {
        $topics = array(
            'vaccine misinformation',
            'election fraud',
            'climate hoax',
            'fake news',
            'conspiracy theory'
        );
        
        $all_tweets = array();
        
        foreach ($topics as $topic) {
            $tweets = self::search_claims($topic, 10);
            $all_tweets = array_merge($all_tweets, $tweets);
            
            usleep(500000); // 0.5s delay between requests
        }
        
        return $all_tweets;
    }
    
    /**
     * Track a specific claim on Twitter
     */
    public static function track_claim($claim_text) {
        // Extract key phrases from claim (max 3 words)
        $words = explode(' ', $claim_text);
        $keywords = implode(' ', array_slice($words, 0, 3));
        
        return self::search_claims($keywords, 50);
    }
}