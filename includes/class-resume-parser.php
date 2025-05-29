<?php
class Resume_Parser {
    
    private $openai_client;
    
    public function __construct() {
        $this->openai_client = new OpenAI_Client();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_nopriv_upload_resume', array($this, 'handle_resume_upload'));
        add_shortcode('resume_parser', array($this, 'display_resume_parser'));
    }
    
    public function init() {
        // Initialize admin settings
        if (is_admin()) {
            new Admin_Settings();
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('resume-upload', RESUME_PARSER_PLUGIN_URL . 'assets/js/resume-upload.js', array('jquery'), RESUME_PARSER_VERSION, true);
        wp_localize_script('resume-upload', 'resume_parser_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resume_parser_nonce')
        ));
    }
    
    public function handle_resume_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'resume_parser_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_FILES['resume_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['resume_file'];
        $allowed_types = array('pdf', 'doc', 'docx', 'txt');
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload PDF, DOC, DOCX, or TXT files.');
        }
        
        // Extract text from file
        $text_content = $this->extract_text_from_file($file);
        
        if (!$text_content) {
            wp_send_json_error('Could not extract text from file');
        }
        
        // Parse resume using OpenAI
        $parsed_data = $this->openai_client->parse_resume($text_content);
        
        if ($parsed_data) {
            // Save to database
            $this->save_parsed_resume($file['name'], $parsed_data);
            wp_send_json_success($parsed_data);
        } else {
            wp_send_json_error('Failed to parse resume');
        }
    }
    
    private function extract_text_from_file($file) {
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $text_content = '';
        
        switch (strtolower($file_extension)) {
            case 'txt':
                $text_content = file_get_contents($file['tmp_name']);
                break;
            case 'pdf':
                // For PDF parsing, you'll need a library like PDF Parser
                // This is a simplified example
                $text_content = $this->extract_pdf_text($file['tmp_name']);
                break;
            case 'doc':
            case 'docx':
                // For DOC/DOCX parsing, you'll need a library like PHPWord
                $text_content = $this->extract_word_text($file['tmp_name']);
                break;
        }
        
        return $text_content;
    }
    
    private function extract_pdf_text($file_path) {
        // Implement PDF text extraction
        // You can use libraries like smalot/pdfparser
        return "PDF text extraction not implemented yet";
    }
    
    private function extract_word_text($file_path) {
        // Implement Word document text extraction
        // You can use PHPOffice/PHPWord
        return "Word text extraction not implemented yet";
    }
    
    private function save_parsed_resume($filename, $parsed_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'parsed_resumes';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'original_filename' => $filename,
                'parsed_data' => json_encode($parsed_data)
            )
        );
    }
    
    public function display_resume_parser($atts) {
        ob_start();
        ?>
        <div id="resume-parser-container">
            <form id="resume-upload-form" enctype="multipart/form-data">
                <div class="upload-area">
                    <label for="resume_file">Upload Resume (PDF, DOC, DOCX, TXT):</label>
                    <input type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx,.txt" required>
                </div>
                <button type="submit" id="parse-resume-btn">Parse Resume</button>
            </form>
            <div id="parsing-result" style="display:none;">
                <h3>Parsed Resume Data:</h3>
                <div id="resume-data"></div>
            </div>
            <div id="loading" style="display:none;">Parsing resume...</div>
        </div>
        <?php
        return ob_get_clean();
    }
}