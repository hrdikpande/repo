<?php
/**
 * RAG (Retrieval-Augmented Generation) processor for content similarity and context preparation
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat RAG Processor Class
 */
class RAG_Chat_RAG_Processor {

    /**
     * Database instance
     *
     * @var RAG_Chat_Database
     */
    private $database;

    /**
     * Maximum context length for API
     *
     * @var int
     */
    private $max_context_length = 4000;

    /**
     * Maximum number of content pieces to include
     *
     * @var int
     */
    private $max_content_pieces = 5;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = RAG_Chat_Database::get_instance();
        $this->max_context_length = apply_filters('rag_chat_max_context_length', 4000);
        $this->max_content_pieces = apply_filters('rag_chat_max_content_pieces', 5);
    }

    /**
     * Find relevant content for a query
     *
     * @param string $query User query
     * @param int $limit Maximum results to return
     * @return array Relevant content with scores
     */
    public function find_relevant_content($query, $limit = 10) {
        // Sanitize query
        $query = RAG_Chat_Security::sanitize_message($query);
        
        if (empty($query)) {
            return array();
        }

        // Search using database text search
        $search_results = $this->database->search_scraped_content($query, $limit);
        
        // Enhanced similarity scoring
        $scored_results = array();
        
        foreach ($search_results as $result) {
            $score = $this->calculate_similarity_score($query, $result);
            
            if ($score > 0.1) { // Minimum relevance threshold
                $scored_results[] = array(
                    'content' => $result,
                    'score' => $score,
                    'relevance_reason' => $this->get_relevance_reason($query, $result)
                );
            }
        }

        // Sort by score
        usort($scored_results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scored_results, 0, $this->max_content_pieces);
    }

    /**
     * Calculate similarity score between query and content
     *
     * @param string $query User query
     * @param object $content Content object
     * @return float Similarity score (0-1)
     */
    private function calculate_similarity_score($query, $content) {
        $score = 0;
        
        // Tokenize query and content
        $query_tokens = $this->tokenize($query);
        $title_tokens = $this->tokenize($content->title);
        $content_tokens = $this->tokenize($content->content);
        
        // Title match (weighted heavily)
        $title_score = $this->calculate_jaccard_similarity($query_tokens, $title_tokens);
        $score += $title_score * 0.4;
        
        // Content match
        $content_score = $this->calculate_jaccard_similarity($query_tokens, $content_tokens);
        $score += $content_score * 0.3;
        
        // Keyword density
        $keyword_score = $this->calculate_keyword_density($query_tokens, $content->content);
        $score += $keyword_score * 0.2;
        
        // Freshness (newer content gets slight boost)
        $freshness_score = $this->calculate_freshness_score($content->updated_at);
        $score += $freshness_score * 0.1;
        
        return min(1.0, $score);
    }

    /**
     * Tokenize text into meaningful tokens
     *
     * @param string $text Text to tokenize
     * @return array Tokens
     */
    private function tokenize($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove punctuation and special characters
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Remove common stop words
        $stop_words = $this->get_stop_words();
        $words = array_diff($words, $stop_words);
        
        // Remove very short words
        $words = array_filter($words, function($word) {
            return strlen($word) > 2;
        });
        
        return array_values(array_unique($words));
    }

    /**
     * Calculate Jaccard similarity between two token sets
     *
     * @param array $tokens1 First token set
     * @param array $tokens2 Second token set
     * @return float Similarity score (0-1)
     */
    private function calculate_jaccard_similarity($tokens1, $tokens2) {
        if (empty($tokens1) || empty($tokens2)) {
            return 0;
        }
        
        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));
        
        return count($intersection) / count($union);
    }

    /**
     * Calculate keyword density score
     *
     * @param array $query_tokens Query tokens
     * @param string $content Content text
     * @return float Density score (0-1)
     */
    private function calculate_keyword_density($query_tokens, $content) {
        if (empty($query_tokens)) {
            return 0;
        }
        
        $content_lower = strtolower($content);
        $total_matches = 0;
        $content_word_count = str_word_count($content);
        
        foreach ($query_tokens as $token) {
            $matches = substr_count($content_lower, strtolower($token));
            $total_matches += $matches;
        }
        
        if ($content_word_count === 0) {
            return 0;
        }
        
        return min(1.0, $total_matches / $content_word_count * 10); // Scale appropriately
    }

    /**
     * Calculate freshness score based on content age
     *
     * @param string $updated_at Last update timestamp
     * @return float Freshness score (0-1)
     */
    private function calculate_freshness_score($updated_at) {
        $updated_timestamp = strtotime($updated_at);
        $now = time();
        $age_days = ($now - $updated_timestamp) / (24 * 60 * 60);
        
        // Fresh content (< 30 days) gets full score
        if ($age_days < 30) {
            return 1.0;
        }
        
        // Gradual decay over time
        return max(0.1, 1.0 - (($age_days - 30) / 365));
    }

    /**
     * Get relevance reason for debugging
     *
     * @param string $query User query
     * @param object $content Content object
     * @return string Relevance reason
     */
    private function get_relevance_reason($query, $content) {
        $query_tokens = $this->tokenize($query);
        $title_tokens = $this->tokenize($content->title);
        $content_tokens = $this->tokenize($content->content);
        
        $title_matches = array_intersect($query_tokens, $title_tokens);
        $content_matches = array_intersect($query_tokens, $content_tokens);
        
        $reasons = array();
        
        if (!empty($title_matches)) {
            $reasons[] = "Title matches: " . implode(', ', $title_matches);
        }
        
        if (!empty($content_matches)) {
            $reasons[] = "Content matches: " . implode(', ', array_slice($content_matches, 0, 3));
        }
        
        return implode('; ', $reasons);
    }

    /**
     * Prepare context for AI model
     *
     * @param string $query User query
     * @param array $relevant_content Relevant content pieces
     * @param array $chat_history Recent chat history
     * @return string Formatted context
     */
    public function prepare_context($query, $relevant_content, $chat_history = array()) {
        $context_parts = array();
        
        // Add system prompt
        $system_prompt = get_option('rag_chat_system_prompt', 
            'You are a helpful assistant. Answer questions based on the provided context from the website.');
        $context_parts[] = "System: " . $system_prompt;
        
        // Add recent chat history for context
        if (!empty($chat_history)) {
            $context_parts[] = "\n--- Recent Conversation ---";
            $history_count = 0;
            foreach (array_reverse($chat_history) as $chat) {
                if ($history_count >= 3) break; // Limit history
                $context_parts[] = "User: " . $chat->user_message;
                if (!empty($chat->bot_response)) {
                    $context_parts[] = "Assistant: " . $chat->bot_response;
                }
                $history_count++;
            }
        }
        
        // Add relevant content
        if (!empty($relevant_content)) {
            $context_parts[] = "\n--- Relevant Website Content ---";
            
            foreach ($relevant_content as $item) {
                $content = $item['content'];
                $score = $item['score'];
                
                $content_text = "Source: " . $content->title . " (Relevance: " . round($score * 100) . "%)\n";
                $content_text .= "URL: " . $content->url . "\n";
                $content_text .= "Content: " . $this->truncate_content($content->content, 800) . "\n";
                
                $context_parts[] = $content_text;
            }
        }
        
        // Add current query
        $context_parts[] = "\n--- Current Question ---";
        $context_parts[] = "User: " . $query;
        $context_parts[] = "Assistant: Please provide a helpful response based on the above context.";
        
        // Join and truncate if necessary
        $full_context = implode("\n", $context_parts);
        
        // Truncate if too long
        if (strlen($full_context) > $this->max_context_length) {
            $full_context = $this->intelligent_truncate($full_context, $this->max_context_length);
        }
        
        return $full_context;
    }

    /**
     * Truncate content intelligently
     *
     * @param string $content Content to truncate
     * @param int $max_length Maximum length
     * @return string Truncated content
     */
    private function truncate_content($content, $max_length) {
        if (strlen($content) <= $max_length) {
            return $content;
        }
        
        // Try to cut at sentence boundary
        $truncated = substr($content, 0, $max_length);
        $last_period = strrpos($truncated, '.');
        $last_question = strrpos($truncated, '?');
        $last_exclamation = strrpos($truncated, '!');
        
        $last_sentence_end = max($last_period, $last_question, $last_exclamation);
        
        if ($last_sentence_end !== false && $last_sentence_end > $max_length * 0.7) {
            return substr($content, 0, $last_sentence_end + 1);
        }
        
        // Fallback to word boundary
        $last_space = strrpos($truncated, ' ');
        if ($last_space !== false) {
            return substr($content, 0, $last_space) . '...';
        }
        
        return $truncated . '...';
    }

    /**
     * Intelligent truncation that preserves important sections
     *
     * @param string $context Full context
     * @param int $max_length Maximum length
     * @return string Truncated context
     */
    private function intelligent_truncate($context, $max_length) {
        // Split into sections
        $sections = explode('---', $context);
        
        // Priority order: System prompt, Current question, Recent conversation, Relevant content
        $priorities = array(
            'System:' => 1,
            'Current Question' => 2,
            'Recent Conversation' => 3,
            'Relevant Website Content' => 4
        );
        
        $result_sections = array();
        $current_length = 0;
        
        // Sort sections by priority
        usort($sections, function($a, $b) use ($priorities) {
            $priority_a = 999;
            $priority_b = 999;
            
            foreach ($priorities as $key => $priority) {
                if (strpos($a, $key) !== false) {
                    $priority_a = $priority;
                    break;
                }
            }
            
            foreach ($priorities as $key => $priority) {
                if (strpos($b, $key) !== false) {
                    $priority_b = $priority;
                    break;
                }
            }
            
            return $priority_a <=> $priority_b;
        });
        
        // Add sections until we reach the limit
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            
            $section_length = strlen($section);
            
            if ($current_length + $section_length <= $max_length) {
                $result_sections[] = $section;
                $current_length += $section_length;
            } else {
                // Try to fit a truncated version
                $remaining_space = $max_length - $current_length - 50; // Leave some buffer
                if ($remaining_space > 100) {
                    $truncated_section = $this->truncate_content($section, $remaining_space);
                    $result_sections[] = $truncated_section;
                }
                break;
            }
        }
        
        return implode("\n---", $result_sections);
    }

    /**
     * Get stop words for filtering
     *
     * @return array Stop words
     */
    private function get_stop_words() {
        return array(
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'you', 'your', 'this', 'that', 'these',
            'those', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'they',
            'them', 'their', 'theirs', 'themselves', 'what', 'which', 'who',
            'whom', 'whose', 'where', 'when', 'why', 'how', 'all', 'any', 'both',
            'each', 'few', 'more', 'most', 'other', 'some', 'such', 'only', 'own',
            'same', 'so', 'than', 'too', 'very', 'can', 'could', 'should', 'would'
        );
    }

    /**
     * Extract key phrases from query
     *
     * @param string $query User query
     * @return array Key phrases
     */
    public function extract_key_phrases($query) {
        $tokens = $this->tokenize($query);
        
        // Simple phrase extraction - look for multi-word concepts
        $phrases = array();
        $words = explode(' ', strtolower(preg_replace('/[^\w\s]/', ' ', $query)));
        
        // Extract 2-3 word phrases
        for ($i = 0; $i < count($words) - 1; $i++) {
            if (strlen($words[$i]) > 2 && strlen($words[$i + 1]) > 2) {
                $phrase = $words[$i] . ' ' . $words[$i + 1];
                if (!in_array($phrase, $this->get_stop_words())) {
                    $phrases[] = $phrase;
                }
                
                // 3-word phrases
                if ($i < count($words) - 2 && strlen($words[$i + 2]) > 2) {
                    $phrase3 = $phrase . ' ' . $words[$i + 2];
                    $phrases[] = $phrase3;
                }
            }
        }
        
        return array_merge($tokens, $phrases);
    }

    /**
     * Analyze query intent
     *
     * @param string $query User query
     * @return array Intent analysis
     */
    public function analyze_query_intent($query) {
        $intent = array(
            'type' => 'general',
            'confidence' => 0.5,
            'entities' => array(),
            'keywords' => array()
        );
        
        $query_lower = strtolower($query);
        
        // Question patterns
        $question_patterns = array(
            'what' => array('definition', 'explanation'),
            'how' => array('instruction', 'process'),
            'where' => array('location', 'navigation'),
            'when' => array('time', 'schedule'),
            'why' => array('reason', 'explanation'),
            'who' => array('person', 'contact'),
            'which' => array('choice', 'comparison')
        );
        
        foreach ($question_patterns as $word => $types) {
            if (strpos($query_lower, $word) === 0) {
                $intent['type'] = $types[0];
                $intent['confidence'] = 0.8;
                break;
            }
        }
        
        // Action patterns
        $action_patterns = array(
            'help' => 'support',
            'find' => 'search',
            'show' => 'display',
            'tell' => 'information',
            'explain' => 'explanation',
            'compare' => 'comparison'
        );
        
        foreach ($action_patterns as $action => $type) {
            if (strpos($query_lower, $action) !== false) {
                $intent['type'] = $type;
                $intent['confidence'] = max($intent['confidence'], 0.7);
            }
        }
        
        $intent['keywords'] = $this->extract_key_phrases($query);
        
        return $intent;
    }

    /**
     * Get content statistics
     *
     * @return array Content statistics
     */
    public function get_content_statistics() {
        return array(
            'total_content' => $this->database->get_statistics()['total_content'],
            'content_by_type' => $this->get_content_by_type_stats(),
            'avg_content_length' => $this->get_average_content_length(),
            'last_indexed' => get_option('rag_chat_last_scrape', 'Never')
        );
    }

    /**
     * Get content statistics by type
     *
     * @return array Content type statistics
     */
    private function get_content_by_type_stats() {
        global $wpdb;
        $table = $this->database->get_scraped_content_table();
        
        $results = $wpdb->get_results(
            "SELECT content_type, COUNT(*) as count 
             FROM {$table} 
             WHERE status = 'active' 
             GROUP BY content_type"
        );
        
        $stats = array();
        foreach ($results as $result) {
            $stats[$result->content_type] = (int) $result->count;
        }
        
        return $stats;
    }

    /**
     * Get average content length
     *
     * @return int Average word count
     */
    private function get_average_content_length() {
        global $wpdb;
        $table = $this->database->get_scraped_content_table();
        
        $result = $wpdb->get_var(
            "SELECT AVG(word_count) 
             FROM {$table} 
             WHERE status = 'active' AND word_count > 0"
        );
        
        return $result ? (int) $result : 0;
    }
}
