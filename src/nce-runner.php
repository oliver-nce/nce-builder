<?php

/**
 * Plugin Name: NCE Runner
 * Description: Universal REST endpoint dispatcher for custom tasks. Pass task number and any parameters - never needs editing.
 * Version: 4.0.0
 * Author: NCE
 */

if (!defined('ABSPATH')) { exit; }

// Suppress WordPress errors for our REST endpoint
if (!function_exists('nce_runner_clean_output')) {
    add_action('rest_api_init', 'nce_runner_clean_output', 1);
    function nce_runner_clean_output() {
        // Start output buffering for our endpoint
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/nce/v1/') !== false) {
            ob_start();
            
            // Suppress all errors
            @ini_set('display_errors', '0');
            error_reporting(0);
            
            // Hook to clean output right before sending response
            add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
                if (strpos($request->get_route(), '/nce/v1/') !== false) {
                    // Clean all output buffers
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }
                return $served;
            }, 999, 4);
        }
    }
}

if (!function_exists('nce_runner_register_routes')) {
    add_action('rest_api_init', 'nce_runner_register_routes');
    function nce_runner_register_routes() {
        register_rest_route('nce/v1', '/run', [
            'methods'             => ['GET','POST'],
            'callback'            => 'nce_runner_rest_run',
            'permission_callback' => '__return_true', // tighten if needed
            'args'                => [
                'task' => ['sanitize_callback' => 'absint'],
            ],
        ]);
    }
}

/**
 * REST endpoint handler - dispatches to task manager
 * This function should never need editing - all business logic goes in task files
 */
if (!function_exists('nce_runner_rest_run')) {
    function nce_runner_rest_run( WP_REST_Request $req ) {
        // Log request
        nce_debug_log('============================================================');
        nce_debug_log('REST ENDPOINT CALLED: /wp-json/nce/v1/run');
        nce_debug_log('Timestamp: ' . date('Y-m-d H:i:s'));
        nce_debug_log('Remote IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Clean any previous output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Collect ALL parameters (from GET, POST, and JSON body)
        $params = [];
        
        // Get query parameters (e.g., ?task=1&batch_size=100)
        $params = array_merge($params, $req->get_query_params());
        
        // Get POST parameters
        $params = array_merge($params, $req->get_body_params());
        
        // Get JSON body parameters
        $json_params = $req->get_json_params();
        if (is_array($json_params)) {
            $params = array_merge($params, $json_params);
        }
        
        // Log parameters
        nce_debug_log('Parameters: ' . json_encode($params));
        
        // Load task manager
        $task_manager = ABSPATH . 'wp-custom-scripts/nce-runner_task_manager.php';
        if (!file_exists($task_manager)) {
            nce_debug_log('ERROR: Task manager not found');
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['error' => 'Task manager not found', 'path' => $task_manager]);
            exit;
        }
        
        require_once $task_manager;
        
        // Execute task via task manager
        try {
            nce_debug_log('Calling task manager...');
            $start_time = microtime(true);
            
            $result = nce_run_task($params);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            // Add metadata
            $result['duration_seconds'] = $duration;
            $result['timestamp'] = date('Y-m-d H:i:s');
            
            nce_debug_log('Task completed in ' . $duration . ' seconds');
            nce_debug_log('Result: ' . json_encode($result));
            nce_debug_log('============================================================');
            
            // Return result
            header('Content-Type: application/json; charset=utf-8');
            $http_code = isset($result['error']) ? 400 : 200;
            http_response_code($http_code);
            echo json_encode($result);
            exit;
            
        } catch (Throwable $e) {
            nce_debug_log('EXCEPTION: ' . $e->getMessage());
            nce_debug_log('============================================================');
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'error' => 'Task execution failed: ' . $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }
}

/**
 * Custom debug logger - writes ONLY to wp-content/nce-runner-debug.log
 */
if (!function_exists('nce_debug_log')) {
    function nce_debug_log(string $message) {
        $log_file = WP_CONTENT_DIR . '/nce-runner-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}\n";
        
        // Write to custom log file only (no error_log to avoid pollution)
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}