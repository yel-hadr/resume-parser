<?php
/**
 * Database Handler Class
 *
 * Manages database operations for resume data
 *
 * @package ResumeParser
 * @since 2.0.0
 */

class Resume_Parser_Database {
    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Instance of this class
     *
     * @var Resume_Parser_Database
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . RESUME_PARSER_TABLE_NAME;
    }

    /**
     * Get instance of this class
     *
     * @return Resume_Parser_Database
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . RESUME_PARSER_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            file_path varchar(255) NOT NULL,
            file_url varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) NOT NULL,
            parsed_data longtext NOT NULL,
            raw_content longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Insert resume data
     *
     * @param array $data Resume data
     * @return int|WP_Error Inserted ID or error
     */
    public function insert($data) {
        global $wpdb;

        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in to upload resumes.', 'resume-parser'));
        }

        $defaults = array(
            'user_id' => get_current_user_id(),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        $required_fields = array('file_path', 'file_url', 'file_name', 'file_type', 'file_size', 'parsed_data', 'raw_content');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'resume-parser'), $field));
            }
        }

        // Ensure parsed_data is JSON
        if (is_array($data['parsed_data'])) {
            $data['parsed_data'] = wp_json_encode($data['parsed_data']);
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%d', // user_id
                '%s', // file_path
                '%s', // file_url
                '%s', // file_name
                '%s', // file_type
                '%d', // file_size
                '%s', // parsed_data
                '%s', // raw_content
                '%s', // status
                '%s', // created_at
                '%s'  // updated_at
            )
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', __('Failed to insert resume data.', 'resume-parser'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Get resume data by ID
     *
     * @param int $id Resume ID
     * @return object|WP_Error Resume data or error
     */
    public function get($id) {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );

        if (null === $result) {
            return new WP_Error('not_found', __('Resume not found.', 'resume-parser'));
        }

        // Parse JSON data
        $result->parsed_data = json_decode($result->parsed_data, true);

        return $result;
    }

    /**
     * Get resumes by user ID
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array|WP_Error Resume data or error
     */
    public function get_by_user($user_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 10,
            'paged' => 1,
            'status' => ''
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['paged'] - 1) * $args['per_page'];

        $where = $wpdb->prepare("WHERE user_id = %d", $user_id);
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
            $where 
            ORDER BY $orderby 
            $limit"
        );

        if (null === $results) {
            return new WP_Error('query_error', __('Failed to retrieve resumes.', 'resume-parser'));
        }

        // Parse JSON data for each result
        foreach ($results as $result) {
            $result->parsed_data = json_decode($result->parsed_data, true);
        }

        return $results;
    }

    /**
     * Update resume data
     *
     * @param int $id Resume ID
     * @param array $data Updated data
     * @return bool|WP_Error True on success, error on failure
     */
    public function update($id, $data) {
        global $wpdb;

        // Ensure parsed_data is JSON
        if (isset($data['parsed_data']) && is_array($data['parsed_data'])) {
            $data['parsed_data'] = wp_json_encode($data['parsed_data']);
        }

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id)
        );

        if ($result === false) {
            return new WP_Error('update_error', __('Failed to update resume data.', 'resume-parser'));
        }

        return true;
    }

    /**
     * Delete resume data
     *
     * @param int $id Resume ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete($id) {
        global $wpdb;

        // Get file path before deletion
        $resume = $this->get($id);
        if (is_wp_error($resume)) {
            return $resume;
        }

        // Delete file
        if (file_exists($resume->file_path)) {
            unlink($resume->file_path);
        }

        // Delete database record
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_error', __('Failed to delete resume data.', 'resume-parser'));
        }

        return true;
    }

    /**
     * Export resumes to CSV
     *
     * @param array $args Query arguments
     * @return string|WP_Error CSV content or error
     */
    public function export_csv($args = array()) {
        global $wpdb;

        $defaults = array(
            'user_id' => 0,
            'status' => '',
            'start_date' => '',
            'end_date' => ''
        );

        $args = wp_parse_args($args, $defaults);
        $where = array();
        $prepare_values = array();

        if (!empty($args['user_id'])) {
            $where[] = "user_id = %d";
            $prepare_values[] = $args['user_id'];
        }

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $prepare_values[] = $args['status'];
        }

        if (!empty($args['start_date'])) {
            $where[] = "created_at >= %s";
            $prepare_values[] = $args['start_date'];
        }

        if (!empty($args['end_date'])) {
            $where[] = "created_at <= %s";
            $prepare_values[] = $args['end_date'];
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $query = "SELECT * FROM {$this->table_name} $where_clause ORDER BY created_at DESC";

        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, $prepare_values);
        }

        $results = $wpdb->get_results($query);

        if (null === $results) {
            return new WP_Error('export_error', __('Failed to export resumes.', 'resume-parser'));
        }

        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array(
            'ID',
            'User ID',
            'File Name',
            'File Type',
            'File Size',
            'Status',
            'Created At',
            'Name',
            'Email',
            'Phone',
            'Education',
            'Experience',
            'Skills'
        ));

        // Write data
        foreach ($results as $row) {
            $parsed_data = json_decode($row->parsed_data, true);
            
            fputcsv($output, array(
                $row->id,
                $row->user_id,
                $row->file_name,
                $row->file_type,
                size_format($row->file_size),
                $row->status,
                $row->created_at,
                $parsed_data['personal_info']['name'] ?? '',
                $parsed_data['personal_info']['email'] ?? '',
                $parsed_data['personal_info']['phone'] ?? '',
                implode('; ', array_map(function($edu) {
                    return "{$edu['degree']} - {$edu['institution']} ({$edu['graduation_year']})";
                }, $parsed_data['education'] ?? array())),
                implode('; ', array_map(function($exp) {
                    return "{$exp['position']} at {$exp['company']} ({$exp['duration']})";
                }, $parsed_data['experience'] ?? array())),
                implode(', ', array_merge(
                    $parsed_data['skills']['technical'] ?? array(),
                    $parsed_data['skills']['soft'] ?? array()
                ))
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
} 