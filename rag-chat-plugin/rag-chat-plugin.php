<?php
/**
 * Plugin Name: RAG Chat Plugin
 * Plugin URI: https://example.com/rag-chat-plugin
 * Description: A complete WordPress plugin that implements RAG (Retrieval-Augmented Generation) functionality with Google's Gemini API for intelligent chat responses based on scraped website content.
 * Version: 2.0.0
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
 * Update URI: https://example.com/rag-chat-plugin/updates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RAG_CHAT_VERSION', '2.0.0');
define('RAG_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAG_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RAG_CHAT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('RAG_CHAT_MIN_PHP_VERSION', '7.4');
define('RAG_CHAT_MIN_WP_VERSION', '5.0');

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
     * Plugin components
     *
     * @var array
     */
    private $components = array();

    /**
     * Error logger
     *
     * @var RAG_Chat_Logger
     */
    private $logger;

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
        // Check system requirements
        if (!$this->check_requirements()) {
            return;
        }

        // Initialize logger first
        $this->init_logger();
        
        // Setup hooks
        $this->setup_hooks();
        
        // Initialize components
        $this->init_components();
        
        // Log plugin initialization
        $this->logger->info('RAG Chat Plugin initialized successfully', array('version' => RAG_CHAT_VERSION));
    }

    /**
     * Check system requirements
     *
     * @return bool Requirements met
     */
    private function check_requirements() {
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, RAG_CHAT_MIN_PHP_VERSION, '<')) {
            $errors[] = sprintf(
                'RAG Chat Plugin requires PHP version %s or higher. Current version: %s',
                RAG_CHAT_MIN_PHP_VERSION,
                PHP_VERSION
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), RAG_CHAT_MIN_WP_VERSION, '<')) {
            $errors[] = sprintf(
                'RAG Chat Plugin requires WordPress version %s or higher. Current version: %s',
                RAG_CHAT_MIN_WP_VERSION,
                get_bloginfo('version')
            );
        }

        // Check required PHP extensions
        $required_extensions = array('curl', 'openssl', 'json', 'mbstring');
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = sprintf('RAG Chat Plugin requires PHP extension: %s', $ext);
            }
        }

        // Display errors if any
        if (!empty($errors)) {
            add_action('admin_notices', function() use ($errors) {
                echo '<div class="notice notice-error"><p>' . implode('<br>', $errors) . '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Initialize logger
     */
    private function init_logger() {
        require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-logger.php';
        $this->logger = new RAG_Chat_Logger();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('RAG_Chat_Plugin', 'uninstall'));

        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        try {
            // Load required files
            $this->load_dependencies();
            
            // Setup hooks
            $this->setup_component_hooks();
            
            // Initialize components
            $this->init_components();
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize plugin', array('error' => $e->getMessage()));
        }
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
        $required_files = array(
            'includes/class-database.php',
            'includes/class-scraper.php',
            'includes/class-rag-processor.php',
            'includes/class-gemini-api.php',
            'includes/class-chat-handler.php',
            'includes/class-security.php',
            'includes/class-cache.php',
            'includes/class-rate-limiter.php'
        );

        foreach ($required_files as $file) {
            $file_path = RAG_CHAT_PLUGIN_PATH . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Required file not found: {$file}");
            }
            require_once $file_path;
        }

        // Load admin classes
        if (is_admin()) {
            require_once RAG_CHAT_PLUGIN_PATH . 'admin/class-admin.php';
            require_once RAG_CHAT_PLUGIN_PATH . 'admin/class-settings.php';
        }

        // Load public classes
        if (!is_admin()) {
            require_once RAG_CHAT_PLUGIN_PATH . 'public/class-public.php';
        }
    }

    /**
     * Setup component hooks
     */
    private function setup_component_hooks() {
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
        
        // Cleanup hooks
        add_action('rag_chat_cleanup_old_data', array($this, 'cleanup_old_data'));
    }

    /**
     * Initialize components
     */
    private function init_components() {
        try {
            // Initialize database
            $this->components['database'] = RAG_Chat_Database::get_instance();
            
            // Initialize cache
            $this->components['cache'] = new RAG_Chat_Cache();
            
            // Initialize rate limiter
            $this->components['rate_limiter'] = new RAG_Chat_Rate_Limiter();
            
            // Initialize admin
            if (is_admin()) {
                $this->components['admin'] = RAG_Chat_Admin::get_instance();
            }
            
            // Initialize public
            if (!is_admin()) {
                $this->components['public'] = RAG_Chat_Public::get_instance();
            }
            
            // Initialize chat handler for AJAX
            $this->components['chat_handler'] = RAG_Chat_Handler::get_instance();
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize components', array('error' => $e->getMessage()));
        }
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Check if chat is enabled
        if (!get_option('rag_chat_enabled', 1)) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'rag-chat-public',
            RAG_CHAT_PLUGIN_URL . 'public/js/chat-scripts.js',
            array('jquery'),
            RAG_CHAT_VERSION,
            true
        );

        // Enqueue CSS
        wp_enqueue_style(
            'rag-chat-public',
            RAG_CHAT_PLUGIN_URL . 'public/css/chat-styles.css',
            array(),
            RAG_CHAT_VERSION
        );

        // Localize script with settings
        wp_localize_script('rag-chat-public', 'ragChat', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chat_nonce'),
            'settings' => $this->get_public_settings(),
            'strings' => $this->get_localized_strings()
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
            array('jquery', 'wp-util'),
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
            'strings' => $this->get_admin_localized_strings()
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
            'maxMessageLength' => get_option('rag_chat_max_message_length', 1000),
            'typingIndicator' => get_option('rag_chat_typing_indicator', 1),
            'autoScroll' => get_option('rag_chat_auto_scroll', 1),
            'soundEnabled' => get_option('rag_chat_sound_enabled', 0),
        );
    }

    /**
     * Get localized strings for public
     */
    private function get_localized_strings() {
        return array(
            'error_message' => __('Sorry, something went wrong. Please try again.', 'rag-chat-plugin'),
            'network_error' => __('Network error. Please check your connection.', 'rag-chat-plugin'),
            'typing' => __('AI is typing...', 'rag-chat-plugin'),
            'send' => __('Send', 'rag-chat-plugin'),
            'minimize' => __('Minimize', 'rag-chat-plugin'),
            'close' => __('Close', 'rag-chat-plugin'),
            'message_too_long' => __('Message is too long. Please shorten it.', 'rag-chat-plugin'),
            'rate_limited' => __('Too many messages. Please wait a moment.', 'rag-chat-plugin'),
        );
    }

    /**
     * Get localized strings for admin
     */
    private function get_admin_localized_strings() {
        return array(
            'confirm_delete' => __('Are you sure you want to delete this?', 'rag-chat-plugin'),
            'saving' => __('Saving...', 'rag-chat-plugin'),
            'saved' => __('Settings saved successfully!', 'rag-chat-plugin'),
            'error' => __('An error occurred. Please try again.', 'rag-chat-plugin'),
            'testing_api' => __('Testing API connection...', 'rag-chat-plugin'),
            'api_success' => __('API connection successful!', 'rag-chat-plugin'),
            'api_error' => __('API connection failed.', 'rag-chat-plugin'),
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        try {
            // Create database tables
            require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-database.php';
            RAG_Chat_Database::create_tables();
            
            // Set default options
            $this->set_default_options();
            
            // Schedule cron jobs
            $this->schedule_cron_jobs();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Log activation
            $this->logger->info('Plugin activated successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Plugin activation failed', array('error' => $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('rag_chat_scheduled_scrape');
            wp_clear_scheduled_hook('rag_chat_cleanup_old_data');
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Log deactivation
            $this->logger->info('Plugin deactivated');
            
        } catch (Exception $e) {
            $this->logger->error('Plugin deactivation failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        try {
            // Remove database tables
            require_once RAG_CHAT_PLUGIN_PATH . 'includes/class-database.php';
            RAG_Chat_Database::drop_tables();
            
            // Remove options
            self::remove_options();
            
            // Clear scheduled events
            wp_clear_scheduled_hook('rag_chat_scheduled_scrape');
            wp_clear_scheduled_hook('rag_chat_cleanup_old_data');
            
            // Clear cache
            wp_cache_flush();
            
            // Log uninstall
            error_log('RAG Chat Plugin uninstalled and cleaned up');
            
        } catch (Exception $e) {
            error_log('RAG Chat Plugin uninstall error: ' . $e->getMessage());
        }
    }

    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Schedule scraping
        if (!wp_next_scheduled('rag_chat_scheduled_scrape')) {
            $frequency = get_option('rag_chat_scrape_frequency', 'daily');
            wp_schedule_event(time(), $frequency, 'rag_chat_scheduled_scrape');
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('rag_chat_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'rag_chat_cleanup_old_data');
        }
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
            'rag_chat_max_message_length' => 1000,
            'rag_chat_temperature' => 0.7,
            'rag_chat_scrape_frequency' => 'daily',
            'rag_chat_content_types' => array('post', 'page'),
            'rag_chat_system_prompt' => 'You are a helpful assistant. Answer questions based on the provided context from the website.',
            'rag_chat_auto_scrape' => 1,
            'rag_chat_typing_indicator' => 1,
            'rag_chat_auto_scroll' => 1,
            'rag_chat_sound_enabled' => 0,
            'rag_chat_rate_limit' => 10,
            'rag_chat_cache_duration' => 3600,
            'rag_chat_log_level' => 'error',
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
            'rag_chat_max_message_length',
            'rag_chat_temperature',
            'rag_chat_scrape_frequency',
            'rag_chat_content_types',
            'rag_chat_system_prompt',
            'rag_chat_auto_scrape',
            'rag_chat_typing_indicator',
            'rag_chat_auto_scroll',
            'rag_chat_sound_enabled',
            'rag_chat_rate_limit',
            'rag_chat_cache_duration',
            'rag_chat_log_level',
            'rag_chat_last_scrape',
            'rag_chat_last_scrape_results',
        );

        foreach ($options as $option) {
            delete_option($option);
        }
    }

    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        try {
            // Clean old chat history
            $chat_handler = RAG_Chat_Handler::get_instance();
            $deleted_chats = $chat_handler->clean_old_chats(30);
            
            // Clean old cache
            $cache = new RAG_Chat_Cache();
            $deleted_cache = $cache->cleanup_old_cache();
            
            // Log cleanup
            $this->logger->info('Cleanup completed', array(
                'deleted_chats' => $deleted_chats,
                'deleted_cache' => $deleted_cache
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Cleanup failed', array('error' => $e->getMessage()));
        }
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check for required configurations
        $this->check_configuration();
    }

    /**
     * Check plugin configuration
     */
    private function check_configuration() {
        // Check if API key is configured
        $gemini_api = new RAG_Chat_Gemini_API();
        if (!$gemini_api->is_api_key_configured()) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('RAG Chat Plugin requires a Google Gemini API key to function.', 'rag-chat-plugin'); ?>
                        <a href="<?php echo admin_url('admin.php?page=rag-chat-settings'); ?>">
                            <?php _e('Configure it now', 'rag-chat-plugin'); ?>
                        </a>
                    </p>
                </div>
                <?php
            });
        }

        // Check if content has been scraped
        $database = RAG_Chat_Database::get_instance();
        $stats = $database->get_statistics();
        
        if ($stats['total_content'] == 0 && get_option('rag_chat_setup_notice_dismissed') !== '1') {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-info is-dismissible" data-dismissible="rag_chat_setup_notice">
                    <p>
                        <?php _e('Welcome to RAG Chat! To get started, please scrape your website content.', 'rag-chat-plugin'); ?>
                        <a href="<?php echo admin_url('admin.php?page=rag-chat-content'); ?>">
                            <?php _e('Manage Content', 'rag-chat-plugin'); ?>
                        </a>
                    </p>
                </div>
                <?php
            });
        }
    }

    /**
     * Get component
     *
     * @param string $name Component name
     * @return mixed Component instance
     */
    public function get_component($name) {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }

    /**
     * Get logger
     *
     * @return RAG_Chat_Logger
     */
    public function get_logger() {
        return $this->logger;
    }
}

// Initialize the plugin
RAG_Chat_Plugin::get_instance();
