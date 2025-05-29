<?php
class OpenAI_Client {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    public function __construct() {
        $this->api_key = get_option('resume_parser_openai_key');
    }
    
    public function parse_resume($resume_text) {
        if (!$this->api_key) {
            error_log('OpenAI API key not configured');
            return false;
        }
        
        $prompt = $this->build_parsing_prompt($resume_text);
        
        $response = $this->make_api_request($prompt);
        
        if ($response) {
            return $this->process_response($response);
        }
        
        return false;
    }
    
    private function build_parsing_prompt($resume_text) {
        return "Please analyze the following resume and extract structured information. Return the data in JSON format with the following structure:
        {
            \"personal_info\": {
                \"name\": \"\",
                \"email\": \"\",
                \"phone\": \"\",
                \"address\": \"\",
                \"linkedin\": \"\",
                \"website\": \"\"
            },
            \"summary\": \"\",
            \"skills\": [],
            \"experience\": [
                {
                    \"company\": \"\",
                    \"position\": \"\",
                    \"duration\": \"\",
                    \"description\": \"\"
                }
            ],
            \"education\": [
                {
                    \"institution\": \"\",
                    \"degree\": \"\",
                    \"field\": \"\",
                    \"graduation_year\": \"\"
                }
            ],
            \"certifications\": [],
            \"languages\": []
        }
        
        Resume text:
        " . $resume_text;
    }
    
    private function make_api_request($prompt) {
        $headers = array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        );
        
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2000,
            'temperature' => 0.3
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        } else {
            error_log('OpenAI API request failed: ' . $response);
            return false;
        }
    }
    
    private function process_response($response) {
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            // Extract JSON from the response
            preg_match('/\{.*\}/s', $content, $matches);
            
            if (!empty($matches[0])) {
                $parsed_data = json_decode($matches[0], true);
                return $parsed_data;
            }
        }
        
        return false;
    }
}