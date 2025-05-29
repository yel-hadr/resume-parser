<?php
/**
 * OpenAI Client Class
 *
 * Handles communication with OpenAI API for resume parsing
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_OpenAI_Client {
    /**
     * OpenAI API key
     *
     * @var string
     */
    private $api_key;

    /**
     * OpenAI API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = Resume_Parser_Plugin::get_option('openai_api_key');
    }

    /**
     * Parse resume content using OpenAI API
     *
     * @param string $content Resume content
     * @return array|WP_Error Parsed data or error
     */
    public function parse_resume($content) {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('OpenAI API key is not configured.', 'resume-parser'));
        }

        $prompt = $this->get_parsing_prompt($content);
        
        try {
            $response = wp_remote_post($this->api_endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => 'gpt-4',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'You are a professional resume parser. Extract and structure the following resume information into JSON format.'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'temperature' => 0.3,
                    'max_tokens' => 2000
                )),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (empty($data) || !isset($data['choices'][0]['message']['content'])) {
                return new WP_Error('invalid_response', __('Invalid response from OpenAI API.', 'resume-parser'));
            }

            $parsed_content = json_decode($data['choices'][0]['message']['content'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', __('Failed to parse API response.', 'resume-parser'));
            }

            return $this->validate_parsed_data($parsed_content);

        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Get parsing prompt for OpenAI
     *
     * @param string $content Resume content
     * @return string Formatted prompt
     */
    private function get_parsing_prompt($content) {
        return "Parse the following resume and extract these details in JSON format:
        - Personal Information (name, email, phone, location)
        - Education (degree, institution, graduation year, GPA if available)
        - Work Experience (company, position, duration, key responsibilities)
        - Skills (technical skills, soft skills)
        - Certifications
        - Languages
        - Projects (if any)

        Resume Content:
        $content

        Return the data in this JSON structure:
        {
            \"personal_info\": {},
            \"education\": [],
            \"experience\": [],
            \"skills\": {
                \"technical\": [],
                \"soft\": []
            },
            \"certifications\": [],
            \"languages\": [],
            \"projects\": []
        }";
    }

    /**
     * Validate parsed data structure
     *
     * @param array $data Parsed resume data
     * @return array|WP_Error Validated data or error
     */
    private function validate_parsed_data($data) {
        $required_fields = array(
            'personal_info',
            'education',
            'experience',
            'skills'
        );

        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf(__('Missing required field: %s', 'resume-parser'), $field)
                );
            }
        }

        return $data;
    }

    /**
     * Check if API key is valid
     *
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function test_api_key() {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('OpenAI API key is not configured.', 'resume-parser'));
        }

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => 'gpt-4',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test'
                    )
                ),
                'max_tokens' => 5
            )),
            'timeout' => 5
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}