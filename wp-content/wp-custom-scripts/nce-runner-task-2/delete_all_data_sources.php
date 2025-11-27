<?php
declare(strict_types=1);

/**
 * Delete All Klaviyo Data Sources - Task 2
 * 
 * Retrieves all data sources from Klaviyo and deletes them one by one.
 * Useful for cleanup/testing or resetting the data source environment.
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): Used to look up API credentials from wp_klaviyo_globals
 *                                            If not provided, defaults to 'default'
 *                      - confirm (required): Must be set to 'yes' to actually delete (safety check)
 * @return array Summary with deletion results
 */
if (!function_exists('nce_task_delete_all_data_sources')) {
    function nce_task_delete_all_data_sources(array $params = []): array {
        // Allow up to 10 minutes of runtime
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);
        
        // Extract parameters
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $confirm = isset($params['confirm']) ? trim((string)$params['confirm']) : '';
        
        error_log("nce_task_delete_all_data_sources: Starting deletion task (Job: {$jobName})");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] DELETE ALL DATA SOURCES - Job: {$jobName}\n", FILE_APPEND);
        
        global $wpdb;
        
        // --- 1. Safety Check ---
        if ($confirm !== 'yes') {
            $message = "Safety check failed: Must pass confirm=yes to delete all data sources";
            error_log("nce_task_delete_all_data_sources: {$message}");
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: {$message}\n", FILE_APPEND);
            return [
                'error' => $message,
                'usage' => 'Add confirm=yes to the request to proceed with deletion',
                'job_name' => $jobName
            ];
        }
        
        // --- 2. Get API credentials from database ---
        $table = $wpdb->prefix . 'klaviyo_globals';
        $g = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_name = %s LIMIT 1",
            $jobName
        ), ARRAY_A);
        
        if (!$g) {
            return [
                'error' => "No configuration found in wp_klaviyo_globals for job_name: {$jobName}",
                'job_name' => $jobName
            ];
        }
        
        $apiKey = trim((string)($g['api_key'] ?? ''));
        $apiVersion = trim((string)($g['api_version'] ?? '2025-10-15'));
        
        if ($apiKey === '') {
            return [
                'error' => 'Missing api_key in configuration',
                'job_name' => $jobName
            ];
        }
        
        if ($apiVersion === '') {
            return [
                'error' => 'Missing api_version in configuration',
                'job_name' => $jobName
            ];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] API credentials loaded\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Confirm=yes received - proceeding with deletion\n", FILE_APPEND);
        
        // --- 3. Get all data sources ---
        $listUrl = 'https://a.klaviyo.com/api/data-sources';
        $allDataSources = [];
        $pageUrl = $listUrl;
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching all data sources...\n", FILE_APPEND);
        error_log("nce_task_delete_all_data_sources: Fetching data sources from Klaviyo");
        
        // Paginate through all data sources
        while ($pageUrl) {
            $response = nce_klaviyo_api_request('GET', $pageUrl, $apiKey, $apiVersion);
            
            if ($response['http'] < 200 || $response['http'] >= 300) {
                $errorMsg = $response['error'] ?? 'Failed to fetch data sources';
                error_log("nce_task_delete_all_data_sources: API error - " . $errorMsg);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: Failed to fetch data sources - HTTP {$response['http']}\n", FILE_APPEND);
                
                return [
                    'error' => 'Failed to fetch data sources from Klaviyo',
                    'http_status' => $response['http'],
                    'details' => $errorMsg,
                    'job_name' => $jobName
                ];
            }
            
            $body = $response['body'];
            
            // Add data sources to our collection
            if (!empty($body['data'])) {
                $allDataSources = array_merge($allDataSources, $body['data']);
            }
            
            // Check for next page
            $pageUrl = $body['links']['next'] ?? null;
            
            // Respect rate limits between pages
            if ($pageUrl) {
                sleep(1);
            }
        }
        
        $totalCount = count($allDataSources);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Found {$totalCount} data sources\n", FILE_APPEND);
        error_log("nce_task_delete_all_data_sources: Found {$totalCount} data sources to delete");
        
        if ($totalCount === 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] No data sources to delete\n", FILE_APPEND);
            return [
                'success' => true,
                'message' => 'No data sources found to delete',
                'deleted' => 0,
                'job_name' => $jobName
            ];
        }
        
        // --- 4. Delete each data source ---
        $deleted = 0;
        $failed = 0;
        $errors = [];
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Starting deletion of {$totalCount} data sources...\n", FILE_APPEND);
        
        foreach ($allDataSources as $index => $dataSource) {
            $dsId = $dataSource['id'] ?? null;
            $dsTitle = $dataSource['attributes']['title'] ?? 'Unknown';
            
            if (!$dsId) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Skipping data source with no ID\n", FILE_APPEND);
                continue;
            }
            
            $deleteUrl = "https://a.klaviyo.com/api/data-sources/{$dsId}";
            $currentNum = $index + 1;
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Deleting [{$currentNum}/{$totalCount}]: {$dsTitle} (ID: {$dsId})\n", FILE_APPEND);
            error_log("nce_task_delete_all_data_sources: Deleting data source [{$currentNum}/{$totalCount}]: {$dsTitle}");
            
            $response = nce_klaviyo_api_request('DELETE', $deleteUrl, $apiKey, $apiVersion);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $deleted++;
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Deleted successfully\n", FILE_APPEND);
            } else {
                $failed++;
                $errorMsg = $response['error'] ?? 'Unknown error';
                $errors[] = [
                    'id' => $dsId,
                    'title' => $dsTitle,
                    'http_status' => $response['http'],
                    'error' => $errorMsg
                ];
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ Failed to delete - HTTP {$response['http']}: {$errorMsg}\n", FILE_APPEND);
                error_log("nce_task_delete_all_data_sources: Failed to delete {$dsTitle} - HTTP {$response['http']}");
            }
            
            // Rate limiting: Wait between deletions to respect API limits
            if ($index < $totalCount - 1) {
                sleep(1); // 1 second between deletions
            }
        }
        
        // --- 5. Summary ---
        $completionMsg = "[" . date('H:i:s') . "] --- DELETION COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total found: {$totalCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Successfully deleted: {$deleted}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed: {$failed}\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_delete_all_data_sources: Complete - Deleted: {$deleted}, Failed: {$failed}");
        
        $result = [
            'success' => true,
            'message' => 'Data source deletion completed',
            'job_name' => $jobName,
            'total_found' => $totalCount,
            'deleted' => $deleted,
            'failed' => $failed
        ];
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
}

/**
 * Helper function to make Klaviyo API requests
 * 
 * @param string $method HTTP method (GET, POST, DELETE, etc.)
 * @param string $url Full URL to request
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision version
 * @param array $payload Optional request body (for POST/PATCH)
 * @return array Response with http code, body, and error info
 */
if (!function_exists('nce_klaviyo_api_request')) {
    function nce_klaviyo_api_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => "Klaviyo-API-Key {$apiKey}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'revision'      => $apiVersion,
            ],
            'timeout' => 30,
        ];
        
        if (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }
        
        $res  = wp_remote_request($url, $args);
        $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $body = is_wp_error($res) ? ['error' => $res->get_error_message()] : json_decode(wp_remote_retrieve_body($res), true);
        
        return [
            'http'  => $http,
            'body'  => $body,
            'error' => is_wp_error($res) ? $res->get_error_message() : null,
        ];
    }
}

