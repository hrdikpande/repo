<?php
/**
 * Logger class for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Logger Class
 */
class RAG_Chat_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Current log level
     *
     * @var string
     */
    private $log_level;

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_level = get_option('rag_chat_log_level', self::LEVEL_ERROR);
        $this->log_file = WP_CONTENT_DIR . '/logs/rag-chat-plugin.log';
        
        // Create logs directory if it doesn't exist
        $this->ensure_log_directory();
    }

    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function debug($message, array $context = array()) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function info($message, array $context = array()) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function warning($message, array $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function error($message, array $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message Message to log
     * @param array $context Additional context
     */
    public function critical($message, array $context = array()) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context
     */
    private function log($level, $message, array $context = array()) {
        // Check if we should log this level
        if (!$this->should_log($level)) {
            return;
        }

        // Format the log entry
        $log_entry = $this->format_log_entry($level, $message, $context);

        // Write to file
        $this->write_to_file($log_entry);

        // Also log to WordPress error log for critical errors
        if ($level === self::LEVEL_CRITICAL) {
            error_log('RAG Chat Plugin CRITICAL: ' . $message);
        }
    }

    /**
     * Check if we should log this level
     *
     * @param string $level Log level
     * @return bool Should log
     */
    private function should_log($level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        );

        $current_level = $levels[$this->log_level] ?? 3;
        $message_level = $levels[$level] ?? 3;

        return $message_level >= $current_level;
    }

    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context
     * @return string Formatted log entry
     */
    private function format_log_entry($level, $message, array $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        
        // Get user info
        $user_id = get_current_user_id();
        $user_info = $user_id ? "User:{$user_id}" : 'Guest';
        
        // Get IP address
        $ip = $this->get_client_ip();
        
        // Format context
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }

        return "[{$timestamp}] [{$level_upper}] [{$user_info}] [{$ip}] {$message}{$context_str}" . PHP_EOL;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
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
     * Write log entry to file
     *
     * @param string $log_entry Log entry to write
     */
    private function write_to_file($log_entry) {
        try {
            // Ensure log directory exists
            $this->ensure_log_directory();
            
            // Write to file
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Rotate log file if it's too large (10MB)
            $this->rotate_log_if_needed();
            
        } catch (Exception $e) {
            // Fallback to WordPress error log
            error_log('RAG Chat Plugin Logger Error: ' . $e->getMessage());
        }
    }

    /**
     * Rotate log file if it's too large
     */
    private function rotate_log_if_needed() {
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (file_exists($this->log_file) && filesize($this->log_file) > $max_size) {
            $backup_file = $this->log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
            rename($this->log_file, $backup_file);
            
            // Keep only last 5 backup files
            $this->cleanup_old_logs();
        }
    }

    /**
     * Cleanup old log files
     */
    private function cleanup_old_logs() {
        $log_dir = dirname($this->log_file);
        $pattern = $log_dir . '/rag-chat-plugin.log.*.bak';
        $files = glob($pattern);
        
        if (count($files) > 5) {
            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($files, 0, count($files) - 5);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Get log file path
     *
     * @return string Log file path
     */
    public function get_log_file() {
        return $this->log_file;
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to get
     * @return array Log entries
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $log_content = file_get_contents($this->log_file);
        $log_lines = explode(PHP_EOL, $log_content);
        
        // Remove empty lines
        $log_lines = array_filter($log_lines);
        
        // Get last N lines
        return array_slice($log_lines, -$lines);
    }

    /**
     * Clear log file
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }

    /**
     * Get log statistics
     *
     * @return array Log statistics
     */
    public function get_log_statistics() {
        if (!file_exists($this->log_file)) {
            return array(
                'total_entries' => 0,
                'file_size' => 0,
                'last_entry' => null,
                'level_counts' => array()
            );
        }

        $log_content = file_get_contents($this->log_file);
        $log_lines = explode(PHP_EOL, $log_content);
        $log_lines = array_filter($log_lines);

        $level_counts = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 0,
            self::LEVEL_WARNING => 0,
            self::LEVEL_ERROR => 0,
            self::LEVEL_CRITICAL => 0
        );

        $last_entry = null;

        foreach ($log_lines as $line) {
            if (preg_match('/\[([^\]]+)\] \[([^\]]+)\]/', $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtolower($matches[2]);
                
                if (isset($level_counts[$level])) {
                    $level_counts[$level]++;
                }
                
                $last_entry = $timestamp;
            }
        }

        return array(
            'total_entries' => count($log_lines),
            'file_size' => filesize($this->log_file),
            'last_entry' => $last_entry,
            'level_counts' => $level_counts
        );
    }
}