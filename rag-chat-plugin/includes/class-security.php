<?php
/**
 * Security class for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Security Class
 */
class RAG_Chat_Security {

    /**
     * Verify nonce for AJAX requests
     *
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool Valid nonce
     */
    public static function verify_nonce($nonce, $action = 'rag_chat_nonce') {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Verify admin nonce
     *
     * @param string $nonce Nonce value
     * @return bool Valid nonce
     */
    public static function verify_admin_nonce($nonce) {
        return wp_verify_nonce($nonce, 'rag_chat_admin_nonce');
    }

    /**
     * Sanitize chat message
     *
     * @param string $message User message
     * @return string Sanitized message
     */
    public static function sanitize_message($message) {
        // Remove HTML tags
        $message = wp_strip_all_tags($message);
        
        // Sanitize text
        $message = sanitize_textarea_field($message);
        
        // Trim and normalize whitespace
        $message = trim(preg_replace('/\s+/', ' ', $message));
        
        // Limit length
        $max_length = apply_filters('rag_chat_max_message_length', 1000);
        if (strlen($message) > $max_length) {
            $message = substr($message, 0, $max_length);
        }
        
        return $message;
    }

    /**
     * Sanitize API key
     *
     * @param string $api_key API key
     * @return string Sanitized API key
     */
    public static function sanitize_api_key($api_key) {
        // Remove whitespace and special characters
        $api_key = preg_replace('/[^a-zA-Z0-9_-]/', '', $api_key);
        
        // Trim
        $api_key = trim($api_key);
        
        return $api_key;
    }

    /**
     * Encrypt API key for storage
     *
     * @param string $api_key Plain API key
     * @return string Encrypted API key
     */
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }

        // Use WordPress salts for encryption
        $key = wp_salt('auth');
        $iv = wp_salt('secure_auth');
        
        // Pad IV to required length
        $iv = substr(hash('sha256', $iv), 0, 16);
        
        // Encrypt using AES-256-CBC
        $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($encrypted);
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Decrypted API key
     */
    public static function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        try {
            // Use WordPress salts for decryption
            $key = wp_salt('auth');
            $iv = wp_salt('secure_auth');
            
            // Pad IV to required length
            $iv = substr(hash('sha256', $iv), 0, 16);
            
            // Decrypt
            $decrypted = openssl_decrypt(base64_decode($encrypted_key), 'AES-256-CBC', $key, 0, $iv);
            
            return $decrypted !== false ? $decrypted : '';
        } catch (Exception $e) {
            error_log('RAG Chat Plugin: Error decrypting API key: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool Valid format
     */
    public static function is_valid_api_key_format($api_key) {
        // Gemini API keys typically start with 'AIza' and are 39 characters long
        return preg_match('/^AIza[a-zA-Z0-9_-]{35}$/', $api_key);
    }

    /**
     * Get user IP address
     *
     * @return string IP address
     */
    public static function get_user_ip() {
        $ip = '';
        
        // Check for various headers
        $headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Take the first IP if there are multiple
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }
        
        // Validate IP address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
        
        // Fallback to REMOTE_ADDR
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Generate session ID
     *
     * @return string Session ID
     */
    public static function generate_session_id() {
        return hash('sha256', uniqid(wp_rand(), true) . time() . self::get_user_ip());
    }

    /**
     * Rate limiting check
     *
     * @param string $ip IP address
     * @param int $limit Requests per minute
     * @return bool Within rate limit
     */
    public static function check_rate_limit($ip, $limit = 10) {
        $transient_key = 'rag_chat_rate_limit_' . md5($ip);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request
            set_transient($transient_key, 1, 60); // 1 minute
            return true;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, 60);
        return true;
    }

    /**
     * Sanitize URL
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    public static function sanitize_url($url) {
        return esc_url_raw($url);
    }

    /**
     * Validate content type
     *
     * @param string $content_type Content type
     * @return bool Valid content type
     */
    public static function is_valid_content_type($content_type) {
        $allowed_types = array('post', 'page', 'product', 'custom');
        return in_array($content_type, $allowed_types, true);
    }

    /**
     * Sanitize HTML content
     *
     * @param string $content HTML content
     * @return string Sanitized content
     */
    public static function sanitize_html_content($content) {
        // Allow basic HTML tags
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'blockquote' => array(),
            'code' => array(),
            'pre' => array(),
        );
        
        return wp_kses($content, $allowed_tags);
    }

    /**
     * Validate and sanitize settings array
     *
     * @param array $settings Settings array
     * @return array Sanitized settings
     */
    public static function sanitize_settings($settings) {
        $sanitized = array();
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'rag_chat_gemini_api_key':
                    $sanitized[$key] = self::sanitize_api_key($value);
                    break;
                
                case 'rag_chat_enabled':
                case 'rag_chat_auto_scrape':
                    $sanitized[$key] = (bool) $value;
                    break;
                
                case 'rag_chat_max_response_length':
                case 'rag_chat_scrape_frequency_hours':
                    $sanitized[$key] = absint($value);
                    break;
                
                case 'rag_chat_temperature':
                    $sanitized[$key] = floatval($value);
                    $sanitized[$key] = max(0, min(2, $sanitized[$key])); // Clamp between 0 and 2
                    break;
                
                case 'rag_chat_position':
                    $allowed_positions = array('bottom-right', 'bottom-left', 'top-right', 'top-left');
                    $sanitized[$key] = in_array($value, $allowed_positions) ? $value : 'bottom-right';
                    break;
                
                case 'rag_chat_theme':
                    $allowed_themes = array('default', 'dark', 'light', 'minimal');
                    $sanitized[$key] = in_array($value, $allowed_themes) ? $value : 'default';
                    break;
                
                case 'rag_chat_content_types':
                    if (is_array($value)) {
                        $sanitized[$key] = array_filter($value, array(self::class, 'is_valid_content_type'));
                    } else {
                        $sanitized[$key] = array('post', 'page');
                    }
                    break;
                
                case 'rag_chat_system_prompt':
                case 'rag_chat_greeting':
                case 'rag_chat_placeholder':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                
                default:
                    // For unknown keys, use basic sanitization
                    if (is_string($value)) {
                        $sanitized[$key] = sanitize_text_field($value);
                    } else {
                        $sanitized[$key] = $value;
                    }
                    break;
            }
        }
        
        return $sanitized;
    }

    /**
     * Check if user has required capabilities
     *
     * @param string $capability Required capability
     * @return bool User has capability
     */
    public static function user_can($capability = 'manage_options') {
        return current_user_can($capability);
    }

    /**
     * Log security event
     *
     * @param string $event Event description
     * @param array $context Additional context
     */
    public static function log_security_event($event, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'ip' => self::get_user_ip(),
            'user_id' => get_current_user_id(),
            'event' => $event,
            'context' => $context,
            'user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        );
        
        error_log('RAG Chat Security: ' . json_encode($log_entry));
        
        // Also store in database for serious events
        $serious_events = array('rate_limit_exceeded', 'invalid_api_key', 'unauthorized_access');
        if (in_array($event, $serious_events)) {
            // Could implement database logging here
        }
    }

    /**
     * Validate AJAX request
     *
     * @param string $action Action name
     * @param bool $require_auth Require user authentication
     * @return bool Valid request
     */
    public static function validate_ajax_request($action, $require_auth = false) {
        // Check if it's an AJAX request
        if (!wp_doing_ajax()) {
            self::log_security_event('non_ajax_request', array('action' => $action));
            return false;
        }
        
        // Check nonce
        $nonce = !empty($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!self::verify_nonce($nonce)) {
            self::log_security_event('invalid_nonce', array('action' => $action));
            return false;
        }
        
        // Check rate limit
        $ip = self::get_user_ip();
        if (!self::check_rate_limit($ip)) {
            self::log_security_event('rate_limit_exceeded', array('action' => $action, 'ip' => $ip));
            return false;
        }
        
        // Check authentication if required
        if ($require_auth && !is_user_logged_in()) {
            self::log_security_event('unauthenticated_request', array('action' => $action));
            return false;
        }
        
        return true;
    }
}
