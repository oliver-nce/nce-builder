<?php // v1.0.0 - 2025-11-28
declare(strict_types=1);

/**
 * Bulk Unsubscribe Email Profiles - Task 7
 * ---
 * Reads a list of email addresses and unsubscribes them from email marketing.
 * This removes marketing consent but does NOT suppress (hard block) the emails.
 * Transactional emails can still be sent after unsubscribe.
 * 
 * - Reads email list from database table or comma-separated parameter
 * - Batches up to 100 emails per request
 * - Uses profile-subscription-bulk-delete-jobs endpoint
 * - Only affects email marketing consent (not SMS)
 * 
 * Note: Unsubscribe ≠ Suppress
 * - Unsubscribe: Removes marketing consent, transactional emails still work
 * - Suppress: Hard block on ALL marketing emails (use only when required)
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'suppression'
 *                      - emails (optional): comma-separated email list
 *                      - source_table (optional): table name to read emails from
 *                      - source_column (optional): column name containing emails
 * @return array Summary with unsubscribe results   
 */
if (!function_exists('nce_task_bulk_unsubscribe_emails')) {
    function nce_task_bulk_unsubscribe_emails(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'suppression';
        
        error_log("nce_task_bulk_unsubscribe_emails: Starting (Job: {$jobName})");
        
        // Initialize temp log file
        $logs_dir = ABSPATH . 'wp-content/wp-custom-scripts/logs/';
        if (!is_dir($logs_dir)) { @mkdir($logs_dir, 0755, true); }
        $temp_log = $logs_dir . 'task7_unsubscribe_' . date('Y-m-d_H-i-s') . '.log';
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] BULK UNSUBSCRIBE EMAILS - Job: {$jobName}\n");
        
        global $wpdb;
        $startTime = microtime(true);
        
        // --- 1. Get configuration ---
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
            return ['error' => 'Missing api_key in configuration', 'job_name' => $jobName];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n", FILE_APPEND);
        
        // --- 2. Get email list from various sources ---
        $emailList = [];
        
        // Option A: Email list provided directly as parameter
        if (!empty($params['emails'])) {
            $emailsParam = trim((string)$params['emails']);
            $emailList = array_map('trim', explode(',', $emailsParam));
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Reading emails from 'emails' parameter\n", FILE_APPEND);
        }
        // Option B: Read from custom table
        elseif (!empty($params['source_table']) && !empty($params['source_column'])) {
            $sourceTable = trim((string)$params['source_table']);
            $sourceColumn = trim((string)$params['source_column']);
            
            // Security: Validate table and column names (no special chars except underscore)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $sourceTable) || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceColumn)) {
                return [
                    'error' => 'Invalid table or column name. Only alphanumeric and underscore allowed.',
                    'job_name' => $jobName
                ];
            }
            
            $fullTable = $wpdb->prefix . $sourceTable;
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Reading emails from table: {$fullTable}, column: {$sourceColumn}\n", FILE_APPEND);
            
            // Check if table exists
            $tableExists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $fullTable
            ));
            
            if (!$tableExists) {
                return [
                    'error' => "Table {$fullTable} does not exist",
                    'job_name' => $jobName
                ];
            }
            
            // Fetch emails
            $query = "SELECT DISTINCT {$sourceColumn} FROM {$fullTable} WHERE {$sourceColumn} IS NOT NULL AND {$sourceColumn} != ''";
            $results = $wpdb->get_col($query);
            
            if ($wpdb->last_error) {
                return [
                    'error' => "Failed to read emails: " . $wpdb->last_error,
                    'job_name' => $jobName,
                    'query' => $query
                ];
            }
            
            $emailList = $results;
        }
        // Option C: Read from query in globals
        elseif (!empty($g['query'])) {
            $queryString = trim((string)$g['query']);
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Reading emails from configured query\n", FILE_APPEND);
            
            $results = $wpdb->get_results($queryString, ARRAY_A);
            
            if ($wpdb->last_error) {
                return [
                    'error' => "Failed to execute query: " . $wpdb->last_error,
                    'job_name' => $jobName,
                    'query' => $queryString
                ];
            }
            
            // Extract email column from results
            $emailList = [];
            foreach ($results as $row) {
                $email = $row['email'] ?? $row['Email'] ?? $row['EMAIL'] ?? null;
                if ($email) {
                    $emailList[] = $email;
                }
            }
        }
        else {
            return [
                'error' => 'No email source specified. Provide: emails parameter, source_table/source_column, or query in globals.',
                'job_name' => $jobName
            ];
        }
        
        $totalEmails = count($emailList);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Found {$totalEmails} email addresses to unsubscribe\n", FILE_APPEND);
        error_log("nce_task_bulk_unsubscribe_emails: Found {$totalEmails} emails");
        
        if ($totalEmails === 0) {
            return [
                'success' => true,
                'message' => 'No emails found to unsubscribe',
                'job_name' => $jobName,
                'unsubscribed' => 0
            ];
        }
        
        // --- 3. Validate and clean email addresses ---
        $validEmails = [];
        $invalidCount = 0;
        
        foreach ($emailList as $email) {
            $email = strtolower(trim($email));
            
            // Basic validation
            if (strlen($email) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidCount++;
                continue;
            }
            
            $validEmails[] = $email;
        }
        
        $validCount = count($validEmails);
        
        if ($invalidCount > 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Skipped {$invalidCount} invalid email addresses\n", FILE_APPEND);
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] {$validCount} valid emails to process\n", FILE_APPEND);
        
        if ($validCount === 0) {
            return [
                'success' => true,
                'message' => 'No valid emails to unsubscribe',
                'job_name' => $jobName,
                'total_found' => $totalEmails,
                'invalid' => $invalidCount,
                'unsubscribed' => 0
            ];
        }
        
        // --- 4. Batch and send to Klaviyo ---
        $batchSize = 100; // Klaviyo recommendation for subscription endpoints
        $batches = array_chunk($validEmails, $batchSize);
        $totalBatches = count($batches);
        $unsubscribedCount = 0;
        $failedBatches = 0;
        $errors = [];
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize} emails each\n", FILE_APPEND);
        
        foreach ($batches as $batchIndex => $batchEmails) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchEmails);
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} emails)...\n", FILE_APPEND);
            error_log("nce_task_bulk_unsubscribe_emails: Processing batch {$batchNum}/{$totalBatches}");
            
            // Build profiles array for Klaviyo unsubscribe endpoint (per RAG Chunk D)
            $profilesData = [];
            foreach ($batchEmails as $email) {
                $profilesData[] = [
                    'type' => 'profile',
                    'attributes' => [
                        'email' => $email
                    ]
                ];
            }
            
            // Log sample from first batch
            if ($batchNum === 1 && !empty($profilesData)) {
                $sampleEmails = array_slice($batchEmails, 0, 5);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample emails: " . implode(', ', $sampleEmails) . "\n", FILE_APPEND);
            }
            
            // Build unsubscribe job payload (per RAG Chunk D)
            $payload = [
                'data' => [
                    'type' => 'profile-subscription-bulk-delete-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => $profilesData
                        ]
                    ]
                ]
            ];
            
            // Send to Klaviyo
            $url = 'https://a.klaviyo.com/api/profile-subscription-bulk-delete-jobs';
            $response = nce_klaviyo_unsubscribe_request('POST', $url, $apiKey, $apiVersion, $payload);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $jobId = $response['body']['data']['id'] ?? 'unknown';
                $unsubscribedCount += count($profilesData);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n", FILE_APPEND);
                error_log("nce_task_bulk_unsubscribe_emails: Batch {$batchNum} submitted (Job ID: {$jobId})");
            } else {
                $failedBatches++;
                $errorMsg = $response['error'] ?? 'Unknown error';
                
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}\n", FILE_APPEND);
                
                if (!empty($response['body'])) {
                    $fullError = json_encode($response['body'], JSON_PRETTY_PRINT);
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] Klaviyo error body:\n{$fullError}\n", FILE_APPEND);
                }
                
                $errorDetail = [
                    'batch' => $batchNum,
                    'http_status' => $response['http'],
                    'error' => $errorMsg,
                    'email_count' => count($profilesData)
                ];
                $errors[] = $errorDetail;
                error_log("nce_task_bulk_unsubscribe_emails: Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}");
            }
            
            // Rate limiting: 10/s burst, 150/min steady
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds between batches
            }
        }
        
        // --- 5. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "[" . date('H:i:s') . "] --- BULK UNSUBSCRIBE COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total emails found: {$totalEmails}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Invalid emails: {$invalidCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Valid emails: {$validCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches processed: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Emails unsubscribed: {$unsubscribedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_bulk_unsubscribe_emails: Complete - Unsubscribed: {$unsubscribedCount}, Failed: {$failedBatches}");
        
        $result = [
            'success' => true,
            'message' => 'Bulk unsubscribe completed',
            'job_name' => $jobName,
            'total_found' => $totalEmails,
            'invalid_emails' => $invalidCount,
            'valid_emails' => $validCount,
            'unsubscribed' => $unsubscribedCount,
            'batches_processed' => $totalBatches,
            'failed_batches' => $failedBatches,
            'duration_seconds' => $duration
        ];
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
}

/**
 * Make Klaviyo unsubscribe API request
 * 
 * @param string $method HTTP method
 * @param string $url Full URL
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param array $payload Request body
 * @return array Response with http code, body, error
 */
if (!function_exists('nce_klaviyo_unsubscribe_request')) {
    function nce_klaviyo_unsubscribe_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => "Klaviyo-API-Key {$apiKey}",
                'Accept'        => 'application/vnd.api+json',
                'Content-Type'  => 'application/vnd.api+json',
                'revision'      => $apiVersion,
            ],
            'timeout' => 60,
        ];
        
        if (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }
        
        $res  = wp_remote_request($url, $args);
        $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
        $body = is_wp_error($res) ? ['error' => $res->get_error_message()] : json_decode($rawBody, true);
        
        $errorMsg = null;
        if (is_wp_error($res)) {
            $errorMsg = $res->get_error_message();
        } elseif (!empty($body['errors'])) {
            $firstError = $body['errors'][0];
            $errorMsg = $firstError['detail'] ?? 'Unknown error';
            
            if (!empty($firstError['source'])) {
                $errorMsg .= ' (Source: ' . json_encode($firstError['source']) . ')';
            }
            
            if (!empty($firstError['meta'])) {
                $errorMsg .= ' (Meta: ' . json_encode($firstError['meta']) . ')';
            }
        }
        
        return [
            'http'     => $http,
            'body'     => $body,
            'error'    => $errorMsg,
            'raw_body' => $rawBody,
        ];
    }
}

