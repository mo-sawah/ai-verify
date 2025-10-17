<?php
/**
 * Deepfake Detector System
 * Integrates Reality Defender API for enterprise-grade deepfake detection
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Deepfake_Detector {
    
    public static function init() {
        // Register shortcodes
        add_shortcode('ai_deepfake_detector', array(__CLASS__, 'render_detector'));
        
        // Initialize AJAX handlers
        add_action('wp_ajax_ai_verify_detect_deepfake', array(__CLASS__, 'detect_deepfake'));
        add_action('wp_ajax_nopriv_ai_verify_detect_deepfake', array(__CLASS__, 'detect_deepfake'));
        
        add_action('wp_ajax_ai_verify_get_detection_history', array(__CLASS__, 'get_detection_history'));
        add_action('wp_ajax_nopriv_ai_verify_get_detection_history', array(__CLASS__, 'get_detection_history'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        
        // Create database tables
        self::create_tables();
    }
    
    /**
     * Create database tables for detection history
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_deepfake_detections';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            detection_id varchar(255) NOT NULL,
            file_type varchar(10) NOT NULL,
            file_name varchar(255) DEFAULT NULL,
            file_url text DEFAULT NULL,
            detection_score float DEFAULT NULL,
            is_deepfake tinyint(1) DEFAULT 0,
            confidence_level varchar(20) DEFAULT NULL,
            analysis_details longtext DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            user_email varchar(255) DEFAULT NULL,
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY detection_id (detection_id),
            KEY detected_at (detected_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue assets
     */
    public static function enqueue_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_deepfake_detector')) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ai-verify-deepfake',
            AI_VERIFY_PLUGIN_URL . 'assets/css/deepfake-detector.css',
            array(),
            AI_VERIFY_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'ai-verify-deepfake',
            AI_VERIFY_PLUGIN_URL . 'assets/js/deepfake-detector.js',
            array('jquery'),
            AI_VERIFY_VERSION,
            true
        );
        
        wp_localize_script('ai-verify-deepfake', 'aiVerifyDeepfake', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_verify_deepfake_nonce'),
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_types' => array('image/jpeg', 'image/png', 'image/webp', 'audio/mpeg', 'audio/wav', 'audio/ogg')
        ));
    }
    
    /**
     * Render detector interface
     */
    public static function render_detector($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => 'Deepfake Detection Tool',
            'subtitle' => 'Upload media or provide a URL to detect AI-generated content and deepfakes',
            'show_history' => 'yes'
        ), $atts);
        
        ob_start();
        include AI_VERIFY_PLUGIN_DIR . 'templates/deepfake-detector.php';
        return ob_get_clean();
    }
    
    public static function detect_deepfake() {
        check_ajax_referer('ai_verify_deepfake_nonce', 'nonce');
        
        $api_key = get_option('ai_verify_reality_defender_key');
        
        if (empty($api_key)) {
            error_log('AI Verify: Reality Defender API key not configured');
            wp_send_json_error(array(
                'message' => 'Reality Defender API key not configured. Please add your API key in Settings > AI Verify.'
            ));
            return;
        }
        
        // Get input type
        $input_type = sanitize_text_field($_POST['input_type']); // 'file' or 'url'
        
        error_log('AI Verify: Starting detection - Input type: ' . $input_type);
        
        if ($input_type === 'file') {
            // Handle file upload
            if (empty($_FILES['media_file'])) {
                wp_send_json_error(array('message' => 'No file uploaded'));
                return;
            }
            
            $result = self::detect_from_file($_FILES['media_file'], $api_key);
        } else {
            // Handle URL
            $media_url = esc_url_raw($_POST['media_url']);
            
            if (empty($media_url)) {
                wp_send_json_error(array('message' => 'No URL provided'));
                return;
            }
            
            $result = self::detect_from_url($media_url, $api_key);
        }
        
        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            
            error_log('AI Verify: Detection failed - Code: ' . $error_code . ' | Message: ' . $error_message);
            
            wp_send_json_error(array(
                'message' => $error_message,
                'code' => $error_code
            ));
        } else {
            error_log('AI Verify: Detection successful');
            wp_send_json_success($result);
        }
    }
    
    /**
     * Detect deepfake from uploaded file
     */
    private static function detect_from_file($file, $api_key) {
        // Validate file
        $allowed_types = array('image/jpeg', 'image/png', 'image/webp', 'audio/mpeg', 'audio/wav', 'audio/ogg');
        
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file', 'Invalid file type. Only images and audio files are supported.');
        }
        
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'File size exceeds 10MB limit');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload failed');
        }
        
        // Determine media type
        $media_type = strpos($file['type'], 'image') !== false ? 'image' : 'audio';
        
        // Call Reality Defender API
        $result = self::call_reality_defender_api($file['tmp_name'], $file['name'], $media_type, $api_key);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Save to database
        self::save_detection_result($result, $file['name'], null, $media_type);
        
        return $result;
    }
    
    /**
     * Detect deepfake from URL
     */
    private static function detect_from_url($url, $api_key) {
        // Download file temporarily
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            return new WP_Error('download_failed', 'Failed to download media from URL');
        }
        
        // Determine file type
        $file_type = wp_check_filetype($temp_file);
        $mime_type = $file_type['type'];
        
        if (empty($mime_type)) {
            @unlink($temp_file);
            return new WP_Error('invalid_url', 'Could not determine file type from URL');
        }
        
        // Determine media type
        $media_type = strpos($mime_type, 'image') !== false ? 'image' : 'audio';
        
        // Call Reality Defender API
        $result = self::call_reality_defender_api($temp_file, basename($url), $media_type, $api_key);
        
        // Clean up temp file
        @unlink($temp_file);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Save to database
        self::save_detection_result($result, basename($url), $url, $media_type);
        
        return $result;
    }
    
    /**
     * Call Reality Defender API - BETTER VERSION using wp_remote_post properly
     */
    private static function call_reality_defender_api($file_path, $file_name, $media_type, $api_key) {
        error_log('AI Verify: Starting Reality Defender detection for: ' . $file_name);
        
        $api_url = 'https://api.realitydefender.com/media/';
        
        // Use cURL directly since WordPress doesn't handle multipart well
        if (!function_exists('curl_init')) {
            return new WP_Error('no_curl', 'cURL is required but not available on this server');
        }
        
        $ch = curl_init();
        
        // Prepare the file for upload
        $cfile = new CURLFile($file_path, mime_content_type($file_path), $file_name);
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' . $api_key
            ),
            CURLOPT_POSTFIELDS => array(
                'file' => $cfile
            ),
            CURLOPT_TIMEOUT => 60
        ));
        
        $response_body = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log('AI Verify: cURL error - ' . $error);
            return new WP_Error('curl_error', 'Connection error: ' . $error);
        }
        
        error_log('AI Verify: Response status: ' . $status_code);
        error_log('AI Verify: Response: ' . substr($response_body, 0, 500));
        
        if ($status_code !== 200 && $status_code !== 201) {
            $data = json_decode($response_body, true);
            $message = isset($data['detail']) ? $data['detail'] : 'API error (Status: ' . $status_code . ')';
            return new WP_Error('api_error', $message);
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data) {
            return new WP_Error('json_error', 'Invalid API response');
        }
        
        return self::parse_detection_response($data, $media_type);
    }
    
    /**
     * Parse Reality Defender API response
     * Based on their actual response structure
     */
    private static function parse_detection_response($response, $media_type) {
        error_log('AI Verify: Parsing detection response');
        
        // Reality Defender returns: { "media_id": "xxx", "status": "processing" or "complete", "results": {...} }
        
        $detection_id = isset($response['media_id']) ? $response['media_id'] : uniqid('detection_');
        
        // Check if results are available
        if (isset($response['status']) && $response['status'] === 'processing') {
            // If still processing, we need to poll for results
            // For now, return a placeholder
            return new WP_Error('processing', 'Media is still being processed. This may take a few moments.');
        }
        
        // Extract results based on Reality Defender's response format
        // According to docs, they return: { "deepfake_probability": 0.0-1.0, "label": "real" or "fake" }
        $results = isset($response['results']) ? $response['results'] : $response;
        
        $deepfake_probability = isset($results['deepfake_probability']) ? floatval($results['deepfake_probability']) : 0;
        $score = $deepfake_probability * 100; // Convert to percentage
        
        $is_deepfake = $score >= 50; // Threshold: 50% or higher = likely deepfake
        
        // Determine confidence level based on how far from threshold
        $distance_from_threshold = abs($score - 50);
        if ($distance_from_threshold >= 40) {
            $confidence = 'very_high';
        } elseif ($distance_from_threshold >= 30) {
            $confidence = 'high';
        } elseif ($distance_from_threshold >= 20) {
            $confidence = 'medium';
        } elseif ($distance_from_threshold >= 10) {
            $confidence = 'low';
        } else {
            $confidence = 'very_low';
        }
        
        // Extract model information if available
        $models_used = array('Reality Defender Multi-Model');
        if (isset($results['model_version'])) {
            $models_used[] = 'Model Version: ' . $results['model_version'];
        }
        
        $manipulation_types = array();
        if (isset($results['manipulation_types']) && is_array($results['manipulation_types'])) {
            $manipulation_types = $results['manipulation_types'];
        }
        
        // Build analysis details
        $analysis = array(
            'detection_method' => 'Reality Defender Multi-Model',
            'models_used' => $models_used,
            'manipulation_types' => $manipulation_types,
            'regions_analyzed' => isset($results['regions']) ? $results['regions'] : array('Full content analysis'),
            'artifacts_detected' => isset($results['artifacts']) ? $results['artifacts'] : array()
        );
        
        return array(
            'detection_id' => $detection_id,
            'media_type' => $media_type,
            'is_deepfake' => $is_deepfake,
            'detection_score' => round($score, 2),
            'confidence_level' => $confidence,
            'verdict' => self::get_verdict($score),
            'analysis' => $analysis,
            'recommendations' => self::get_recommendations($score, $is_deepfake),
            'timestamp' => current_time('mysql'),
            'raw_response' => $results // Keep for debugging
        );
    }
    
    /**
     * Get human-readable verdict
     */
    private static function get_verdict($score) {
        if ($score >= 80) {
            return 'Highly Likely Deepfake';
        } elseif ($score >= 60) {
            return 'Likely Deepfake';
        } elseif ($score >= 40) {
            return 'Possibly Manipulated';
        } elseif ($score >= 20) {
            return 'Likely Authentic';
        } else {
            return 'Highly Likely Authentic';
        }
    }
    
    /**
     * Get recommendations based on detection
     */
    private static function get_recommendations($score, $is_deepfake) {
        $recommendations = array();
        
        if ($is_deepfake) {
            $recommendations[] = 'Do not trust this media as authentic';
            $recommendations[] = 'Verify through alternative sources';
            $recommendations[] = 'Report if used for fraud or misinformation';
            
            if ($score >= 80) {
                $recommendations[] = 'Strong evidence of AI manipulation detected';
            }
        } else {
            $recommendations[] = 'Media appears to be authentic';
            
            if ($score <= 20) {
                $recommendations[] = 'No significant signs of manipulation detected';
            } else {
                $recommendations[] = 'Minor inconsistencies detected - verify if critical';
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Save detection result to database
     */
    private static function save_detection_result($result, $file_name, $file_url, $media_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_verify_deepfake_detections';
        
        $wpdb->insert(
            $table_name,
            array(
                'detection_id' => $result['detection_id'],
                'file_type' => $media_type,
                'file_name' => $file_name,
                'file_url' => $file_url,
                'detection_score' => $result['detection_score'],
                'is_deepfake' => $result['is_deepfake'] ? 1 : 0,
                'confidence_level' => $result['confidence_level'],
                'analysis_details' => json_encode($result['analysis']),
                'user_ip' => self::get_user_ip(),
                'detected_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get detection history
     */
    public static function get_detection_history() {
        check_ajax_referer('ai_verify_deepfake_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_verify_deepfake_detections';
        
        $limit = intval($_POST['limit']) ?: 10;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY detected_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        // Parse analysis details
        foreach ($results as &$result) {
            $result['analysis'] = json_decode($result['analysis_details'], true);
            unset($result['analysis_details']);
        }
        
        wp_send_json_success(array('history' => $results));
    }
    
    /**
     * Get user IP
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
        return sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
}