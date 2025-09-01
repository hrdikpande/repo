<?php
/**
 * Rate limiter class for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Rate Limiter Class
 */
class RAG_Chat_Rate_Limiter {

    /**
     * Default rate limit (requests per minute)
     *
     * @var int
     */
    private $default_limit = 10;

    /**
     * Cache instance
     *
     * @var RAG_Chat_Cache
     */
    private $cache;

    /**
     * Logger instance
     *
     * @var RAG_Chat_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->default_limit = get_option('rag_chat_rate_limit', 10);
        $this->cache = new RAG_Chat_Cache();
        $this->logger = new RAG_Chat_Logger();
    }

    /**
     * Check if request is within rate limit
     *
     * @param string $identifier User identifier (IP, user ID, etc.)
     * @param string $action Action being rate limited
     * @param int $limit Custom limit for this action
     * @return bool Within rate limit
     */
    public function check_rate_limit($identifier, $action = 'default', $limit = null) {
        if ($limit === null) {
            $limit = $this->default_limit;
        }

        $cache_key = $this->get_rate_limit_key($identifier, $action);
        $current_time = time();
        $window_start = $current_time - 60; // 1 minute window

        // Get current requests
        $requests = $this->cache->get($cache_key, array());

        // Filter out old requests
        $requests = array_filter($requests, function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });

        // Check if limit exceeded
        if (count($requests) >= $limit) {
            $this->logger->warning('Rate limit exceeded', array(
                'identifier' => $identifier,
                'action' => $action,
                'limit' => $limit,
                'current' => count($requests)
            ));
            return false;
        }

        // Add current request
        $requests[] = $current_time;

        // Cache for 2 minutes to ensure we don't lose data
        $this->cache->set($cache_key, $requests, 120);

        return true;
    }

    /**
     * Get rate limit key
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @return string Cache key
     */
    private function get_rate_limit_key($identifier, $action) {
        return "rate_limit_{$action}_" . md5($identifier);
    }

    /**
     * Get remaining requests for user
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @param int $limit Custom limit
     * @return int Remaining requests
     */
    public function get_remaining_requests($identifier, $action = 'default', $limit = null) {
        if ($limit === null) {
            $limit = $this->default_limit;
        }

        $cache_key = $this->get_rate_limit_key($identifier, $action);
        $current_time = time();
        $window_start = $current_time - 60;

        $requests = $this->cache->get($cache_key, array());
        $requests = array_filter($requests, function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });

        return max(0, $limit - count($requests));
    }

    /**
     * Get rate limit info for user
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @param int $limit Custom limit
     * @return array Rate limit info
     */
    public function get_rate_limit_info($identifier, $action = 'default', $limit = null) {
        if ($limit === null) {
            $limit = $this->default_limit;
        }

        $current_time = time();
        $window_start = $current_time - 60;
        $cache_key = $this->get_rate_limit_key($identifier, $action);

        $requests = $this->cache->get($cache_key, array());
        $requests = array_filter($requests, function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });

        $used = count($requests);
        $remaining = max(0, $limit - $used);
        $reset_time = $current_time + (60 - ($current_time % 60));

        return array(
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'reset_time' => $reset_time,
            'window_start' => $window_start,
            'window_end' => $current_time
        );
    }

    /**
     * Reset rate limit for user
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @return bool Success
     */
    public function reset_rate_limit($identifier, $action = 'default') {
        $cache_key = $this->get_rate_limit_key($identifier, $action);
        return $this->cache->delete($cache_key);
    }

    /**
     * Check rate limit for chat message
     *
     * @param string $session_id Session ID
     * @return bool Within rate limit
     */
    public function check_chat_rate_limit($session_id) {
        $identifier = $this->get_chat_identifier($session_id);
        return $this->check_rate_limit($identifier, 'chat_message', 5); // 5 messages per minute
    }

    /**
     * Check rate limit for API requests
     *
     * @param string $api_key API key
     * @return bool Within rate limit
     */
    public function check_api_rate_limit($api_key) {
        return $this->check_rate_limit($api_key, 'api_request', 60); // 60 requests per minute
    }

    /**
     * Check rate limit for content scraping
     *
     * @param string $identifier User identifier
     * @return bool Within rate limit
     */
    public function check_scraping_rate_limit($identifier) {
        return $this->check_rate_limit($identifier, 'scraping', 2); // 2 scraping operations per minute
    }

    /**
     * Get chat identifier
     *
     * @param string $session_id Session ID
     * @return string Identifier
     */
    private function get_chat_identifier($session_id) {
        $user_id = get_current_user_id();
        if ($user_id) {
            return "user_{$user_id}";
        }

        $ip = $this->get_client_ip();
        return "ip_{$ip}";
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get rate limit statistics
     *
     * @return array Statistics
     */
    public function get_rate_limit_stats() {
        global $wpdb;

        // Get recent rate limit violations from logs
        $log_file = WP_CONTENT_DIR . '/logs/rag-chat-plugin.log';
        $violations = 0;

        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $violations = substr_count($log_content, 'Rate limit exceeded');
        }

        return array(
            'total_violations' => $violations,
            'default_limit' => $this->default_limit,
            'active_limits' => array(
                'chat_message' => 5,
                'api_request' => 60,
                'scraping' => 2
            )
        );
    }

    /**
     * Cleanup old rate limit data
     *
     * @return int Number of cleaned entries
     */
    public function cleanup_old_data() {
        // This would be implemented based on your cache system
        // For now, we'll return 0 as the cache handles expiration
        return 0;
    }

    /**
     * Set custom rate limit for action
     *
     * @param string $action Action name
     * @param int $limit Rate limit
     * @return bool Success
     */
    public function set_custom_limit($action, $limit) {
        $option_name = "rag_chat_rate_limit_{$action}";
        return update_option($option_name, $limit);
    }

    /**
     * Get custom rate limit for action
     *
     * @param string $action Action name
     * @return int|null Custom limit or null
     */
    public function get_custom_limit($action) {
        $option_name = "rag_chat_rate_limit_{$action}";
        $limit = get_option($option_name);
        return $limit !== false ? $limit : null;
    }

    /**
     * Check if user is rate limited
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @return bool Is rate limited
     */
    public function is_rate_limited($identifier, $action = 'default') {
        return !$this->check_rate_limit($identifier, $action);
    }

    /**
     * Get rate limit message
     *
     * @param string $identifier User identifier
     * @param string $action Action
     * @param int $limit Custom limit
     * @return string Rate limit message
     */
    public function get_rate_limit_message($identifier, $action = 'default', $limit = null) {
        $info = $this->get_rate_limit_info($identifier, $action, $limit);
        $reset_time = date('H:i:s', $info['reset_time']);
        
        return sprintf(
            __('Rate limit exceeded. You can make %d more requests. Limit resets at %s.', 'rag-chat-plugin'),
            $info['remaining'],
            $reset_time
        );
    }
}