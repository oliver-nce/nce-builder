<?php
// LAST UPDATED: 2025-12-07 18:08:00
/**
 * Plugin Name: Multi-Table Editor API
 * Description: REST API endpoints for CRUD operations on configurable tables
 * Version: 2.0.0
 */   

if (!defined('ABSPATH')) exit; 

add_action('rest_api_init', function() {
    $namespace = 'table-editor/v1';
    
    // === DEBUG ENDPOINT ===
    register_rest_route($namespace, '/debug-columns/(?P<table>[a-zA-Z0-9_]+)', [
        'methods' => 'GET',
        'callback' => 'te_debug_columns',
        'permission_callback' => '__return_true',  // Keep public for debugging
    ]); 
     
    // === TABLE MANAGEMENT ===
     
    // Get list of available tables
    register_rest_route($namespace, '/tables', [
        'methods' => 'GET',
        'callback' => 'te_get_tables',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get column schema for a table (from INFORMATION_SCHEMA)
    register_rest_route($namespace, '/schema/(?P<table>[a-zA-Z0-9_]+)', [
        'methods' => 'GET',
        'callback' => 'te_get_table_schema',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // === RECORD OPERATIONS ===
    
    // Get all records (with optional search)
    register_rest_route($namespace, '/records', [
        'methods' => 'GET',
        'callback' => 'te_get_records',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get single record
    register_rest_route($namespace, '/records/(?P<pk_value>.+)', [
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
    register_rest_route($namespace, '/records/(?P<pk_value>.+)', [
        'methods' => 'PUT',
        'callback' => 'te_update_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Delete record
    register_rest_route($namespace, '/records/(?P<pk_value>.+)', [
        'methods' => 'DELETE',
        'callback' => 'te_delete_record',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Bulk update records (single column)
    register_rest_route($namespace, '/bulk-update', [
        'methods' => 'POST',
        'callback' => 'te_bulk_update_records',
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
    
    // Get all saved views (filtered by table)
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
    
    // Update sql_filter for a view
    register_rest_route($namespace, '/views/(?P<id>\d+)/filter', [
        'methods' => 'POST',
        'callback' => 'te_update_view_filter',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get lookup options for a column
    register_rest_route($namespace, '/lookup/(?P<table>[a-zA-Z0-9_]+)', [
        'methods' => 'GET',
        'callback' => 'te_get_lookup_options',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get available portals for a table
    register_rest_route($namespace, '/portals', [
        'methods' => 'GET',
        'callback' => 'te_get_available_portals',
        'permission_callback' => 'te_check_permission',
    ]);
    
    // Get portal data (records from a portal)
    register_rest_route($namespace, '/portal-data', [
        'methods' => 'GET',
        'callback' => 'te_get_portal_data',
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
 * Debug endpoint to see column definitions
 */
function te_debug_columns(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $request->get_param('table');
    
    // Get raw column info from MySQL
    $db_name = DB_NAME;
    $raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, EXTRA 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            $db_name,
            $table_name
        ),
        ARRAY_A
    ); 
    
    // Get processed columns
    $columns = te_get_column_definitions_for_table($table_name);
    
    return new WP_REST_Response([
        'table' => $table_name,
        'raw_mysql_info' => $raw,
        'processed_columns' => $columns,
    ], 200);
}

/**
 * Get current table name from request parameter
 */
function te_get_table_from_request($request) {
    global $wpdb;
    $table = $request->get_param('table');
    
    // Default to product_eligibility for backwards compatibility
    if (empty($table)) {
        return $wpdb->prefix . 'zoho_product_eligibility';
    }
    
    // Security: must start with wp_ prefix
    if (!preg_match('/^wp_[a-zA-Z0-9_]+$/', $table)) {
        return null;
    }
    
    return $table;
}
  
/**
 * Get list of available tables from wp_zoho_table_editors
 */
function te_get_tables() {
    global $wpdb;
    $views_table = te_views_table();
    
    // Get distinct tables that have views defined
    $tables = $wpdb->get_col(
        "SELECT DISTINCT `target_table` FROM `{$views_table}` WHERE `target_table` IS NOT NULL AND `target_table` != '' ORDER BY `target_table` ASC"
    );
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'tables' => $tables,
    ], 200);
}

/**
 * Get table schema from MySQL INFORMATION_SCHEMA
 */
function te_get_table_schema(WP_REST_Request $request) {
    global $wpdb;
    
    $table_name = $request->get_param('table');
    
    // Security: must start with wp_ prefix
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
    
    // Get column info from INFORMATION_SCHEMA
    $db_name = DB_NAME;
    $columns_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as data_type,
                COLUMN_TYPE as column_type,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                IS_NULLABLE as nullable,
                COLUMN_KEY as column_key,
                COLUMN_DEFAULT as default_value,
                EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s 
            ORDER BY ORDINAL_POSITION",
            $db_name,
            $table_name
        ),
        ARRAY_A
    );
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    // Transform to our column definition format
    $columns = [];
    $primary_key = null;
    
    foreach ($columns_raw as $col) {
        $is_primary = ($col['column_key'] === 'PRI');
        // Check for actual generated columns (VIRTUAL/STORED), NOT columns with DEFAULT values
        // MySQL 8.0+ marks DEFAULT columns as "DEFAULT_GENERATED" which we should NOT treat as generated
        $extra_upper = strtoupper($col['extra'] ?? '');
        $is_generated = (
            strpos($extra_upper, 'VIRTUAL GENERATED') !== false || 
            strpos($extra_upper, 'STORED GENERATED') !== false ||
            strpos($extra_upper, 'GENERATED ALWAYS') !== false
        );
        $is_auto_inc = (strpos($col['extra'], 'auto_increment') !== false);
        
        // Map MySQL types to simplified types
        $type = $col['data_type'];
        if (in_array($type, ['varchar', 'char'])) $type = 'varchar';
        if (in_array($type, ['text', 'mediumtext', 'longtext'])) $type = 'text';
        if (in_array($type, ['int', 'bigint', 'smallint', 'mediumint'])) $type = 'int';
        if (in_array($type, ['decimal', 'float', 'double'])) $type = 'decimal';
        
        $columns[$col['name']] = [
            'name' => $col['name'],
            'type' => $type,
            'length' => $col['max_length'] ? intval($col['max_length']) : null,
            'nullable' => ($col['nullable'] === 'YES'),
            'primary' => $is_primary,
            'generated' => $is_generated,
            'auto_increment' => $is_auto_inc,
            'editable' => !$is_generated && !$is_primary, // Primary keys not editable on existing records
            'required' => ($col['nullable'] === 'NO' && $col['default_value'] === null && !$is_auto_inc),
        ];
        
        if ($is_primary) {
            $primary_key = $col['name'];
        }
    }
    
    return new WP_REST_Response([
        'success' => true,
        'table' => $table_name,
        'primary_key' => $primary_key,
        'columns' => $columns,
    ], 200);
}

/**
 * Get column definitions for a table (backward compatible wrapper)
 */
function te_get_column_definitions_for_table($table_name) {
    global $wpdb;
    
    $db_name = DB_NAME;
    $columns_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as data_type,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                IS_NULLABLE as nullable,
                COLUMN_KEY as column_key,
                EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s 
            ORDER BY ORDINAL_POSITION",
            $db_name,
            $table_name
        ),
        ARRAY_A
    );
    
    $columns = [];
    foreach ($columns_raw as $col) {
        $is_primary = ($col['column_key'] === 'PRI');
        // Check for actual generated columns (VIRTUAL/STORED), NOT columns with DEFAULT values
        // MySQL 8.0+ marks DEFAULT columns as "DEFAULT_GENERATED" which we should NOT treat as generated
        $extra_upper = strtoupper($col['extra'] ?? '');
        $is_generated = (
            strpos($extra_upper, 'VIRTUAL GENERATED') !== false || 
            strpos($extra_upper, 'STORED GENERATED') !== false ||
            strpos($extra_upper, 'GENERATED ALWAYS') !== false
        );
        
        $type = $col['data_type'];
        if (in_array($type, ['varchar', 'char'])) $type = 'varchar';
        if (in_array($type, ['text', 'mediumtext', 'longtext'])) $type = 'text';
        if (in_array($type, ['int', 'bigint', 'smallint', 'mediumint'])) $type = 'int';
        
        $columns[$col['name']] = [
            'type' => $type,
            'primary' => $is_primary,
            'generated' => $is_generated,
            'editable' => !$is_generated,
        ];
    }
    
    return $columns;
}

/**
 * Get primary key column for a table
 */
function te_get_primary_key($table_name) {
    global $wpdb;
    $db_name = DB_NAME;
    
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_KEY = 'PRI'",
            $db_name,
            $table_name
        )
    );
}

/**
 * Get column metadata (now requires table parameter)
 */
function te_get_columns(WP_REST_Request $request) {
    $table = te_get_table_from_request($request);
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid table name',
        ], 400);
    }
    
    $columns = te_get_column_definitions_for_table($table);
    $primary_key = te_get_primary_key($table);
    
    return new WP_REST_Response([
        'success' => true,
        'table' => $table,
        'primary_key' => $primary_key,
        'columns' => $columns,
    ], 200);
}

/**
 * Get all records with pagination
 */
function te_get_records(WP_REST_Request $request) {
    global $wpdb;
    $table = te_get_table_from_request($request);
    $view_id = intval($request->get_param('view_id')) ?: null;
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if (!$table_exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Table not found: ' . $table,
        ], 404);
    }
    
    $primary_key = te_get_primary_key($table);
    $default_order = $primary_key ?: 'id';
    
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page = min(500, max(10, intval($request->get_param('per_page') ?: 100)));
    $offset = ($page - 1) * $per_page;
    
    $order_by = sanitize_sql_orderby($request->get_param('order_by') ?: $default_order);
    $order = strtoupper($request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';
    
    // Apply optional table filter
    $filter = te_get_table_filter($table, $view_id);
    if (is_wp_error($filter)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter->get_error_message(),
        ], 500);
    }
    $filter_valid = te_validate_table_filter($table, $filter);
    if (is_wp_error($filter_valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter_valid->get_error_message(),
        ], 400);
    }
    $filter_clause = $filter ? "WHERE ({$filter})" : '';
    
    // Get total count
    $total = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` {$filter_clause}");
    
    // Get records
    $records_sql = $wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY `{$order_by}` {$order} LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );
    // Insert filter after prepare to avoid placeholder conflicts with % in filters
    if ($filter_clause) {
        $records_sql = str_replace("ORDER BY", "{$filter_clause} ORDER BY", $records_sql);
    }
    $records = $wpdb->get_results($records_sql, ARRAY_A);
    
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
        'primary_key' => $primary_key,
    ], 200);
}

/**
 * Get single record
 */
function te_get_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_get_table_from_request($request);
    $view_id = intval($request->get_param('view_id')) ?: null;
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $primary_key = te_get_primary_key($table);
    $pk_value = $request->get_param('pk_value');
    
    // Apply optional table filter
    $filter = te_get_table_filter($table, $view_id);
    if (is_wp_error($filter)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter->get_error_message(),
        ], 500);
    }
    $filter_valid = te_validate_table_filter($table, $filter);
    if (is_wp_error($filter_valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter_valid->get_error_message(),
        ], 400);
    }
    $filter_clause = $filter ? " AND ({$filter})" : '';
    
    $record_sql = $wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value);
    $record_sql .= $filter_clause;
    
    $record = $wpdb->get_row($record_sql, ARRAY_A);
    
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
    $table = te_get_table_from_request($request);
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $columns = te_get_column_definitions_for_table($table);
    $primary_key = te_get_primary_key($table);
    $data = $request->get_json_params();
    
    // Check if primary key value is provided (for non-auto-increment)
    $pk_value = isset($data[$primary_key]) ? $data[$primary_key] : null;
    
    if (!empty($pk_value)) {
        // Check if primary key already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value)
        );
        
        if ($exists) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "A record with this {$primary_key} already exists",
            ], 400);
        }
    }
    
    // Build insert data (only non-generated columns)
    $insert_data = [];
    $formats = [];
    
    foreach ($columns as $col_name => $col_def) {
        // Skip generated columns
        if (!empty($col_def['generated'])) continue;
        
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
    
    // Determine the primary key value for fetching (might be auto-increment)
    $fetch_pk = $pk_value ?: $wpdb->insert_id;
    
    // Fetch the created record (to get generated columns and auto-increment ID)
    $record_sql = $wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$primary_key}` = %s", $fetch_pk);
    $record = $wpdb->get_row($record_sql, ARRAY_A);
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Record created successfully',
        'record' => $record,
    ], 201);
}

/**
 * Update existing record
 * Simple approach: just update by primary key, check if it worked
 */
function te_update_record(WP_REST_Request $request) {
    global $wpdb;
    $table = te_get_table_from_request($request);
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $columns = te_get_column_definitions_for_table($table);
    $primary_key = te_get_primary_key($table);
    $pk_value = $request->get_param('pk_value');
    $data = $request->get_json_params();
    
    // Debug: if no data received, check raw body
    if (empty($data)) {
        $raw_body = $request->get_body();
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No JSON data received',
            'debug' => [
                'raw_body' => $raw_body,
                'content_type' => $request->get_content_type(),
            ],
        ], 400);
    }
    
    // Build update data (only editable columns, exclude primary key and generated)
    $update_data = [];
    $formats = [];
    
    foreach ($columns as $col_name => $col_def) {
        // Skip primary key and generated columns
        if (!empty($col_def['primary']) || !empty($col_def['generated'])) continue;
        if (empty($col_def['editable'])) continue;
        
        if (array_key_exists($col_name, $data)) {
            $update_data[$col_name] = $data[$col_name];
            $formats[] = te_get_format($col_def['type']);
        }
    }
    
    if (empty($update_data)) {
        // Debug: show what we received vs what we expected
        $col_names = array_keys($columns);
        $data_keys = array_keys($data);
        $editable_cols = array_keys(array_filter($columns, function($c) {
            return !empty($c['editable']) && empty($c['primary']) && empty($c['generated']);
        }));
        
        // Show full column info for diagnosis
        $col_details = [];
        foreach ($columns as $name => $def) {
            $col_details[$name] = [
                'editable' => $def['editable'] ?? false,
                'primary' => $def['primary'] ?? false,
                'generated' => $def['generated'] ?? false,
            ];
        }
        
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No valid fields to update',
            'debug' => [
                'table' => $table,
                'primary_key' => $primary_key,
                'pk_value' => $pk_value,
                'table_columns' => $col_names,
                'editable_columns' => $editable_cols,
                'data_keys_received' => $data_keys,
                'column_details' => $col_details,
            ],
        ], 400);
    }
    
    // Just do the update by primary key - no existence check, no filters
    $result = $wpdb->update(
        $table,
        $update_data,
        [$primary_key => $pk_value],
        $formats,
        ['%s']
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
        ], 500);
    }
    
    if ($result === 0) {
        // 0 rows affected - either record doesn't exist or no changes made
        // Try to fetch it to see if it exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value)
        );
        if (!$exists) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Record not found',
            ], 404);
        }
        // Record exists but no changes - that's OK
    }
    
    // Fetch the updated record
    $record = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value),
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
    $table = te_get_table_from_request($request);
    $view_id = intval($request->get_param('view_id')) ?: null;
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $primary_key = te_get_primary_key($table);
    $pk_value = $request->get_param('pk_value');
    
    // Apply optional table filter
    $filter = te_get_table_filter($table, $view_id);
    if (is_wp_error($filter)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter->get_error_message(),
        ], 500);
    }
    $filter_valid = te_validate_table_filter($table, $filter);
    if (is_wp_error($filter_valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter_valid->get_error_message(),
        ], 400);
    }
    $filter_clause = $filter ? " AND ({$filter})" : '';
    
    // Check if record exists
    $exists_sql = $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value);
    $exists_sql .= $filter_clause;
    $exists = $wpdb->get_var($exists_sql);
    
    if (!$exists) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Record not found',
        ], 404);
    }
    
    if ($filter_clause) {
        $delete_sql = $wpdb->prepare("DELETE FROM `{$table}` WHERE `{$primary_key}` = %s", $pk_value);
        $delete_sql .= $filter_clause;
        $result = $wpdb->query($delete_sql);
    } else {
        $result = $wpdb->delete($table, [$primary_key => $pk_value], ['%s']);
    }
    
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
 * Bulk update a single column for multiple records
 */
function te_bulk_update_records(WP_REST_Request $request) {
    global $wpdb;
    $table = te_get_table_from_request($request);
    $view_id = intval($request->get_param('view_id')) ?: null;
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $columns = te_get_column_definitions_for_table($table);
    $primary_key = te_get_primary_key($table);
    $body = $request->get_json_params();
    
    $column = sanitize_key($body['column'] ?? '');
    $value = $body['value'] ?? null;
    $ids = $body['ids'] ?? [];
    
    // Apply optional table filter
    $filter = te_get_table_filter($table, $view_id);
    if (is_wp_error($filter)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter->get_error_message(),
        ], 500);
    }
    $filter_valid = te_validate_table_filter($table, $filter);
    if (is_wp_error($filter_valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter_valid->get_error_message(),
        ], 400);
    }
    $filter_clause = $filter ? " AND ({$filter})" : '';
    
    if (!$column || !isset($columns[$column])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid column name',
        ], 400);
    }
    
    $col_def = $columns[$column];
    
    // Only allow editable, non-primary, non-generated columns
    if (!empty($col_def['primary']) || !empty($col_def['generated']) || empty($col_def['editable'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Column is not editable',
        ], 400);
    }
    
    if (empty($ids) || !is_array($ids)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No record IDs provided',
        ], 400);
    }
    
    // Prepare placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '%s'));
    $format = te_get_format($col_def['type']);
    
    $base_sql = $wpdb->prepare(
        "UPDATE `{$table}` SET `{$column}` = {$format} WHERE `{$primary_key}` IN ({$placeholders})",
        array_merge([$value], $ids)
    );
    $sql = $filter_clause ? ($base_sql . $filter_clause) : $base_sql;
    
    $result = $wpdb->query($sql);
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to bulk update records: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'updated' => intval($result),
    ], 200);
}

/**
 * Get lookup field definitions from view's column_prefs
 */
function te_get_lookup_definitions_for_view($column_prefs) {
    $lookups = [];
    
    if (!empty($column_prefs['column_prefs'])) {
        foreach ($column_prefs['column_prefs'] as $pref) {
            if ($pref['pref_type'] === 'lookup') {
                $lookups[$pref['column']] = [
                    'lookup_table' => $pref['lookup_table'],
                    'value_field' => $pref['value_field'],
                    'display_field' => $pref['display_field'],
                ];
            }
        }
    }
    
    return $lookups;
}

/**
 * Search records (FileMaker-style find)
 */
function te_search_records(WP_REST_Request $request) {
    global $wpdb;
    $table = te_get_table_from_request($request);
    $view_id = intval($request->get_param('view_id')) ?: null;
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid or missing table parameter',
        ], 400);
    }
    
    $columns = te_get_column_definitions_for_table($table);
    $primary_key = te_get_primary_key($table);
    
    // Apply optional table filter
    $filter = te_get_table_filter($table, $view_id);
    if (is_wp_error($filter)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter->get_error_message(),
        ], 500);
    }
    $filter_valid = te_validate_table_filter($table, $filter);
    if (is_wp_error($filter_valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $filter_valid->get_error_message(),
        ], 400);
    }
    $filter_clause = $filter ? " AND ({$filter})" : '';
    
    // Get lookup definitions from request body if provided
    $body = $request->get_json_params();
    $criteria = isset($body['criteria']) ? $body['criteria'] : $body;
    $lookups = isset($body['lookups']) ? $body['lookups'] : [];
    
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page = min(500, max(10, intval($request->get_param('per_page') ?: 100)));
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_parts = [];
    $values = [];
    $joins = [];
    
    foreach ($criteria as $col_name => $search_value) {
        if (!isset($columns[$col_name])) continue;
        if ($search_value === '' || $search_value === null) continue;
        
        $col_def = $columns[$col_name];
        $col_type = $col_def['type'];
        
        // Check if this is a lookup field - search on display value
        if (isset($lookups[$col_name])) {
            $lookup = $lookups[$col_name];
            $alias = 'lkp_' . $col_name;
            
            // Add JOIN for lookup table
            $joins[$col_name] = "LEFT JOIN `{$lookup['lookup_table']}` AS `{$alias}` ON `{$table}`.`{$col_name}` = `{$alias}`.`{$lookup['value_field']}`";
            
            // Search on display field
            $where_parts[] = "`{$alias}`.`{$lookup['display_field']}` LIKE %s";
            $values[] = '%' . $wpdb->esc_like($search_value) . '%';
        }
        // Text fields: partial match (LIKE)
        else if (in_array($col_type, ['varchar', 'text'])) {
            $where_parts[] = "`{$table}`.`{$col_name}` LIKE %s";
            $values[] = '%' . $wpdb->esc_like($search_value) . '%';
        }
        // Numeric/date fields: exact match
        else {
            $where_parts[] = "`{$table}`.`{$col_name}` = %s";
            $values[] = $search_value;
        }
    }
     
    if (empty($where_parts)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No search criteria provided',
        ], 400);
    }
    
    $join_clause = implode(' ', $joins);
    $where_clause = implode(' AND ', $where_parts);
    
    // Get total matching count
    $count_sql = "SELECT COUNT(*) FROM `{$table}` {$join_clause} WHERE {$where_clause}";
    $count_sql = $wpdb->prepare($count_sql, ...$values);
    if ($filter_clause) {
        $count_sql .= $filter_clause;
    }
    $total = $wpdb->get_var($count_sql);
    
    // Get matching records
    $order_col = $primary_key ?: 'id';
    $sql = "SELECT `{$table}`.* FROM `{$table}` {$join_clause} WHERE {$where_clause} ORDER BY `{$table}`.`{$order_col}` ASC LIMIT %d OFFSET %d";
    $all_values = array_merge($values, [$per_page, $offset]);
    $records_sql = $wpdb->prepare($sql, ...$all_values);
    if ($filter_clause) {
        $records_sql = str_replace(" ORDER BY", " {$filter_clause} ORDER BY", $records_sql);
    }
    $records = $wpdb->get_results($records_sql, ARRAY_A);
    
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
 * Get a table-level SQL filter from the editors table (sql_filter column)
 * Cached per request.
 */
function te_get_table_filter($table, $view_id = null) {
    static $cache = [];
    $cache_key = $table . '|' . ($view_id ?: 'table');
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }
    
    global $wpdb;
    $views_table = te_views_table();
    
    if ($view_id) {
        // IMPORTANT: Only use filter if view belongs to THIS table
        $filter = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sql_filter FROM `{$views_table}` WHERE id = %d AND `target_table` = %s LIMIT 1",
                $view_id,
                $table
            )
        );
    } else {
        // Fallback: first non-empty filter defined for this table
        $filter = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sql_filter FROM `{$views_table}` WHERE `target_table` = %s AND sql_filter IS NOT NULL AND sql_filter != '' LIMIT 1",
                $table
            )
        );
    }
    
    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Failed to load sql_filter: ' . $wpdb->last_error);
    }
    
    $cache[$cache_key] = $filter ?: null;
    return $cache[$cache_key];
}

/**
 * Validate a sql_filter by running an EXPLAIN on a trivial query.
 */
function te_validate_table_filter($table, $filter) {
    global $wpdb;
    if (!$filter) return true;
    
    // Validate syntax; allow references to other tables.
    $test_sql = "EXPLAIN SELECT 1 FROM `{$table}` WHERE ({$filter})";
    $wpdb->get_results($test_sql);
    
    if ($wpdb->last_error) {
        return new WP_Error('invalid_filter', 'Invalid sql_filter: ' . $wpdb->last_error);
    }
    
    return true;
}

/**
 * Get all saved views (filtered by table)
 */
function te_get_views(WP_REST_Request $request) {
    global $wpdb;
    $views_table = te_views_table();
    $filter_table = $request->get_param('table');
    
    if ($filter_table) {
        $views = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$views_table}` 
                WHERE `target_table` = %s 
                ORDER BY view_name ASC",
                $filter_table
            ),
            ARRAY_A
        );
    } else {
        $views = $wpdb->get_results(
            "SELECT * FROM `{$views_table}` 
            ORDER BY `target_table` ASC, view_name ASC",
            ARRAY_A
        );
    }
    
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
    $views_table = te_views_table();
    
    $data = $request->get_json_params();
    
    $data_table = sanitize_text_field($data['target_table'] ?? '');
    $view_name = sanitize_text_field($data['view_name'] ?? '');
    $prefs = $data['prefs'] ?? [];
    $sql_filter = isset($data['sql_filter']) ? $data['sql_filter'] : null;
    
    if (empty($view_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'View name is required',
        ], 400);
    }
    
    if (empty($data_table)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Table name is required',
        ], 400);
    }
    
    // Get current user
    $current_user = wp_get_current_user();
    $created_by = $current_user->display_name ?: $current_user->user_login;
    
    // Check if view name already exists FOR THIS TABLE
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM `{$views_table}` WHERE `target_table` = %s AND view_name = %s",
            $data_table,
            $view_name
        )
    );
    
    if ($exists) {
        // Update existing view
        $result = $wpdb->update(
            $views_table,
            [
                'prefs' => json_encode($prefs),
                'created_by' => $created_by,
                'sql_filter' => $sql_filter,
            ],
            ['id' => $exists],
            ['%s', '%s', '%s'],
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
            $views_table,
            [
                'target_table' => $data_table,
                'view_name' => $view_name,
                'prefs' => json_encode($prefs),
                'created_by' => $created_by,
                'sql_filter' => $sql_filter,
            ],
            ['%s', '%s', '%s', '%s', '%s']
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
 * Update sql_filter for a view
 */
function te_update_view_filter(WP_REST_Request $request) {
    global $wpdb;
    $views_table = te_views_table();
    $id = intval($request->get_param('id'));
    
    if ($id <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid view ID',
        ], 400);
    }
    
    $body = $request->get_json_params();
    $sql_filter = isset($body['sql_filter']) ? $body['sql_filter'] : null;
    
    // Get table for validation
    $table = $wpdb->get_var($wpdb->prepare("SELECT `target_table` FROM `{$views_table}` WHERE id = %d", $id));
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'View not found',
        ], 404);
    }
    
    // Validate filter syntax
    $valid = te_validate_table_filter($table, $sql_filter);
    if (is_wp_error($valid)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $valid->get_error_message(),
        ], 400);
    }
    
    $result = $wpdb->update(
        $views_table,
        ['sql_filter' => $sql_filter],
        ['id' => $id],
        ['%s'],
        ['%d']
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to update filter: ' . $wpdb->last_error,
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Filter updated',
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

/**
 * Get available portals for a table
 */
function te_get_available_portals(WP_REST_Request $request) {
    global $wpdb;
    
    $table = $request->get_param('table');
    
    if (!$table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Table parameter required',
        ], 400);
    }
    
    // Get all views for this table
    $views = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM wp_zoho_table_editors WHERE target_table = %s",
            $table
        )
    );
    
    if (empty($views)) {
        return new WP_REST_Response([
            'success' => true,
            'portals' => [],
        ], 200);
    }
    
    $view_ids = array_map(function($v) { return $v->id; }, $views);
    $placeholders = implode(',', array_fill(0, count($view_ids), '%d'));
    
    // Get all enabled portals for these views
    $sql = "SELECT id, portal_name, display_order 
            FROM wp_zoho_editor_subselects 
            WHERE parent_view_id IN ($placeholders) 
            AND enabled = 1 
            ORDER BY display_order ASC";
    
    $portals = $wpdb->get_results(
        $wpdb->prepare($sql, ...$view_ids),
        ARRAY_A
    );
    
    return new WP_REST_Response([
        'success' => true,
        'portals' => $portals,
    ], 200);
}

/**
 * Get portal data - fetch records for a portal with WHERE clause substitution
 */
function te_get_portal_data(WP_REST_Request $request) {
    global $wpdb;
    
    $portal_id = intval($request->get_param('portal_id'));
    $parent_pk_value = $request->get_param('parent_pk_value');
    $parent_table = $request->get_param('parent_table');
    
    if (!$portal_id || !$parent_pk_value || !$parent_table) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Missing required parameters: portal_id, parent_pk_value, parent_table',
        ], 400);
    }
    
    // Get the portal configuration
    $portal = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM wp_zoho_editor_subselects WHERE id = %d AND enabled = 1",
            $portal_id
        ),
        ARRAY_A
    );
    
    if (!$portal) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Portal not found or disabled',
        ], 404);
    }
    
    // Get the reference view (to get target_table and column settings)
    $reference_view = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM wp_zoho_table_editors WHERE id = %d",
            $portal['reference_view_id']
        ),
        ARRAY_A
    );
    
    if (!$reference_view) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Reference view not found',
        ], 404);
    }
    
    $reference_table = $reference_view['target_table'];
    
    // Get parent record data for WHERE clause substitution
    $parent_pk = te_get_primary_key($parent_table);
    if (!$parent_pk) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Could not determine parent table primary key',
        ], 500);
    }
    
    $parent_record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM `{$parent_table}` WHERE `{$parent_pk}` = %s LIMIT 1",
            $parent_pk_value
        ),
        ARRAY_A
    );
    
    if (!$parent_record) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Parent record not found',
        ], 404);
    }
    
    // Substitute parent table references in WHERE clause
    // Supports two formats:
    // 1. {parent.column} placeholder syntax
    // 2. parent_table.column SQL syntax (auto-detected)
    $where_clause = $portal['where_clause'];
    
    foreach ($parent_record as $col => $val) {
        // Format 1: {parent.column} placeholders
        $placeholder = '{parent.' . $col . '}';
        $escaped_val = esc_sql($val);
        $where_clause = str_replace($placeholder, "'" . $escaped_val . "'", $where_clause);
        
        // Format 2: parent_table.column references (e.g., wp_zoho_product_eligibility.wp_id)
        $table_col_ref = $parent_table . '.' . $col;
        $where_clause = str_replace($table_col_ref, "'" . $escaped_val . "'", $where_clause);
    }
    
    // Validate no unresolved {parent.} placeholders remain
    if (preg_match('/\{parent\.[^}]+\}/', $where_clause)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WHERE clause contains unresolved placeholders',
        ], 400);
    }
    
    // Get pagination params (per_page from request overrides portal config)
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page_param = $request->get_param('per_page');
    $per_page = $per_page_param ? min(500, intval($per_page_param)) : (intval($portal['rows_per_page']) ?: 10);
    $offset = ($page - 1) * $per_page;
    
    // Get sort params from portal config
    $order_by = $portal['default_sort_column'] ?: te_get_primary_key($reference_table) ?: 'id';
    $order = strtoupper($portal['default_sort_direction']) === 'DESC' ? 'DESC' : 'ASC';
    
    // Also apply reference view's filter if it exists
    $view_filter = $reference_view['filter'] ?? '';
    $combined_filter = $where_clause;
    if ($view_filter) {
        $combined_filter = "({$where_clause}) AND ({$view_filter})";
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM `{$reference_table}` WHERE {$combined_filter}";
    $total = $wpdb->get_var($count_sql);
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error (count): ' . $wpdb->last_error,
            'sql' => $count_sql,
        ], 500);
    }
    
    // Get records
    $records_sql = "SELECT * FROM `{$reference_table}` WHERE {$combined_filter} ORDER BY `{$order_by}` {$order} LIMIT {$per_page} OFFSET {$offset}";
    $records = $wpdb->get_results($records_sql, ARRAY_A);
    
    if ($wpdb->last_error) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error (records): ' . $wpdb->last_error,
            'sql' => $records_sql,
        ], 500);
    }
    
    // Get column definitions for the reference table
    $columns_obj = te_get_column_definitions_for_table($reference_table);
    $primary_key = te_get_primary_key($reference_table);
    
    // Convert columns object to array format: [{name: 'col1', type: 'int', ...}, ...]
    $columns = [];
    foreach ($columns_obj as $col_name => $col_def) {
        $columns[] = array_merge(['name' => $col_name], $col_def);
    }
    
    // Get column prefs from reference view if available
    $column_prefs = null;
    if (!empty($reference_view['column_prefs'])) {
        $column_prefs = json_decode($reference_view['column_prefs'], true);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'portal_name' => $portal['portal_name'],
        'reference_table' => $reference_table,
        'records' => $records,
        'columns' => $columns,
        'column_prefs' => $column_prefs,
        'primary_key' => $primary_key,
        'total' => intval($total),
        'rows_per_page' => intval($portal['rows_per_page']) ?: 10,
    ], 200);
}

// Add shortcode for the widget
add_shortcode('product_eligibility_editor', function() {
    $widget_path = ABSPATH . 'wp-content/wp-custom-scripts/table-editor/table-editor-widget.html';
    
    if (!file_exists($widget_path)) {
        return '<p style="color:red;">Widget file not found: ' . esc_html($widget_path) . '</p>';
    }
    
    $contents = file_get_contents($widget_path);
    
    if ($contents === false) {
        return '<p style="color:red;">Unable to load widget file.</p>';
    }
    
    return $contents;
});

