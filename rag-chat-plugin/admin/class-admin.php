<?php
/**
 * Admin interface for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Admin Class
 */
class RAG_Chat_Admin {

    /**
     * Instance of this class
     *
     * @var RAG_Chat_Admin
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var RAG_Chat_Settings
     */
    private $settings;

    /**
     * Get instance
     *
     * @return RAG_Chat_Admin
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
        $this->settings = new RAG_Chat_Settings();
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('plugin_action_links_' . RAG_CHAT_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('RAG Chat', 'rag-chat-plugin'),
            __('RAG Chat', 'rag-chat-plugin'),
            'manage_options',
            'rag-chat',
            array($this, 'display_main_page'),
            'dashicons-format-chat',
            30
        );

        // Settings submenu
        add_submenu_page(
            'rag-chat',
            __('Settings', 'rag-chat-plugin'),
            __('Settings', 'rag-chat-plugin'),
            'manage_options',
            'rag-chat-settings',
            array($this, 'display_settings_page')
        );

        // Content Management submenu
        add_submenu_page(
            'rag-chat',
            __('Content Management', 'rag-chat-plugin'),
            __('Content Management', 'rag-chat-plugin'),
            'manage_options',
            'rag-chat-content',
            array($this, 'display_content_page')
        );

        // Analytics submenu
        add_submenu_page(
            'rag-chat',
            __('Analytics', 'rag-chat-plugin'),
            __('Analytics', 'rag-chat-plugin'),
            'manage_options',
            'rag-chat-analytics',
            array($this, 'display_analytics_page')
        );

        // Chat History submenu
        add_submenu_page(
            'rag-chat',
            __('Chat History', 'rag-chat-plugin'),
            __('Chat History', 'rag-chat-plugin'),
            'manage_options',
            'rag-chat-history',
            array($this, 'display_history_page')
        );
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Initialize settings
        $this->settings->init();
        
        // Check for required configurations
        $this->check_configuration();
    }

    /**
     * Display main dashboard page
     */
    public function display_main_page() {
        $chat_handler = RAG_Chat_Handler::get_instance();
        $stats = $chat_handler->get_chat_statistics();
        $scraper = new RAG_Chat_Scraper();
        $scraper_stats = $scraper->get_scraping_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('RAG Chat Dashboard', 'rag-chat-plugin'); ?></h1>
            
            <div class="rag-chat-dashboard">
                <?php $this->display_status_cards($stats, $scraper_stats); ?>
                
                <div class="rag-chat-dashboard-grid">
                    <div class="rag-chat-dashboard-section">
                        <h2><?php _e('Quick Actions', 'rag-chat-plugin'); ?></h2>
                        <?php $this->display_quick_actions(); ?>
                    </div>
                    
                    <div class="rag-chat-dashboard-section">
                        <h2><?php _e('Recent Activity', 'rag-chat-plugin'); ?></h2>
                        <?php $this->display_recent_activity(); ?>
                    </div>
                </div>
                
                <div class="rag-chat-dashboard-section">
                    <h2><?php _e('System Status', 'rag-chat-plugin'); ?></h2>
                    <?php $this->display_system_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Redirect to settings page
     */
    public function redirect_to_settings() {
        wp_redirect(admin_url('options-general.php?page=rag_chat_settings'));
        exit;
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        // Register settings if not already done
        $this->settings->register_settings();
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'rag_chat_settings')) {
            // Debug form submission
            error_log('RAG Chat: Form submitted');
            error_log('RAG Chat: POST data: ' . print_r($_POST, true));
            $this->save_settings();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('RAG Chat Settings', 'rag-chat-plugin'); ?></h1>
            
            <!-- Debug info -->
            <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
                <strong>Debug Info:</strong><br>
                Settings registered: <?php echo get_option('rag_chat_settings_registered') ? 'Yes' : 'No'; ?><br>
                API Key exists: <?php echo get_option('rag_chat_gemini_api_key') ? 'Yes' : 'No'; ?><br>
                Sections count: <?php echo count($this->settings->get_sections()); ?><br>
                Fields count: <?php echo count($this->settings->get_fields()); ?><br>
                Current API Key: <?php echo get_option('rag_chat_gemini_api_key') ? 'Set (encrypted)' : 'Not set'; ?><br>
                Current Temperature: <?php echo get_option('rag_chat_temperature', 'Not set'); ?><br>
                Current Max Length: <?php echo get_option('rag_chat_max_response_length', 'Not set'); ?>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('rag_chat_settings'); ?>
                <?php
                settings_fields('rag_chat_settings');
                do_settings_sections('rag_chat_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $fields = $this->settings->get_fields();
        $saved_count = 0;
        
        error_log('RAG Chat: Starting to save settings');
        error_log('RAG Chat: Found ' . count($fields) . ' fields to process');
        
        foreach ($fields as $field) {
            $field_id = $field['id'];
            $value = null;
            
            if (isset($_POST[$field_id])) {
                $value = $_POST[$field_id];
                error_log('RAG Chat: Processing field ' . $field_id . ' with value: ' . (is_array($value) ? 'array' : $value));
            } elseif ($field['type'] === 'checkbox') {
                $value = 0; // Checkbox not checked
                error_log('RAG Chat: Processing checkbox ' . $field_id . ' as unchecked');
            } else {
                error_log('RAG Chat: Field ' . $field_id . ' not found in POST data');
            }
            
            if ($value !== null) {
                // Sanitize based on field type
                $sanitized_value = $this->sanitize_field_value($value, $field);
                $result = update_option($field_id, $sanitized_value);
                error_log('RAG Chat: Saved ' . $field_id . ' = ' . (is_array($sanitized_value) ? 'array' : $sanitized_value) . ' (result: ' . ($result ? 'success' : 'failed') . ')');
                $saved_count++;
            }
        }
        
        error_log('RAG Chat: Saved ' . $saved_count . ' settings');
        
        // Show success message
        if ($saved_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 sprintf(__('%d settings saved successfully!', 'rag-chat-plugin'), $saved_count) . 
                 '</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>' . 
                 __('No settings were changed.', 'rag-chat-plugin') . 
                 '</p></div>';
        }
    }

    /**
     * Sanitize field value based on field configuration
     */
    private function sanitize_field_value($value, $field) {
        $field_id = $field['id'];
        
        switch ($field['type']) {
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
                $options = array_keys($field['options']);
                return in_array($value, $options) ? $value : (isset($field['default']) ? $field['default'] : '');

            case 'multiselect':
                if (!is_array($value)) {
                    return isset($field['default']) ? $field['default'] : array();
                }
                $options = array_keys($field['options']);
                return array_intersect($value, $options);

            case 'checkbox':
                return $value ? 1 : 0;

            case 'number':
                $number = floatval($value);
                if (isset($field['min'])) {
                    $number = max($number, $field['min']);
                }
                if (isset($field['max'])) {
                    $number = min($number, $field['max']);
                }
                return $number;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Display content management page
     */
    public function display_content_page() {
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_content_actions();
        }
        
        $database = RAG_Chat_Database::get_instance();
        
        // Get content list
        global $wpdb;
        $table = $database->get_scraped_content_table();
        $content_list = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY updated_at DESC LIMIT 50"
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Management', 'rag-chat-plugin'); ?></h1>
            
            <div class="rag-chat-content-actions">
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('rag_chat_admin_action'); ?>
                    <input type="hidden" name="action" value="scrape_all">
                    <button type="submit" class="button button-primary">
                        <?php _e('Scrape All Content', 'rag-chat-plugin'); ?>
                    </button>
                </form>
                
                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <?php wp_nonce_field('rag_chat_admin_action'); ?>
                    <input type="hidden" name="action" value="clear_content">
                    <button type="submit" class="button button-secondary" 
                            onclick="return confirm('<?php _e('Are you sure you want to clear all content?', 'rag-chat-plugin'); ?>')">
                        <?php _e('Clear All Content', 'rag-chat-plugin'); ?>
                    </button>
                </form>
            </div>
            
            <h2><?php _e('Scraped Content', 'rag-chat-plugin'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Type', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Words', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Last Updated', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Actions', 'rag-chat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($content_list)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <?php _e('No content found. Click "Scrape All Content" to begin.', 'rag-chat-plugin'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($content_list as $content): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($content->title); ?></strong>
                                    <br>
                                    <small><a href="<?php echo esc_url($content->url); ?>" target="_blank">
                                        <?php echo esc_html($content->url); ?>
                                    </a></small>
                                </td>
                                <td><?php echo esc_html($content->content_type); ?></td>
                                <td><?php echo number_format($content->word_count); ?></td>
                                <td><?php echo esc_html(mysql2date('M j, Y g:i A', $content->updated_at)); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('rag_chat_admin_action'); ?>
                                        <input type="hidden" name="action" value="rescrape_content">
                                        <input type="hidden" name="content_id" value="<?php echo $content->id; ?>">
                                        <button type="submit" class="button button-small">
                                            <?php _e('Re-scrape', 'rag-chat-plugin'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Display analytics page
     */
    public function display_analytics_page() {
        $chat_handler = RAG_Chat_Handler::get_instance();
        $insights = $chat_handler->generate_insights();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Chat Analytics', 'rag-chat-plugin'); ?></h1>
            
            <div class="rag-chat-analytics">
                <div class="rag-chat-analytics-grid">
                    <div class="rag-chat-analytics-section">
                        <h2><?php _e('Common Queries', 'rag-chat-plugin'); ?></h2>
                        <?php $this->display_common_queries($insights['common_queries']); ?>
                    </div>
                    
                    <div class="rag-chat-analytics-section">
                        <h2><?php _e('Response Time Trend', 'rag-chat-plugin'); ?></h2>
                        <?php $this->display_response_time_trend($insights['response_time_trend']); ?>
                    </div>
                    
                    <div class="rag-chat-analytics-section">
                        <h2><?php _e('Engagement Patterns', 'rag-chat-plugin'); ?></h2>
                        <?php $this->display_engagement_patterns($insights['engagement_patterns']); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display chat history page
     */
    public function display_history_page() {
        global $wpdb;
        $database = RAG_Chat_Database::get_instance();
        $table = $database->get_chat_history_table();
        
        // Pagination
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $total_pages = ceil($total_items / $per_page);
        
        // Get chat history
        $chat_history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Chat History', 'rag-chat-plugin'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft">
                    <form method="post">
                        <?php wp_nonce_field('rag_chat_admin_action'); ?>
                        <input type="hidden" name="action" value="clear_old_chats">
                        <button type="submit" class="button" 
                                onclick="return confirm('<?php _e('Clear chats older than 30 days?', 'rag-chat-plugin'); ?>')">
                            <?php _e('Clear Old Chats', 'rag-chat-plugin'); ?>
                        </button>
                    </form>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(__('%s items', 'rag-chat-plugin'), number_format_i18n($total_items)); ?>
                        </span>
                        
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('User', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Message', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Response', 'rag-chat-plugin'); ?></th>
                        <th><?php _e('Response Time', 'rag-chat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chat_history)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <?php _e('No chat history found.', 'rag-chat-plugin'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chat_history as $chat): ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('M j, Y g:i A', $chat->created_at)); ?></td>
                                <td>
                                    <?php if ($chat->user_id): ?>
                                        <?php $user = get_userdata($chat->user_id); ?>
                                        <?php echo $user ? esc_html($user->display_name) : __('Unknown User', 'rag-chat-plugin'); ?>
                                    <?php else: ?>
                                        <?php _e('Guest', 'rag-chat-plugin'); ?>
                                    <?php endif; ?>
                                    <br>
                                    <small><?php echo esc_html($chat->user_ip); ?></small>
                                </td>
                                <td>
                                    <div style="max-width: 250px; overflow: hidden;">
                                        <?php echo esc_html(wp_trim_words($chat->user_message, 15)); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden;">
                                        <?php echo esc_html(wp_trim_words($chat->bot_response, 20)); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($chat->response_time): ?>
                                        <?php echo number_format($chat->response_time, 2); ?>s
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Display status cards
     */
    private function display_status_cards($stats, $scraper_stats) {
        ?>
        <div class="rag-chat-status-cards">
            <div class="rag-chat-status-card">
                <h3><?php _e('Total Chats', 'rag-chat-plugin'); ?></h3>
                <div class="stat-value"><?php echo number_format($stats['total_chats']); ?></div>
            </div>
            
            <div class="rag-chat-status-card">
                <h3><?php _e('Content Pages', 'rag-chat-plugin'); ?></h3>
                <div class="stat-value"><?php echo number_format($stats['total_content']); ?></div>
            </div>
            
            <div class="rag-chat-status-card">
                <h3><?php _e('Avg Response Time', 'rag-chat-plugin'); ?></h3>
                <div class="stat-value">
                    <?php 
                    echo $stats['avg_response_time'] ? 
                        number_format($stats['avg_response_time'], 2) . 's' : 
                        __('N/A', 'rag-chat-plugin'); 
                    ?>
                </div>
            </div>
            
            <div class="rag-chat-status-card">
                <h3><?php _e('Last Scrape', 'rag-chat-plugin'); ?></h3>
                <div class="stat-value">
                    <?php 
                    echo $stats['last_scrape'] ? 
                        human_time_diff(strtotime($stats['last_scrape'])) . ' ago' : 
                        __('Never', 'rag-chat-plugin'); 
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display quick actions
     */
    private function display_quick_actions() {
        ?>
        <div class="rag-chat-quick-actions">
            <a href="<?php echo admin_url('admin.php?page=rag-chat-settings'); ?>" class="button button-primary">
                <?php _e('Configure Settings', 'rag-chat-plugin'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=rag-chat-content'); ?>" class="button">
                <?php _e('Manage Content', 'rag-chat-plugin'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=rag-chat-analytics'); ?>" class="button">
                <?php _e('View Analytics', 'rag-chat-plugin'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Display recent activity
     */
    private function display_recent_activity() {
        global $wpdb;
        $database = RAG_Chat_Database::get_instance();
        $table = $database->get_chat_history_table();
        
        $recent_chats = $wpdb->get_results(
            "SELECT user_message, bot_response, created_at 
             FROM {$table} 
             ORDER BY created_at DESC 
             LIMIT 5"
        );
        
        if (empty($recent_chats)) {
            echo '<p>' . __('No recent activity.', 'rag-chat-plugin') . '</p>';
            return;
        }
        
        echo '<ul class="rag-chat-recent-activity">';
        foreach ($recent_chats as $chat) {
            echo '<li>';
            echo '<strong>' . esc_html(wp_trim_words($chat->user_message, 10)) . '</strong><br>';
            echo '<small>' . human_time_diff(strtotime($chat->created_at)) . ' ago</small>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Display system status
     */
    private function display_system_status() {
        $gemini_api = new RAG_Chat_Gemini_API();
        $api_configured = $gemini_api->is_api_key_configured();
        
        ?>
        <div class="rag-chat-system-status">
            <table class="form-table">
                <tr>
                    <th><?php _e('Plugin Status', 'rag-chat-plugin'); ?></th>
                    <td>
                        <?php if (get_option('rag_chat_enabled', 1)): ?>
                            <span class="status-active"><?php _e('Active', 'rag-chat-plugin'); ?></span>
                        <?php else: ?>
                            <span class="status-inactive"><?php _e('Inactive', 'rag-chat-plugin'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('API Configuration', 'rag-chat-plugin'); ?></th>
                    <td>
                        <?php if ($api_configured): ?>
                            <span class="status-active"><?php _e('Configured', 'rag-chat-plugin'); ?></span>
                        <?php else: ?>
                            <span class="status-error"><?php _e('Not Configured', 'rag-chat-plugin'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('Database Tables', 'rag-chat-plugin'); ?></th>
                    <td><span class="status-active"><?php _e('OK', 'rag-chat-plugin'); ?></span></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Display common queries
     */
    private function display_common_queries($queries) {
        if (empty($queries)) {
            echo '<p>' . __('No data available.', 'rag-chat-plugin') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($queries as $query) {
            echo '<li>';
            echo esc_html(wp_trim_words($query->user_message, 10));
            echo ' <span class="count">(' . $query->count . ')</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Display response time trend
     */
    private function display_response_time_trend($trend_data) {
        if (empty($trend_data)) {
            echo '<p>' . __('No data available.', 'rag-chat-plugin') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($trend_data as $data) {
            echo '<li>';
            echo esc_html($data->date) . ': ';
            echo number_format($data->avg_time, 2) . 's';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Display engagement patterns
     */
    private function display_engagement_patterns($patterns) {
        if (empty($patterns)) {
            echo '<p>' . __('No data available.', 'rag-chat-plugin') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($patterns as $pattern) {
            $hour_label = $pattern->hour . ':00 - ' . ($pattern->hour + 1) . ':00';
            echo '<li>';
            echo esc_html($hour_label) . ': ';
            echo number_format($pattern->messages) . ' messages';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Handle content management actions
     */
    private function handle_content_actions() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'rag_chat_admin_action')) {
            wp_die(__('Security check failed.', 'rag-chat-plugin'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'scrape_all':
                $scraper = new RAG_Chat_Scraper();
                $results = $scraper->scrape_all_content();
                
                add_settings_error(
                    'rag_chat_admin',
                    'scrape_complete',
                    sprintf(
                        __('Scraping completed. Success: %d, Errors: %d, Skipped: %d', 'rag-chat-plugin'),
                        $results['success'],
                        $results['errors'],
                        $results['skipped']
                    ),
                    'updated'
                );
                break;
                
            case 'clear_content':
                global $wpdb;
                $database = RAG_Chat_Database::get_instance();
                $table = $database->get_scraped_content_table();
                $wpdb->query("DELETE FROM {$table}");
                
                add_settings_error(
                    'rag_chat_admin',
                    'content_cleared',
                    __('All content has been cleared.', 'rag-chat-plugin'),
                    'updated'
                );
                break;
                
            case 'clear_old_chats':
                $chat_handler = RAG_Chat_Handler::get_instance();
                $deleted = $chat_handler->clean_old_chats(30);
                
                add_settings_error(
                    'rag_chat_admin',
                    'chats_cleared',
                    sprintf(__('Deleted %d old chat records.', 'rag-chat-plugin'), $deleted),
                    'updated'
                );
                break;
        }
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        settings_errors('rag_chat_admin');
        
        // Check if API key is configured
        $gemini_api = new RAG_Chat_Gemini_API();
        if (!$gemini_api->is_api_key_configured() && isset($_GET['page']) && strpos($_GET['page'], 'rag-chat') === 0) {
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
        }
    }

    /**
     * Check plugin configuration
     */
    private function check_configuration() {
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
     * Add action links to plugin page
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=rag-chat-settings') . '">' . 
                        __('Settings', 'rag-chat-plugin') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
