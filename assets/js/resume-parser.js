jQuery(document).ready(function($) {
    const form = $('#resume-upload-form');
    const progressBar = form.find('.progress-bar');
    const progress = progressBar.find('.progress');
    const resultsDiv = $('#parse-results');
    const parsedContent = resultsDiv.find('.parsed-content');

    form.on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'upload_resume');
        formData.append('nonce', resumeParserSettings.nonce);

        // Validate file size
        const file = formData.get('resume_file');
        if (file.size > resumeParserSettings.maxFileSize) {
            alert('File is too large. Maximum allowed size is ' + (resumeParserSettings.maxFileSize / 1024 / 1024) + 'MB');
            return;
        }

        // Validate file type
        const fileExt = file.name.split('.').pop().toLowerCase();
        if (!resumeParserSettings.allowedTypes.includes(fileExt)) {
            alert('Invalid file type. Allowed types are: ' + resumeParserSettings.allowedTypes.join(', '));
            return;
        }

        // Show progress bar
        progressBar.show();
        progress.css('width', '0%');

        $.ajax({
            url: resumeParserSettings.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progress.css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    displayParsedData(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Error uploading file: ' + error);
            },
            complete: function() {
                progressBar.hide();
                progress.css('width', '0%');
            }
        });
    });

    function displayParsedData(data) {
        let html = '<div class="parsed-resume-data">';

        // Personal Details
        if (data.personal_details) {
            html += '<div class="section personal-details">';
            html += '<h4>Personal Details</h4>';
            for (const [key, value] of Object.entries(data.personal_details)) {
                html += `<p><strong>${key.replace('_', ' ').toUpperCase()}:</strong> ${value}</p>`;
            }
            html += '</div>';
        }

        // Education
        if (data.education && data.education.length) {
            html += '<div class="section education">';
            html += '<h4>Education</h4>';
            data.education.forEach(edu => {
                html += '<div class="education-item">';
                for (const [key, value] of Object.entries(edu)) {
                    html += `<p><strong>${key.replace('_', ' ').toUpperCase()}:</strong> ${value}</p>`;
                }
                html += '</div>';
            });
            html += '</div>';
        }

        // Experience
        if (data.experience && data.experience.length) {
            html += '<div class="section experience">';
            html += '<h4>Experience</h4>';
            data.experience.forEach(exp => {
                html += '<div class="experience-item">';
                for (const [key, value] of Object.entries(exp)) {
                    html += `<p><strong>${key.replace('_', ' ').toUpperCase()}:</strong> ${value}</p>`;
                }
                html += '</div>';
            });
            html += '</div>';
        }

        // Skills
        if (data.skills && data.skills.length) {
            html += '<div class="section skills">';
            html += '<h4>Skills</h4>';
            html += `<p>${data.skills.join(', ')}</p>`;
            html += '</div>';
        }

        // Certifications
        if (data.certifications && data.certifications.length) {
            html += '<div class="section certifications">';
            html += '<h4>Certifications</h4>';
            data.certifications.forEach(cert => {
                html += '<div class="certification-item">';
                for (const [key, value] of Object.entries(cert)) {
                    html += `<p><strong>${key.replace('_', ' ').toUpperCase()}:</strong> ${value}</p>`;
                }
                html += '</div>';
            });
            html += '</div>';
        }

        html += '</div>';

        parsedContent.html(html);
        resultsDiv.show();
    }
}); 