<?php
/**
 * Plugin Name: Resume Parser
 * Description: A WordPress plugin that enables users to upload and parse resumes using OpenAI API
 * Version: 1.0.0
 * Author: youssef el hadraoui
 * License: GPL v2 or later
 * Text Domain: resume-parser
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RESUME_PARSER_VERSION', '1.0.0');
define('RESUME_PARSER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RESUME_PARSER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser.php';
require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-api.php';
require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-post-type.php';

// Initialize the plugin
function resume_parser_init() {
    $plugin = new Resume_Parser();
    $plugin->init();
}
add_action('plugins_loaded', 'resume_parser_init');

// Activation hook
register_activation_hook(__FILE__, 'resume_parser_activate');
function resume_parser_activate() {
    // Create custom tables if needed
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'resume_parser_data';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        resume_file varchar(255) NOT NULL,
        parsed_data longtext NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create upload directory
    $upload_dir = wp_upload_dir();
    $resume_dir = $upload_dir['basedir'] . '/resumes';
    if (!file_exists($resume_dir)) {
        wp_mkdir_p($resume_dir);
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'resume_parser_deactivate');
function resume_parser_deactivate() {
    // Clean up if needed
} 