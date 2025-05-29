<?php

class Resume_Parser_Post_Type {
    const POST_TYPE = 'resume';
    const TAXONOMY = 'resume_category';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => _x('Resumes', 'post type general name', 'resume-parser'),
            'singular_name'      => _x('Resume', 'post type singular name', 'resume-parser'),
            'menu_name'          => _x('Resumes', 'admin menu', 'resume-parser'),
            'add_new'            => _x('Add New', 'resume', 'resume-parser'),
            'add_new_item'       => __('Add New Resume', 'resume-parser'),
            'edit_item'          => __('Edit Resume', 'resume-parser'),
            'new_item'           => __('New Resume', 'resume-parser'),
            'view_item'          => __('View Resume', 'resume-parser'),
            'search_items'       => __('Search Resumes', 'resume-parser'),
            'not_found'          => __('No resumes found', 'resume-parser'),
            'not_found_in_trash' => __('No resumes found in Trash', 'resume-parser')
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'resume'),
            'capability_type'     => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'author', 'thumbnail'),
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-media-document'
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('Resume Categories', 'taxonomy general name', 'resume-parser'),
            'singular_name'     => _x('Resume Category', 'taxonomy singular name', 'resume-parser'),
            'search_items'      => __('Search Resume Categories', 'resume-parser'),
            'all_items'         => __('All Resume Categories', 'resume-parser'),
            'parent_item'       => __('Parent Resume Category', 'resume-parser'),
            'parent_item_colon' => __('Parent Resume Category:', 'resume-parser'),
            'edit_item'         => __('Edit Resume Category', 'resume-parser'),
            'update_item'       => __('Update Resume Category', 'resume-parser'),
            'add_new_item'      => __('Add New Resume Category', 'resume-parser'),
            'new_item_name'     => __('New Resume Category Name', 'resume-parser'),
            'menu_name'         => __('Categories', 'resume-parser'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'          => true,
            'show_admin_column' => true,
            'query_var'        => true,
            'rewrite'          => array('slug' => 'resume-category'),
            'show_in_rest'     => true,
        );

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'resume_details',
            __('Resume Details', 'resume-parser'),
            array($this, 'render_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('resume_details_nonce', 'resume_details_nonce');
        
        $parsed_data = get_post_meta($post->ID, '_resume_parsed_data', true);
        if (!$parsed_data) {
            $parsed_data = array(
                'personal_details' => array(),
                'education' => array(),
                'experience' => array(),
                'skills' => array(),
                'certifications' => array()
            );
        }
        
        ?>
        <div class="resume-details-wrapper">
            <h3><?php _e('Personal Details', 'resume-parser'); ?></h3>
            <div class="personal-details">
                <?php
                foreach ($parsed_data['personal_details'] as $key => $value) {
                    echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
                }
                ?>
            </div>

            <h3><?php _e('Education', 'resume-parser'); ?></h3>
            <div class="education">
                <?php
                foreach ($parsed_data['education'] as $edu) {
                    echo '<div class="education-item">';
                    foreach ($edu as $key => $value) {
                        echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <h3><?php _e('Experience', 'resume-parser'); ?></h3>
            <div class="experience">
                <?php
                foreach ($parsed_data['experience'] as $exp) {
                    echo '<div class="experience-item">';
                    foreach ($exp as $key => $value) {
                        echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <h3><?php _e('Skills', 'resume-parser'); ?></h3>
            <div class="skills">
                <?php
                if (is_array($parsed_data['skills'])) {
                    echo '<p>' . esc_html(implode(', ', $parsed_data['skills'])) . '</p>';
                }
                ?>
            </div>

            <h3><?php _e('Certifications', 'resume-parser'); ?></h3>
            <div class="certifications">
                <?php
                foreach ($parsed_data['certifications'] as $cert) {
                    echo '<div class="certification-item">';
                    foreach ($cert as $key => $value) {
                        echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['resume_details_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['resume_details_nonce'], 'resume_details_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save parsed data if it exists
        if (isset($_POST['resume_parsed_data'])) {
            update_post_meta($post_id, '_resume_parsed_data', $_POST['resume_parsed_data']);
        }
    }
} 