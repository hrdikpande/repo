<?php
/**
 * Public frontend functionality for RAG Chat Plugin
 *
 * @package RAG_Chat_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RAG Chat Public Class
 */
class RAG_Chat_Public {

    /**
     * Instance of this class
     *
     * @var RAG_Chat_Public
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RAG_Chat_Public
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
        $this->init_hooks();
    }

    /**
     * Initialize public hooks
     */
    private function init_hooks() {
        add_action('wp_footer', array($this, 'render_chat_widget'));
        add_action('wp_head', array($this, 'add_chat_styles'));
        add_shortcode('rag_chat', array($this, 'chat_shortcode'));
    }

    /**
     * Render chat widget in footer
     */
    public function render_chat_widget() {
        // Check if chat is enabled
        if (!get_option('rag_chat_enabled', 1)) {
            return;
        }

        // Don't show on admin pages
        if (is_admin()) {
            return;
        }

        // Get settings
        $position = get_option('rag_chat_position', 'bottom-right');
        $theme = get_option('rag_chat_theme', 'default');
        $widget_title = get_option('rag_chat_widget_title', 'Chat Assistant');
        $greeting = get_option('rag_chat_greeting', 'Hello! How can I help you today?');
        $placeholder = get_option('rag_chat_placeholder', 'Type your message here...');

        // Generate unique session ID
        $session_id = RAG_Chat_Security::generate_session_id();

        ?>
        <div id="rag-chat-widget" class="rag-chat-widget rag-chat-position-<?php echo esc_attr($position); ?> rag-chat-theme-<?php echo esc_attr($theme); ?>" data-session-id="<?php echo esc_attr($session_id); ?>">
            <!-- Chat toggle button -->
            <div id="rag-chat-toggle" class="rag-chat-toggle">
                <span class="rag-chat-toggle-icon">💬</span>
                <span class="rag-chat-toggle-text"><?php echo esc_html($widget_title); ?></span>
            </div>

            <!-- Chat container -->
            <div id="rag-chat-container" class="rag-chat-container" style="display: none;">
                <!-- Chat header -->
                <div class="rag-chat-header">
                    <div class="rag-chat-header-title">
                        <span class="rag-chat-header-icon">🤖</span>
                        <?php echo esc_html($widget_title); ?>
                    </div>
                    <div class="rag-chat-header-actions">
                        <button id="rag-chat-minimize" class="rag-chat-action-btn" title="<?php _e('Minimize', 'rag-chat-plugin'); ?>">−</button>
                        <button id="rag-chat-close" class="rag-chat-action-btn" title="<?php _e('Close', 'rag-chat-plugin'); ?>">×</button>
                    </div>
                </div>

                <!-- Chat messages area -->
                <div id="rag-chat-messages" class="rag-chat-messages">
                    <div class="rag-chat-message rag-chat-message-bot">
                        <div class="rag-chat-message-avatar">🤖</div>
                        <div class="rag-chat-message-content">
                            <div class="rag-chat-message-text"><?php echo esc_html($greeting); ?></div>
                            <div class="rag-chat-message-time"><?php echo date('H:i'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div id="rag-chat-typing" class="rag-chat-typing" style="display: none;">
                    <div class="rag-chat-message rag-chat-message-bot">
                        <div class="rag-chat-message-avatar">🤖</div>
                        <div class="rag-chat-message-content">
                            <div class="rag-chat-typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat input area -->
                <div class="rag-chat-input-area">
                    <div class="rag-chat-input-container">
                        <textarea id="rag-chat-input" 
                                class="rag-chat-input" 
                                placeholder="<?php echo esc_attr($placeholder); ?>" 
                                rows="1"></textarea>
                        <button id="rag-chat-send" class="rag-chat-send-btn" title="<?php _e('Send message', 'rag-chat-plugin'); ?>">
                            <span class="rag-chat-send-icon">→</span>
                        </button>
                    </div>
                    <div class="rag-chat-input-footer">
                        <small><?php _e('Powered by AI', 'rag-chat-plugin'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add chat styles to head
     */
    public function add_chat_styles() {
        if (!get_option('rag_chat_enabled', 1) || is_admin()) {
            return;
        }

        // Get theme settings
        $theme = get_option('rag_chat_theme', 'default');
        
        ?>
        <style id="rag-chat-inline-styles">
            /* Prevent scrollbar when chat is open */
            body.rag-chat-open {
                overflow-x: hidden;
            }
            
            /* Widget positioning */
            .rag-chat-position-bottom-right {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
            }
            
            .rag-chat-position-bottom-left {
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 9999;
            }
            
            .rag-chat-position-top-right {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
            }
            
            .rag-chat-position-top-left {
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 9999;
            }
            
            /* Mobile responsiveness */
            @media (max-width: 768px) {
                .rag-chat-widget {
                    position: fixed !important;
                    bottom: 10px !important;
                    right: 10px !important;
                    left: 10px !important;
                    top: auto !important;
                }
                
                .rag-chat-container {
                    width: 100% !important;
                    height: 70vh !important;
                    max-height: 500px !important;
                }
            }
        </style>
        <?php
    }

    /**
     * Chat shortcode handler
     */
    public function chat_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '400px',
            'width' => '100%',
            'theme' => get_option('rag_chat_theme', 'default'),
            'title' => get_option('rag_chat_widget_title', 'Chat Assistant')
        ), $atts);

        // Check if chat is enabled
        if (!get_option('rag_chat_enabled', 1)) {
            return '<p>' . __('Chat is currently disabled.', 'rag-chat-plugin') . '</p>';
        }

        $session_id = RAG_Chat_Security::generate_session_id();
        $greeting = get_option('rag_chat_greeting', 'Hello! How can I help you today?');
        $placeholder = get_option('rag_chat_placeholder', 'Type your message here...');

        ob_start();
        ?>
        <div class="rag-chat-shortcode rag-chat-theme-<?php echo esc_attr($atts['theme']); ?>" 
             data-session-id="<?php echo esc_attr($session_id); ?>"
             style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
            
            <div class="rag-chat-shortcode-container">
                <!-- Chat header -->
                <div class="rag-chat-header">
                    <div class="rag-chat-header-title">
                        <span class="rag-chat-header-icon">🤖</span>
                        <?php echo esc_html($atts['title']); ?>
                    </div>
                </div>

                <!-- Chat messages area -->
                <div class="rag-chat-messages" style="height: calc(100% - 120px); overflow-y: auto;">
                    <div class="rag-chat-message rag-chat-message-bot">
                        <div class="rag-chat-message-avatar">🤖</div>
                        <div class="rag-chat-message-content">
                            <div class="rag-chat-message-text"><?php echo esc_html($greeting); ?></div>
                            <div class="rag-chat-message-time"><?php echo date('H:i'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div class="rag-chat-typing" style="display: none;">
                    <div class="rag-chat-message rag-chat-message-bot">
                        <div class="rag-chat-message-avatar">🤖</div>
                        <div class="rag-chat-message-content">
                            <div class="rag-chat-typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat input area -->
                <div class="rag-chat-input-area">
                    <div class="rag-chat-input-container">
                        <textarea class="rag-chat-input" 
                                placeholder="<?php echo esc_attr($placeholder); ?>" 
                                rows="1"></textarea>
                        <button class="rag-chat-send-btn" title="<?php _e('Send message', 'rag-chat-plugin'); ?>">
                            <span class="rag-chat-send-icon">→</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Check if current page should show chat
     */
    private function should_show_chat() {
        // Don't show on admin pages
        if (is_admin()) {
            return false;
        }

        // Check if chat is enabled
        if (!get_option('rag_chat_enabled', 1)) {
            return false;
        }

        // Allow filtering
        return apply_filters('rag_chat_should_show', true);
    }

    /**
     * Get chat configuration for JavaScript
     */
    public function get_chat_config() {
        return array(
            'enabled' => get_option('rag_chat_enabled', 1),
            'position' => get_option('rag_chat_position', 'bottom-right'),
            'theme' => get_option('rag_chat_theme', 'default'),
            'greeting' => get_option('rag_chat_greeting', 'Hello! How can I help you today?'),
            'placeholder' => get_option('rag_chat_placeholder', 'Type your message here...'),
            'widget_title' => get_option('rag_chat_widget_title', 'Chat Assistant'),
            'api_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chat_nonce'),
            'strings' => array(
                'error_message' => __('Sorry, something went wrong. Please try again.', 'rag-chat-plugin'),
                'network_error' => __('Network error. Please check your connection.', 'rag-chat-plugin'),
                'typing' => __('AI is typing...', 'rag-chat-plugin'),
                'send' => __('Send', 'rag-chat-plugin'),
                'minimize' => __('Minimize', 'rag-chat-plugin'),
                'close' => __('Close', 'rag-chat-plugin')
            )
        );
    }

    /**
     * Add custom CSS for themes
     */
    public function add_theme_styles($theme) {
        $custom_css = '';
        
        switch ($theme) {
            case 'dark':
                $custom_css = '
                    .rag-chat-theme-dark {
                        --rag-chat-bg-primary: #2c2c2c;
                        --rag-chat-bg-secondary: #3c3c3c;
                        --rag-chat-text-primary: #ffffff;
                        --rag-chat-text-secondary: #cccccc;
                        --rag-chat-accent: #4CAF50;
                        --rag-chat-border: #555555;
                    }
                ';
                break;
                
            case 'light':
                $custom_css = '
                    .rag-chat-theme-light {
                        --rag-chat-bg-primary: #ffffff;
                        --rag-chat-bg-secondary: #f9f9f9;
                        --rag-chat-text-primary: #333333;
                        --rag-chat-text-secondary: #666666;
                        --rag-chat-accent: #2196F3;
                        --rag-chat-border: #e0e0e0;
                    }
                ';
                break;
                
            case 'minimal':
                $custom_css = '
                    .rag-chat-theme-minimal {
                        --rag-chat-bg-primary: #ffffff;
                        --rag-chat-bg-secondary: #ffffff;
                        --rag-chat-text-primary: #000000;
                        --rag-chat-text-secondary: #666666;
                        --rag-chat-accent: #000000;
                        --rag-chat-border: #cccccc;
                    }
                    .rag-chat-theme-minimal .rag-chat-container {
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                ';
                break;
        }
        
        if (!empty($custom_css)) {
            echo '<style>' . $custom_css . '</style>';
        }
    }

    /**
     * Handle chat widget visibility
     */
    public function handle_widget_visibility() {
        // Add body class when chat is open
        add_action('wp_footer', function() {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const chatWidget = document.getElementById('rag-chat-widget');
                if (chatWidget) {
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                                const isOpen = chatWidget.classList.contains('rag-chat-open');
                                document.body.classList.toggle('rag-chat-open', isOpen);
                            }
                        });
                    });
                    
                    observer.observe(chatWidget, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                }
            });
            </script>
            <?php
        });
    }
}
