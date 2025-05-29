<?php
/**
 * File Handler Class
 *
 * Handles file uploads and content extraction for resumes
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_File_Handler {
    /**
     * Allowed file types
     *
     * @var array
     */
    private $allowed_types = array('pdf', 'docx');

    /**
     * Maximum file size in bytes (20MB)
     *
     * @var int
     */
    private $max_file_size = 20971520;

    /**
     * Upload directory path
     *
     * @var string
     */
    private $upload_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->upload_dir = Resume_Parser_Plugin::get_option(
            'upload_dir',
            RESUME_PARSER_UPLOAD_DIR
        );
    }

    /**
     * Handle file upload
     *
     * @param array $file $_FILES array element
     * @return array|WP_Error Upload result or error
     */
    public function handle_upload($file) {
        // Verify nonce and user capabilities
        if (!current_user_can('upload_files')) {
            return new WP_Error('permission_denied', __('You do not have permission to upload files.', 'resume-parser'));
        }

        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Create upload directory if it doesn't exist
        if (!wp_mkdir_p($this->upload_dir)) {
            return new WP_Error('directory_creation_failed', __('Failed to create upload directory.', 'resume-parser'));
        }

        // Generate unique filename
        $filename = $this->generate_unique_filename($file['name']);
        $filepath = $this->upload_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', __('Failed to move uploaded file.', 'resume-parser'));
        }

        // Extract content based on file type
        $content = $this->extract_content($filepath);
        if (is_wp_error($content)) {
            unlink($filepath); // Clean up on failure
            return $content;
        }

        return array(
            'file' => array(
                'name' => $filename,
                'path' => $filepath,
                'url' => str_replace(ABSPATH, get_site_url() . '/', $filepath),
                'type' => wp_check_filetype($filename)['type'],
                'size' => filesize($filepath)
            ),
            'content' => $content
        );
    }

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array element
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __('File size (%s) exceeds maximum allowed size (%s).', 'resume-parser'),
                    size_format($file['size']),
                    size_format($this->max_file_size)
                )
            );
        }

        // Check file type
        $file_type = wp_check_filetype($file['name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowed_types)) {
            return new WP_Error(
                'invalid_file_type',
                sprintf(
                    __('Invalid file type. Allowed types: %s', 'resume-parser'),
                    implode(', ', $this->allowed_types)
                )
            );
        }

        return true;
    }

    /**
     * Generate unique filename
     *
     * @param string $original_filename Original filename
     * @return string Unique filename
     */
    private function generate_unique_filename($original_filename) {
        $info = pathinfo($original_filename);
        $ext = $info['extension'];
        $filename = sanitize_file_name($info['filename']);

        $number = 1;
        $new_filename = $filename . '.' . $ext;

        while (file_exists($this->upload_dir . '/' . $new_filename)) {
            $new_filename = sprintf('%s-%d.%s', $filename, $number, $ext);
            $number++;
        }

        return $new_filename;
    }

    /**
     * Extract content from uploaded file
     *
     * @param string $filepath Path to uploaded file
     * @return string|WP_Error Extracted content or error
     */
    private function extract_content($filepath) {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'pdf':
                return $this->extract_pdf_content($filepath);
            
            case 'docx':
                return $this->extract_docx_content($filepath);
            
            default:
                return new WP_Error(
                    'unsupported_file_type',
                    __('Unsupported file type for content extraction.', 'resume-parser')
                );
        }
    }

    /**
     * Extract content from PDF file
     *
     * @param string $filepath Path to PDF file
     * @return string|WP_Error Extracted content or error
     */
    private function extract_pdf_content($filepath) {
        if (!class_exists('FPDF')) {
            require_once RESUME_PARSER_PLUGIN_DIR . 'vendor/fpdf/fpdf.php';
        }
        
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filepath);
            return $pdf->getText();
        } catch (Exception $e) {
            return new WP_Error('pdf_extraction_failed', $e->getMessage());
        }
    }

    /**
     * Extract content from DOCX file
     *
     * @param string $filepath Path to DOCX file
     * @return string|WP_Error Extracted content or error
     */
    private function extract_docx_content($filepath) {
        try {
            $content = '';
            $zip = new ZipArchive;

            if ($zip->open($filepath) === true) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $content = $zip->getFromIndex($index);
                }
                $zip->close();

                // Convert content to plain text
                $content = strip_tags($content);
                $content = preg_replace('/[\r\n]+/', "\n", $content);
                $content = preg_replace('/[\s]+/', ' ', $content);
                
                return trim($content);
            }

            return new WP_Error('docx_extraction_failed', __('Failed to open DOCX file.', 'resume-parser'));
        } catch (Exception $e) {
            return new WP_Error('docx_extraction_failed', $e->getMessage());
        }
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'resume-parser');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'resume-parser');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded.', 'resume-parser');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded.', 'resume-parser');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder.', 'resume-parser');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk.', 'resume-parser');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload.', 'resume-parser');
            default:
                return __('Unknown upload error.', 'resume-parser');
        }
    }
} 