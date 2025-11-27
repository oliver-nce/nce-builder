<?php
declare(strict_types=1);

/**
 * Klaviyo Upload Task - OPTIMIZED VERSION
 * 
 * Optimizations over original version:
 * - No delays between batches (unless rate limited)
 * - Only retries on HTTP 429 (rate limit)
 * - Better rate limit monitoring
 * - Less frequent database logging (every 10 batches vs every batch)
 * - Supports larger batch sizes (default 1000, max 10000)
 * - Enhanced statistics and performance metrics
 * - Auto-creates data source if not present
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (required): Used to look up configuration in wp_klaviyo_globals WHERE job_name = $job_name
 *                                            If not provided, defaults to 'default'
 *                                            If data source ID is missing, automatically creates one
 * @return array Summary with progress or error details
 */
if (!function_exists('klaviyo_write_objects_optimized')) {
    function klaviyo_write_objects_optimized(array $params = []): array {
        // Allow up to 30 minutes of runtime and 512MB memory
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        // Extract job_name parameter
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        
        error_log("klaviyo_write_objects_optimized: Function started. Job: {$jobName}, Memory limit: " . ini_get('memory_limit'));
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] JOB STARTED (OPTIMIZED VERSION) - Job: {$jobName}\n", FILE_APPEND);
        
        global $wpdb;
        
        // Enable autocommit for real-time visibility in external clients (Navicat, etc.)
        $wpdb->query('SET autocommit = 1');
        error_log("klaviyo_write_objects_optimized: Enabled autocommit for real-time write visibility");
        
        // --- 1. Read configuration from database ---
        // Look up config by job_name
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
        
        $apiKey         = trim((string)($g['api_key'] ?? ''));
        $apiVersion     = trim((string)($g['api_version'] ?? '2025-10-15'));
        $dsId           = trim((string)($g['object_ds_id'] ?? ''));
        $objectQuery1   = trim((string)($g['object_query'] ?? ''));
        $objectQuery2   = trim((string)($g['object_query_2'] ?? ''));
        $objectQuery3   = trim((string)($g['object_query_3'] ?? ''));
        $batchSize      = isset($g['batch_size']) && $g['batch_size'] !== null ? (int)$g['batch_size'] : 1000; // INCREASED from 450
        $batchLimit     = isset($g['batch_limit']) && $g['batch_limit'] !== null ? (int)$g['batch_limit'] : 0;
        $startingOffset = isset($g['starting_offset']) && $g['starting_offset'] !== null ? (int)$g['starting_offset'] : 0;
        $queryToUse     = isset($g['query_to_use']) && $g['query_to_use'] !== null ? (int)$g['query_to_use'] : 1;
        $controlParam2  = trim((string)($g['control_param_2'] ?? ''));
        $globalsId      = (int)$g['id'];
        
        // Select which query to use based on query_to_use field
        $baseSql = '';
        $queryName = '';
        switch ($queryToUse) {
            case 2:
                $baseSql = $objectQuery2;
                $queryName = 'object_query_2';
                break;
            case 3:
                $baseSql = $objectQuery3;
                $queryName = 'object_query_3';
                break;
            case 1:
            default:
                $baseSql = $objectQuery1;
                $queryName = 'object_query';
                break;
        }
        
        $endpoint = 'https://a.klaviyo.com/api/data-source-record-bulk-create-jobs';

        // Clear previous result and first_batch_payload before starting new run
        // $table already defined above
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_result = %s, first_batch_payload = NULL WHERE id = %d",
            "Optimized cron job has started\n",
            $globalsId
        ));
        $wpdb->query('COMMIT');
        
        error_log("klaviyo_write_objects_optimized: Cleared first_batch_payload and initialized last_result (Job: {$jobName})");
        
        // --- 2. Validate required inputs ---
        if ($apiKey === '') {
            return nce_finish_and_log(['error' => 'Missing api_key'], $globalsId);
        }
        if ($apiVersion === '') {
            return nce_finish_and_log(['error' => 'Missing api_version'], $globalsId);
        }
        
        // Check if data source exists, if not create it automatically
        if ($dsId === '') {
            error_log("klaviyo_write_objects_optimized: No data source ID found, creating new data source for job: {$jobName}");
            file_put_contents($temp_log, "[" . date('H:i:s') . "] No data source found, creating new one...\n", FILE_APPEND);
            
            // Call data source creation function
            $dsResult = nce_create_klaviyo_data_source_from_db($jobName);
            
            if (isset($dsResult['error'])) {
                error_log("klaviyo_write_objects_optimized: Failed to create data source: " . $dsResult['error']);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: Failed to create data source - " . $dsResult['error'] . "\n", FILE_APPEND);
                return nce_finish_and_log([
                    'error' => 'Failed to create data source: ' . $dsResult['error'],
                    'ds_creation_result' => $dsResult
                ], $globalsId);
            }
            
            // Data source created successfully, use the new ID
            $dsId = $dsResult['object_ds_id'];
            $dsName = $dsResult['object_ds_name'];
            error_log("klaviyo_write_objects_optimized: Created new data source: {$dsName} (ID: {$dsId})");
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Created data source: {$dsName} (ID: {$dsId})\n", FILE_APPEND);
            
            // Note: The data source ID is already saved to the database by nce_create_klaviyo_data_source_from_db
        }
        
        if ($baseSql === '') {
            return nce_finish_and_log(['error' => "Missing {$queryName} (query_to_use={$queryToUse})"], $globalsId);
        }
        if ($batchSize < 1 || $batchSize > 10000) { // INCREASED max from 1000 to 10000
            return nce_finish_and_log(['error' => 'batch_size must be between 1 and 10000'], $globalsId);
        }
        
        // --- 3. SQL safety check ---
        $lc = ' ' . strtolower($baseSql) . ' ';
        if (str_contains($lc, ' delete ') || str_contains($lc, ' drop ') || str_contains($lc, ' truncate ')) {
            return nce_finish_and_log(['error' => "Destructive SQL not allowed in {$queryName}"], $globalsId);
        }
        
        // --- 4. Initialize counters and rate limit tracking ---
        $offset       = $startingOffset;
        $batches      = 0;
        $uploaded     = 0;
        $rateLimitHits = 0;
        $totalRetries = 0;
        
        // Rate limit tracking
        $rateLimitInfo = [
            'limit' => null,
            'remaining' => null,
            'reset' => null,
        ];
        
        // Log configuration
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded for job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch size: {$batchSize} records per batch\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] OPTIMIZATION: No delays between batches (async processing)\n", FILE_APPEND);
        if ($startingOffset > 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Using starting_offset: {$startingOffset}\n", FILE_APPEND);
        }
        if ($queryToUse > 1) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Using query: {$queryName} (query_to_use={$queryToUse})\n", FILE_APPEND);
        }
        
        // --- 5. Main batch loop ---
        $startTime = microtime(true);
        
        while (true) {
            // Log progress every 10 batches (less frequent)
            if ($batches > 0 && $batches % 10 === 0) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $rate = $batches > 0 ? round($uploaded / $elapsed, 2) : 0;
                error_log("klaviyo_write_objects_optimized: Batch {$batches}/{$batchLimit}, Uploaded: {$uploaded}, Rate: {$rate} records/sec, Memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
            }
            
            // Check batch limit
            if ($batchLimit > 0 && $batches >= $batchLimit) {
                error_log("klaviyo_write_objects_optimized: Batch limit reached ({$batchLimit})");
                break;
            }
            
            // Fetch next batch of rows
            $sql  = nce_build_paged_sql($baseSql, $batchSize, $offset);
            $rows = $wpdb->get_results($sql, ARRAY_A);
            
            // Handle SQL errors
            if ($wpdb->last_error) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] SQL ERROR - " . $wpdb->last_error . "\n", FILE_APPEND);
                
                return nce_finish_and_log([
                    'error'   => 'SQL error: ' . $wpdb->last_error,
                    'offset'  => $offset,
                    'batches' => $batches,
                    'uploaded' => $uploaded,
                ], $globalsId);
            }
            
            // No more rows - done
            if (empty($rows)) {
                break;
            }
            
            // Build payload for bulk create job endpoint
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'type' => 'data-source-record',
                    'attributes' => [
                        'record' => $row,
                    ],
                ];
            }
            
            // Build payload
            $payload = [
                'data' => [
                    'type' => 'data-source-record-bulk-create-job',
                    'attributes' => [
                        'data-source-records' => [
                            'data' => $records,
                        ],
                    ],
                    'relationships' => [
                        'data-source' => [
                            'data' => [
                                'type' => 'data-source',
                                'id'   => $dsId,
                            ],
                        ],
                    ],
                ],
            ];
            
            // Log first batch payload for debugging
            if ($batches === 0) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] First batch payload sample:\n", FILE_APPEND);
                file_put_contents($temp_log, "  Job Name: {$jobName}\n", FILE_APPEND);
                file_put_contents($temp_log, "  Data Source ID: {$dsId}\n", FILE_APPEND);
                file_put_contents($temp_log, "  Endpoint: {$endpoint}\n", FILE_APPEND);
                file_put_contents($temp_log, "  Batch Size: {$batchSize} records\n", FILE_APPEND);
                file_put_contents($temp_log, "  Records in this batch: " . count($records) . "\n", FILE_APPEND);
                if (!empty($records[0])) {
                    file_put_contents($temp_log, "  Sample record keys: " . implode(', ', array_keys($records[0]['attributes']['record'])) . "\n", FILE_APPEND);
                }
            }
            
            // Send to Klaviyo with SIMPLIFIED retry logic (only for 429)
            $maxRetries = 3;
            $retryCount = 0;
            $resp = null;
            
            while ($retryCount <= $maxRetries) {
                $resp = nce_klaviyo_request('POST', $endpoint, $apiKey, $apiVersion, $payload);
                
                // Update rate limit info from headers
                if (!empty($resp['headers'])) {
                    $rateLimitInfo['limit'] = $resp['headers']['ratelimit-limit'] ?? $resp['headers']['RateLimit-Limit'] ?? $rateLimitInfo['limit'];
                    $rateLimitInfo['remaining'] = $resp['headers']['ratelimit-remaining'] ?? $resp['headers']['RateLimit-Remaining'] ?? $rateLimitInfo['remaining'];
                    $rateLimitInfo['reset'] = $resp['headers']['ratelimit-reset'] ?? $resp['headers']['RateLimit-Reset'] ?? $rateLimitInfo['reset'];
                }
                
                // Success - break out
                if ($resp['http'] >= 200 && $resp['http'] < 300) {
                    break;
                }
                
                // ONLY retry on rate limit (429)
                if ($resp['http'] === 429) {
                    $rateLimitHits++;
                    $retryAfter = $resp['retry_after'] ?? 5; // Use Retry-After header or default to 5 seconds
                    
                    if ($retryCount < $maxRetries) {
                        $totalRetries++;
                        error_log("klaviyo_write_objects_optimized: Rate limited (429), retrying after {$retryAfter} seconds (attempt " . ($retryCount + 1) . "/{$maxRetries})");
                        file_put_contents($temp_log, "[" . date('H:i:s') . "] RATE LIMITED - Waiting {$retryAfter}s (retry " . ($retryCount + 1) . ")\n", FILE_APPEND);
                        sleep((int)$retryAfter);
                        $retryCount++;
                        continue;
                    }
                }
                
                // Other error - stop immediately (don't retry)
                break;
            }
            
            $batches++;
            
            // Determine status and uploaded count
            $status = ($resp['http'] >= 200 && $resp['http'] < 300) ? 'SUCCESS' : 'FAILED';
            $uploadedThisBatch = $status === 'SUCCESS' ? count($records) : 0;
            
            // Extract x-klaviyo-req-id from headers
            $klaviyoReqId = 'none';
            if (!empty($resp['headers'])) {
                $klaviyoReqId = $resp['headers']['x-klaviyo-req-id'] 
                    ?? $resp['headers']['X-Klaviyo-Req-Id'] 
                    ?? 'none';
            }
            
            // Count errors
            $errorCount = 0;
            if (!empty($resp['body']['errors'])) {
                $errorCount = count($resp['body']['errors']);
            } elseif ($resp['error']) {
                $errorCount = 1;
            }
            
            // Build log line with rate limit info
            $logLine = sprintf(
                "[%s] Batch %03d | Records: %d | HTTP: %d | Status: %s | RateLimit: %s/%s (remaining) | Memory: %.1fMB | ReqID: %s\n",
                date('H:i:s'),
                $batches,
                count($records),
                $resp['http'],
                $status,
                $rateLimitInfo['remaining'] ?? '?',
                $rateLimitInfo['limit'] ?? '?',
                memory_get_usage() / 1024 / 1024,
                $klaviyoReqId
            );
            
            // Write to temp_log
            file_put_contents($temp_log, $logLine, FILE_APPEND);
            
            // Append to database ONLY every 10 batches (less frequent)
            if ($batches % 10 === 0 || $status === 'FAILED') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                    $logLine,
                    $globalsId
                ));
                $wpdb->query('COMMIT');
            }
            
            // Save first batch payload to first_batch_payload field
            if ($batches === 1) {
                $payloadJson = wp_json_encode($payload, JSON_PRETTY_PRINT);
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET first_batch_payload = %s WHERE id = %d",
                    $payloadJson,
                    $globalsId
                ));
                $wpdb->query('COMMIT');
                error_log("klaviyo_write_objects_optimized: Saved first batch payload to database");
            }
            
            // Stop on failure (after retries exhausted)
            if ($resp['http'] < 200 || $resp['http'] >= 300 || $resp['error']) {
                $errorMsg = $resp['error'] ?? ($resp['body']['errors'][0]['detail'] ?? 'Unknown');
                $failMsg = "[" . date('H:i:s') . "] JOB FAILED - " . $errorMsg . "\n";
                file_put_contents($temp_log, $failMsg, FILE_APPEND);
                
                // Append failure message to last_result
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                    $failMsg,
                    $globalsId
                ));
                $wpdb->query('COMMIT');
                
                return [
                    'error' => 'Job failed - see last_result field for details',
                    'job_name' => $jobName
                ];
            }
            
            $uploaded += $uploadedThisBatch;
            $offset   += $batchSize;
            
            // NO SLEEP HERE - let Klaviyo handle async processing
            // Only slept if we were rate limited (handled in retry loop above)
            
            if ($batchLimit > 0 && $batches >= $batchLimit) {
                $limitMsg = "[" . date('H:i:s') . "] Batch limit reached ({$batchLimit})\n";
                file_put_contents($temp_log, $limitMsg, FILE_APPEND);
                break;
            }
        }
        
        // --- 6. Job completed - append summary to last_result ---
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        $avgRate = $totalTime > 0 ? round($uploaded / $totalTime, 2) : 0;
        
        $completionMsg = "[" . date('H:i:s') . "] --- JOB COMPLETE (OPTIMIZED) ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total batches: {$batches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total uploaded: {$uploaded}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total time: {$totalTime}s\n";
        $completionMsg .= "[" . date('H:i:s') . "] Average rate: {$avgRate} records/sec\n";
        $completionMsg .= "[" . date('H:i:s') . "] Rate limit hits: {$rateLimitHits}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total retries: {$totalRetries}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Final rate limit: {$rateLimitInfo['remaining']}/{$rateLimitInfo['limit']}\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
            $completionMsg,
            $globalsId
        ));
        $wpdb->query('COMMIT');
        
        return [
            'success' => true, 
            'message' => 'Job completed - see last_result field for details',
            'job_name' => $jobName,
            'stats' => [
                'batches' => $batches,
                'uploaded' => $uploaded,
                'duration_seconds' => $totalTime,
                'avg_rate' => $avgRate,
                'rate_limit_hits' => $rateLimitHits,
                'total_retries' => $totalRetries,
            ]
        ];
    }
}

