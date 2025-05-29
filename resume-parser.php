<?php

/*
Plugin Name: Resume Parser
Description: A WordPress plugin to parse resumes and extract relevant information.
Version: 1.0
Author: youssef el hadraoui
*/
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RESUME_PARSER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RESUME_PARSER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RESUME_PARSER_VERSION', '1.0.0');

// Include required files
require_once RESUME_PARSER_PLUGIN_PATH . 'includes/class-resume-parser.php';
require_once RESUME_PARSER_PLUGIN_PATH . 'includes/class-openai-client.php';
require_once RESUME_PARSER_PLUGIN_PATH . 'includes/class-admin-settings.php';

// Initialize the plugin
function resume_parser_init() {
    new Resume_Parser();
}
add_action('plugins_loaded', 'resume_parser_init');

// Activation hook
register_activation_hook(__FILE__, 'resume_parser_activate');
function resume_parser_activate() {
    // Create database table for storing parsed resumes
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'parsed_resumes';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT NULL,
        original_filename varchar(255) NOT NULL,
        parsed_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
