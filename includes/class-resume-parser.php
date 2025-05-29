<?php

class Resume_Parser {
    private $api;
    private $post_type;

    public function init() {
        // Initialize components
        $this->api = new Resume_Parser_API();
        $this->post_type = new Resume_Parser_Post_Type();

        // Register hooks
        add_action('init', array($this, 'register_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_shortcode('resume_upload_form', array($this, 'render_upload_form'));

        // Register AJAX handlers
        add_action('wp_ajax_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_export_resumes_csv', array($this, 'export_resumes_csv'));
    }

    public function register_scripts() {
        wp_register_script(
            'resume-parser',
            RESUME_PARSER_PLUGIN_URL . 'assets/js/resume-parser.js',
            array('jquery'),
            RESUME_PARSER_VERSION,
            true
        );

        wp_register_style(
            'resume-parser',
            RESUME_PARSER_PLUGIN_URL . 'assets/css/resume-parser.css',
            array(),
            RESUME_PARSER_VERSION
        );

        wp_localize_script('resume-parser', 'resumeParserSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resume_parser_nonce'),
            'maxFileSize' => wp_max_upload_size(),
            'allowedTypes' => array('pdf', 'docx')
        ));
    }

    public function enqueue_scripts() {
        if (is_user_logged_in()) {
            wp_enqueue_script('resume-parser');
            wp_enqueue_style('resume-parser');
        }
    }

    public function render_upload_form() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to upload resumes.', 'resume-parser') . '</p>';
        }

        ob_start();
        ?>
        <div class="resume-parser-upload-form">
            <form id="resume-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('resume_parser_upload', 'resume_parser_nonce'); ?>
                
                <div class="upload-area">
                    <input type="file" name="resume_file" id="resume-file" accept=".pdf,.docx" required />
                    <p class="description"><?php esc_html_e('Accepted formats: PDF, DOCX', 'resume-parser'); ?></p>
                </div>

                <div class="progress-bar" style="display: none;">
                    <div class="progress"></div>
                </div>

                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Upload and Parse Resume', 'resume-parser'); ?>
                </button>
            </form>

            <div id="parse-results" class="parse-results" style="display: none;">
                <h3><?php esc_html_e('Parsed Resume Data', 'resume-parser'); ?></h3>
                <div class="parsed-content"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_resume_upload() {
        check_ajax_referer('resume_parser_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $file = $_FILES['resume_file'] ?? null;
        if (!$file) {
            wp_send_json_error('No file uploaded');
        }

        // Validate file type
        $allowed_types = array('application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type');
        }

        // Handle file upload
        $upload_dir = wp_upload_dir();
        $resume_dir = $upload_dir['basedir'] . '/resumes';
        $filename = sanitize_file_name($file['name']);
        $target_path = $resume_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            try {
                // Parse resume using OpenAI API
                $parsed_data = $this->api->parse_resume($target_path);
                
                // Store parsed data
                $result = $this->store_parsed_data($parsed_data, $filename);
                
                // Trigger action for third-party integrations
                do_action('resume_parsed', $parsed_data);
                
                wp_send_json_success(array(
                    'message' => 'Resume parsed successfully',
                    'data' => $parsed_data
                ));
            } catch (Exception $e) {
                wp_send_json_error($e->getMessage());
            }
        } else {
            wp_send_json_error('Failed to upload file');
        }
    }

    private function store_parsed_data($parsed_data, $filename) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'resume_parser_data',
            array(
                'user_id' => get_current_user_id(),
                'resume_file' => $filename,
                'parsed_data' => json_encode($parsed_data),
                'status' => 'completed'
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    public function export_resumes_csv() {
        check_ajax_referer('resume_parser_nonce', 'nonce');

        if (!current_user_can('export')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'resume_parser_data';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        $filename = 'resume-data-export-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'User', 'Resume File', 'Parsed Data', 'Status', 'Created At'));

        foreach ($results as $row) {
            $user = get_userdata($row->user_id);
            fputcsv($output, array(
                $row->id,
                $user ? $user->user_email : 'Unknown',
                $row->resume_file,
                $row->parsed_data,
                $row->status,
                $row->created_at
            ));
        }

        fclose($output);
        exit;
    }
} 