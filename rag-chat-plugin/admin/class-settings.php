<?php
/**
 * Settings management for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Settings Class
 */
class RAG_Chat_Settings {

    /**
     * Settings sections
     *
     * @var array
     */
    private $sections = array();

    /**
     * Settings fields
     *
     * @var array
     */
    private $fields = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_sections();
        $this->setup_fields();
    }

    /**
     * Get sections
     */
    public function get_sections() {
        return $this->sections;
    }

    /**
     * Get fields
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Initialize settings
     */
    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_rag_chat_test_api', array($this, 'ajax_test_api'));
    }

    /**
     * Setup settings sections
     */
    private function setup_sections() {
        $this->sections = array(
            array(
                'id' => 'rag_chat_general',
                'title' => __('General Settings', 'rag-chat-plugin'),
                'description' => __('Configure basic plugin settings.', 'rag-chat-plugin')
            ),
            array(
                'id' => 'rag_chat_api',
                'title' => __('API Configuration', 'rag-chat-plugin'),
                'description' => __('Configure Google Gemini API settings.', 'rag-chat-plugin')
            ),
            array(
                'id' => 'rag_chat_scraping',
                'title' => __('Content Scraping', 'rag-chat-plugin'),
                'description' => __('Configure content scraping and indexing options.', 'rag-chat-plugin')
            ),
            array(
                'id' => 'rag_chat_chat',
                'title' => __('Chat Behavior', 'rag-chat-plugin'),
                'description' => __('Configure chat interface and AI behavior.', 'rag-chat-plugin')
            ),
            array(
                'id' => 'rag_chat_appearance',
                'title' => __('Appearance', 'rag-chat-plugin'),
                'description' => __('Customize the chat interface appearance.', 'rag-chat-plugin')
            )
        );
    }

    /**
     * Setup settings fields
     */
    private function setup_fields() {
        $this->fields = array(
            // General Settings
            array(
                'id' => 'rag_chat_enabled',
                'title' => __('Enable Chat', 'rag-chat-plugin'),
                'type' => 'checkbox',
                'section' => 'rag_chat_general',
                'description' => __('Enable or disable the chat functionality.', 'rag-chat-plugin'),
                'default' => 1
            ),
            
            // API Configuration
            array(
                'id' => 'rag_chat_gemini_api_key',
                'title' => __('Gemini API Key', 'rag-chat-plugin'),
                'type' => 'password',
                'section' => 'rag_chat_api',
                'description' => __('Enter your Google Gemini API key. <a href="https://makersuite.google.com/app/apikey" target="_blank">Get API key</a>', 'rag-chat-plugin'),
                'validation' => 'api_key'
            ),
            array(
                'id' => 'rag_chat_temperature',
                'title' => __('AI Temperature', 'rag-chat-plugin'),
                'type' => 'number',
                'section' => 'rag_chat_api',
                'description' => __('Controls randomness in AI responses (0.0 - 2.0). Lower = more focused, Higher = more creative.', 'rag-chat-plugin'),
                'default' => 0.7,
                'min' => 0,
                'max' => 2,
                'step' => 0.1
            ),
            array(
                'id' => 'rag_chat_max_response_length',
                'title' => __('Max Response Length', 'rag-chat-plugin'),
                'type' => 'number',
                'section' => 'rag_chat_api',
                'description' => __('Maximum number of tokens in AI responses.', 'rag-chat-plugin'),
                'default' => 500,
                'min' => 50,
                'max' => 2048
            ),
            
            // Content Scraping
            array(
                'id' => 'rag_chat_auto_scrape',
                'title' => __('Auto-scrape Content', 'rag-chat-plugin'),
                'type' => 'checkbox',
                'section' => 'rag_chat_scraping',
                'description' => __('Automatically scrape content when posts are created or updated.', 'rag-chat-plugin'),
                'default' => 1
            ),
            array(
                'id' => 'rag_chat_content_types',
                'title' => __('Content Types to Scrape', 'rag-chat-plugin'),
                'type' => 'multiselect',
                'section' => 'rag_chat_scraping',
                'description' => __('Select which content types to include in scraping.', 'rag-chat-plugin'),
                'options' => $this->get_content_types(),
                'default' => array('post', 'page')
            ),
            array(
                'id' => 'rag_chat_scrape_frequency',
                'title' => __('Scraping Frequency', 'rag-chat-plugin'),
                'type' => 'select',
                'section' => 'rag_chat_scraping',
                'description' => __('How often to automatically scrape all content.', 'rag-chat-plugin'),
                'options' => array(
                    'never' => __('Never (Manual only)', 'rag-chat-plugin'),
                    'daily' => __('Daily', 'rag-chat-plugin'),
                    'weekly' => __('Weekly', 'rag-chat-plugin'),
                    'monthly' => __('Monthly', 'rag-chat-plugin')
                ),
                'default' => 'daily'
            ),
            
            // Chat Behavior
            array(
                'id' => 'rag_chat_system_prompt',
                'title' => __('System Prompt', 'rag-chat-plugin'),
                'type' => 'textarea',
                'section' => 'rag_chat_chat',
                'description' => __('Instructions for the AI on how to behave and respond.', 'rag-chat-plugin'),
                'default' => 'You are a helpful assistant. Answer questions based on the provided context from the website. Be concise and accurate.'
            ),
            array(
                'id' => 'rag_chat_greeting',
                'title' => __('Greeting Message', 'rag-chat-plugin'),
                'type' => 'text',
                'section' => 'rag_chat_chat',
                'description' => __('Initial message shown to users when they open the chat.', 'rag-chat-plugin'),
                'default' => 'Hello! How can I help you today?'
            ),
            array(
                'id' => 'rag_chat_placeholder',
                'title' => __('Input Placeholder', 'rag-chat-plugin'),
                'type' => 'text',
                'section' => 'rag_chat_chat',
                'description' => __('Placeholder text for the chat input field.', 'rag-chat-plugin'),
                'default' => 'Type your message here...'
            ),
            
            // Appearance
            array(
                'id' => 'rag_chat_position',
                'title' => __('Chat Position', 'rag-chat-plugin'),
                'type' => 'select',
                'section' => 'rag_chat_appearance',
                'description' => __('Where to display the chat widget on the website.', 'rag-chat-plugin'),
                'options' => array(
                    'bottom-right' => __('Bottom Right', 'rag-chat-plugin'),
                    'bottom-left' => __('Bottom Left', 'rag-chat-plugin'),
                    'top-right' => __('Top Right', 'rag-chat-plugin'),
                    'top-left' => __('Top Left', 'rag-chat-plugin')
                ),
                'default' => 'bottom-right'
            ),
            array(
                'id' => 'rag_chat_theme',
                'title' => __('Color Theme', 'rag-chat-plugin'),
                'type' => 'select',
                'section' => 'rag_chat_appearance',
                'description' => __('Visual theme for the chat interface.', 'rag-chat-plugin'),
                'options' => array(
                    'default' => __('Default', 'rag-chat-plugin'),
                    'dark' => __('Dark', 'rag-chat-plugin'),
                    'light' => __('Light', 'rag-chat-plugin'),
                    'minimal' => __('Minimal', 'rag-chat-plugin')
                ),
                'default' => 'default'
            ),
            array(
                'id' => 'rag_chat_widget_title',
                'title' => __('Widget Title', 'rag-chat-plugin'),
                'type' => 'text',
                'section' => 'rag_chat_appearance',
                'description' => __('Title displayed in the chat widget header.', 'rag-chat-plugin'),
                'default' => 'Chat Assistant'
            )
        );
    }

    /**
     * Register all settings
     */
    public function register_settings() {
        // Register sections
        foreach ($this->sections as $section) {
            add_settings_section(
                $section['id'],
                $section['title'],
                array($this, 'section_callback'),
                'rag_chat_settings'
            );
        }

        // Register fields
        foreach ($this->fields as $field) {
            register_setting(
                'rag_chat_settings',
                $field['id'],
                array(
                    'sanitize_callback' => array($this, 'sanitize_field'),
                    'default' => isset($field['default']) ? $field['default'] : ''
                )
            );

            add_settings_field(
                $field['id'],
                $field['title'],
                array($this, 'field_callback'),
                'rag_chat_settings',
                $field['section'],
                $field
            );
        }
    }

    /**
     * Section callback
     */
    public function section_callback($args) {
        foreach ($this->sections as $section) {
            if ($section['id'] === $args['id']) {
                echo '<p>' . esc_html($section['description']) . '</p>';
                break;
            }
        }
    }

    /**
     * Field callback
     */
    public function field_callback($field) {
        $value = get_option($field['id'], isset($field['default']) ? $field['default'] : '');
        $name = $field['id'];
        $id = $field['id'];

        switch ($field['type']) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;

            case 'password':
                echo '<input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                
                // Add API test button for API key field
                if ($field['id'] === 'rag_chat_gemini_api_key') {
                    echo '<br><button type="button" id="test-api-key" class="button button-secondary" style="margin-top: 5px;">' . 
                         __('Test API Key', 'rag-chat-plugin') . '</button>';
                    echo '<div id="api-test-result" style="margin-top: 5px;"></div>';
                }
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="5" cols="50" class="large-text">' . 
                     esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
                foreach ($field['options'] as $option_value => $option_label) {
                    $selected = selected($value, $option_value, false);
                    echo '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . 
                         esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'multiselect':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '[]" multiple size="5" style="width: 200px;">';
                foreach ($field['options'] as $option_value => $option_label) {
                    $selected = in_array($option_value, (array) $value) ? 'selected="selected"' : '';
                    echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . 
                         esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'checkbox':
                $checked = checked($value, 1, false);
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . $checked . ' />';
                break;

            case 'number':
                $min = isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : '';
                $max = isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : '';
                $step = isset($field['step']) ? 'step="' . esc_attr($field['step']) . '"' : '';
                
                echo '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . 
                     esc_attr($value) . '" ' . $min . ' ' . $max . ' ' . $step . ' class="small-text" />';
                break;
        }

        if (isset($field['description'])) {
            echo '<p class="description">' . wp_kses_post($field['description']) . '</p>';
        }
    }

    /**
     * Sanitize field values
     */
    public function sanitize_field($value) {
        $field_id = '';
        
        // Get the field ID from the current request
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'rag_chat_settings') {
            foreach ($_POST as $key => $val) {
                if (strpos($key, 'rag_chat_') === 0) {
                    $field_id = $key;
                    break;
                }
            }
        }

        // Find field configuration
        $field_config = null;
        foreach ($this->fields as $field) {
            if ($field['id'] === $field_id) {
                $field_config = $field;
                break;
            }
        }

        if (!$field_config) {
            return RAG_Chat_Security::sanitize_settings(array($field_id => $value))[$field_id] ?? $value;
        }

        // Sanitize based on field type
        switch ($field_config['type']) {
            case 'text':
                return sanitize_text_field($value);

            case 'password':
                if ($field_id === 'rag_chat_gemini_api_key') {
                    $sanitized = RAG_Chat_Security::sanitize_api_key($value);
                    return RAG_Chat_Security::encrypt_api_key($sanitized);
                }
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'select':
                $options = array_keys($field_config['options']);
                return in_array($value, $options) ? $value : $field_config['default'];

            case 'multiselect':
                if (!is_array($value)) {
                    return isset($field_config['default']) ? $field_config['default'] : array();
                }
                $options = array_keys($field_config['options']);
                return array_intersect($value, $options);

            case 'checkbox':
                return $value ? 1 : 0;

            case 'number':
                $number = floatval($value);
                if (isset($field_config['min'])) {
                    $number = max($number, $field_config['min']);
                }
                if (isset($field_config['max'])) {
                    $number = min($number, $field_config['max']);
                }
                return $number;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Get available content types
     */
    private function get_content_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $options = array();
        
        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->labels->name;
        }
        
        return $options;
    }

    /**
     * AJAX handler for API key testing
     */
    public function ajax_test_api() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
        }

        // Test the API key
        $gemini_api = new RAG_Chat_Gemini_API();
        $result = $gemini_api->validate_api_key($api_key);

        if ($result['valid']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Get all plugin options
     */
    public function get_all_options() {
        $options = array();
        
        foreach ($this->fields as $field) {
            $options[$field['id']] = get_option($field['id'], isset($field['default']) ? $field['default'] : '');
        }
        
        return $options;
    }

    /**
     * Reset all settings to defaults
     */
    public function reset_to_defaults() {
        foreach ($this->fields as $field) {
            if (isset($field['default'])) {
                update_option($field['id'], $field['default']);
            } else {
                delete_option($field['id']);
            }
        }
    }

    /**
     * Export settings
     */
    public function export_settings() {
        $settings = $this->get_all_options();
        
        // Don't export sensitive data
        unset($settings['rag_chat_gemini_api_key']);
        
        return $settings;
    }

    /**
     * Import settings
     */
    public function import_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }

        $imported = 0;
        foreach ($this->fields as $field) {
            $field_id = $field['id'];
            
            // Skip sensitive fields
            if ($field_id === 'rag_chat_gemini_api_key') {
                continue;
            }
            
            if (isset($settings[$field_id])) {
                $sanitized_value = $this->sanitize_field($settings[$field_id]);
                update_option($field_id, $sanitized_value);
                $imported++;
            }
        }

        return $imported;
    }
}
