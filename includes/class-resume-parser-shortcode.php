<?php
/**
 * Shortcode Handler Class
 *
 * Manages frontend shortcodes for resume upload and display
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_Shortcode {
    /**
     * Instance of this class
     *
     * @var Resume_Parser_Shortcode
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        add_shortcode('upload-resume', array($this, 'render_upload_form'));
        add_shortcode('resume-list', array($this, 'render_resume_list'));
    }

    /**
     * Get instance of this class
     *
     * @return Resume_Parser_Shortcode
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render resume upload form
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_upload_form($atts = array()) {
        if (!is_user_logged_in()) {
            return sprintf(
                '<p class="resume-parser-error">%s</p>',
                __('Please log in to upload resumes.', 'resume-parser')
            );
        }

        $atts = shortcode_atts(array(
            'max_size' => '20', // MB
            'show_parsed' => 'true'
        ), $atts, 'upload-resume');

        ob_start();
        ?>
        <div class="resume-parser-upload-form" id="resume-parser-upload-form">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('resume_parser_upload', 'resume_parser_nonce'); ?>
                
                <div class="resume-parser-field">
                    <label for="resume_file">
                        <?php _e('Select Resume (PDF or DOCX)', 'resume-parser'); ?>
                    </label>
                    <input type="file" 
                           name="resume_file" 
                           id="resume_file" 
                           accept=".pdf,.docx" 
                           required />
                    <p class="description">
                        <?php printf(
                            __('Maximum file size: %s MB', 'resume-parser'),
                            esc_html($atts['max_size'])
                        ); ?>
                    </p>
                </div>

                <div class="resume-parser-submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Upload Resume', 'resume-parser'); ?>
                    </button>
                </div>

                <div class="resume-parser-progress" style="display: none;">
                    <div class="resume-parser-progress-bar">
                        <div class="resume-parser-progress-fill"></div>
                    </div>
                    <p class="resume-parser-status"></p>
                </div>

                <?php if ($atts['show_parsed'] === 'true'): ?>
                <div class="resume-parser-result" style="display: none;">
                    <h3><?php _e('Parsed Resume Data', 'resume-parser'); ?></h3>
                    <div class="resume-parser-data"></div>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <script type="text/template" id="resume-parser-data-template">
            <div class="resume-section personal-info">
                <h4><?php _e('Personal Information', 'resume-parser'); ?></h4>
                <p><strong><?php _e('Name:', 'resume-parser'); ?></strong> <%= personal_info.name %></p>
                <p><strong><?php _e('Email:', 'resume-parser'); ?></strong> <%= personal_info.email %></p>
                <p><strong><?php _e('Phone:', 'resume-parser'); ?></strong> <%= personal_info.phone %></p>
                <p><strong><?php _e('Location:', 'resume-parser'); ?></strong> <%= personal_info.location %></p>
            </div>

            <div class="resume-section education">
                <h4><?php _e('Education', 'resume-parser'); ?></h4>
                <% _.each(education, function(edu) { %>
                    <div class="education-item">
                        <p><strong><%= edu.degree %></strong></p>
                        <p><%= edu.institution %> (<%= edu.graduation_year %>)</p>
                        <% if (edu.gpa) { %>
                            <p><?php _e('GPA:', 'resume-parser'); ?> <%= edu.gpa %></p>
                        <% } %>
                    </div>
                <% }); %>
            </div>

            <div class="resume-section experience">
                <h4><?php _e('Work Experience', 'resume-parser'); ?></h4>
                <% _.each(experience, function(exp) { %>
                    <div class="experience-item">
                        <p><strong><%= exp.position %></strong></p>
                        <p><%= exp.company %> (<%= exp.duration %>)</p>
                        <ul>
                            <% _.each(exp.responsibilities, function(resp) { %>
                                <li><%= resp %></li>
                            <% }); %>
                        </ul>
                    </div>
                <% }); %>
            </div>

            <div class="resume-section skills">
                <h4><?php _e('Skills', 'resume-parser'); ?></h4>
                <div class="skills-technical">
                    <h5><?php _e('Technical Skills', 'resume-parser'); ?></h5>
                    <ul>
                        <% _.each(skills.technical, function(skill) { %>
                            <li><%= skill %></li>
                        <% }); %>
                    </ul>
                </div>
                <div class="skills-soft">
                    <h5><?php _e('Soft Skills', 'resume-parser'); ?></h5>
                    <ul>
                        <% _.each(skills.soft, function(skill) { %>
                            <li><%= skill %></li>
                        <% }); %>
                    </ul>
                </div>
            </div>

            <% if (certifications && certifications.length) { %>
            <div class="resume-section certifications">
                <h4><?php _e('Certifications', 'resume-parser'); ?></h4>
                <ul>
                    <% _.each(certifications, function(cert) { %>
                        <li><%= cert %></li>
                    <% }); %>
                </ul>
            </div>
            <% } %>

            <% if (languages && languages.length) { %>
            <div class="resume-section languages">
                <h4><?php _e('Languages', 'resume-parser'); ?></h4>
                <ul>
                    <% _.each(languages, function(lang) { %>
                        <li><%= lang %></li>
                    <% }); %>
                </ul>
            </div>
            <% } %>
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render resume list
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_resume_list($atts = array()) {
        if (!is_user_logged_in()) {
            return sprintf(
                '<p class="resume-parser-error">%s</p>',
                __('Please log in to view your resumes.', 'resume-parser')
            );
        }

        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_export' => 'true'
        ), $atts, 'resume-list');

        $db = Resume_Parser_Database::get_instance();
        $resumes = $db->get_by_user(
            get_current_user_id(),
            array(
                'per_page' => intval($atts['per_page']),
                'paged' => get_query_var('paged') ? get_query_var('paged') : 1
            )
        );

        if (is_wp_error($resumes)) {
            return sprintf(
                '<p class="resume-parser-error">%s</p>',
                $resumes->get_error_message()
            );
        }

        ob_start();
        ?>
        <div class="resume-parser-list">
            <?php if ($atts['show_export'] === 'true'): ?>
            <div class="resume-parser-export">
                <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                    <?php wp_nonce_field('resume_parser_export', 'resume_parser_export_nonce'); ?>
                    <input type="hidden" name="action" value="resume_parser_export" />
                    <button type="submit" class="button">
                        <?php _e('Export to CSV', 'resume-parser'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (empty($resumes)): ?>
                <p><?php _e('No resumes found.', 'resume-parser'); ?></p>
            <?php else: ?>
                <div class="resume-parser-grid">
                    <?php foreach ($resumes as $resume): ?>
                        <div class="resume-parser-item">
                            <h3><?php echo esc_html($resume->file_name); ?></h3>
                            <div class="resume-parser-meta">
                                <p>
                                    <strong><?php _e('Uploaded:', 'resume-parser'); ?></strong>
                                    <?php echo esc_html(
                                        date_i18n(
                                            get_option('date_format'),
                                            strtotime($resume->created_at)
                                        )
                                    ); ?>
                                </p>
                                <p>
                                    <strong><?php _e('Size:', 'resume-parser'); ?></strong>
                                    <?php echo esc_html(size_format($resume->file_size)); ?>
                                </p>
                            </div>
                            
                            <div class="resume-parser-actions">
                                <button class="button view-parsed-data" 
                                        data-id="<?php echo esc_attr($resume->id); ?>">
                                    <?php _e('View Parsed Data', 'resume-parser'); ?>
                                </button>
                                <a href="<?php echo esc_url($resume->file_url); ?>" 
                                   class="button" 
                                   target="_blank">
                                    <?php _e('Download Resume', 'resume-parser'); ?>
                                </a>
                                <button class="button button-link-delete delete-resume" 
                                        data-id="<?php echo esc_attr($resume->id); ?>">
                                    <?php _e('Delete', 'resume-parser'); ?>
                                </button>
                            </div>

                            <div class="resume-parser-data" 
                                 id="resume-data-<?php echo esc_attr($resume->id); ?>" 
                                 style="display: none;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
} 