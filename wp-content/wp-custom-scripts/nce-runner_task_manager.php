<?php
// LAST UPDATED: 2025-11-28 19:05:00
// v2.2.0 - 2025-11-28 (Added Task 7 & 8 for SMS backfill)
/**
 * NCE Runner Task Manager
 * 
 * Routes incoming requests to appropriate task handlers.
 * This file orchestrates which task gets executed based on the 'task' parameter.
 * 
 * @param array $params All parameters from REST request
 * @return array Result from task execution
 */
  
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main task manager function
 * 
 * @param array $params All request parameters (task number + any custom params)
 * @return array Task result or error
 */
function nce_run_task(array $params): array {
    // Extract task number
    $task_num = isset($params['task']) ? intval($params['task']) : 0;
    
    // Task registry - maps task number to task folder and entry function
    $task_registry = [
        1 => [
            'folder'   => 'nce-runner-task-1',
            'file'     => 'task_upload_klaviyo.php',
            'function' => 'nce_task_upload_klaviyo',
            'description' => 'Upload data to Klaviyo'
        ],
        2 => [
            'folder'   => 'nce-runner-task-2',
            'file'     => 'delete_all_data_sources.php',
            'function' => 'nce_task_delete_all_data_sources',
            'description' => 'Delete all Klaviyo data sources'
        ],
        3 => [
            'folder'   => 'nce-runner-task-3',
            'file'     => 'bulk_upsert_profiles.php',
            'function' => 'nce_task_upsert_klaviyo_profiles',
            'description' => 'Bulk upsert Klaviyo profiles from database'
        ],
        4 => [
            'folder'   => 'nce-runner-task-4',
            'file'     => 'grant_email_consent.php',
            'function' => 'nce_task_grant_email_consent',
            'description' => 'Grant email consent for NEW profiles (lookback window)'
        ],
        5 => [
            'folder'   => 'nce-runner-task-5',
            'file'     => 'grant_sms_consent.php',
            'function' => 'nce_task_grant_sms_consent',
            'description' => 'Grant SMS consent for NEW profiles (requires explicit opt-in)'
        ],
        6 => [
            'folder'   => 'nce-runner-task-6',
            'file'     => 'bulk_unsubscribe_emails.php',
            'function' => 'nce_task_bulk_unsubscribe_emails',
            'description' => 'Bulk unsubscribe emails from marketing (suppression list)'
        ],
        7 => [
            'folder'   => 'nce-runner-task-7',
            'file'     => 'fetch_and_cache_profiles.php',
            'function' => 'nce_task_fetch_and_cache_profiles',
            'description' => 'Fetch & cache ALL profiles with phone numbers to database'
        ],
        8 => [
            'folder'   => 'nce-runner-task-8',
            'file'     => 'process_cached_profiles.php',
            'function' => 'nce_task_process_cached_profiles',
            'description' => 'Process cached profiles in batches - grant SMS consent'
        ],
        9 => [
            'folder'   => 'nce-runner-task-9',
            'file'     => null,  // Reserved for future use
            'function' => null,
            'description' => 'Reserved'
        ],
        10 => [
            'folder'   => 'nce-runner-task-10',
            'file'     => null,  // Reserved for future use
            'function' => null,
            'description' => 'Reserved'
        ],
    ];
    
    // Validate task number
    if ($task_num < 1 || $task_num > 10) {
        return [
            'error' => 'Invalid task number. Must be between 1 and 10.',
            'task' => $task_num,
            'available_tasks' => array_filter(array_map(function($num, $config) {
                return $config['function'] !== null ? $num : null;
            }, array_keys($task_registry), $task_registry))
        ];
    }
    
    // Check if task exists
    if (!isset($task_registry[$task_num])) {
        return [
            'error' => "Task {$task_num} not found in registry.",
            'task' => $task_num
        ];
    }
    
    $task_config = $task_registry[$task_num];
    
    // Check if task is implemented
    if ($task_config['file'] === null || $task_config['function'] === null) {
        return [
            'error' => "Task {$task_num} is reserved but not yet implemented.",
            'task' => $task_num,
            'description' => $task_config['description']
        ];
    }
    
    // Build path to task file
    $base_path = ABSPATH . 'wp-content/wp-custom-scripts/';
    $task_file = $base_path . $task_config['folder'] . '/' . $task_config['file'];
    
    // Check if task file exists
    if (!file_exists($task_file)) {
        return [
            'error' => "Task file not found: {$task_file}",
            'task' => $task_num,
            'expected_path' => $task_file
        ];
    } 
    
    // Load task file
    require_once $task_file;
    
    // Check if function exists
    if (!function_exists($task_config['function'])) {
        return [
            'error' => "Task function '{$task_config['function']}' not found in file.",
            'task' => $task_num,
            'file' => $task_file
        ];
    }
    
    // Execute task function with all parameters
    try {
        $result = call_user_func($task_config['function'], $params);
        
        // Ensure result is an array
        if (!is_array($result)) {
            $result = ['result' => $result];
        }
        
        // Add task number to result
        $result['task'] = $task_num;
        
        return $result;
        
    } catch (Throwable $e) {
        return [
            'error' => 'Task execution failed: ' . $e->getMessage(),
            'task' => $task_num,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

/**
 * Load all files from includes/ folder (shared utilities)
 */
function nce_load_includes(): void {
    $includes_path = ABSPATH . 'wp-content/wp-custom-scripts/includes/';
    
    if (!is_dir($includes_path)) {
        return;
    }
    
    $files = glob($includes_path . '*.php');
    
    if ($files === false) {
        return;
    }
    
    foreach ($files as $file) {
        require_once $file;
    }
}

// Auto-load shared includes when this file is loaded
nce_load_includes();

