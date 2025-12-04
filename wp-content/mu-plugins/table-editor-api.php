<?php
/**
 * Plugin Name: Product Eligibility Table Editor API
 * Description: REST API endpoints for CRUD operations on wp_zoho_product_eligibility table
 * Version: 1.0.0
 */ 

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    $namespace = 'table-editor/v1';
    
    // Get all records (with optional search)
    register_rest_route($namespace, '/records', [
        'methods' => 'GET',
        'callback' => 'te_get_records',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get single record
    register_rest_route($namespace, '/records/(?P<wp_id>.+)', [
        'methods' => 'GET',
        'callback' => 'te_get_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Create record
    register_rest_route($namespace, '/records', [
        'methods' => 'POST',
        'callback' => 'te_create_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Update record
    register_rest_route($namespace, '/records/(?P<wp_id>.+)', [
        'methods' => 'PUT',
        'callback' => 'te_update_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Delete record
    register_rest_route($namespace, '/records/(?P<wp_id>.+)', [
        'methods' => 'DELETE',
        'callback' => 'te_delete_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Search records (FileMaker-style find)
    register_rest_route($namespace, '/search', [
        'methods' => 'POST',
        'callback' => 'te_search_records',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get column metadata
    register_rest_route($namespace, '/columns', [
        'methods' => 'GET',
        'callback' => 'te_get_columns',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // === SAVED VIEWS ENDPOINTS ===
    
    // Get all saved views
    register_rest_route($namespace, '/views', [
        'methods' => 'GET',
        'callback' => 'te_get_views',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Save a new view
    register_rest_route($namespace, '/views', [
        'methods' => 'POST',
        'callback' => 'te_save_view',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Delete a view
    register_rest_route($namespace, '/views/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'te_delete_view',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get lookup options for a column
    register_rest_route($namespace, '/lookup/(?P<table>[a-zA-Z0-9_]+)', [
        'methods' => 'GET',
        'callback' => 'te_get_lookup_options',
        'permission_callback' => 'te_check_permission',
    ]);
});

/**
 * Check if user has permission (must be logged in admin)
 */
function te_check_permission() {
    return current_user_can('manage_options');
}

/**
 * Table name constant
 */
function te_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'zoho_product_eligibility';
}

/**
 * Column definitions
 */
function te_get_column_definitions() {
    return [
        'wp_id' => ['type' => 'varchar', 'length' => 255, 'primary' => true, 'editable' => false, 'required' => true],
        'name' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'venue_id' => ['type' => 'int', 'editable' => true],
        'venue_short_name' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'sku_old' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'sku_ext_1' => ['type' => 'varchar', 'length' => 10, 'editable' => true],
        'sku_ext_2' => ['type' => 'varchar', 'length' => 10, 'editable' => true],
        'status' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'max_yob' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'min_yob' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'max_rating' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'min_rating' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Males' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Females' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'product_type' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Field_Players' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Gk_s' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Sessions' => ['type' => 'text', 'editable' => true],
        'enrollment_max' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        'Message' => ['type' => 'text', 'editable' => true],
        'enrollment_close_date' => ['type' => 'date', 'editable' => true],
        'first_session_date' => ['type' => 'date', 'editable' => true],
        'days_apart' => ['type' => 'tinyint', 'editable' => true],
        'number_of_sessions' => ['type' => 'int', 'editable' => true],
        'session_dates' => ['type' => 'text', 'editable' => true],
        'session1_time' => ['type' => 'time', 'editable' => true],
        'session2_time' => ['type' => 'time', 'editable' => true],
        'min_yob_for_session_1' => ['type' => 'varchar', 'length' => 255, 'editable' => true],
        // Generated columns (read-only)
        'year' => ['type' => 'int', 'editable' => false, 'generated' => true],
        'month' => ['type' => 'int', 'editable' => false, 'generated' => true],
        'position_filter' => ['type' => 'varchar', 'length' => 5, 'editable' => false, 'generated' => true],
        'gender_filter' => ['type' => 'varchar', 'length' => 5, 'editable' => false, 'generated' => true],
        'sku' => ['type' => 'varchar', 'length' => 255, 'editable' => false, 'generated' => true],
    ];
}

/**
 * Get column metadata
 */
function te_get_columns() {
    return new WP_REST_Response([
        'success' => true,
        'columns' => te_get_column_definitions(),
    ], 200);
}

/**
 * Get all records with pagination
 */
function te_get_records(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page = min(500, max(10, intval($request->get_param('per_page') ?: 100)));
    $offset = ($page - 1) * $per_page;
    
    $order_by = sanitize_sql_orderby($request->get_param('order_by') ?: 'wp_id');
    $order = strtoupper($request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';
    
    // Get total count
    $total = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
    
    // Get records
    $records = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `{$table}` ORDER BY `{$order_by}` {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ),
        ARRAY_A
    );
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'records' => $records,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
    ], 200);
}

/**
 * Get single record
 */
function te_get_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    $wp_id = $request->get_param('wp_id');
    
    $record = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$table}` WHERE wp_id = %s", $wp_id),
        ARRAY_A
    );
    
    if (!$record) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Record not found',
        ], 404);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'record' => $record,
    ], 200);
}

/**
 * Create new record
 */
function te_create_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    $columns = te_get_column_definitions();
    
    $data = $request->get_json_params();
    
    // Validate wp_id is provided
    if (empty($data['wp_id'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'wp_id is required',
        ], 400);
    }
    
    // Check if wp_id already exists
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE wp_id = %s", $data['wp_id'])
    );
    
    if ($exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'A record with this wp_id already exists',
        ], 400);
    }
    
    // Build insert data (only editable columns + primary key for new records)
    $insert_data = [];
    $formats = [];
    
    foreach ($columns as $col_name => $col_def) {
        // Skip generated columns
        if (!empty($col_def['generated'])) continue;
        
        // For new records, wp_id is allowed
        if (isset($data[$col_name])) {
            $insert_data[$col_name] = $data[$col_name];
            $formats[] = te_get_format($col_def['type']);
        }
    }
    
    $result = $wpdb->insert($table, $insert_data, $formats);
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to create record: ' . $wpdb->last_error,
        ], 500);
    }
    
    // Fetch the created record (to get generated columns)
    $record = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$table}` WHERE wp_id = %s", $data['wp_id']),
        ARRAY_A
    );
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Record created successfully',
        'record' => $record,
    ], 201);
}

/**
 * Update existing record
 */
function te_update_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    $columns = te_get_column_definitions();
    $wp_id = $request->get_param('wp_id');
    
    // Check if record exists
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE wp_id = %s", $wp_id)
    );
    
    if (!$exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Record not found',
        ], 404);
    }
    
    $data = $request->get_json_params();
    
    // Build update data (only editable columns, exclude primary key and generated)
    $update_data = [];
    $formats = [];
    
    foreach ($columns as $col_name => $col_def) {
        // Skip primary key and generated columns
        if (!empty($col_def['primary']) || !empty($col_def['generated'])) continue;
        if (!$col_def['editable']) continue;
        
        if (array_key_exists($col_name, $data)) {
            $update_data[$col_name] = $data[$col_name];
            $formats[] = te_get_format($col_def['type']);
        }
    }
    
    if (empty($update_data)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No valid fields to update',
        ], 400);
    }
    
    $result = $wpdb->update(
        $table,
        $update_data,
        ['wp_id' => $wp_id],
        $formats,
        ['%s']
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to update record: ' . $wpdb->last_error,
        ], 500);
    }
    
    // Fetch the updated record
    $record = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$table}` WHERE wp_id = %s", $wp_id),
        ARRAY_A
    );
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Record updated successfully',
        'record' => $record,
    ], 200);
}

/**
 * Delete record
 */
function te_delete_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    $wp_id = $request->get_param('wp_id');
    
    // Check if record exists
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE wp_id = %s", $wp_id)
    );
    
    if (!$exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Record not found',
        ], 404);
    }
    
    $result = $wpdb->delete($table, ['wp_id' => $wp_id], ['%s']);
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to delete record: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Record deleted successfully',
    ], 200);
}

/**
 * Search records (FileMaker-style find)
 */
function te_search_records(WP_REST_Request $request) {
    global $wpdb;
    $table = te_table_name();
    $columns = te_get_column_definitions();
    
    $criteria = $request->get_json_params();
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page = min(500, max(10, intval($request->get_param('per_page') ?: 100)));
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_parts = [];
    $values = [];
    
    foreach ($criteria as $col_name => $search_value) {
        if (!isset($columns[$col_name])) continue;
        if ($search_value === '' || $search_value === null) continue;
        
        $col_def = $columns[$col_name];
        $col_type = $col_def['type'];
        
        // Text fields: partial match (LIKE)
        if (in_array($col_type, ['varchar', 'text'])) {
            $where_parts[] = "`{$col_name}` LIKE %s";
            $values[] = '%' . $wpdb->esc_like($search_value) . '%';
        }
        // Numeric/date fields: exact match
        else {
            $where_parts[] = "`{$col_name}` = %s";
            $values[] = $search_value;
        }
    }
    
    if (empty($where_parts)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No search criteria provided',
        ], 400);
    }
    
    $where_clause = implode(' AND ', $where_parts);
    
    // Get total matching count
    $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}";
    $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$values));
    
    // Get matching records
    $sql = "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY wp_id ASC LIMIT %d OFFSET %d";
    $all_values = array_merge($values, [$per_page, $offset]);
    $records = $wpdb->get_results($wpdb->prepare($sql, ...$all_values), ARRAY_A);
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'records' => $records,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
    ], 200);
}

/**
 * Get format string for wpdb based on column type
 */
function te_get_format($type) {
    switch ($type) {
        case 'int':
        case 'tinyint':
            return '%d';
        case 'float':
        case 'decimal':
            return '%f';
        default:
            return '%s';
    }
}

// ============================================
// SAVED VIEWS FUNCTIONS
// ============================================

/**
 * Views table name
 */
function te_views_table() {
    global $wpdb;
    return $wpdb->prefix . 'zoho_table_editors';
}

/**
 * Get all saved views
 */
function te_get_views() {
    global $wpdb;
    $table = te_views_table();
    
    $views = $wpdb->get_results(
        "SELECT id, view_name, prefs, column_prefs, created_by, created_at FROM `{$table}` ORDER BY view_name ASC",
        ARRAY_A
    );
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    // Decode JSON fields
    foreach ($views as &$view) {
        $view['prefs'] = json_decode($view['prefs'], true);
        $view['column_prefs'] = json_decode($view['column_prefs'], true);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'views' => $views,
    ], 200);
}

/**
 * Save a new view
 */
function te_save_view(WP_REST_Request $request) {
    global $wpdb;
    $table = te_views_table();
    
    $data = $request->get_json_params();
    
    $view_name = sanitize_text_field($data['view_name'] ?? '');
    $prefs = $data['prefs'] ?? [];
    
    if (empty($view_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'View name is required',
        ], 400);
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    $created_by = $current_user->display_name ?: $current_user->user_login;
    
    // Check if view name already exists
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM `{$table}` WHERE view_name = %s", $view_name)
    );
    
    if ($exists) {
        // Update existing view
        $result = $wpdb->update(
            $table,
            [
                'prefs' => json_encode($prefs),
                'created_by' => $created_by,
            ],
            ['id' => $exists],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update view: ' . $wpdb->last_error,
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'View updated successfully',
            'id' => intval($exists),
        ], 200);
    } else {
        // Insert new view
        $result = $wpdb->insert(
            $table,
            [
                'view_name' => $view_name,
                'prefs' => json_encode($prefs),
                'created_by' => $created_by,
            ],
            ['%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to save view: ' . $wpdb->last_error,
            ], 500);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'View saved successfully',
            'id' => $wpdb->insert_id,
        ], 201);
    }
}

/**
 * Delete a view
 */
function te_delete_view(WP_REST_Request $request) {
    global $wpdb;
    $table = te_views_table();
    $id = intval($request->get_param('id'));
    
    if ($id <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid view ID',
        ], 400);
    }
    
    $result = $wpdb->delete($table, ['id' => $id], ['%d']);
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to delete view: ' . $wpdb->last_error,
        ], 500);
    }
    
    if ($result === 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'View not found',
        ], 404);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'View deleted successfully',
    ], 200);
}

/**
 * Get lookup options from a related table
 */
function te_get_lookup_options(WP_REST_Request $request) {
    global $wpdb;
    
    $table_name = $request->get_param('table');
    $value_field = sanitize_key($request->get_param('value_field') ?: 'id');
    $display_field = sanitize_key($request->get_param('display_field') ?: 'name');
    $order_by = sanitize_key($request->get_param('order_by') ?: $display_field);
    $order = strtoupper($request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';
    
    // Validate table name (must start with wp_ prefix for security)
    if (!preg_match('/^wp_[a-zA-Z0-9_]+$/', $table_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid table name',
        ], 400);
    }
    
    // Check if table exists
    $table_exists = $wpdb->get_var(
        $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
    );
    
    if (!$table_exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Table not found: ' . $table_name,
        ], 404);
    }
    
    // Fetch options
    $sql = "SELECT `{$value_field}` as value, `{$display_field}` as label FROM `{$table_name}` ORDER BY `{$order_by}` {$order}";
    $options = $wpdb->get_results($sql, ARRAY_A);
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'options' => $options,
    ], 200);
}

// Add shortcode for the widget
add_shortcode('product_eligibility_editor', function() {
    $widget_path = ABSPATH . 'wp-content/wp-custom-scripts/nce-runner-task-10/table-editor-widget.html';
    
    if (!file_exists($widget_path)) {
        return '<p style="color:red;">Widget file not found: ' . esc_html($widget_path) . '</p>';
    }
    
    $contents = file_get_contents($widget_path);
    
    if ($contents === false) {
        return '<p style="color:red;">Unable to load widget file.</p>';
    }
    
    return $contents;
});

