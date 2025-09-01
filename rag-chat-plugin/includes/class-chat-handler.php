<?php
/**
 * Chat handler for processing user messages and generating responses
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Handler Class
 */
class RAG_Chat_Handler {

    /**
     * Instance of this class
     *
     * @var RAG_Chat_Handler
     */
    private static $instance = null;

    /**
     * Database instance
     *
     * @var RAG_Chat_Database
     */
    private $database;

    /**
     * RAG processor instance
     *
     * @var RAG_Chat_RAG_Processor
     */
    private $rag_processor;

    /**
     * Gemini API instance
     *
     * @var RAG_Chat_Gemini_API
     */
    private $gemini_api;

    /**
     * Get instance
     *
     * @return RAG_Chat_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->database = RAG_Chat_Database::get_instance();
        $this->rag_processor = new RAG_Chat_RAG_Processor();
        $this->gemini_api = new RAG_Chat_Gemini_API();
    }

    /**
     * Process chat message and generate response
     *
     * @param string $message User message
     * @param string $session_id Session ID
     * @param array $context Additional context
     * @return array Response data
     */
    public function process_message($message, $session_id, $context = array()) {
        $start_time = microtime(true);
        
        try {
            // Sanitize input
            $message = RAG_Chat_Security::sanitize_message($message);
            $session_id = sanitize_text_field($session_id);
            
            if (empty($message)) {
                return $this->create_error_response('Message cannot be empty');
            }

            // Check if chat is enabled
            if (!get_option('rag_chat_enabled', 1)) {
                return $this->create_error_response('Chat service is currently disabled');
            }

            // Analyze user intent
            $intent_analysis = $this->rag_processor->analyze_query_intent($message);
            
            // Find relevant content
            $relevant_content = $this->rag_processor->find_relevant_content($message, 10);
            
            // Get recent chat history
            $chat_history = $this->database->get_chat_history($session_id, 5);
            
            // Prepare context for AI
            $prepared_context = $this->rag_processor->prepare_context(
                $message, 
                $relevant_content, 
                $chat_history
            );
            
            // Generate AI response
            $ai_response = $this->gemini_api->generate_response($prepared_context);
            
            $response_time = microtime(true) - $start_time;
            
            if (!$ai_response['success']) {
                // Use fallback response
                $fallback_response = $this->generate_fallback_response($message, $relevant_content);
                
                $response_data = array(
                    'success' => true,
                    'message' => $fallback_response,
                    'is_fallback' => true,
                    'relevant_content_count' => count($relevant_content),
                    'response_time' => $response_time
                );
            } else {
                $response_data = array(
                    'success' => true,
                    'message' => $ai_response['response'],
                    'is_fallback' => false,
                    'relevant_content_count' => count($relevant_content),
                    'response_time' => $response_time,
                    'token_count' => isset($ai_response['token_count']) ? $ai_response['token_count'] : 0
                );
                
                // Update API usage statistics
                $this->gemini_api->update_usage_statistics($ai_response);
            }
            
            // Store chat history
            $this->store_chat_history($session_id, $message, $response_data, $relevant_content, $context);
            
            return $response_data;
            
        } catch (Exception $e) {
            error_log('RAG Chat Handler Error: ' . $e->getMessage());
            
            return $this->create_error_response(
                'An unexpected error occurred while processing your message',
                array('error_details' => $e->getMessage())
            );
        }
    }

    /**
     * Generate fallback response when AI fails
     *
     * @param string $message User message
     * @param array $relevant_content Relevant content found
     * @return string Fallback response
     */
    private function generate_fallback_response($message, $relevant_content) {
        if (empty($relevant_content)) {
            return "I apologize, but I couldn't find any relevant information to answer your question. You might want to try rephrasing your question or browsing our website directly.";
        }
        
        $fallback = "I found some relevant information that might help:\n\n";
        
        foreach (array_slice($relevant_content, 0, 3) as $content) {
            $item = $content['content'];
            $fallback .= "• " . $item->title . "\n";
            $fallback .= "  " . $this->truncate_text($item->content, 150) . "\n";
            $fallback .= "  Learn more: " . $item->url . "\n\n";
        }
        
        $fallback .= "If you need more specific information, please try rephrasing your question or contact our support team.";
        
        return $fallback;
    }

    /**
     * Store chat interaction in database
     *
     * @param string $session_id Session ID
     * @param string $user_message User message
     * @param array $response_data Response data
     * @param array $relevant_content Relevant content used
     * @param array $context Additional context
     */
    private function store_chat_history($session_id, $user_message, $response_data, $relevant_content, $context) {
        try {
            $chat_data = array(
                'session_id' => $session_id,
                'user_id' => get_current_user_id(),
                'user_ip' => RAG_Chat_Security::get_user_ip(),
                'user_message' => $user_message,
                'bot_response' => $response_data['message'],
                'context_used' => json_encode(array(
                    'relevant_content_ids' => array_map(function($item) {
                        return $item['content']->id;
                    }, $relevant_content),
                    'content_count' => count($relevant_content),
                    'is_fallback' => $response_data['is_fallback'],
                    'token_count' => isset($response_data['token_count']) ? $response_data['token_count'] : 0
                )),
                'response_time' => $response_data['response_time'],
                'user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'page_url' => isset($context['page_url']) ? $context['page_url'] : ''
            );
            
            $this->database->insert_chat_history($chat_data);
            
        } catch (Exception $e) {
            error_log('RAG Chat: Error storing chat history: ' . $e->getMessage());
        }
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param array $additional_data Additional data
     * @return array Error response
     */
    private function create_error_response($message, $additional_data = array()) {
        return array_merge(array(
            'success' => false,
            'message' => $message,
            'is_error' => true
        ), $additional_data);
    }

    /**
     * Truncate text to specified length
     *
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @return string Truncated text
     */
    private function truncate_text($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $truncated = substr($text, 0, $length);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($text, 0, $last_space);
        }
        
        return $truncated . '...';
    }

    /**
     * Handle AJAX chat message
     */
    public static function handle_ajax_message() {
        // Verify security
        if (!RAG_Chat_Security::validate_ajax_request('rag_chat_nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
        }

        // Get instance
        $handler = self::get_instance();
        
        // Get request data
        $message = !empty($_POST['message']) ? $_POST['message'] : '';
        $session_id = !empty($_POST['session_id']) ? $_POST['session_id'] : '';
        
        // Generate session ID if not provided
        if (empty($session_id)) {
            $session_id = RAG_Chat_Security::generate_session_id();
        }
        
        // Additional context
        $context = array(
            'page_url' => !empty($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '',
            'user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        );
        
        // Process message
        $response = $handler->process_message($message, $session_id, $context);
        
        // Add session ID to response
        $response['session_id'] = $session_id;
        
        // Send JSON response
        if ($response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }

    /**
     * Get chat statistics
     *
     * @return array Chat statistics
     */
    public function get_chat_statistics() {
        $stats = $this->database->get_statistics();
        
        // Add additional statistics
        $additional_stats = array(
            'api_usage' => $this->gemini_api->get_usage_statistics(),
            'content_stats' => $this->rag_processor->get_content_statistics(),
            'recent_activity' => $this->get_recent_activity_stats()
        );
        
        return array_merge($stats, $additional_stats);
    }

    /**
     * Get recent activity statistics
     *
     * @return array Recent activity stats
     */
    private function get_recent_activity_stats() {
        global $wpdb;
        $table = $this->database->get_chat_history_table();
        
        // Get stats for last 24 hours
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $stats = array();
        
        // Messages in last 24 hours
        $stats['messages_24h'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at > %s",
                $yesterday
            )
        );
        
        // Average response time in last 24 hours
        $stats['avg_response_time_24h'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(response_time) FROM {$table} WHERE created_at > %s AND response_time IS NOT NULL",
                $yesterday
            )
        );
        
        // Most active hours
        $stats['active_hours'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT HOUR(created_at) as hour, COUNT(*) as count 
                 FROM {$table} 
                 WHERE created_at > %s 
                 GROUP BY HOUR(created_at) 
                 ORDER BY count DESC 
                 LIMIT 5",
                $yesterday
            )
        );
        
        return $stats;
    }

    /**
     * Clean old chat sessions
     *
     * @param int $days Days to keep chat history
     * @return int Number of deleted records
     */
    public function clean_old_chats($days = 30) {
        return $this->database->clean_old_chat_history($days);
    }

    /**
     * Get conversation by session ID
     *
     * @param string $session_id Session ID
     * @param int $limit Message limit
     * @return array Conversation messages
     */
    public function get_conversation($session_id, $limit = 50) {
        return $this->database->get_chat_history($session_id, $limit);
    }

    /**
     * Export chat data for analysis
     *
     * @param array $filters Export filters
     * @return array Chat data
     */
    public function export_chat_data($filters = array()) {
        global $wpdb;
        $table = $this->database->get_chat_history_table();
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Date range filter
        if (!empty($filters['start_date'])) {
            $where_clauses[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        // User ID filter
        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $limit = !empty($filters['limit']) ? intval($filters['limit']) : 1000;
        
        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_results(
            $wpdb->prepare($sql, $params)
        );
    }

    /**
     * Generate chat insights
     *
     * @return array Chat insights
     */
    public function generate_insights() {
        global $wpdb;
        $table = $this->database->get_chat_history_table();
        
        $insights = array();
        
        // Most common queries
        $insights['common_queries'] = $wpdb->get_results(
            "SELECT user_message, COUNT(*) as count 
             FROM {$table} 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY user_message 
             HAVING count > 1 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        // Response time trends
        $insights['response_time_trend'] = $wpdb->get_results(
            "SELECT DATE(created_at) as date, AVG(response_time) as avg_time 
             FROM {$table} 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
             AND response_time IS NOT NULL 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC"
        );
        
        // User engagement patterns
        $insights['engagement_patterns'] = $wpdb->get_results(
            "SELECT HOUR(created_at) as hour, COUNT(*) as messages 
             FROM {$table} 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY HOUR(created_at) 
             ORDER BY hour"
        );
        
        return $insights;
    }

    /**
     * Process feedback on response
     *
     * @param int $chat_id Chat ID
     * @param string $feedback_type Feedback type (helpful, not_helpful)
     * @param string $feedback_text Optional feedback text
     * @return bool Success
     */
    public function process_feedback($chat_id, $feedback_type, $feedback_text = '') {
        // Store feedback in meta table or create feedback table
        $feedback_data = array(
            'chat_id' => intval($chat_id),
            'feedback_type' => sanitize_text_field($feedback_type),
            'feedback_text' => sanitize_textarea_field($feedback_text),
            'created_at' => current_time('mysql'),
            'user_ip' => RAG_Chat_Security::get_user_ip()
        );
        
        // For now, log the feedback
        error_log('RAG Chat Feedback: ' . json_encode($feedback_data));
        
        return true;
    }
}
