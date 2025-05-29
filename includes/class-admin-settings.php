<?php
class Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Resume Parser Settings',
            'Resume Parser',
            'manage_options',
            'resume-parser-settings',
            array($this, 'admin_page')
        );
    }
    
    public function init_settings() {
        register_setting('resume_parser_settings', 'resume_parser_openai_key');
        
        add_settings_section(
            'resume_parser_api_section',
            'API Configuration',
            null,
            'resume-parser-settings'
        );
        
        add_settings_field(
            'resume_parser_openai_key',
            'OpenAI API Key',
            array($this, 'openai_key_field'),
            'resume-parser-settings',
            'resume_parser_api_section'
        );
    }
    
    public function openai_key_field() {
        $value = get_option('resume_parser_openai_key');
        echo '<input type="password" name="resume_parser_openai_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Enter your OpenAI API key to enable resume parsing.</p>';
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Resume Parser Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('resume_parser_settings');
                do_settings_sections('resume-parser-settings');
                submit_button();
                ?>
            </form>
            
            <div class="postbox">
                <h3>Usage Instructions</h3>
                <div class="inside">
                    <p>To display the resume parser on any page or post, use the shortcode:</p>
                    <code>[resume_parser]</code>
                </div>
            </div>
        </div>
        <?php
    }
}