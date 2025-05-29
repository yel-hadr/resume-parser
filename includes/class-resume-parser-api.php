<?php

class Resume_Parser_API {
    private $api_key;
    private $model = 'gpt-4-1106-preview';

    public function __construct() {
        $this->api_key = get_option('resume_parser_openai_api_key');
    }

    public function parse_resume($file_path) {
        if (!$this->api_key) {
            throw new Exception('OpenAI API key not configured');
        }

        // Extract text from PDF/DOCX
        $text = $this->extract_text($file_path);
        if (!$text) {
            throw new Exception('Failed to extract text from file');
        }

        // Prepare the prompt for OpenAI
        $prompt = $this->prepare_prompt($text);

        // Call OpenAI API
        $parsed_data = $this->call_openai_api($prompt);

        return $parsed_data;
    }

    private function extract_text($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if ($extension === 'pdf') {
            return $this->extract_pdf_text($file_path);
        } elseif ($extension === 'docx') {
            return $this->extract_docx_text($file_path);
        }
        
        throw new Exception('Unsupported file type');
    }

    private function extract_pdf_text($file_path) {
        if (!class_exists('FPDI')) {
            require_once RESUME_PARSER_PLUGIN_DIR . 'vendor/autoload.php';
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file_path);
            return $pdf->getText();
        } catch (Exception $e) {
            throw new Exception('Failed to parse PDF: ' . $e->getMessage());
        }
    }

    private function extract_docx_text($file_path) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is required for DOCX parsing');
        }

        $zip = new ZipArchive();
        if ($zip->open($file_path) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $content = $zip->getFromIndex($index);
                $zip->close();
                
                $content = str_replace('</w:p>', "\n", $content);
                $content = strip_tags($content);
                return trim($content);
            }
            $zip->close();
        }
        
        throw new Exception('Failed to parse DOCX file');
    }

    private function prepare_prompt($text) {
        return array(
            "messages" => array(
                array(
                    "role" => "system",
                    "content" => "You are a professional resume parser. Extract and structure the following information from the resume: personal details, education, work experience, skills, and certifications. Format the response as JSON."
                ),
                array(
                    "role" => "user",
                    "content" => $text
                )
            ),
            "temperature" => 0.3,
            "max_tokens" => 1000
        );
    }

    private function call_openai_api($prompt) {
        $headers = array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        );

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_merge($prompt, array('model' => $this->model))));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('OpenAI API request failed: ' . $response);
        }

        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenAI API');
        }

        return json_decode($result['choices'][0]['message']['content'], true);
    }
} 