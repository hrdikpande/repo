<?php
/**
 * Google Gemini API integration for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Gemini API Class
 */
class RAG_Chat_Gemini_API {

    /**
     * API endpoint base URL
     *
     * @var string
     */
    private $api_base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Model name
     *
     * @var string
     */
    private $model = 'gemini-pro';

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Constructor
     */
    public function __construct() {
        $encrypted_key = get_option('rag_chat_gemini_api_key', '');
        $this->api_key = RAG_Chat_Security::decrypt_api_key($encrypted_key);
        $this->model = apply_filters('rag_chat_gemini_model', 'gemini-pro');
        $this->timeout = apply_filters('rag_chat_api_timeout', 30);
    }

    /**
     * Check if API key is configured
     *
     * @return bool API key is set
     */
    public function is_api_key_configured() {
        return !empty($this->api_key);
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return array Validation result
     */
    public function validate_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->api_key;
        }

        if (empty($api_key)) {
            return array(
                'valid' => false,
                'message' => 'API key is empty'
            );
        }

        // Check format
        if (!RAG_Chat_Security::is_valid_api_key_format($api_key)) {
            return array(
                'valid' => false,
                'message' => 'Invalid API key format'
            );
        }

        // Test API call
        $test_result = $this->test_api_connection($api_key);
        
        return $test_result;
    }

    /**
     * Test API connection
     *
     * @param string $api_key API key to test
     * @return array Test result
     */
    private function test_api_connection($api_key) {
        $url = $this->api_base_url . $this->model . ':generateContent?key=' . $api_key;
        
        $test_payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => 'Hello, this is a test. Please respond with "API connection successful."')
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.1,
                'maxOutputTokens' => 50
            )
        );

        $response = $this->make_api_request($url, $test_payload, $api_key);

        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => 'API request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return array(
                    'valid' => true,
                    'message' => 'API key is valid and working'
                );
            }
        }

        // Parse error response
        if ($response_code === 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? 
                $error_data['error']['message'] : 'Bad request';
            
            return array(
                'valid' => false,
                'message' => 'API error: ' . $error_message
            );
        }

        if ($response_code === 403) {
            return array(
                'valid' => false,
                'message' => 'API key is invalid or access denied'
            );
        }

        return array(
            'valid' => false,
            'message' => 'Unexpected API response: ' . $response_code
        );
    }

    /**
     * Generate response using Gemini API
     *
     * @param string $context Prepared context
     * @param array $options Generation options
     * @return array API response
     */
    public function generate_response($context, $options = array()) {
        if (!$this->is_api_key_configured()) {
            return array(
                'success' => false,
                'message' => 'API key not configured',
                'response' => 'I apologize, but the AI service is not properly configured. Please contact the website administrator.'
            );
        }

        // Default options
        $defaults = array(
            'temperature' => get_option('rag_chat_temperature', 0.7),
            'max_tokens' => get_option('rag_chat_max_response_length', 500),
            'top_p' => 0.9,
            'top_k' => 40
        );

        $options = wp_parse_args($options, $defaults);

        // Prepare API request
        $url = $this->api_base_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $context)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => floatval($options['temperature']),
                'maxOutputTokens' => intval($options['max_tokens']),
                'topP' => floatval($options['top_p']),
                'topK' => intval($options['top_k'])
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );

        $start_time = microtime(true);
        $response = $this->make_api_request($url, $payload);
        $response_time = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            error_log('RAG Chat Gemini API Error: ' . $response->get_error_message());
            
            return array(
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message(),
                'response' => 'I apologize, but I encountered an error while processing your request. Please try again later.',
                'response_time' => $response_time
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = $this->parse_error_response($response_code, $response_body);
            error_log('RAG Chat Gemini API Error: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => $error_message,
                'response' => 'I apologize, but I encountered an error while processing your request. Please try again later.',
                'response_time' => $response_time
            );
        }

        // Parse successful response
        $data = json_decode($response_body, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('RAG Chat Gemini API: Unexpected response structure: ' . $response_body);
            
            return array(
                'success' => false,
                'message' => 'Unexpected API response structure',
                'response' => 'I apologize, but I received an unexpected response. Please try again later.',
                'response_time' => $response_time
            );
        }

        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Post-process response
        $processed_response = $this->post_process_response($generated_text);

        return array(
            'success' => true,
            'message' => 'Response generated successfully',
            'response' => $processed_response,
            'response_time' => $response_time,
            'token_count' => $this->estimate_token_count($generated_text),
            'safety_ratings' => isset($data['candidates'][0]['safetyRatings']) ? 
                $data['candidates'][0]['safetyRatings'] : array()
        );
    }

    /**
     * Make API request
     *
     * @param string $url Request URL
     * @param array $payload Request payload
     * @param string $api_key Optional API key override
     * @return array|WP_Error Response
     */
    private function make_api_request($url, $payload, $api_key = null) {
        if ($api_key === null) {
            $api_key = $this->api_key;
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'RAG-Chat-Plugin/' . RAG_CHAT_VERSION . ' WordPress/' . get_bloginfo('version')
        );

        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => $this->timeout,
            'sslverify' => true
        );

        return wp_remote_request($url, $args);
    }

    /**
     * Parse error response
     *
     * @param int $response_code HTTP response code
     * @param string $response_body Response body
     * @return string Error message
     */
    private function parse_error_response($response_code, $response_body) {
        $error_data = json_decode($response_body, true);
        
        switch ($response_code) {
            case 400:
                $message = isset($error_data['error']['message']) ? 
                    $error_data['error']['message'] : 'Bad request';
                return 'API Error (400): ' . $message;
                
            case 401:
                return 'API Error (401): Unauthorized - Invalid API key';
                
            case 403:
                return 'API Error (403): Forbidden - Access denied';
                
            case 429:
                return 'API Error (429): Rate limit exceeded - Please try again later';
                
            case 500:
                return 'API Error (500): Internal server error - Please try again later';
                
            case 503:
                return 'API Error (503): Service unavailable - Please try again later';
                
            default:
                return 'API Error (' . $response_code . '): Unknown error';
        }
    }

    /**
     * Post-process API response
     *
     * @param string $response Raw response text
     * @return string Processed response
     */
    private function post_process_response($response) {
        // Trim whitespace
        $response = trim($response);
        
        // Remove any markdown formatting if present
        $response = preg_replace('/\*\*(.*?)\*\*/', '$1', $response); // Bold
        $response = preg_replace('/\*(.*?)\*/', '$1', $response); // Italic
        
        // Convert line breaks to proper format
        $response = str_replace('\n', "\n", $response);
        
        // Remove excessive line breaks
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        
        // Ensure response ends with punctuation
        if (!empty($response) && !preg_match('/[.!?]$/', $response)) {
            $response .= '.';
        }
        
        return $response;
    }

    /**
     * Estimate token count for text
     *
     * @param string $text Text to count tokens for
     * @return int Estimated token count
     */
    private function estimate_token_count($text) {
        // Simple estimation: 1 token ≈ 4 characters for English text
        return ceil(strlen($text) / 4);
    }

    /**
     * Get API usage statistics
     *
     * @return array Usage statistics
     */
    public function get_usage_statistics() {
        // This would require implementing usage tracking
        // For now, return basic info
        return array(
            'api_configured' => $this->is_api_key_configured(),
            'model' => $this->model,
            'last_request' => get_option('rag_chat_last_api_request', 'Never'),
            'total_requests' => get_option('rag_chat_total_api_requests', 0),
            'total_tokens' => get_option('rag_chat_total_tokens', 0)
        );
    }

    /**
     * Update usage statistics
     *
     * @param array $response_data Response data from API
     */
    public function update_usage_statistics($response_data) {
        // Update last request time
        update_option('rag_chat_last_api_request', current_time('mysql'));
        
        // Increment request counter
        $total_requests = get_option('rag_chat_total_api_requests', 0);
        update_option('rag_chat_total_api_requests', $total_requests + 1);
        
        // Update token usage
        if (isset($response_data['token_count'])) {
            $total_tokens = get_option('rag_chat_total_tokens', 0);
            update_option('rag_chat_total_tokens', $total_tokens + $response_data['token_count']);
        }
    }

    /**
     * Get available models
     *
     * @return array Available models
     */
    public function get_available_models() {
        return array(
            'gemini-pro' => array(
                'name' => 'Gemini Pro',
                'description' => 'Most capable model for text generation',
                'max_tokens' => 8192
            ),
            'gemini-pro-vision' => array(
                'name' => 'Gemini Pro Vision',
                'description' => 'Multimodal model for text and images',
                'max_tokens' => 4096
            )
        );
    }

    /**
     * Clear cached API data
     */
    public function clear_cache() {
        delete_transient('rag_chat_api_validation');
        delete_option('rag_chat_last_api_request');
    }

    /**
     * Get model configuration
     *
     * @return array Model configuration
     */
    public function get_model_config() {
        return array(
            'model' => $this->model,
            'temperature' => get_option('rag_chat_temperature', 0.7),
            'max_tokens' => get_option('rag_chat_max_response_length', 500),
            'timeout' => $this->timeout
        );
    }

    /**
     * Set model configuration
     *
     * @param array $config Configuration array
     */
    public function set_model_config($config) {
        if (isset($config['model'])) {
            $this->model = sanitize_text_field($config['model']);
        }
        
        if (isset($config['temperature'])) {
            update_option('rag_chat_temperature', floatval($config['temperature']));
        }
        
        if (isset($config['max_tokens'])) {
            update_option('rag_chat_max_response_length', intval($config['max_tokens']));
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = intval($config['timeout']);
        }
    }
}
