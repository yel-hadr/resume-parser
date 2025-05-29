# WordPress Resume Parser Plugin

A WordPress plugin that enables logged-in users to upload resumes (PDF or DOCX) and automatically extract candidate information using OpenAI's API.

## Features

- Upload and parse PDF/DOCX resumes
- Extract candidate information (education, skills, experience, etc.)
- Store parsed data in a custom database table
- Display structured candidate data
- Progress indicator for file uploads
- CSV export functionality
- Hooks for third-party integrations
- Responsive design
- Security features (nonce verification, file validation)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenAI API key
- PHP extensions: ZipArchive (for DOCX parsing)

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"
5. Go to Settings > Resume Parser
6. Enter your OpenAI API key and save the settings

## Usage

### Basic Usage

1. Add the resume upload form to any page or post using the shortcode:
```
[resume_upload_form]
```

2. Logged-in users can upload their resumes through the form
3. The plugin will automatically parse the resume and display the structured data
4. Parsed data is stored in the database and can be accessed through the WordPress admin panel

### CSV Export

1. Go to WordPress admin panel > Resumes
2. Click the "Export to CSV" button
3. A CSV file containing all parsed resume data will be downloaded

### Developer Integration

The plugin provides hooks for third-party integration:

```php
// Hook triggered after a resume is successfully parsed
add_action('resume_parsed', function($parsed_data) {
    // Your code here
    // $parsed_data contains the structured resume data
});

// Filter to modify parsed data before saving
add_filter('resume_parser_data', function($parsed_data) {
    // Modify $parsed_data as needed
    return $parsed_data;
});
```

## Security

The plugin implements several security measures:

- File type validation
- File size limits
- Nonce verification
- User capability checks
- Sanitization of parsed data
- Secure file storage

## Customization

### Styling

The plugin's appearance can be customized by adding CSS to your theme or through a custom CSS plugin:

```css
.resume-parser-upload-form {
    /* Your custom styles */
}
```

### File Types

To modify allowed file types, use the filter:

```php
add_filter('resume_parser_allowed_types', function($types) {
    return array('pdf', 'docx', 'doc'); // Add or remove file types
});
```

## Troubleshooting

### Common Issues

1. **Upload fails**
   - Check file size limits in WordPress and PHP settings
   - Verify file permissions in the upload directory

2. **Parsing fails**
   - Verify OpenAI API key is correct
   - Check API rate limits
   - Ensure file is properly formatted

3. **Display issues**
   - Clear browser cache
   - Check for JavaScript conflicts
   - Verify theme compatibility

## Support

For support, feature requests, or bug reports:

1. Create an issue in the GitHub repository
2. Contact the plugin author
3. Visit the WordPress support forums

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- OpenAI API for resume parsing
- PDF Parser library for PDF text extraction
- WordPress community for inspiration and support 