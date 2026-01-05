<?php
/**
 * Klaviyo Direct Sync - Cron Handler
 * 
 * Self-contained cron handler with task configuration.
 * Each task runs in its own PHP execution (60s timeout each).
 * Includes lock mechanism to prevent overlapping runs.
 * 
 * @package Klaviyo
 * @version 4.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// GLOBAL KLAVIYO API CREDENTIALS
// ============================================================================
// Retrieved once and available to all tasks via global variables
// ============================================================================

global $NCE_KLAVIYO_API_KEY, $NCE_KLAVIYO_API_VERSION;

// Get API key from tasks-config.json
$config_file = __DIR__ . '/tasks-config.json';
$config = null;
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
}
$NCE_KLAVIYO_API_KEY = !empty($config['api_key']) ? trim($config['api_key']) : '';
$NCE_KLAVIYO_API_VERSION = !empty($config['api_version']) ? $config['api_version'] : '2025-10-15';

// Log if API key is missing
if (empty($NCE_KLAVIYO_API_KEY)) {
    error_log('[Klaviyo Cron] WARNING: No API key found in tasks-config.json');
}

// ============================================================================
// TASK CONFIGURATION - Loaded from tasks-config.json
// ============================================================================
// Edit tasks-config.json to enable/disable tasks and change order.
// Tasks execute in array order. Move rows up/down to change execution order.
// 
// JSON schema:
//   order        - Execution order (tasks sorted by this before running)
//   id           - Task identifier (string or number)
//   name         - Display name for logging
//   enabled      - true/false to run or skip
//   pause        - Seconds to wait after this task before starting the next
//   stop_on_fail - If true, cancel remaining tasks if this one fails
//   type         - 'sql' (stored procedure) or 'task' (PHP function)
//   procedure    - For type='sql': stored procedure name
//   file         - For type='task': PHP file path (relative to wp-custom-scripts)
//   function     - For type='task': function name to call
//   params       - For type='task': optional parameters to pass to function
// ============================================================================

$config_file = __DIR__ . '/tasks-config.json';

if (!file_exists($config_file)) {
    error_log('[Klaviyo Cron] ERROR: tasks-config.json not found at ' . $config_file);
    $GLOBALS['KLAVIYO_SYNC_TASKS'] = [];
} else {
    $config_json = file_get_contents($config_file);
    $config = json_decode($config_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Klaviyo Cron] ERROR: Invalid JSON in tasks-config.json: ' . json_last_error_msg());
        $GLOBALS['KLAVIYO_SYNC_TASKS'] = [];
    } else {
        $tasks = $config['tasks'] ?? [];
        // Sort tasks by 'order' field
        usort($tasks, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        $GLOBALS['KLAVIYO_SYNC_TASKS'] = $tasks;
    }
}

// ============================================================================
// LOCK MECHANISM - Prevents overlapping sync runs
// ============================================================================

define('KLAVIYO_SYNC_LOCK_KEY', 'klaviyo_sync_lock');
define('KLAVIYO_SYNC_LOCK_TIMEOUT', 1800);  // 30 minutes max lock time

/**
 * Acquire sync lock
 * @return bool True if lock acquired, false if sync already running
 */
function klaviyo_acquire_sync_lock() {
    $existing_lock = get_transient(KLAVIYO_SYNC_LOCK_KEY);
    
    if ($existing_lock !== false) {
        return false;  // Lock exists - sync already running
    }
    
    // Set lock with current timestamp
    set_transient(KLAVIYO_SYNC_LOCK_KEY, time(), KLAVIYO_SYNC_LOCK_TIMEOUT);
    return true;
}

/**
 * Release sync lock
 */
function klaviyo_release_sync_lock() {
    delete_transient(KLAVIYO_SYNC_LOCK_KEY);
}

/**
 * Get lock info if sync is running
 * @return array|false Lock info or false if not locked
 */
function klaviyo_get_lock_info() {
    $lock_time = get_transient(KLAVIYO_SYNC_LOCK_KEY);
    
    if ($lock_time === false) {
        return false;
    }
    
    return [
        'started_at' => date('Y-m-d H:i:s', $lock_time),
        'running_for' => time() - $lock_time,
    ];
}

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Get or create the current sync log file path
 * Uses a transient to share the log file across chained executions
 */
function klaviyo_get_sync_log_file($create_new = false) {
    $transient_key = 'klaviyo_sync_log_file';
    
    if ($create_new) {
        $log_file = ABSPATH . 'wp-content/wp-custom-scripts/logs/cron_sync_' . date('Y-m-d_H-i-s') . '.log';
        set_transient($transient_key, $log_file, 3600);  // 1 hour expiry
        return $log_file;
    }
    
    $log_file = get_transient($transient_key);
    if (!$log_file) {
        // No active sync, create new log
        $log_file = ABSPATH . 'wp-content/wp-custom-scripts/logs/cron_sync_' . date('Y-m-d_H-i-s') . '.log';
        set_transient($transient_key, $log_file, 3600);
    }
    
    return $log_file;
}

/**
 * Write to cron sync log file
 */
function klaviyo_cron_log($message) {
    $log_file = klaviyo_get_sync_log_file();
    $timestamp = date('H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    error_log('[Klaviyo Cron] ' . $message);
}

// ============================================================================
// TASK EXECUTION
// ============================================================================

/**
 * Execute a single task
 * 
 * @param array $task Task configuration
 * @return array Result with success, message, duration
 */
function klaviyo_execute_task($task) {
    global $wpdb;
    
    $start_time = microtime(true);
    $task_label = "Task {$task['id']} ({$task['name']})";
    
    // Check if disabled
    if (!$task['enabled']) {
        klaviyo_cron_log("{$task_label} - SKIPPED (disabled)");
        return ['success' => true, 'skipped' => true, 'duration' => 0];
    }
    
    klaviyo_cron_log("{$task_label} - RUNNING...");
    
    try {
        if ($task['type'] === 'sql') {
            // Execute stored procedure
            $result = $wpdb->query("CALL {$task['procedure']}()");
            
            if ($result === false) {
                throw new Exception($wpdb->last_error ?: 'Unknown database error');
            }
            
            $duration = round(microtime(true) - $start_time, 2);
            klaviyo_cron_log("{$task_label} - DONE ({$duration}s)");
            
            return ['success' => true, 'duration' => $duration];
            
        } elseif ($task['type'] === 'task') {
            // Load and execute PHP task
            $task_file = ABSPATH . 'wp-content/wp-custom-scripts/' . $task['file'];
            
            if (!file_exists($task_file)) {
                throw new Exception("File not found: {$task['file']}");
            }
            
            require_once $task_file;
            
            if (!function_exists($task['function'])) {
                throw new Exception("Function not found: {$task['function']}");
            }
            
            $params = $task['params'] ?? [];
            $result = call_user_func($task['function'], $params);
            
            $duration = round(microtime(true) - $start_time, 2);
            
            // Build summary from result
            $summary = '';
            if (isset($result['uploaded'])) {
                $summary = " - {$result['uploaded']} records";
            } elseif (isset($result['profiles_processed'])) {
                $summary = " - {$result['profiles_processed']} profiles";
            } elseif (isset($result['message'])) {
                $summary = " - " . substr($result['message'], 0, 50);
            }
            
            if ($result['success'] ?? false) {
                klaviyo_cron_log("{$task_label} - DONE ({$duration}s){$summary}");
            } else {
                $error_msg = $result['error'] ?? $result['message'] ?? 'Unknown error';
                klaviyo_cron_log("{$task_label} - FAILED ({$duration}s) - {$error_msg}");
            }
            
            $result['duration'] = $duration;
            return $result;
        }
        
    } catch (Exception $e) {
        $duration = round(microtime(true) - $start_time, 2);
        klaviyo_cron_log("{$task_label} - ERROR ({$duration}s) - {$e->getMessage()}");
        
        return ['success' => false, 'message' => $e->getMessage(), 'duration' => $duration];
    }
    
    return ['success' => false, 'message' => 'Unknown task type', 'duration' => 0];
}

/**
 * Run a task by its index in the array, then schedule the next task
 * 
 * @param int $task_index Index of the task to run (0-based)
 */
function klaviyo_run_task_at_index($task_index) {
    $tasks = $GLOBALS['KLAVIYO_SYNC_TASKS'];
    $total_tasks = count($tasks);
    
    // Validate index
    if ($task_index < 0 || $task_index >= $total_tasks) {
        klaviyo_cron_log("=== SYNC COMPLETE (all tasks processed) ===");
        klaviyo_release_sync_lock();  // Release lock
        delete_transient('klaviyo_sync_log_file');  // Clean up
        return;
    }
    
    $task = $tasks[$task_index];
    
    // Execute the task
    $result = klaviyo_execute_task($task);
    
    // Check if task failed and stop_on_fail is true
    $task_failed = !($result['success'] ?? false) && !($result['skipped'] ?? false);
    $stop_on_fail = $task['stop_on_fail'] ?? false;
    
    if ($task_failed && $stop_on_fail) {
        klaviyo_cron_log("=== CHAIN CANCELLED - Task {$task['id']} failed with stop_on_fail=true ===");
        klaviyo_release_sync_lock();
        delete_transient('klaviyo_sync_log_file');
        return $result;
    }
    
    // Get pause duration for this task (default 5 seconds)
    $pause = isset($task['pause']) ? (int)$task['pause'] : 5;
    
    // Schedule the next task (even if this one was skipped/disabled)
    $next_index = $task_index + 1;
    
    if ($next_index < $total_tasks) {
        $next_task = $tasks[$next_index];
        $hook_name = 'klaviyo_sync_task_' . $next_index;
        
        if (!wp_next_scheduled($hook_name)) {
            wp_schedule_single_event(time() + $pause, $hook_name);
            klaviyo_cron_log("Scheduled next: Task {$next_task['id']} ({$next_task['name']}) in {$pause}s");
        }
    } else {
        klaviyo_cron_log("=== SYNC COMPLETE (all tasks processed) ===");
        klaviyo_release_sync_lock();  // Release lock
        delete_transient('klaviyo_sync_log_file');  // Clean up
    }
    
    return $result;
}

// ============================================================================
// REGISTER HOOKS FOR EACH TASK POSITION
// ============================================================================

// Create hook handlers for each possible task position
for ($i = 0; $i < 20; $i++) {  // Support up to 20 tasks
    $hook_name = 'klaviyo_sync_task_' . $i;
    $task_index = $i;  // Capture in closure
    
    add_action($hook_name, function() use ($task_index) {
        klaviyo_run_task_at_index($task_index);
    });
}

// ============================================================================
// CRON FUNCTIONS - Use these in WP Crontrol
// ============================================================================

/**
 * CHAINED SYNC: Start the task chain from the first task
 * 
 * Each task runs in its own PHP execution (60s timeout each).
 * Tasks are scheduled with configurable pause between them.
 * Will skip if a sync is already in progress.
 * 
 * Use this function in WP Crontrol for scheduled syncs.
 */
function klaviyo_chained_sync_cron() {
    // Check for existing lock
    $lock_info = klaviyo_get_lock_info();
    
    if ($lock_info !== false) {
        // Sync already running - skip this run
        $running_for = $lock_info['running_for'];
        $started = $lock_info['started_at'];
        
        error_log("[Klaviyo Cron] SKIPPED - Sync already in progress (started: {$started}, running: {$running_for}s)");
        
        // Still log to file for visibility
        klaviyo_cron_log("=== SYNC SKIPPED ===");
        klaviyo_cron_log("Another sync is already in progress");
        klaviyo_cron_log("Started at: {$started}");
        klaviyo_cron_log("Running for: {$running_for} seconds");
        
        return [
            'success' => false,
            'message' => 'Sync already in progress',
            'lock_info' => $lock_info
        ];
    }
    
    // Acquire lock
    if (!klaviyo_acquire_sync_lock()) {
        error_log("[Klaviyo Cron] SKIPPED - Could not acquire lock");
        return ['success' => false, 'message' => 'Could not acquire lock'];
    }
    
    // Create new log file for this sync session
    klaviyo_get_sync_log_file(true);
    
    klaviyo_cron_log('=== KLAVIYO CHAINED SYNC STARTED ===');
    klaviyo_cron_log('Each task runs in its own PHP execution');
    klaviyo_cron_log('Lock acquired - no overlapping runs allowed');
    
    // Run the first task (index 0)
    klaviyo_run_task_at_index(0);
    
    return [
        'success' => true,
        'message' => 'Sync chain started - tasks will run sequentially'
    ];
}

/**
 * RUN SINGLE TASK: Run a specific task by ID (for testing)
 * Does NOT acquire lock - use for individual task testing only
 * 
 * @param mixed $task_id The task ID (e.g., 3, '1b', 'zoho')
 */
function klaviyo_run_single_task($task_id) {
    $tasks = $GLOBALS['KLAVIYO_SYNC_TASKS'];
    
    foreach ($tasks as $task) {
        if ($task['id'] == $task_id) {
            klaviyo_get_sync_log_file(true);
            klaviyo_cron_log("=== RUNNING SINGLE TASK: {$task['id']} ({$task['name']}) ===");
            return klaviyo_execute_task($task);
        }
    }
    
    return ['success' => false, 'message' => "Task not found: {$task_id}"];
}

/**
 * FORCE RELEASE LOCK: Use if sync gets stuck
 * Call this to manually clear the lock
 */
function klaviyo_force_release_lock() {
    klaviyo_release_sync_lock();
    error_log("[Klaviyo Cron] Lock force-released");
    return ['success' => true, 'message' => 'Lock released'];
}

/**
 * RUN ALL TASKS - Single execution (may timeout if tasks take too long)
 * Use klaviyo_chained_sync_cron() instead for production
 */
function klaviyo_full_sync_cron() {
    // Check for existing lock
    $lock_info = klaviyo_get_lock_info();
    if ($lock_info !== false) {
        return ['success' => false, 'message' => 'Sync already in progress', 'lock_info' => $lock_info];
    }
    
    if (!klaviyo_acquire_sync_lock()) {
        return ['success' => false, 'message' => 'Could not acquire lock'];
    }
    
    klaviyo_get_sync_log_file(true);
    klaviyo_cron_log('=== KLAVIYO FULL SYNC STARTED (single execution) ===');
    
    $tasks = $GLOBALS['KLAVIYO_SYNC_TASKS'];
    $results = [];
    $total_start = microtime(true);
    
    foreach ($tasks as $task) {
        $results[$task['id']] = klaviyo_execute_task($task);
    }
    
    $total_duration = round(microtime(true) - $total_start, 2);
    klaviyo_cron_log("=== FULL SYNC COMPLETE ({$total_duration}s) ===");
    
    klaviyo_release_sync_lock();
    
    return [
        'success' => true,
        'results' => $results,
        'total_duration' => $total_duration
    ];
}

// ============================================================================
// REST API ENDPOINTS FOR TASK CONFIG
// ============================================================================

/**
 * Get tasks config as JSON
 */
function klaviyo_get_tasks_config() {
    $config_file = ABSPATH . 'wp-content/wp-custom-scripts/tasks-config.json';
    
    if (!file_exists($config_file)) {
        return ['success' => false, 'error' => 'Config file not found'];
    }
    
    $json = file_get_contents($config_file);
    $config = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
    }
    
    return ['success' => true, 'config' => $config];
}

/**
 * Save tasks config from JSON
 */
function klaviyo_save_tasks_config($data) {
    $config_file = ABSPATH . 'wp-content/wp-custom-scripts/tasks-config.json';
    
    // Handle both old format (tasks array) and new format (full config object)
    if (isset($data['tasks']) && is_array($data['tasks'])) {
        // New format: full config with api_key, api_version, tasks
        $config = [
            'api_key' => $data['api_key'] ?? '',
            'api_version' => $data['api_version'] ?? '2025-10-15',
            'tasks' => $data['tasks']
        ];
    } elseif (is_array($data)) {
        // Old format: just tasks array (backwards compatible)
        $config = ['tasks' => $data];
    } else {
        return ['success' => false, 'error' => 'Invalid data format'];
    }
    
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        return ['success' => false, 'error' => 'Failed to encode JSON'];
    }
    
    $result = file_put_contents($config_file, $json);
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Failed to write config file'];
    }
    
    return ['success' => true, 'message' => 'Config saved', 'bytes' => $result];
}

// Register REST API endpoints
add_action('rest_api_init', function() {
    // GET /wp-json/nce/v1/tasks-config
    register_rest_route('nce/v1', '/tasks-config', [
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response(klaviyo_get_tasks_config(), 200);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    // POST /wp-json/nce/v1/tasks-config
    register_rest_route('nce/v1', '/tasks-config', [
        'methods' => 'POST',
        'callback' => function($request) {
            $tasks = $request->get_json_params();
            return new WP_REST_Response(klaviyo_save_tasks_config($tasks), 200);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

