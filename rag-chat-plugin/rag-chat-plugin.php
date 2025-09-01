<?php
/**
 * Plugin Name: RAG Chat Plugin
 * Plugin URI: https://example.com/rag-chat-plugin
 * Description: A complete WordPress plugin that implements RAG (Retrieval-Augmented Generation) functionality with Google's Gemini API for intelligent chat responses based on scraped website content.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rag-chat-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RAG_CHAT_VERSION', '1.0.0');
define('RAG_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAG_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RAG_CHAT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main RAG Chat Plugin Class
 */
class RAG_Chat_Plugin {

    /**
     * Plugin instance
     *
     * @var RAG_Chat_Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return RAG_Chat_Plugin
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
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('RAG_Chat_Plugin', 'uninstall'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Setup hooks
        $this->setup_hooks();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'rag-chat-plugin',
            false,
            dirname(RAG_CHAT_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-database.php';
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-scraper.php';
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-rag-processor.php';
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-gemini-api.php';
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-chat-handler.php';
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-security.php';

        // Admin classes
        if (is_admin()) {
            require_once RAG_CHAT_PLUGIN_PATH . 'admin/class-admin.php';
            require_once RAG_CHAT_PLUGIN_PATH . 'admin/class-settings.php';
        }

        // Public classes
        if (!is_admin()) {
            require_once RAG_CHAT_PLUGIN_PATH . 'public/class-public.php';
        }
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize database
        RAG_Chat_Database::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            RAG_Chat_Admin::get_instance();
        }
        
        // Initialize public
        if (!is_admin()) {
            RAG_Chat_Public::get_instance();
        }
        
        // Initialize chat handler for AJAX
        RAG_Chat_Handler::get_instance();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_rag_chat_send_message', array('RAG_Chat_Handler', 'handle_ajax_message'));
        add_action('wp_ajax_nopriv_rag_chat_send_message', array('RAG_Chat_Handler', 'handle_ajax_message'));
        add_action('wp_ajax_rag_chat_scrape_content', array('RAG_Chat_Scraper', 'handle_ajax_scrape'));
        
        // Scheduled hooks
        add_action('rag_chat_scheduled_scrape', array('RAG_Chat_Scraper', 'scheduled_scrape'));
        
        // Content hooks for auto-scraping
        add_action('save_post', array('RAG_Chat_Scraper', 'scrape_on_save'), 10, 2);
        add_action('delete_post', array('RAG_Chat_Scraper', 'remove_on_delete'), 10, 1);
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        wp_enqueue_script(
            'rag-chat-public',
            RAG_CHAT_PLUGIN_URL . 'public/js/chat-scripts.js',
            array('jquery'),
            RAG_CHAT_VERSION,
            true
        );

        wp_enqueue_style(
            'rag-chat-public',
            RAG_CHAT_PLUGIN_URL . 'public/css/chat-styles.css',
            array(),
            RAG_CHAT_VERSION
        );

        // Localize script
        wp_localize_script('rag-chat-public', 'ragChat', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chat_nonce'),
            'settings' => $this->get_public_settings()
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'rag-chat') === false) {
            return;
        }

        wp_enqueue_script(
            'rag-chat-admin',
            RAG_CHAT_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            RAG_CHAT_VERSION,
            true
        );

        wp_enqueue_style(
            'rag-chat-admin',
            RAG_CHAT_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            RAG_CHAT_VERSION
        );

        wp_localize_script('rag-chat-admin', 'ragChatAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chat_admin_nonce'),
        ));
    }

    /**
     * Get public settings for JavaScript
     */
    private function get_public_settings() {
        return array(
            'enabled' => get_option('rag_chat_enabled', 1),
            'position' => get_option('rag_chat_position', 'bottom-right'),
            'theme' => get_option('rag_chat_theme', 'default'),
            'greeting' => get_option('rag_chat_greeting', 'Hello! How can I help you today?'),
            'placeholder' => get_option('rag_chat_placeholder', 'Type your message here...'),
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-database.php';
        RAG_Chat_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron job
        if (!wp_next_scheduled('rag_chat_scheduled_scrape')) {
            wp_schedule_event(time(), 'daily', 'rag_chat_scheduled_scrape');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('RAG Chat Plugin activated successfully');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('rag_chat_scheduled_scrape');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('RAG Chat Plugin deactivated');
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove database tables
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-database.php';
        RAG_Chat_Database::drop_tables();
        
        // Remove options
        self::remove_options();
        
        // Clear scheduled events
        wp_clear_scheduled_hook('rag_chat_scheduled_scrape');
        
        // Log uninstall
        error_log('RAG Chat Plugin uninstalled and cleaned up');
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'rag_chat_enabled' => 1,
            'rag_chat_position' => 'bottom-right',
            'rag_chat_theme' => 'default',
            'rag_chat_greeting' => 'Hello! How can I help you today?',
            'rag_chat_placeholder' => 'Type your message here...',
            'rag_chat_max_response_length' => 500,
            'rag_chat_temperature' => 0.7,
            'rag_chat_scrape_frequency' => 'daily',
            'rag_chat_content_types' => array('post', 'page'),
            'rag_chat_system_prompt' => 'You are a helpful assistant. Answer questions based on the provided context from the website.',
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Remove plugin options
     */
    private static function remove_options() {
        $options = array(
            'rag_chat_enabled',
            'rag_chat_gemini_api_key',
            'rag_chat_position',
            'rag_chat_theme',
            'rag_chat_greeting',
            'rag_chat_placeholder',
            'rag_chat_max_response_length',
            'rag_chat_temperature',
            'rag_chat_scrape_frequency',
            'rag_chat_content_types',
            'rag_chat_system_prompt',
            'rag_chat_last_scrape',
        );

        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

// Initialize the plugin
RAG_Chat_Plugin::get_instance();
