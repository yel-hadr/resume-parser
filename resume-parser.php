<?php
/**
 * Plugin Name: Resume Parser Pro
 * Description: A comprehensive WordPress plugin to parse resumes and extract relevant information using OpenAI API. Supports PDF and DOCX files with structured data storage and CSV export.
 * Version: 2.0.0
 * Author: Youssef El Hadraoui
 * Text Domain: resume-parser
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package ResumeParser
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * Main plugin class
 * 
 * @since 2.0.0
 */
final class Resume_Parser_Plugin {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    const VERSION = '2.0.0';
    
    /**
     * Plugin instance
     * 
     * @var Resume_Parser_Plugin|null
     */
    private static $instance = null;
    
    /**
     * Plugin constructor
     * 
     * @since 2.0.0
     */
    private function __construct() {
        $this->define_constants();
        $this->include_files();
        $this->init_hooks();
    }
    
    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return Resume_Parser_Plugin
     * @since 2.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Define plugin constants
     * 
     * @since 2.0.0
     */
    private function define_constants() {
        define('RESUME_PARSER_VERSION', self::VERSION);
        define('RESUME_PARSER_PLUGIN_FILE', __FILE__);
        define('RESUME_PARSER_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('RESUME_PARSER_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('RESUME_PARSER_PLUGIN_BASENAME', plugin_basename(__FILE__));
        define('RESUME_PARSER_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/resume-parser/');
        define('RESUME_PARSER_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/resume-parser/');
        define('RESUME_PARSER_TABLE_NAME', 'resume_parser_data');
    }
    
    /**
     * Include required files
     * 
     * @since 2.0.0
     */
    private function include_files() {
        // Core classes
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-core.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-admin.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-ajax.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-openai.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-database.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-file-handler.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-csv-export.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-shortcode.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-rest-api.php';
        
        // Utilities
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-utilities.php';
        require_once RESUME_PARSER_PLUGIN_DIR . 'includes/class-resume-parser-logger.php';
    }
    
    /**
     * Initialize plugin hooks
     * 
     * @since 2.0.0
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_plugin'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Resume_Parser_Plugin', 'uninstall'));
    }
    
    /**
     * Initialize plugin components
     * 
     * @since 2.0.0
     */
    public function init_plugin() {
        // Load text domain for translations
        load_plugin_textdomain('resume-parser', false, dirname(RESUME_PARSER_PLUGIN_BASENAME) . '/languages');
        
        // Initialize core components
        Resume_Parser_Core::get_instance();
        Resume_Parser_Database::get_instance();
        Resume_Parser_Shortcode::get_instance();
        Resume_Parser_REST_API::get_instance();
        
        // Initialize admin components (admin only)
        if (is_admin()) {
            Resume_Parser_Admin::get_instance();
        }
        
        // Initialize AJAX handlers
        Resume_Parser_AJAX::get_instance();
        
        /**
         * Plugin initialization complete
         * 
         * @since 2.0.0
         * @param Resume_Parser_Plugin $plugin Plugin instance
         */
        do_action('resume_parser_init', $this);
    }
    
    /**
     * Enqueue public assets
     * 
     * @since 2.0.0
     */
    public function enqueue_public_assets() {
        // CSS
        wp_enqueue_style(
            'resume-parser-public',
            RESUME_PARSER_PLUGIN_URL . 'assets/css/public.css',
            array(),
            RESUME_PARSER_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'resume-parser-public',
            RESUME_PARSER_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            RESUME_PARSER_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('resume-parser-public', 'resumeParserAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('resume-parser/v1/'),
            'nonce' => wp_create_nonce('resume_parser_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'maxFileSize' => wp_max_upload_size(),
            'allowedTypes' => array('pdf', 'docx'),
            'strings' => array(
                'uploading' => __('Uploading...', 'resume-parser'),
                'parsing' => __('Parsing resume...', 'resume-parser'),
                'success' => __('Resume parsed successfully!', 'resume-parser'),
                'error' => __('An error occurred. Please try again.', 'resume-parser'),
                'invalidFile' => __('Please select a valid PDF or DOCX file.', 'resume-parser'),
                'fileTooLarge' => __('File size exceeds maximum allowed size.', 'resume-parser'),
            )
        ));
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     * @since 2.0.0
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin admin pages
        if (strpos($hook, 'resume-parser') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'resume-parser-admin',
            RESUME_PARSER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RESUME_PARSER_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'resume-parser-admin',
            RESUME_PARSER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            RESUME_PARSER_VERSION,
            true
        );
        
        wp_localize_script('resume-parser-admin', 'resumeParserAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resume_parser_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this resume data?', 'resume-parser'),
                'exportSuccess' => __('Export completed successfully!', 'resume-parser'),
                'exportError' => __('Export failed. Please try again.', 'resume-parser'),
            )
        ));
    }
    
    /**
     * Plugin activation
     * 
     * @since 2.0.0
     */
    public function activate() {
        // Create database tables
        Resume_Parser_Database::create_tables();
        
        // Create upload directory
        $this->create_upload_directory();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        /**
         * Plugin activation complete
         * 
         * @since 2.0.0
         */
        do_action('resume_parser_activated');
        
        Resume_Parser_Logger::log('Plugin activated successfully', 'info');
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 2.0.0
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('resume_parser_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        /**
         * Plugin deactivation complete
         * 
         * @since 2.0.0
         */
        do_action('resume_parser_deactivated');
        
        Resume_Parser_Logger::log('Plugin deactivated', 'info');
    }
    
    /**
     * Plugin uninstall (static method)
     * 
     * @since 2.0.0
     */
    public static function uninstall() {
        // Remove database tables
        Resume_Parser_Database::drop_tables();
        
        // Remove options
        delete_option('resume_parser_settings');
        delete_option('resume_parser_version');
        
        // Remove upload directory and files
        self::remove_upload_directory();
        
        /**
         * Plugin uninstall complete
         * 
         * @since 2.0.0
         */
        do_action('resume_parser_uninstalled');
    }
    
    /**
     * Create upload directory
     * 
     * @since 2.0.0
     */
    private function create_upload_directory() {
        if (!file_exists(RESUME_PARSER_UPLOAD_DIR)) {
            wp_mkdir_p(RESUME_PARSER_UPLOAD_DIR);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents(RESUME_PARSER_UPLOAD_DIR . '.htaccess', $htaccess_content);
            
            // Create index.php for additional security
            file_put_contents(RESUME_PARSER_UPLOAD_DIR . 'index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Remove upload directory and files
     * 
     * @since 2.0.0
     */
    private static function remove_upload_directory() {
        if (file_exists(RESUME_PARSER_UPLOAD_DIR)) {
            $files = glob(RESUME_PARSER_UPLOAD_DIR . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir(RESUME_PARSER_UPLOAD_DIR);
        }
    }
    
    /**
     * Set default plugin options
     * 
     * @since 2.0.0
     */
    private function set_default_options() {
        $default_settings = array(
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o',
            'max_file_size' => 5, // MB
            'allowed_file_types' => array('pdf', 'docx'),
            'auto_delete_files' => true,
            'delete_after_days' => 30,
            'enable_logging' => true,
            'require_login' => true,
            'custom_upload_path' => '',
        );
        
        add_option('resume_parser_settings', $default_settings);
        add_option('resume_parser_version', RESUME_PARSER_VERSION);
    }
    
    /**
     * Get plugin option
     * 
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     * @since 2.0.0
     */
    public static function get_option($key, $default = null) {
        $settings = get_option('resume_parser_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update plugin option
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool True on success
     * @since 2.0.0
     */
    public static function update_option($key, $value) {
        $settings = get_option('resume_parser_settings', array());
        $settings[$key] = $value;
        return update_option('resume_parser_settings', $settings);
    }
}

/**
 * Initialize the plugin
 * 
 * @return Resume_Parser_Plugin
 * @since 2.0.0
 */
function resume_parser() {
    return Resume_Parser_Plugin::get_instance();
}

// Start the plugin
resume_parser();

/**
 * Example usage for developers:
 * 
 * // Hook into resume parsing completion
 * add_action('resume_parsed', function($parsed_data, $user_id, $file_info) {
 *     // Custom logic after resume is parsed
 *     // e.g., send notification, trigger workflow, etc.
 *     error_log('Resume parsed for user: ' . $user_id);
 * }, 10, 3);
 * 
 * // Filter parsed data before saving
 * add_filter('resume_parser_before_save', function($parsed_data) {
 *     // Modify or validate data before saving
 *     $parsed_data['custom_field'] = 'custom_value';
 *     return $parsed_data;
 * });
 * 
 * // Custom file validation
 * add_filter('resume_parser_validate_file', function($is_valid, $file) {
 *     // Additional validation logic
 *     if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
 *         return false;
 *     }
 *     return $is_valid;
 * }, 10, 2);
 * 
 * // Access parsed resume data programmatically
 * $resume_data = Resume_Parser_Database::get_user_resumes(get_current_user_id());
 * foreach ($resume_data as $resume) {
 *     $parsed_info = json_decode($resume->parsed_data, true);
 *     // Process data...
 * }
 */