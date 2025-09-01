<?php
/**
 * Website content scraper for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Scraper Class
 */
class RAG_Chat_Scraper {

    /**
     * Database instance
     *
     * @var RAG_Chat_Database
     */
    private $database;

    /**
     * Chunk size for content processing
     *
     * @var int
     */
    private $chunk_size = 1000;

    /**
     * Maximum chunks per content
     *
     * @var int
     */
    private $max_chunks = 10;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = RAG_Chat_Database::get_instance();
        $this->chunk_size = apply_filters('rag_chat_chunk_size', 1000);
        $this->max_chunks = apply_filters('rag_chat_max_chunks', 10);
    }

    /**
     * Scrape all content from the website
     *
     * @return array Results summary
     */
    public function scrape_all_content() {
        $results = array(
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'total' => 0,
            'messages' => array()
        );

        // Get content types to scrape
        $content_types = get_option('rag_chat_content_types', array('post', 'page'));
        
        foreach ($content_types as $content_type) {
            $type_results = $this->scrape_content_type($content_type);
            $results['success'] += $type_results['success'];
            $results['errors'] += $type_results['errors'];
            $results['skipped'] += $type_results['skipped'];
            $results['total'] += $type_results['total'];
            $results['messages'] = array_merge($results['messages'], $type_results['messages']);
        }

        // Update last scrape time
        update_option('rag_chat_last_scrape', current_time('mysql'));

        return $results;
    }

    /**
     * Scrape content of specific type
     *
     * @param string $content_type Content type to scrape
     * @return array Results summary
     */
    public function scrape_content_type($content_type) {
        $results = array(
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'total' => 0,
            'messages' => array()
        );

        // Get posts of this type
        $posts = get_posts(array(
            'post_type' => $content_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));

        $results['total'] = count($posts);

        foreach ($posts as $post_id) {
            try {
                $scrape_result = $this->scrape_post($post_id);
                
                if ($scrape_result['success']) {
                    $results['success']++;
                } elseif ($scrape_result['skipped']) {
                    $results['skipped']++;
                } else {
                    $results['errors']++;
                    $results['messages'][] = "Error scraping post {$post_id}: " . $scrape_result['message'];
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['messages'][] = "Exception scraping post {$post_id}: " . $e->getMessage();
                error_log("RAG Chat Scraper: Exception scraping post {$post_id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Scrape individual post
     *
     * @param int $post_id Post ID to scrape
     * @return array Scrape result
     */
    public function scrape_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return array(
                'success' => false,
                'skipped' => true,
                'message' => 'Post not found or not published'
            );
        }

        // Get post URL
        $url = get_permalink($post_id);
        
        // Extract content
        $content = $this->extract_post_content($post);
        
        if (empty($content)) {
            return array(
                'success' => false,
                'skipped' => true,
                'message' => 'No content to scrape'
            );
        }

        // Generate content hash
        $content_hash = hash('sha256', $content);
        
        // Check if content already exists and is unchanged
        $existing = $this->database->get_scraped_content_by_post_id($post_id);
        if ($existing && $existing->content_hash === $content_hash) {
            return array(
                'success' => false,
                'skipped' => true,
                'message' => 'Content unchanged'
            );
        }

        // Prepare data
        $data = array(
            'post_id' => $post_id,
            'url' => $url,
            'title' => $post->post_title,
            'content' => $content,
            'content_type' => $post->post_type,
            'content_hash' => $content_hash,
            'word_count' => str_word_count(strip_tags($content))
        );

        // Insert or update content
        if ($existing) {
            $success = $this->database->update_scraped_content($existing->id, $data);
            $content_id = $existing->id;
        } else {
            $content_id = $this->database->insert_scraped_content($data);
            $success = $content_id !== false;
        }

        if (!$success) {
            return array(
                'success' => false,
                'skipped' => false,
                'message' => 'Database insert/update failed'
            );
        }

        // Process content into chunks
        $this->process_content_chunks($content_id, $content);

        return array(
            'success' => true,
            'skipped' => false,
            'message' => 'Content scraped successfully',
            'content_id' => $content_id
        );
    }

    /**
     * Extract content from post
     *
     * @param WP_Post $post Post object
     * @return string Extracted content
     */
    private function extract_post_content($post) {
        // Get post content
        $content = $post->post_content;
        
        // Apply WordPress filters to process shortcodes, etc.
        $content = apply_filters('the_content', $content);
        
        // Remove HTML tags but preserve structure
        $content = $this->clean_html_content($content);
        
        // Add post title and excerpt
        $full_content = '';
        
        if (!empty($post->post_title)) {
            $full_content .= "Title: " . $post->post_title . "\n\n";
        }
        
        if (!empty($post->post_excerpt)) {
            $full_content .= "Excerpt: " . $post->post_excerpt . "\n\n";
        }
        
        $full_content .= "Content: " . $content;
        
        // Get custom fields if needed
        $custom_fields = $this->extract_custom_fields($post->ID);
        if (!empty($custom_fields)) {
            $full_content .= "\n\nAdditional Information: " . $custom_fields;
        }
        
        return trim($full_content);
    }

    /**
     * Clean HTML content
     *
     * @param string $content HTML content
     * @return string Cleaned content
     */
    private function clean_html_content($content) {
        // Remove script and style elements
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);
        
        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        
        // Convert some HTML elements to text equivalents
        $content = str_replace(array('<br>', '<br/>', '<br />'), "\n", $content);
        $content = str_replace('</p>', "\n\n", $content);
        $content = str_replace(array('</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'), "\n\n", $content);
        $content = str_replace('</li>', "\n", $content);
        
        // Remove all remaining HTML tags
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = preg_replace('/\n\s*\n/', "\n\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        return trim($content);
    }

    /**
     * Extract relevant custom fields
     *
     * @param int $post_id Post ID
     * @return string Custom fields content
     */
    private function extract_custom_fields($post_id) {
        $custom_content = '';
        
        // Get custom fields that might be useful
        $useful_fields = apply_filters('rag_chat_useful_custom_fields', array(
            'description',
            'summary',
            'meta_description',
            'excerpt',
            'product_description',
            'features',
            'specifications'
        ));
        
        foreach ($useful_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            if (!empty($value) && is_string($value)) {
                $custom_content .= $field . ': ' . strip_tags($value) . "\n";
            }
        }
        
        return trim($custom_content);
    }

    /**
     * Process content into chunks
     *
     * @param int $content_id Content ID
     * @param string $content Content text
     */
    private function process_content_chunks($content_id, $content) {
        // Delete existing chunks
        $this->database->delete_content_chunks($content_id);
        
        // Split content into chunks
        $chunks = $this->split_content_into_chunks($content);
        
        // Insert chunks
        foreach ($chunks as $index => $chunk_text) {
            $chunk_data = array(
                'content_id' => $content_id,
                'chunk_text' => $chunk_text,
                'chunk_index' => $index,
                'word_count' => str_word_count($chunk_text)
            );
            
            $this->database->insert_content_chunk($chunk_data);
        }
    }

    /**
     * Split content into manageable chunks
     *
     * @param string $content Content to split
     * @return array Array of chunks
     */
    private function split_content_into_chunks($content) {
        $chunks = array();
        $words = explode(' ', $content);
        $current_chunk = '';
        $word_count = 0;
        
        foreach ($words as $word) {
            $current_chunk .= $word . ' ';
            $word_count++;
            
            // Check if we've reached the chunk size
            if ($word_count >= $this->chunk_size) {
                $chunks[] = trim($current_chunk);
                $current_chunk = '';
                $word_count = 0;
                
                // Limit number of chunks
                if (count($chunks) >= $this->max_chunks) {
                    break;
                }
            }
        }
        
        // Add remaining content as final chunk
        if (!empty(trim($current_chunk)) && count($chunks) < $this->max_chunks) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }

    /**
     * Handle AJAX scrape request
     */
    public static function handle_ajax_scrape() {
        // Verify security
        if (!RAG_Chat_Security::validate_ajax_request('rag_chat_scrape', true)) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Security check failed'
            )));
        }

        // Check user permissions
        if (!RAG_Chat_Security::user_can('manage_options')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Insufficient permissions'
            )));
        }

        // Create scraper instance
        $scraper = new self();
        
        // Get scrape type
        $scrape_type = !empty($_POST['scrape_type']) ? sanitize_text_field($_POST['scrape_type']) : 'all';
        
        try {
            if ($scrape_type === 'all') {
                $results = $scraper->scrape_all_content();
            } else {
                $results = $scraper->scrape_content_type($scrape_type);
            }
            
            wp_die(json_encode(array(
                'success' => true,
                'message' => 'Scraping completed successfully',
                'results' => $results
            )));
        } catch (Exception $e) {
            error_log('RAG Chat Scraper AJAX Error: ' . $e->getMessage());
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Scraping failed: ' . $e->getMessage()
            )));
        }
    }

    /**
     * Handle scheduled scraping
     */
    public static function scheduled_scrape() {
        // Check if auto-scraping is enabled
        if (!get_option('rag_chat_auto_scrape', false)) {
            return;
        }

        try {
            $scraper = new self();
            $results = $scraper->scrape_all_content();
            
            // Log results
            error_log('RAG Chat Scheduled Scrape: ' . json_encode($results));
            
            // Store results for admin review
            update_option('rag_chat_last_scrape_results', $results);
            
        } catch (Exception $e) {
            error_log('RAG Chat Scheduled Scrape Error: ' . $e->getMessage());
        }
    }

    /**
     * Scrape content when post is saved
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public static function scrape_on_save($post_id, $post) {
        // Skip if auto-scraping is disabled
        if (!get_option('rag_chat_auto_scrape', false)) {
            return;
        }

        // Skip for certain conditions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if this post type should be scraped
        $content_types = get_option('rag_chat_content_types', array('post', 'page'));
        if (!in_array($post->post_type, $content_types)) {
            return;
        }

        try {
            $scraper = new self();
            $scraper->scrape_post($post_id);
        } catch (Exception $e) {
            error_log('RAG Chat Auto-scrape Error for post ' . $post_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Remove content when post is deleted
     *
     * @param int $post_id Post ID
     */
    public static function remove_on_delete($post_id) {
        try {
            $database = RAG_Chat_Database::get_instance();
            $content = $database->get_scraped_content_by_post_id($post_id);
            
            if ($content) {
                $database->delete_scraped_content($content->id);
            }
        } catch (Exception $e) {
            error_log('RAG Chat Auto-remove Error for post ' . $post_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Get scraping statistics
     *
     * @return array Statistics
     */
    public function get_scraping_stats() {
        return $this->database->get_statistics();
    }
}
