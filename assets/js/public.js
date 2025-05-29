jQuery(document).ready(function($) {
    'use strict';

    // Resume upload form handler
    const uploadForm = $('#resume-parser-upload-form form');
    const progressBar = $('.resume-parser-progress');
    const progressFill = $('.resume-parser-progress-fill');
    const statusText = $('.resume-parser-status');
    const resultDiv = $('.resume-parser-result');
    const dataDiv = $('.resume-parser-data');

    if (uploadForm.length) {
        uploadForm.on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'resume_parser_upload');
            formData.append('nonce', resumeParserAjax.nonce);

            // Validate file
            const file = formData.get('resume_file');
            if (!file) {
                showError(resumeParserAjax.strings.error);
                return;
            }

            // Check file size
            if (file.size > resumeParserAjax.maxFileSize) {
                showError(resumeParserAjax.strings.fileTooLarge);
                return;
            }

            // Check file type
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!resumeParserAjax.allowedTypes.includes(fileExt)) {
                showError(resumeParserAjax.strings.invalidFile);
                return;
            }

            // Show progress bar
            progressBar.show();
            progressFill.width('0%');
            statusText.text(resumeParserAjax.strings.uploading);
            resultDiv.hide();

            // Upload file
            $.ajax({
                url: resumeParserAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            progressFill.width(percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        statusText.text(resumeParserAjax.strings.success);
                        displayParsedData(response.data);
                        uploadForm[0].reset();
                    } else {
                        showError(response.data.message);
                    }
                },
                error: function() {
                    showError(resumeParserAjax.strings.error);
                },
                complete: function() {
                    setTimeout(function() {
                        progressBar.fadeOut();
                    }, 2000);
                }
            });
        });
    }

    // Resume list handlers
    const resumeList = $('.resume-parser-list');
    if (resumeList.length) {
        // View parsed data
        resumeList.on('click', '.view-parsed-data', function() {
            const button = $(this);
            const resumeId = button.data('id');
            const dataContainer = $(`#resume-data-${resumeId}`);

            if (dataContainer.is(':empty')) {
                button.prop('disabled', true);
                
                $.ajax({
                    url: resumeParserAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'resume_parser_get_data',
                        nonce: resumeParserAjax.nonce,
                        resume_id: resumeId
                    },
                    success: function(response) {
                        if (response.success) {
                            displayParsedData(response.data, dataContainer);
                            dataContainer.slideDown();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert(resumeParserAjax.strings.error);
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            } else {
                dataContainer.slideToggle();
            }
        });

        // Delete resume
        resumeList.on('click', '.delete-resume', function() {
            const button = $(this);
            const resumeId = button.data('id');

            if (!confirm(resumeParserAjax.strings.confirmDelete)) {
                return;
            }

            button.prop('disabled', true);

            $.ajax({
                url: resumeParserAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'resume_parser_delete',
                    nonce: resumeParserAjax.nonce,
                    resume_id: resumeId
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('.resume-parser-item').fadeOut(function() {
                            $(this).remove();
                            if (!$('.resume-parser-item').length) {
                                resumeList.html('<p>' + resumeParserAjax.strings.noResumes + '</p>');
                            }
                        });
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(resumeParserAjax.strings.error);
                    button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Display parsed resume data
     *
     * @param {Object} data Parsed resume data
     * @param {jQuery} container Optional container element
     */
    function displayParsedData(data, container = null) {
        const target = container || dataDiv;
        const template = $('#resume-parser-data-template').html();
        
        if (!template) {
            console.error('Resume data template not found');
            return;
        }

        try {
            const compiled = _.template(template);
            target.html(compiled(data)).show();
        } catch (e) {
            console.error('Error rendering template:', e);
            showError(resumeParserAjax.strings.error);
        }
    }

    /**
     * Show error message
     *
     * @param {string} message Error message
     */
    function showError(message) {
        statusText.text(message).addClass('error');
        progressBar.show();
        progressFill.width('100%').addClass('error');
        
        setTimeout(function() {
            progressBar.fadeOut(function() {
                statusText.removeClass('error');
                progressFill.removeClass('error');
            });
        }, 3000);
    }
}); 