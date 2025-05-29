<?php
/**
 * AJAX Handler Class
 *
 * Manages AJAX requests for resume uploads and data retrieval
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_AJAX {
    /**
     * Instance of this class
     *
     * @var Resume_Parser_AJAX
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        // File upload handlers
        add_action('wp_ajax_resume_parser_upload', array($this, 'handle_upload'));
        add_action('wp_ajax_nopriv_resume_parser_upload', array($this, 'handle_unauthorized'));

        // Resume data handlers
        add_action('wp_ajax_resume_parser_get_data', array($this, 'get_resume_data'));
        add_action('wp_ajax_nopriv_resume_parser_get_data', array($this, 'handle_unauthorized'));

        // Delete resume handler
        add_action('wp_ajax_resume_parser_delete', array($this, 'delete_resume'));
        add_action('wp_ajax_nopriv_resume_parser_delete', array($this, 'handle_unauthorized'));

        // Export handler
        add_action('wp_ajax_resume_parser_export', array($this, 'export_resumes'));
        add_action('wp_ajax_nopriv_resume_parser_export', array($this, 'handle_unauthorized'));
    }

    /**
     * Get instance of this class
     *
     * @return Resume_Parser_AJAX
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle unauthorized access
     *
     * @return void
     */
    public function handle_unauthorized() {
        wp_send_json_error(array(
            'message' => __('You must be logged in to perform this action.', 'resume-parser')
        ));
    }

    /**
     * Handle file upload
     *
     * @return void
     */
    public function handle_upload() {
        check_ajax_referer('resume_parser_upload', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to upload files.', 'resume-parser')
            ));
        }

        if (empty($_FILES['resume_file'])) {
            wp_send_json_error(array(
                'message' => __('No file was uploaded.', 'resume-parser')
            ));
        }

        $file_handler = new Resume_Parser_File_Handler();
        $upload_result = $file_handler->handle_upload($_FILES['resume_file']);

        if (is_wp_error($upload_result)) {
            wp_send_json_error(array(
                'message' => $upload_result->get_error_message()
            ));
        }

        // Parse resume content
        $openai = new Resume_Parser_OpenAI_Client();
        $parsed_data = $openai->parse_resume($upload_result['content']);

        if (is_wp_error($parsed_data)) {
            wp_send_json_error(array(
                'message' => $parsed_data->get_error_message()
            ));
        }

        // Store in database
        $db = Resume_Parser_Database::get_instance();
        $insert_data = array(
            'file_path' => $upload_result['file']['path'],
            'file_url' => $upload_result['file']['url'],
            'file_name' => $upload_result['file']['name'],
            'file_type' => $upload_result['file']['type'],
            'file_size' => $upload_result['file']['size'],
            'parsed_data' => $parsed_data,
            'raw_content' => $upload_result['content'],
            'status' => 'completed'
        );

        $result = $db->insert($insert_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        /**
         * Fires after a resume has been successfully parsed
         *
         * @param array $parsed_data Parsed resume data
         * @param array $file_data File information
         * @since 2.0.0
         */
        do_action('resume_parser_resume_parsed', $parsed_data, $upload_result['file']);

        wp_send_json_success(array(
            'message' => __('Resume uploaded and parsed successfully.', 'resume-parser'),
            'data' => $parsed_data,
            'file' => $upload_result['file']
        ));
    }

    /**
     * Get resume data
     *
     * @return void
     */
    public function get_resume_data() {
        check_ajax_referer('resume_parser_get_data', 'nonce');

        if (empty($_POST['resume_id'])) {
            wp_send_json_error(array(
                'message' => __('Resume ID is required.', 'resume-parser')
            ));
        }

        $db = Resume_Parser_Database::get_instance();
        $resume = $db->get(intval($_POST['resume_id']));

        if (is_wp_error($resume)) {
            wp_send_json_error(array(
                'message' => $resume->get_error_message()
            ));
        }

        // Check if user has permission to view this resume
        if ($resume->user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to view this resume.', 'resume-parser')
            ));
        }

        wp_send_json_success(array(
            'data' => $resume->parsed_data
        ));
    }

    /**
     * Delete resume
     *
     * @return void
     */
    public function delete_resume() {
        check_ajax_referer('resume_parser_delete', 'nonce');

        if (empty($_POST['resume_id'])) {
            wp_send_json_error(array(
                'message' => __('Resume ID is required.', 'resume-parser')
            ));
        }

        $db = Resume_Parser_Database::get_instance();
        $resume = $db->get(intval($_POST['resume_id']));

        if (is_wp_error($resume)) {
            wp_send_json_error(array(
                'message' => $resume->get_error_message()
            ));
        }

        // Check if user has permission to delete this resume
        if ($resume->user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to delete this resume.', 'resume-parser')
            ));
        }

        $result = $db->delete($resume->id);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        /**
         * Fires after a resume has been deleted
         *
         * @param int $resume_id Resume ID
         * @param object $resume Resume data
         * @since 2.0.0
         */
        do_action('resume_parser_resume_deleted', $resume->id, $resume);

        wp_send_json_success(array(
            'message' => __('Resume deleted successfully.', 'resume-parser')
        ));
    }

    /**
     * Export resumes to CSV
     *
     * @return void
     */
    public function export_resumes() {
        check_ajax_referer('resume_parser_export', 'resume_parser_export_nonce');

        $db = Resume_Parser_Database::get_instance();
        $csv = $db->export_csv(array(
            'user_id' => get_current_user_id()
        ));

        if (is_wp_error($csv)) {
            wp_die($csv->get_error_message());
        }

        $filename = 'resumes-export-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }
} 