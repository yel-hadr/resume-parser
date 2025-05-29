<?php
/**
 * Admin Settings Class
 *
 * Manages plugin settings and configuration
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_Admin_Settings {
    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'resume-parser-settings';

    /**
     * Settings group name
     *
     * @var string
     */
    private $option_group = 'resume_parser_options';

    /**
     * Settings page hook suffix
     *
     * @var string
     */
    private $hook_suffix;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add settings page to admin menu
     *
     * @return void
     */
    public function add_settings_page() {
        $this->hook_suffix = add_options_page(
            __('Resume Parser Settings', 'resume-parser'),
            __('Resume Parser', 'resume-parser'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            $this->option_group,
            'resume_parser_settings',
            array($this, 'sanitize_settings')
        );

        // General Settings Section
        add_settings_section(
            'resume_parser_general',
            __('General Settings', 'resume-parser'),
            array($this, 'render_general_section'),
            $this->page_slug
        );

        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'resume-parser'),
            array($this, 'render_api_key_field'),
            $this->page_slug,
            'resume_parser_general'
        );

        add_settings_field(
            'upload_path',
            __('Upload Directory', 'resume-parser'),
            array($this, 'render_upload_path_field'),
            $this->page_slug,
            'resume_parser_general'
        );

        add_settings_field(
            'max_file_size',
            __('Maximum File Size (MB)', 'resume-parser'),
            array($this, 'render_max_file_size_field'),
            $this->page_slug,
            'resume_parser_general'
        );

        // Parser Settings Section
        add_settings_section(
            'resume_parser_parser',
            __('Parser Settings', 'resume-parser'),
            array($this, 'render_parser_section'),
            $this->page_slug
        );

        add_settings_field(
            'model_version',
            __('GPT Model Version', 'resume-parser'),
            array($this, 'render_model_version_field'),
            $this->page_slug,
            'resume_parser_parser'
        );

        add_settings_field(
            'temperature',
            __('Model Temperature', 'resume-parser'),
            array($this, 'render_temperature_field'),
            $this->page_slug,
            'resume_parser_parser'
        );

        // Display Settings Section
        add_settings_section(
            'resume_parser_display',
            __('Display Settings', 'resume-parser'),
            array($this, 'render_display_section'),
            $this->page_slug
        );

        add_settings_field(
            'show_parsed_data',
            __('Show Parsed Data', 'resume-parser'),
            array($this, 'render_show_parsed_data_field'),
            $this->page_slug,
            'resume_parser_display'
        );

        add_settings_field(
            'enable_csv_export',
            __('Enable CSV Export', 'resume-parser'),
            array($this, 'render_enable_csv_export_field'),
            $this->page_slug,
            'resume_parser_display'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // OpenAI API Key
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);

        // Upload Path
        $upload_path = sanitize_text_field($input['upload_path']);
        $sanitized['upload_path'] = rtrim($upload_path, '/');

        // Max File Size
        $max_size = absint($input['max_file_size']);
        $sanitized['max_file_size'] = $max_size > 0 ? $max_size : 20;

        // Model Version
        $sanitized['model_version'] = sanitize_text_field($input['model_version']);

        // Temperature
        $temperature = floatval($input['temperature']);
        $sanitized['temperature'] = max(0, min(1, $temperature));

        // Show Parsed Data
        $sanitized['show_parsed_data'] = isset($input['show_parsed_data']);

        // Enable CSV Export
        $sanitized['enable_csv_export'] = isset($input['enable_csv_export']);

        return $sanitized;
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'resume-parser'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Usage Instructions', 'resume-parser'); ?></h2>
            <p><?php _e('Use the following shortcodes to display resume upload and management features:', 'resume-parser'); ?></p>
            <ul>
                <li><code>[upload-resume]</code> - <?php _e('Displays the resume upload form', 'resume-parser'); ?></li>
                <li><code>[resume-list]</code> - <?php _e('Displays a list of uploaded resumes', 'resume-parser'); ?></li>
            </ul>

            <h3><?php _e('Shortcode Parameters', 'resume-parser'); ?></h3>
            <p><?php _e('Upload Form:', 'resume-parser'); ?></p>
            <ul>
                <li><code>max_size</code> - <?php _e('Maximum file size in MB (default: 20)', 'resume-parser'); ?></li>
                <li><code>show_parsed</code> - <?php _e('Show parsed data after upload (default: true)', 'resume-parser'); ?></li>
            </ul>

            <p><?php _e('Resume List:', 'resume-parser'); ?></p>
            <ul>
                <li><code>per_page</code> - <?php _e('Number of resumes per page (default: 10)', 'resume-parser'); ?></li>
                <li><code>show_export</code> - <?php _e('Show export button (default: true)', 'resume-parser'); ?></li>
            </ul>

            <h3><?php _e('Developer Hooks', 'resume-parser'); ?></h3>
            <p><?php _e('Available action hooks:', 'resume-parser'); ?></p>
            <ul>
                <li><code>resume_parser_resume_parsed</code> - <?php _e('Fires after a resume is successfully parsed', 'resume-parser'); ?></li>
                <li><code>resume_parser_resume_deleted</code> - <?php _e('Fires after a resume is deleted', 'resume-parser'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render general section description
     *
     * @return void
     */
    public function render_general_section() {
        echo '<p>' . __('Configure general plugin settings.', 'resume-parser') . '</p>';
    }

    /**
     * Render parser section description
     *
     * @return void
     */
    public function render_parser_section() {
        echo '<p>' . __('Configure resume parsing settings.', 'resume-parser') . '</p>';
    }

    /**
     * Render display section description
     *
     * @return void
     */
    public function render_display_section() {
        echo '<p>' . __('Configure how resume data is displayed.', 'resume-parser') . '</p>';
    }

    /**
     * Render API key field
     *
     * @return void
     */
    public function render_api_key_field() {
        $options = get_option('resume_parser_settings');
        ?>
        <input type="password" 
               id="openai_api_key" 
               name="resume_parser_settings[openai_api_key]" 
               value="<?php echo esc_attr($options['openai_api_key'] ?? ''); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Enter your OpenAI API key. Get one from', 'resume-parser'); ?>
            <a href="https://platform.openai.com/api-keys" target="_blank">
                <?php _e('OpenAI Dashboard', 'resume-parser'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Render upload path field
     *
     * @return void
     */
    public function render_upload_path_field() {
        $options = get_option('resume_parser_settings');
        $default_path = RESUME_PARSER_UPLOAD_DIR;
        ?>
        <input type="text" 
               id="upload_path" 
               name="resume_parser_settings[upload_path]" 
               value="<?php echo esc_attr($options['upload_path'] ?? $default_path); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Directory path where resumes will be stored.', 'resume-parser'); ?>
        </p>
        <?php
    }

    /**
     * Render max file size field
     *
     * @return void
     */
    public function render_max_file_size_field() {
        $options = get_option('resume_parser_settings');
        ?>
        <input type="number" 
               id="max_file_size" 
               name="resume_parser_settings[max_file_size]" 
               value="<?php echo esc_attr($options['max_file_size'] ?? '20'); ?>" 
               min="1" 
               max="100" 
               step="1">
        <p class="description">
            <?php _e('Maximum allowed file size in megabytes.', 'resume-parser'); ?>
        </p>
        <?php
    }

    /**
     * Render model version field
     *
     * @return void
     */
    public function render_model_version_field() {
        $options = get_option('resume_parser_settings');
        $current = $options['model_version'] ?? 'gpt-4';
        ?>
        <select id="model_version" name="resume_parser_settings[model_version]">
            <option value="gpt-4" <?php selected($current, 'gpt-4'); ?>>
                <?php _e('GPT-4 (Recommended)', 'resume-parser'); ?>
            </option>
            <option value="gpt-3.5-turbo" <?php selected($current, 'gpt-3.5-turbo'); ?>>
                <?php _e('GPT-3.5 Turbo (Faster)', 'resume-parser'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Select the OpenAI model to use for parsing.', 'resume-parser'); ?>
        </p>
        <?php
    }

    /**
     * Render temperature field
     *
     * @return void
     */
    public function render_temperature_field() {
        $options = get_option('resume_parser_settings');
        ?>
        <input type="number" 
               id="temperature" 
               name="resume_parser_settings[temperature]" 
               value="<?php echo esc_attr($options['temperature'] ?? '0.3'); ?>" 
               min="0" 
               max="1" 
               step="0.1">
        <p class="description">
            <?php _e('Model temperature (0-1). Lower values are more focused.', 'resume-parser'); ?>
        </p>
        <?php
    }

    /**
     * Render show parsed data field
     *
     * @return void
     */
    public function render_show_parsed_data_field() {
        $options = get_option('resume_parser_settings');
        ?>
        <label>
            <input type="checkbox" 
                   name="resume_parser_settings[show_parsed_data]" 
                   value="1" 
                   <?php checked(isset($options['show_parsed_data']) && $options['show_parsed_data']); ?>>
            <?php _e('Show parsed data immediately after upload', 'resume-parser'); ?>
        </label>
        <?php
    }

    /**
     * Render enable CSV export field
     *
     * @return void
     */
    public function render_enable_csv_export_field() {
        $options = get_option('resume_parser_settings');
        ?>
        <label>
            <input type="checkbox" 
                   name="resume_parser_settings[enable_csv_export]" 
                   value="1" 
                   <?php checked(isset($options['enable_csv_export']) && $options['enable_csv_export']); ?>>
            <?php _e('Enable CSV export functionality', 'resume-parser'); ?>
        </label>
        <?php
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook_suffix Current admin page
     * @return void
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if ($hook_suffix !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'resume-parser-admin',
            RESUME_PARSER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RESUME_PARSER_VERSION
        );

        wp_enqueue_script(
            'resume-parser-admin',
            RESUME_PARSER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            RESUME_PARSER_VERSION,
            true
        );

        wp_localize_script('resume-parser-admin', 'resumeParserAdmin', array(
            'strings' => array(
                'testSuccess' => __('API key is valid!', 'resume-parser'),
                'testError' => __('API key is invalid or there was an error.', 'resume-parser')
            )
        ));
    }
}