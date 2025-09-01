<?php
/**
 * Cache class for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Cache Class
 */
class RAG_Chat_Cache {

    /**
     * Cache group name
     *
     * @var string
     */
    private $cache_group = 'rag_chat_cache';

    /**
     * Default cache duration in seconds
     *
     * @var int
     */
    private $default_duration = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct() {
        $this->default_duration = get_option('rag_chat_cache_duration', 3600);
    }

    /**
     * Set cache value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $duration Cache duration in seconds
     * @return bool Success
     */
    public function set($key, $value, $duration = null) {
        if ($duration === null) {
            $duration = $this->default_duration;
        }

        $cache_key = $this->get_cache_key($key);
        $cache_data = array(
            'value' => $value,
            'expires' => time() + $duration,
            'created' => time()
        );

        return wp_cache_set($cache_key, $cache_data, $this->cache_group, $duration);
    }

    /**
     * Get cache value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get($key, $default = null) {
        $cache_key = $this->get_cache_key($key);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached === false) {
            return $default;
        }

        // Check if expired
        if (isset($cached['expires']) && $cached['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $cached['value'] ?? $default;
    }

    /**
     * Delete cache value
     *
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        return wp_cache_delete($cache_key, $this->cache_group);
    }

    /**
     * Check if cache key exists and is valid
     *
     * @param string $key Cache key
     * @return bool Exists and valid
     */
    public function exists($key) {
        $cache_key = $this->get_cache_key($key);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached === false) {
            return false;
        }

        // Check if expired
        if (isset($cached['expires']) && $cached['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Get cache key with prefix
     *
     * @param string $key Original key
     * @return string Prefixed key
     */
    private function get_cache_key($key) {
        return 'rag_chat_' . md5($key);
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        global $wp_object_cache;

        if (method_exists($wp_object_cache, 'getStats')) {
            return $wp_object_cache->getStats();
        }

        return array(
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => 0
        );
    }

    /**
     * Clear all plugin cache
     *
     * @return bool Success
     */
    public function clear_all() {
        return wp_cache_flush_group($this->cache_group);
    }

    /**
     * Cleanup old cache entries
     *
     * @return int Number of deleted entries
     */
    public function cleanup_old_cache() {
        global $wpdb;

        // This is a simplified cleanup - in a real implementation,
        // you might want to use a more sophisticated approach
        $deleted = 0;

        // Clean up transients that might be related to our plugin
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s",
                '_transient_%',
                '%rag_chat%'
            )
        );

        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            if (delete_transient($transient_name)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Cache API response
     *
     * @param string $query User query
     * @param array $response API response
     * @param int $duration Cache duration
     * @return bool Success
     */
    public function cache_api_response($query, $response, $duration = null) {
        $cache_key = 'api_response_' . md5($query);
        return $this->set($cache_key, $response, $duration);
    }

    /**
     * Get cached API response
     *
     * @param string $query User query
     * @return array|null Cached response or null
     */
    public function get_cached_api_response($query) {
        $cache_key = 'api_response_' . md5($query);
        return $this->get($cache_key);
    }

    /**
     * Cache search results
     *
     * @param string $query Search query
     * @param array $results Search results
     * @param int $duration Cache duration
     * @return bool Success
     */
    public function cache_search_results($query, $results, $duration = null) {
        $cache_key = 'search_results_' . md5($query);
        return $this->set($cache_key, $results, $duration);
    }

    /**
     * Get cached search results
     *
     * @param string $query Search query
     * @return array|null Cached results or null
     */
    public function get_cached_search_results($query) {
        $cache_key = 'search_results_' . md5($query);
        return $this->get($cache_key);
    }

    /**
     * Cache content chunks
     *
     * @param int $content_id Content ID
     * @param array $chunks Content chunks
     * @param int $duration Cache duration
     * @return bool Success
     */
    public function cache_content_chunks($content_id, $chunks, $duration = null) {
        $cache_key = 'content_chunks_' . $content_id;
        return $this->set($cache_key, $chunks, $duration);
    }

    /**
     * Get cached content chunks
     *
     * @param int $content_id Content ID
     * @return array|null Cached chunks or null
     */
    public function get_cached_content_chunks($content_id) {
        $cache_key = 'content_chunks_' . $content_id;
        return $this->get($cache_key);
    }

    /**
     * Cache user session data
     *
     * @param string $session_id Session ID
     * @param array $data Session data
     * @param int $duration Cache duration
     * @return bool Success
     */
    public function cache_session_data($session_id, $data, $duration = null) {
        $cache_key = 'session_data_' . $session_id;
        return $this->set($cache_key, $data, $duration);
    }

    /**
     * Get cached session data
     *
     * @param string $session_id Session ID
     * @return array|null Cached session data or null
     */
    public function get_cached_session_data($session_id) {
        $cache_key = 'session_data_' . $session_id;
        return $this->get($cache_key);
    }

    /**
     * Increment cache counter
     *
     * @param string $key Cache key
     * @param int $increment Increment value
     * @return int New value
     */
    public function increment($key, $increment = 1) {
        $cache_key = $this->get_cache_key($key);
        $current = wp_cache_get($cache_key, $this->cache_group);
        
        if ($current === false) {
            $new_value = $increment;
        } else {
            $new_value = (int)($current['value'] ?? 0) + $increment;
        }

        $this->set($key, $new_value);
        return $new_value;
    }

    /**
     * Get cache info for a key
     *
     * @param string $key Cache key
     * @return array Cache info
     */
    public function get_cache_info($key) {
        $cache_key = $this->get_cache_key($key);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if ($cached === false) {
            return array(
                'exists' => false,
                'expires' => null,
                'created' => null,
                'ttl' => null
            );
        }

        $now = time();
        $ttl = isset($cached['expires']) ? max(0, $cached['expires'] - $now) : null;

        return array(
            'exists' => true,
            'expires' => $cached['expires'] ?? null,
            'created' => $cached['created'] ?? null,
            'ttl' => $ttl
        );
    }
}