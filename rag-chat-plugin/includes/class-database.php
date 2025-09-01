<?php
/**
 * Database management class for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Database Class
 */
class RAG_Chat_Database {

    /**
     * Instance of this class
     *
     * @var RAG_Chat_Database
     */
    private static $instance = null;

    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Table names
     */
    private $scraped_content_table;
    private $chat_history_table;
    private $content_chunks_table;

    /**
     * Get instance
     *
     * @return RAG_Chat_Database
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
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set table names
        $this->scraped_content_table = $this->wpdb->prefix . 'rag_chat_scraped_content';
        $this->chat_history_table = $this->wpdb->prefix . 'rag_chat_history';
        $this->content_chunks_table = $this->wpdb->prefix . 'rag_chat_content_chunks';
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        $instance = self::get_instance();
        $instance->create_scraped_content_table();
        $instance->create_chat_history_table();
        $instance->create_content_chunks_table();
    }

    /**
     * Drop database tables
     */
    public static function drop_tables() {
        $instance = self::get_instance();
        $instance->drop_scraped_content_table();
        $instance->drop_chat_history_table();
        $instance->drop_content_chunks_table();
    }

    /**
     * Create scraped content table
     */
    private function create_scraped_content_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->scraped_content_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT NULL,
            url varchar(2048) NOT NULL,
            title text DEFAULT NULL,
            content longtext DEFAULT NULL,
            content_type varchar(50) DEFAULT 'post',
            content_hash varchar(64) DEFAULT NULL,
            word_count int(11) DEFAULT 0,
            scraped_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_content_type (content_type),
            KEY idx_content_hash (content_hash),
            KEY idx_status (status),
            KEY idx_scraped_at (scraped_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            error_log('RAG Chat Plugin: Error creating scraped_content table: ' . $this->wpdb->last_error);
        }
    }

    /**
     * Create chat history table
     */
    private function create_chat_history_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->chat_history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            user_message longtext NOT NULL,
            bot_response longtext DEFAULT NULL,
            context_used longtext DEFAULT NULL,
            response_time float DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_agent text DEFAULT NULL,
            page_url varchar(2048) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_session_id (session_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_user_ip (user_ip)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            error_log('RAG Chat Plugin: Error creating chat_history table: ' . $this->wpdb->last_error);
        }
    }

    /**
     * Create content chunks table
     */
    private function create_content_chunks_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->content_chunks_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_id bigint(20) unsigned NOT NULL,
            chunk_text longtext NOT NULL,
            chunk_index int(11) NOT NULL DEFAULT 0,
            word_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_content_id (content_id),
            KEY idx_chunk_index (chunk_index),
            FOREIGN KEY (content_id) REFERENCES {$this->scraped_content_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            error_log('RAG Chat Plugin: Error creating content_chunks table: ' . $this->wpdb->last_error);
        }
    }

    /**
     * Drop scraped content table
     */
    private function drop_scraped_content_table() {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->scraped_content_table}");
    }

    /**
     * Drop chat history table
     */
    private function drop_chat_history_table() {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->chat_history_table}");
    }

    /**
     * Drop content chunks table
     */
    private function drop_content_chunks_table() {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->content_chunks_table}");
    }

    /**
     * Get table names
     */
    public function get_scraped_content_table() {
        return $this->scraped_content_table;
    }

    public function get_chat_history_table() {
        return $this->chat_history_table;
    }

    public function get_content_chunks_table() {
        return $this->content_chunks_table;
    }

    /**
     * Insert scraped content
     *
     * @param array $data Content data
     * @return int|false Insert ID or false on failure
     */
    public function insert_scraped_content($data) {
        $defaults = array(
            'post_id' => null,
            'url' => '',
            'title' => '',
            'content' => '',
            'content_type' => 'post',
            'content_hash' => '',
            'word_count' => 0,
            'status' => 'active'
        );

        $data = wp_parse_args($data, $defaults);
        
        // Generate content hash if not provided
        if (empty($data['content_hash'])) {
            $data['content_hash'] = hash('sha256', $data['content']);
        }

        // Calculate word count if not provided
        if (empty($data['word_count'])) {
            $data['word_count'] = str_word_count(strip_tags($data['content']));
        }

        $result = $this->wpdb->insert(
            $this->scraped_content_table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update scraped content
     *
     * @param int $id Content ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update_scraped_content($id, $data) {
        // Generate content hash if content is being updated
        if (isset($data['content']) && !isset($data['content_hash'])) {
            $data['content_hash'] = hash('sha256', $data['content']);
        }

        // Calculate word count if content is being updated
        if (isset($data['content']) && !isset($data['word_count'])) {
            $data['word_count'] = str_word_count(strip_tags($data['content']));
        }

        $result = $this->wpdb->update(
            $this->scraped_content_table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get scraped content by ID
     *
     * @param int $id Content ID
     * @return object|null Content object or null
     */
    public function get_scraped_content($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->scraped_content_table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Get scraped content by post ID
     *
     * @param int $post_id Post ID
     * @return object|null Content object or null
     */
    public function get_scraped_content_by_post_id($post_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->scraped_content_table} WHERE post_id = %d AND status = 'active'",
                $post_id
            )
        );
    }

    /**
     * Get scraped content by URL
     *
     * @param string $url URL
     * @return object|null Content object or null
     */
    public function get_scraped_content_by_url($url) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->scraped_content_table} WHERE url = %s AND status = 'active'",
                $url
            )
        );
    }

    /**
     * Delete scraped content
     *
     * @param int $id Content ID
     * @return bool Success
     */
    public function delete_scraped_content($id) {
        return $this->wpdb->delete(
            $this->scraped_content_table,
            array('id' => $id),
            array('%d')
        ) !== false;
    }

    /**
     * Search scraped content
     *
     * @param string $query Search query
     * @param int $limit Results limit
     * @return array Results
     */
    public function search_scraped_content($query, $limit = 10) {
        $search_terms = explode(' ', sanitize_text_field($query));
        $where_clauses = array();
        $params = array();

        foreach ($search_terms as $term) {
            $term = trim($term);
            if (!empty($term)) {
                $where_clauses[] = "(title LIKE %s OR content LIKE %s)";
                $params[] = '%' . $this->wpdb->esc_like($term) . '%';
                $params[] = '%' . $this->wpdb->esc_like($term) . '%';
            }
        }

        if (empty($where_clauses)) {
            return array();
        }

        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;

        $sql = "SELECT *, 
                MATCH(title, content) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance
                FROM {$this->scraped_content_table} 
                WHERE status = 'active' AND ({$where_sql})
                ORDER BY relevance DESC, updated_at DESC 
                LIMIT %d";

        array_unshift($params, $query);

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params)
        );
    }

    /**
     * Insert chat history
     *
     * @param array $data Chat data
     * @return int|false Insert ID or false on failure
     */
    public function insert_chat_history($data) {
        $defaults = array(
            'session_id' => '',
            'user_id' => null,
            'user_ip' => '',
            'user_message' => '',
            'bot_response' => '',
            'context_used' => '',
            'response_time' => null,
            'user_agent' => '',
            'page_url' => ''
        );

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->chat_history_table,
            $data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get chat history by session
     *
     * @param string $session_id Session ID
     * @param int $limit Results limit
     * @return array Chat history
     */
    public function get_chat_history($session_id, $limit = 50) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->chat_history_table} 
                WHERE session_id = %s 
                ORDER BY created_at DESC 
                LIMIT %d",
                $session_id,
                $limit
            )
        );
    }

    /**
     * Insert content chunk
     *
     * @param array $data Chunk data
     * @return int|false Insert ID or false on failure
     */
    public function insert_content_chunk($data) {
        $defaults = array(
            'content_id' => 0,
            'chunk_text' => '',
            'chunk_index' => 0,
            'word_count' => 0
        );

        $data = wp_parse_args($data, $defaults);
        
        // Calculate word count if not provided
        if (empty($data['word_count'])) {
            $data['word_count'] = str_word_count(strip_tags($data['chunk_text']));
        }

        $result = $this->wpdb->insert(
            $this->content_chunks_table,
            $data,
            array('%d', '%s', '%d', '%d')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get content chunks by content ID
     *
     * @param int $content_id Content ID
     * @return array Chunks
     */
    public function get_content_chunks($content_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->content_chunks_table} 
                WHERE content_id = %d 
                ORDER BY chunk_index ASC",
                $content_id
            )
        );
    }

    /**
     * Delete content chunks by content ID
     *
     * @param int $content_id Content ID
     * @return bool Success
     */
    public function delete_content_chunks($content_id) {
        return $this->wpdb->delete(
            $this->content_chunks_table,
            array('content_id' => $content_id),
            array('%d')
        ) !== false;
    }

    /**
     * Get database statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        $stats = array();

        // Total scraped content
        $stats['total_content'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->scraped_content_table} WHERE status = 'active'"
        );

        // Total chat messages
        $stats['total_chats'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->chat_history_table}"
        );

        // Total content chunks
        $stats['total_chunks'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->content_chunks_table}"
        );

        // Average response time
        $stats['avg_response_time'] = $this->wpdb->get_var(
            "SELECT AVG(response_time) FROM {$this->chat_history_table} WHERE response_time IS NOT NULL"
        );

        // Last scrape time
        $stats['last_scrape'] = $this->wpdb->get_var(
            "SELECT MAX(scraped_at) FROM {$this->scraped_content_table}"
        );

        return $stats;
    }

    /**
     * Clean old chat history
     *
     * @param int $days Days to keep
     * @return int Deleted rows
     */
    public function clean_old_chat_history($days = 30) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->chat_history_table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
