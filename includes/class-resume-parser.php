<?php
/**
 * Resume Parser Core Class
 * 
 * Handles core functionality for resume parsing
 * 
 * @package ResumeParser
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core class for resume parsing functionality
 * 
 * @since 2.0.0
 */
class Resume_Parser_Core {
    
    /**
     * Class instance
     * 
     * @var Resume_Parser_Core|null
     */
    private static $instance = null;
    
    /**
     * OpenAI client instance
     * 
     * @var Resume_Parser_OpenAI
     */
    private $openai_client;
    
    /**
     * File handler instance
     * 
     * @var Resume_Parser_File_Handler
     */
    private $file_handler;
    
    /**
     * Database handler instance
     * 
     * @var Resume_Parser_Database
     */
    private $database;
    
    /**
     * Constructor
     * 
     * @since 2.0.0
     */
    private function __construct() {
        $this->init_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Get instance (Singleton pattern)
     * 
     * @return Resume_Parser_Core
     * @since 2.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize dependencies
     * 
     * @since 2.0.0
     */
    private function init_dependencies() {
        $this->openai_client = Resume_Parser_OpenAI::get_instance();
        $this->file_handler = Resume_Parser_File_Handler::get_instance();
        $this->database = Resume_Parser_Database::get_instance();
    }
    
    /**
     * Initialize hooks
     * 
     * @since 2.0.0
     */
    private function init_hooks() {
        add_action('wp_loaded', array($this, 'handle_scheduled_cleanup'));
        add_action('resume_parser_cleanup', array($this, 'cleanup_old_files'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('resume_parser_cleanup')) {
            wp_schedule_event(time(), 'daily', 'resume_parser_cleanup');
        }
    }
    
    /**
     * Process uploaded resume file
     * 
     * @param array $file Uploaded file data from $_FILES
     * @param int $user_id User ID (optional)
     * @return array|WP_Error Processing result or error
     * @since 2.0.0
     */
    public function process_resume($file, $user_id = null) {
        try {
            // Validate user permissions
            if (Resume_Parser_Plugin::get_option('require_login', true) && !is_user_logged_in()) {
                return new WP_Error(
                    'authentication_required',
                    __('You must be logged in to upload resumes.', 'resume-parser')
                );
            }
            
            // Set user ID if not provided
            if (null === $user_id) {
                $user_id = get_current_user_id();
            }
            
            // Validate file
            $validation_result = $this->validate_file($file);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
            
            // Extract text from file
            $text_content = $this->file_handler->extract_text($file);
            if (is_wp_error($text_content)) {
                return $text_content;
            }
            
            // Parse resume with OpenAI
            $parsed_data = $this->openai_client->parse_resume($text_content);
            if (is_wp_error($parsed_data)) {
                return $parsed_data;
            }
            
            // Apply filters to parsed data
            $parsed_data = apply_filters('resume_parser_before_save', $parsed_data, $user_id, $file);
            
            // Save to database
            $resume_id = $this->database->save_resume($user_id, $file['name'], $parsed_data, $text_content);
            if (is_wp_error($resume_id)) {
                return $resume_id;
            }
            
            // Store file if configured to do so
            $file_path = null;
            if (!Resume_Parser_Plugin::get_option('auto_delete_files', true)) {
                $file_path = $this->file_handler->save_file($file, $resume_id);
            }
            
            // Prepare response data
            $response_data = array(
                'id' => $resume_id,
                'parsed_data' => $parsed_data,
                'file_info' => array(
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'path' => $file_path
                ),
                'user_id' => $user_id,
                'created_at' => current_time('mysql')
            );
            
            /**
             * Fires after a resume has been successfully parsed and saved
             * 
             * @since 2.0.0
             * @param array $parsed_data The parsed resume data
             * @param int $user_id The user ID
             * @param array $file_info File information
             * @param int $resume_id The database record ID
             */
            do_action('resume_parsed', $parsed_data, $user_id, $response_data['file_info'], $resume_id);
            
            Resume_Parser_Logger::log(
                sprintf('Resume parsed successfully for user %d, file: %s', $user_id, $file['name']),
                'info'
            );
            
            return $response_data;
            
        } catch (Exception $e) {
            Resume_Parser_Logger::log(
                sprintf('Resume processing error: %s', $e->getMessage()),
                'error'
            );
            
            return new WP_Error(
                'processing_failed',
                __('Failed to process resume. Please try again.', 'resume-parser')
            );
        }
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file Uploaded file data
     * @return bool|WP_Error True if valid, WP_Error otherwise
     * @since 2.0.0
     */
    private function validate_file($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return new WP_Error('no_file', __('No file was uploaded.', 'resume-parser'));
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }
        
        // Check file size
        $max_size = Resume_Parser_Plugin::get_option('max_file_size', 5) * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $max_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __('File size exceeds maximum allowed size of %s MB.', 'resume-parser'),
                    Resume_Parser_Plugin::get_option('max_file_size', 5)
                )
            );
        }
        
        // Check file type
        $allowed_types = Resume_Parser_Plugin::get_option('allowed_file_types', array('pdf', 'docx'));
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            return new WP_Error(
                'invalid_file_type',
                sprintf(
                    __('Invalid file type. Allowed types: %s', 'resume-parser'),
                    implode(', ', $allowed_types)
                )
            );
        }
        
        // Additional MIME type validation
        $allowed_mime_types = array(
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        
        $file_mime_type = wp_check_filetype($file['name']);
        if (!isset($allowed_mime_types[$file_extension]) || 
            $file_mime_type['type'] !== $allowed_mime_types[$file_extension]) {
            return new WP_Error(
                'invalid_mime_type',
                __('Invalid file format detected.', 'resume-parser')
            );
        }
        
        // Apply custom validation filter
        $is_valid = apply_filters('resume_parser_validate_file', true, $file);
        if (!$is_valid) {
            return new WP_Error('custom_validation_failed', __('File validation failed.', 'resume-parser'));
        }
        
        return true;
    }
    
    /**
     * Get upload error message
     * 
     * @param int $error_code PHP upload error code
     * @return string Error message
     * @since 2.0.0
     */
    private function get_upload_error_message($error_code) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => __('File exceeds maximum allowed size.', 'resume-parser'),
            UPLOAD_ERR_FORM_SIZE => __('File exceeds form maximum size.', 'resume-parser'),
            UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'resume-parser'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'resume-parser'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary upload directory.', 'resume-parser'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'resume-parser'),
            UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'resume-parser'),
        );
        
        return isset($error_messages[$error_code]) 
            ? $error_messages[$error_code] 
            : __('Unknown upload error.', 'resume-parser');
    }
    
    /**
     * Get resume data for a user
     * 
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Resume data
     * @since 2.0.0
     */
    public function get_user_resumes($user_id, $args = array()) {
        return $this->database->get_user_resumes($user_id, $args);
    }
    
    /**
     * Get single resume data
     * 
     * @param int $resume_id Resume ID
     * @param int $user_id User ID (for security check)
     * @return array|null Resume data or null if not found
     * @since 2.0.0
     */
    public function get_resume($resume_id, $user_id = null) {
        return $this->database->get_resume($resume_id, $user_id);
    }
    
    /**
     * Delete resume data
     * 
     * @param int $resume_id Resume ID
     * @param int $user_id User ID (for security check)
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 2.0.0
     */
    public function delete_resume($resume_id, $user_id = null) {
        // Get resume data first to check ownership
        $resume = $this->get_resume($resume_id, $user_id);
        if (!$resume) {
            return new WP_Error('resume_not_found', __('Resume not found.', 'resume-parser'));
        }
        
        // Delete associated file if exists
        if (!empty($resume->file_path) && file_exists($resume->file_path)) {
            unlink($resume->file_path);
        }
        
        // Delete from database
        $deleted = $this->database->delete_resume($resume_id);
        if (!$deleted) {
            return new WP_Error('delete_failed', __('Failed to delete resume.', 'resume-parser'));
        }
        
        /**
         * Fires after a resume has been deleted
         * 
         * @since 2.0.0
         * @param int $resume_id The resume ID
         * @param object $resume The resume data that was deleted
         */
        do_action('resume_parser_resume_deleted', $resume_id, $resume);
        
        Resume_Parser_Logger::log(
            sprintf('Resume deleted: ID %d, User %d', $resume_id, $resume->user_id),
            'info'
        );
        
        return true;
    }
    
    /**
     * Handle scheduled cleanup
     * 
     * @since 2.0.0
     */
    public function handle_scheduled_cleanup() {
        // This method can be used for any scheduled tasks
    }
    
    /**
     * Clean up old files and data
     * 
     * @since 2.0.0
     */
    public function cleanup_old_files() {
        if (!Resume_Parser_Plugin::get_option('auto_delete_files', true)) {
            return;
        }
        
        $days = Resume_Parser_Plugin::get_option('delete_after_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get old resumes
        $old_resumes = $this->database->get_resumes_before_date($cutoff_date);
        
        foreach ($old_resumes as $resume) {
            // Delete file if exists
            if (!empty($resume->file_path) && file_exists($resume->file_path)) {
                unlink($resume->file_path);
            }
            
            // Delete database record
            $this->database->delete_resume($resume->id);
        }
        
        if (count($old_resumes) > 0) {
            Resume_Parser_Logger::log(
                sprintf('Cleaned up %d old resume records and files', count($old_resumes)),
                'info'
            );
        }
    }
    
    /**
     * Get parsing statistics
     * 
     * @param int $user_id User ID (optional, for user-specific stats)
     * @return array Statistics data
     * @since 2.0.0
     */
    public function get_statistics($user_id = null) {
        return $this->database->get_statistics($user_id);
    }
    
    /**
     * Reparse existing resume with updated AI model
     * 
     * @param int $resume_id Resume ID
     * @param int $user_id User ID (for security check)
     * @return array|WP_Error Updated resume data or error
     * @since 2.0.0
     */
    public function reparse_resume($resume_id, $user_id = null) {
        // Get existing resume
        $resume = $this->get_resume($resume_id, $user_id);
        if (!$resume) {
            return new WP_Error('resume_not_found', __('Resume not found.', 'resume-parser'));
        }
        
        // Parse with current AI model
        $parsed_data = $this->openai_client->parse_resume($resume->original_text);
        if (is_wp_error($parsed_data)) {
            return $parsed_data;
        }
        
        // Update database with new parsed data
        $updated = $this->database->update_resume_data($resume_id, $parsed_data);
        if (!$updated) {
            return new WP_Error('update_failed', __('Failed to update resume data.', 'resume-parser'));
        }
        
        /**
         * Fires after a resume has been reparsed
         * 
         * @since 2.0.0
         * @param int $resume_id The resume ID
         * @param array $parsed_data The new parsed data
         * @param int $user_id The user ID
         */
        do_action('resume_parser_resume_reparsed', $resume_id, $parsed_data, $user_id);
        
        Resume_Parser_Logger::log(
            sprintf('Resume reparsed: ID %d, User %d', $resume_id, $user_id),
            'info'
        );
        
        return array(
            'id' => $resume_id,
            'parsed_data' => $parsed_data,
            'updated_at' => current_time('mysql')
        );
    }
}