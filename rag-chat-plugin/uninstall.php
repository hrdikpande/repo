<?php
/**
 * Uninstall script for RAG Chat Plugin
 * 
 * This file is called when the plugin is uninstalled to clean up all data
 *
 * @package RAG_Chat_Plugin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the database class
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Remove database tables
RAG_Chat_Database::drop_tables();

// Remove all plugin options
$options_to_remove = array(
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
    'rag_chat_auto_scrape',
    'rag_chat_widget_title',
    'rag_chat_last_scrape_results',
    'rag_chat_total_api_requests',
    'rag_chat_total_tokens',
    'rag_chat_last_api_request',
    'rag_chat_setup_notice_dismissed'
);

foreach ($options_to_remove as $option) {
    delete_option($option);
    delete_site_option($option); // For multisite
}

// Clear any scheduled events
wp_clear_scheduled_hook('rag_chat_scheduled_scrape');

// Clear any transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rag_chat_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rag_chat_%'");

// For multisite
if (is_multisite()) {
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_rag_chat_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_rag_chat_%'");
}

// Log uninstall
error_log('RAG Chat Plugin: Uninstall completed - all data removed');
