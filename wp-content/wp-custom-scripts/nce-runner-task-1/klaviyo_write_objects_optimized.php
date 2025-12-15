<?php
declare(strict_types=1);

/**
 * Klaviyo Upload Task - OPTIMIZED VERSION 12-2-25 
 * 
 * Optimizations over original version:
 * - No delays between batches (unless rate limited)
 * - Only retries on HTTP 429 (rate limit)
 * - Better rate limit monitoring
 * - Less frequent database logging (every 10 batches vs every batch)
 * - Fixed 450-record batches for stable throughput
 * - Enhanced statistics and performance metrics
 * - Auto-creates data source if not present
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (required): Used to look up configuration in wp_klaviyo_globals WHERE job_name = $job_name
 *                                             If not provided, defaults to 'default'
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
        $skipLogClear = !empty($params['skip_log_clear']); // Don't clear log when called from full sync
        
        error_log("klaviyo_write_objects_optimized: Function started. Job: {$jobName}, Memory limit: " . ini_get('memory_limit'));
        
        // Initialize temp log file (only clear if not called from full sync)
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        if (!$skipLogClear) {
            file_put_contents($temp_log, ""); // Clear the file
        }
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
        $dsName         = trim((string)($g['object_ds_name'] ?? '')); 
        $queryString    = trim((string)($g['query'] ?? ''));
        $onlyRunIf      = trim((string)($g['only_run_if'] ?? ''));
        $batchSize      = 450;
        $batchLimit     = isset($g['batch_limit']) && $g['batch_limit'] !== null ? (int)$g['batch_limit'] : 0;
        $startingOffset = isset($g['starting_offset']) && $g['starting_offset'] !== null ? (int)$g['starting_offset'] : 0;
        $controlParam2  = trim((string)($g['control_param_2'] ?? ''));
        $bulkMode       = isset($g['bulk']) && (int)$g['bulk'] === 1;
        $globalsId      = (int)$g['id'];
        $baseSql        = $queryString;
        $queryName      = 'query';
        $createdDataSource = null;
        
        $endpoint = 'https://a.klaviyo.com/api/data-source-record-bulk-create-jobs';
        
        // --- Pre-check: only_run_if query ---
        $onlyRunIfInfo = null; // Track only_run_if execution details
        
        if ($onlyRunIf !== '') {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Checking only_run_if condition...\n", FILE_APPEND);
            error_log("klaviyo_write_objects_optimized: Executing only_run_if check for job: {$jobName}");
            
            // Replace {{column_name}} placeholders with values from globals row
            $processedQuery = $onlyRunIf;
            $hasNullPlaceholder = false;
            $nullPlaceholderName = null;
            
            // Find all {{placeholder}} patterns
            if (preg_match_all('/\{\{(\w+)\}\}/', $onlyRunIf, $matches)) {
                foreach ($matches[1] as $columnName) {
                    $placeholder = '{{' . $columnName . '}}';
                    $value = $g[$columnName] ?? null;
                    
                    if ($value === null || $value === '') {
                        $hasNullPlaceholder = true;
                        $nullPlaceholderName = $placeholder;
                        file_put_contents($temp_log, "[" . date('H:i:s') . "] Placeholder {$placeholder} is null/empty - will run job\n", FILE_APPEND);
                        error_log("klaviyo_write_objects_optimized: Placeholder {$placeholder} is null/empty for job: {$jobName}");
                        break;
                    }
                    
                    // Escape and quote the value for SQL
                    $escapedValue = "'" . esc_sql($value) . "'";
                    $processedQuery = str_replace($placeholder, $escapedValue, $processedQuery);
                }
            }
            
            // If any placeholder was null/empty, run the job (e.g., first run)
            if ($hasNullPlaceholder) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Running job (placeholder value missing - likely first run)\n", FILE_APPEND);
                $onlyRunIfInfo = [
                    'executed' => false,
                    'reason' => "Placeholder {$nullPlaceholderName} is null/empty (first run)",
                    'query' => $onlyRunIf
                ];
            } else {
                // Execute the check query
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Processed query: " . substr($processedQuery, 0, 200) . "\n", FILE_APPEND);
                $checkStartTime = microtime(true);
                $checkResult = $wpdb->get_var($processedQuery);
                $checkDuration = round((microtime(true) - $checkStartTime) * 1000, 2); // milliseconds
                
                if ($wpdb->last_error) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] WARNING: only_run_if query error: " . $wpdb->last_error . " - will run job anyway ({$checkDuration}ms)\n", FILE_APPEND);
                    error_log("klaviyo_write_objects_optimized: only_run_if query error (running anyway): " . $wpdb->last_error);
                    $onlyRunIfInfo = [
                        'executed' => true,
                        'duration_ms' => $checkDuration,
                        'result' => null,
                        'error' => $wpdb->last_error,
                        'query' => $processedQuery
                    ];
                    // Continue anyway if the check query fails (fail-safe)
                } elseif (empty($checkResult)) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] ⏭️  SKIPPED: only_run_if returned empty/null - no updates to process ({$checkDuration}ms)\n", FILE_APPEND);
                    error_log("klaviyo_write_objects_optimized: Skipping job {$jobName} - only_run_if returned empty");
                    return [
                        'success' => true,
                        'skipped' => true,
                        'message' => 'Skipped: only_run_if condition not met (no updates detected)',
                        'job_name' => $jobName,
                        'only_run_if' => [
                            'executed' => true,
                            'duration_ms' => $checkDuration,
                            'result' => null,
                            'query' => $processedQuery
                        ]
                    ];
                } else {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ only_run_if check passed (result: {$checkResult}, {$checkDuration}ms)\n", FILE_APPEND);
                    error_log("klaviyo_write_objects_optimized: only_run_if check passed for job: {$jobName}");
                    $onlyRunIfInfo = [
                        'executed' => true,
                        'duration_ms' => $checkDuration,
                        'result' => $checkResult,
                        'query' => $processedQuery
                    ];
                }
            }
        }

        // --- Run sql_prep statements if configured ---
        $sqlPrep = trim((string)($g['sql_prep'] ?? ''));
        $sqlPrepInfo = null;
        
        if ($sqlPrep !== '') {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Running sql_prep statements...\n", FILE_APPEND);
            error_log("klaviyo_write_objects_optimized: Executing sql_prep for job: {$jobName}");
            
            $prepStartTime = microtime(true);
            $prepStatements = array_filter(array_map('trim', explode(';', $sqlPrep)));
            $prepResults = [];
            $prepFailed = false;
            
            foreach ($prepStatements as $stmt) {
                if ($stmt === '') continue;
                
                $stmtDisplay = strlen($stmt) > 100 ? substr($stmt, 0, 100) . '...' : $stmt;
                file_put_contents($temp_log, "[" . date('H:i:s') . "]   Executing: {$stmtDisplay}\n", FILE_APPEND);
                
                $stmtStart = microtime(true);
                $stmtResult = $wpdb->query($stmt);
                $stmtDuration = round((microtime(true) - $stmtStart) * 1000, 2);
                
                if ($wpdb->last_error) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "]   ✗ FAILED ({$stmtDuration}ms): " . $wpdb->last_error . "\n", FILE_APPEND);
                    error_log("klaviyo_write_objects_optimized: sql_prep statement failed: " . $wpdb->last_error);
                    $prepResults[] = [
                        'statement' => $stmtDisplay,
                        'success' => false,
                        'error' => $wpdb->last_error,
                        'duration_ms' => $stmtDuration
                    ];
                    $prepFailed = true;
                    break; // Stop on first error
                } else {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "]   ✓ Success ({$stmtDuration}ms)\n", FILE_APPEND);
                    $prepResults[] = [
                        'statement' => $stmtDisplay,
                        'success' => true,
                        'affected_rows' => $stmtResult,
                        'duration_ms' => $stmtDuration
                    ];
                }
            }
            
            $prepDuration = round((microtime(true) - $prepStartTime) * 1000, 2);
            
            $sqlPrepInfo = [
                'executed' => true,
                'duration_ms' => $prepDuration,
                'statements' => $prepResults,
                'success' => !$prepFailed
            ];
            
            if ($prepFailed) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ sql_prep FAILED - aborting job\n", FILE_APPEND);
                return [
                    'error' => 'sql_prep failed - see details',
                    'job_name' => $jobName,
                    'sql_prep' => $sqlPrepInfo
                ];
            }
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ sql_prep completed successfully ({$prepDuration}ms)\n", FILE_APPEND);
            error_log("klaviyo_write_objects_optimized: sql_prep completed in {$prepDuration}ms for job: {$jobName}");
        }

        // Clear previous result and first_batch_payload before starting new run
        // $table already defined above
        $jobStartTime = current_time('mysql', true);
        
        // Only update last_run_datetime if NOT in bulk mode
        if (!$bulkMode) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_run_datetime = %s WHERE id = %d",
            $jobStartTime,
            $globalsId
        ));
        $wpdb->query('COMMIT');
        error_log("klaviyo_write_objects_optimized: Set last_run_datetime={$jobStartTime} for job {$jobName}");
        } else {
            error_log("klaviyo_write_objects_optimized: BULK MODE - skipping last_run_datetime update for job {$jobName}");
            file_put_contents($temp_log, "[" . date('H:i:s') . "] BULK MODE enabled - will increment starting_offset instead of updating last_run_datetime\n", FILE_APPEND);
        }
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
            $dsName = $dsResult['object_ds_name'] ?? '';
            $createdDataSource = [
                'id' => $dsId,
                'name' => $dsName,
                'created_at' => current_time('mysql'),
            ];
            error_log("klaviyo_write_objects_optimized: Created new data source: {$dsName} (ID: {$dsId})");
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Created data source: {$dsName} (ID: {$dsId})\n", FILE_APPEND);
            
            // Ensure globals row stores the new data source details
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET object_ds_id = %s, object_ds_name = %s WHERE id = %d",
                $dsId,
                $dsName,
                $globalsId
            ));
            $wpdb->query('COMMIT');
        }
        
        // If a data source already exists but has a name, append job name for clarity
        if ($dsId !== '' && $dsName !== '' && stripos($dsName, $jobName) === false) {
            $newDsName = "{$dsName} ({$jobName})";
            $updateName = nce_update_klaviyo_data_source_name($dsId, $newDsName, $apiKey, $apiVersion);
            if (empty($updateName['error'])) {
                $dsName = $newDsName;
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET object_ds_name = %s WHERE id = %d",
                    $dsName,
                    $globalsId
                ));
                $wpdb->query('COMMIT');
                error_log("klaviyo_write_objects_optimized: Appended job name to existing data source ({$dsName})");
            } else {
                error_log("klaviyo_write_objects_optimized: Failed to update data source name: " . $updateName['error']);
            }
        }
        
        if ($baseSql === '') {
            return nce_finish_and_log(['error' => 'Missing query (configure wp_klaviyo_globals.query for this job)'], $globalsId);
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
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch limit: " . ($batchLimit > 0 ? $batchLimit : '0 (no limit)') . "\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Starting offset: {$startingOffset}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] OPTIMIZATION: No delays between batches (async processing)\n", FILE_APPEND);
        
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
        if ($dsId !== '') {
            $dsLine = $createdDataSource ? "Data source: {$dsName} ({$dsId}) [CREATED THIS RUN]" : "Data source: {$dsName} ({$dsId})";
            $completionMsg .= "[" . date('H:i:s') . "] {$dsLine}\n";
        }
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
            $completionMsg,
            $globalsId
        ));
        $wpdb->query('COMMIT');
        
        // --- Bulk mode: increment starting_offset instead of updating last_run_datetime ---
        $bulkModeInfo = null;
        if ($bulkMode && $uploaded > 0) {
            $newOffset = $startingOffset + $uploaded;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET starting_offset = %d WHERE id = %d",
                $newOffset,
                $globalsId
            ));
            $wpdb->query('COMMIT');
            
            $bulkMsg = "[" . date('H:i:s') . "] BULK MODE: Incremented starting_offset from {$startingOffset} to {$newOffset} (+{$uploaded})\n";
            file_put_contents($temp_log, $bulkMsg, FILE_APPEND);
            error_log("klaviyo_write_objects_optimized: BULK MODE - starting_offset updated: {$startingOffset} -> {$newOffset}");
            
            $bulkModeInfo = [
                'enabled' => true,
                'previous_offset' => $startingOffset,
                'new_offset' => $newOffset,
                'increment' => $uploaded
            ];
        }
        
        $dataSourceInfo = [
            'id' => $dsId,
            'name' => $dsName,
            'created_this_run' => $createdDataSource !== null,
        ];
        
        $result = [
            'success' => true, 
            'message' => 'Job completed - see last_result field for details',
            'job_name' => $jobName,
            'query' => $baseSql,
            'data_source' => $dataSourceInfo,
            'stats' => [
                'batches' => $batches,
                'uploaded' => $uploaded,
                'duration_seconds' => $totalTime,
                'avg_rate' => $avgRate,
                'rate_limit_hits' => $rateLimitHits,
                'total_retries' => $totalRetries,
            ]
        ];
        
        // Add only_run_if info if it was executed
        if ($onlyRunIfInfo !== null) {
            $result['only_run_if'] = $onlyRunIfInfo;
        }
        
        // Add sql_prep info if it was executed
        if ($sqlPrepInfo !== null) {
            $result['sql_prep'] = $sqlPrepInfo;
        }
        
        // Add bulk mode info if enabled
        if ($bulkModeInfo !== null) {
            $result['bulk_mode'] = $bulkModeInfo;
        }
        
        return $result;
    }
}

/* ============================================================
 * Helper Functions (shared with legacy Task 1 runner)
 * ============================================================ */

if (!function_exists('nce_build_paged_sql')) {
    function nce_build_paged_sql(string $baseSql, int $limit, int $offset): string {
        $trimmed = rtrim($baseSql);
        $trimmed = rtrim($trimmed, "; \t\n\r\0\x0B");
        return $trimmed . " LIMIT {$limit} OFFSET {$offset}";
    }
}

if (!function_exists('nce_klaviyo_request')) {
    function nce_klaviyo_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
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
        $headers = is_wp_error($res) ? [] : wp_remote_retrieve_headers($res);
        
        $retryAfter = null;
        $rateLimit = null;
        $rateRemaining = null;
        $rateReset = null;
        
        if (!empty($headers)) {
            $retryAfter = $headers['retry-after'] ?? $headers['Retry-After'] ?? null;
            $rateLimit = $headers['ratelimit-limit'] ?? $headers['RateLimit-Limit'] ?? null;
            $rateRemaining = $headers['ratelimit-remaining'] ?? $headers['RateLimit-Remaining'] ?? null;
            $rateReset = $headers['ratelimit-reset'] ?? $headers['RateLimit-Reset'] ?? null;
        }
        
        $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
        
        return [
            'http'           => $http,
            'body'           => $body,
            'error'          => is_wp_error($res) ? $res->get_error_message() : null,
            'retry_after'    => $retryAfter ? (int)$retryAfter : null,
            'rate_limit'     => $rateLimit,
            'rate_remaining' => $rateRemaining,
            'rate_reset'     => $rateReset,
            'headers'        => $headers,
            'raw_body'       => $rawBody,
        ];
    }
}

if (!function_exists('nce_finish_and_log')) {
    function nce_finish_and_log(array $result, int $globalsId): array {
        error_log('nce_finish_and_log: CALLED with globalsId=' . $globalsId);
        
        global $wpdb;
        $table = $wpdb->prefix . 'klaviyo_globals';
        
        // Add timestamp if not present
        if (!isset($result['timestamp'])) {
            $result['timestamp'] = current_time('mysql');
        }
        
        $json = wp_json_encode($result, JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = json_encode(['error' => 'JSON encoding failed', 'json_error' => json_last_error_msg()]);
        }
        
        $updated = $wpdb->update(
            $table,
            ['last_result' => $json],
            ['id' => $globalsId],
            ['%s'],
            ['%d']
        );
        
        if ($updated === false) {
            error_log('nce_finish_and_log: Database update FAILED - ' . $wpdb->last_error);
        }
        
        return $result;
    }
}

if (!function_exists('nce_update_klaviyo_data_source_name')) {
    function nce_update_klaviyo_data_source_name(string $dsId, string $newName, string $apiKey, string $apiVersion): array {
        $url = "https://a.klaviyo.com/api/data-sources/{$dsId}";
        $payload = [
            'data' => [
                'type' => 'data-source',
                'id' => $dsId,
                'attributes' => [
                    'name' => $newName,
                ],
            ],
        ];
        
        $response = nce_klaviyo_request('PATCH', $url, $apiKey, $apiVersion, $payload);
        if ($response['http'] >= 200 && $response['http'] < 300) {
            return ['success' => true];
        }
        
        $error = $response['error'] ?? ($response['body']['errors'][0]['detail'] ?? 'Unknown');
        return ['error' => $error, 'http' => $response['http'], 'body' => $response['body']];
    }
}

