<?php
declare(strict_types=1);

/**
 * Klaviyo Upload Task - BULK VERSION (v1.1 - Updated 12-16-25)
 * 
 * Uploads database records to Klaviyo Data Source API in batches (450 records per request).
 * Complete self-contained task - handles setup, execution, logging, and cleanup.
 * 
 * FIXES:
 * - Now supports job_name parameter
 * - Looks up correct row in wp_klaviyo_globals by job_name
 * - Updates correct timestamp field (last_run_datetime)
 * - Updates starting_offset for resumable jobs
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (required): Used to look up configuration in wp_klaviyo_globals
 * @return array Summary with progress or error details
 */
if (!function_exists('klaviyo_write_objects')) {
    function klaviyo_write_objects(array $params = []): array {
        // Allow up to 30 minutes of runtime and 512MB memory
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        // Extract job_name parameter
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'family_members';
        
        error_log("klaviyo_write_objects: Function started. Job: {$jobName}, Memory limit: " . ini_get('memory_limit'));
        
        // Initialize temp log file with job name
        $logs_dir = ABSPATH . 'wp-content/wp-custom-scripts/logs/';
        if (!is_dir($logs_dir)) { @mkdir($logs_dir, 0755, true); }
        $temp_log = $logs_dir . 'task1_write_objects_' . date('Y-m-d_H-i-s') . '.log';
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] JOB STARTED (BULK) - Job: {$jobName}\n");
        
        global $wpdb;
        
        // Enable autocommit for real-time visibility in external clients (Navicat, etc.)
        $wpdb->query('SET autocommit = 1');
        error_log("klaviyo_write_objects: Enabled autocommit for real-time write visibility");
        
        // --- 1. Read configuration from database BY JOB NAME ---
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
        $batchSize      = 450;
        $batchLimit     = isset($g['batch_limit']) && $g['batch_limit'] !== null ? (int)$g['batch_limit'] : 0;
        $startingOffset = isset($g['starting_offset']) && $g['starting_offset'] !== null ? (int)$g['starting_offset'] : 0;
        $controlParam2  = trim((string)($g['control_param_2'] ?? ''));
        $globalsId      = (int)$g['id'];
        $baseSql        = $queryString;
        $queryName      = 'query';
        
        if (isset($params['batch_limit'])) {
            $batchLimitParam = max(0, (int)$params['batch_limit']);
            if ($batchLimitParam !== $batchLimit) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Override batch_limit from request: {$batchLimitParam}\n", FILE_APPEND);
            }
            $batchLimit = $batchLimitParam;
        }
        if (isset($params['starting_offset'])) {
            $startingOffsetParam = max(0, (int)$params['starting_offset']);
            if ($startingOffsetParam !== $startingOffset) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Override starting_offset from request: {$startingOffsetParam}\n", FILE_APPEND);
            }
            $startingOffset = $startingOffsetParam;
        }
        
    
        $endpoint = 'https://a.klaviyo.com/api/data-source-record-bulk-create-jobs';

        // Clear previous result and first_batch_payload before starting new run
        // $table already defined above
        $jobStartTime = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_run_datetime = %s WHERE id = %d",
            $jobStartTime,
            $globalsId
        ));
        $wpdb->query('COMMIT');
        error_log("klaviyo_write_objects: Set last_run_datetime={$jobStartTime} for job {$jobName}");
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_result = %s, first_batch_payload = NULL WHERE id = %d",
            "Cron job has started\n",
            $globalsId
        ));
        $wpdb->query('COMMIT'); // Force immediate commit for visibility
        
        if ($wpdb->last_error) {
            error_log("klaviyo_write_objects: Database error during initialization: " . $wpdb->last_error);
        }
        
        error_log("klaviyo_write_objects: Cleared first_batch_payload and initialized last_result (affected rows: {$affected}, table: {$table}, id: {$globalsId})");
        
        // --- 2. Validate required inputs ---
        if ($apiKey === '') {
            return nce_finish_and_log(['error' => 'Missing api_key'], $globalsId);
        }
        if ($apiVersion === '') {
            return nce_finish_and_log(['error' => 'Missing api_version'], $globalsId);
        }
        if ($dsId === '') {
            return nce_finish_and_log(['error' => 'Missing object_ds_id (run data source creation first)'], $globalsId);
        }
        if ($baseSql === '') {
            return nce_finish_and_log(['error' => 'Missing query (configure wp_klaviyo_globals.query)'], $globalsId);
        }
        if ($batchSize < 1 || $batchSize > 1000) {
            return nce_finish_and_log(['error' => 'batch_size must be between 1 and 1000'], $globalsId);
        }
        
        // --- 3. SQL safety check ---
        $lc = ' ' . strtolower($baseSql) . ' ';
        if (str_contains($lc, ' delete ') || str_contains($lc, ' drop ') || str_contains($lc, ' truncate ')) {
            return nce_finish_and_log(['error' => "Destructive SQL not allowed in {$queryName}"], $globalsId);
        }
        
        // --- 4. Initialize counters ---
        $offset         = $startingOffset;  // Use starting_offset from database (defaults to 0)
        $batches        = 0;
        $uploaded       = 0;
        $delaySeconds   = 3;  // Start with 3 seconds, increase if rate limited
        $ranOutOfRows   = false;
        $limitTriggered = false;
        
        // Log configuration
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded for job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Mode: BULK (450 records per batch)\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch size: {$batchSize} records per batch\n", FILE_APPEND);
        error_log("klaviyo_write_objects: Using batch size: {$batchSize}");
        if ($batchLimit > 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch limit: {$batchLimit} batches per run\n", FILE_APPEND);
        }
        if ($startingOffset > 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Using starting_offset: {$startingOffset}\n", FILE_APPEND);
            error_log("klaviyo_write_objects: Starting from offset {$startingOffset}");
        }
        if ($controlParam2 !== '' && intval($controlParam2) > 0) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] control_param_2: {$controlParam2}\n", FILE_APPEND);
        }
        
        // --- 5. Main batch loop ---
        while (true) {
            // Log progress every 10 batches
            if ($batches > 0 && $batches % 10 === 0) {
                error_log("klaviyo_write_objects: Progress - Batch {$batches}/{$batchLimit}, Uploaded: {$uploaded}, Memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
            }
            
            // Check batch limit
            if ($batchLimit > 0 && $batches >= $batchLimit) {
                error_log("klaviyo_write_objects: Batch limit reached ({$batchLimit})");
                $limitTriggered = true;
                break;
            }
            
            // Fetch next batch of rows
            $sql  = nce_build_paged_sql($baseSql, $batchSize, $offset);
            $rows = $wpdb->get_results($sql, ARRAY_A);
            
            // Handle SQL errors
            if ($wpdb->last_error) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] SQL ERROR - " . $wpdb->last_error . "\n", FILE_APPEND);
                nce_update_starting_offset($globalsId, $offset);
                
                return nce_finish_and_log([
                    'error'   => 'SQL error: ' . $wpdb->last_error,
                    'offset'  => $offset,
                    'batches' => $batches,
                    'uploaded' => $uploaded,
                ], $globalsId);
            }
            
            // No more rows - done
            if (empty($rows)) {
                $ranOutOfRows = true;
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
            
            // Build payload (format confirmed via Klaviyo documentation Nov 3, 2025)
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
                file_put_contents($temp_log, "  Data Source ID: {$dsId}\n", FILE_APPEND);
                file_put_contents($temp_log, "  Endpoint: {$endpoint}\n", FILE_APPEND);
                file_put_contents($temp_log, "  Batch Size: {$batchSize} records\n", FILE_APPEND);
                file_put_contents($temp_log, "  Records in this batch: " . count($records) . "\n", FILE_APPEND);
                if (!empty($records[0])) {
                    file_put_contents($temp_log, "  Sample record keys: " . implode(', ', array_keys($records[0]['attributes']['record'])) . "\n", FILE_APPEND);
                }
            }
            
            // Send to Klaviyo with retry logic
            $maxRetries = 3;
            $retryCount = 0;
            $resp = null;
            $rateLimited = false;
            
            while ($retryCount <= $maxRetries) {
                $resp = nce_klaviyo_request('POST', $endpoint, $apiKey, $apiVersion, $payload);
                
                // Success - break out
                if ($resp['http'] >= 200 && $resp['http'] < 300) {
                    break;
                }
                
                // Rate limited (429) - retry with backoff
                if ($resp['http'] === 429) {
                    $rateLimited = true;
                    $retryAfter = $resp['retry_after'] ?? (2 ** $retryCount); // Exponential: 1, 2, 4
                    
                    if ($retryCount < $maxRetries) {
                        error_log("klaviyo_write_objects: Rate limited (429), retrying after {$retryAfter} seconds (attempt " . ($retryCount + 1) . "/{$maxRetries})");
                        sleep((int)$retryAfter);
                        $retryCount++;
                        continue;
                    }
                }
                
                // Other error - stop immediately
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
            
            // Build first log line (batch result)
            $logLine1 = sprintf(
                "[%s] Batch %03d/%03d | Records: %d | HTTP: %d | Status: %s | Uploaded: %d | Memory: %.1fMB | Klaviyo req id: %s | Errors: %d\n",
                date('H:i:s'),
                $batches,
                $batchLimit > 0 ? $batchLimit : 999,
                count($records),
                $resp['http'],
                $status,
                $uploadedThisBatch,
                memory_get_usage() / 1024 / 1024,
                $klaviyoReqId,
                $errorCount
            );
            
            // Build second log line (headers as single-line JSON)
            $headersJson = !empty($resp['headers']) 
                ? str_replace(["\n", "\r"], '', json_encode($resp['headers'])) 
                : '{}';
            $logLine2 = sprintf(
                "[%s] Batch %03d/%03d | Headers: %s\n",
                date('H:i:s'),
                $batches,
                $batchLimit > 0 ? $batchLimit : 999,
                $headersJson
            );
            
            // Write to temp_log
            file_put_contents($temp_log, $logLine1 . $logLine2, FILE_APPEND);
            
            // Append batch result to last_result in database
            $batchResult = $logLine1 . $logLine2;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                $batchResult,
                $globalsId
            ));
            $wpdb->query('COMMIT'); // Commit immediately for real-time visibility
            
            // Save first batch payload to first_batch_payload field
            if ($batches === 1) {
                $payloadJson = wp_json_encode($payload, JSON_PRETTY_PRINT);
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET first_batch_payload = %s WHERE id = %d",
                    $payloadJson,
                    $globalsId
                ));
                $wpdb->query('COMMIT'); // Commit immediately for real-time visibility
                error_log("klaviyo_write_objects: Saved first batch payload to database");
            }
            
            // If we were rate limited, bump up delay for remaining batches
            if ($rateLimited && $retryCount > 0) {
                $delaySeconds++;
                error_log("klaviyo_write_objects: Increased batch delay to {$delaySeconds} seconds");
                file_put_contents($temp_log, "[" . date('H:i:s') . "] RATE LIMITED - Delay increased to {$delaySeconds}s\n", FILE_APPEND);
                
                // Append rate limit info to last_result
                $rateLimitMsg = "[" . date('H:i:s') . "] RATE LIMITED - Delay increased to {$delaySeconds}s\n";
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                    $rateLimitMsg,
                    $globalsId
                ));
                $wpdb->query('COMMIT'); // Commit immediately for real-time visibility
            }
            
            // Stop on failure (after retries exhausted)
            if ($resp['http'] < 200 || $resp['http'] >= 300 || $resp['error']) {
                $errorMsg = $resp['error'] ?? ($resp['body']['errors'][0]['detail'] ?? 'Unknown');
                $failMsg = "[" . date('H:i:s') . "] JOB FAILED - " . $errorMsg . "\n";
                file_put_contents($temp_log, $failMsg, FILE_APPEND);
                
                // Append failure message to last_result
                nce_update_starting_offset($globalsId, $offset);
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                    $failMsg,
                    $globalsId
                ));
                $wpdb->query('COMMIT'); // Commit immediately for real-time visibility
                
                return [
                    'error' => 'Job failed - see last_result field for details',
                    'job_name' => $jobName
                ];
            }
            
            $uploaded += $uploadedThisBatch;
            $offset   += $batchSize;
            
            // Wait before next batch (dynamic delay)
            sleep($delaySeconds);
            
            if ($batchLimit > 0 && $batches >= $batchLimit) {
                $limitMsg = "[" . date('H:i:s') . "] Batch limit reached ({$batchLimit})\n";
                file_put_contents($temp_log, $limitMsg, FILE_APPEND);
                $limitTriggered = true;
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
                    $limitMsg,
                    $globalsId
                ));
                $wpdb->query('COMMIT'); // Commit immediately for real-time visibility
                break;
            }
        }
        
        // --- 6. Job completed - append summary to last_result ---
        $completionMsg = "[" . date('H:i:s') . "] --- JOB COMPLETE (BULK) ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Job: {$jobName}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total batches: {$batches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total uploaded: {$uploaded}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Final delay: {$delaySeconds}s\n";
        if ($dsId !== '') {
            $dsLine = $dsName !== '' ? "Data source: {$dsName} ({$dsId})" : "Data source ID: {$dsId}";
            $completionMsg .= "[" . date('H:i:s') . "] {$dsLine}\n";
        }
        
        $nextStartingOffset = $ranOutOfRows ? 0 : $offset;
        nce_update_starting_offset($globalsId, $nextStartingOffset);
        if ($limitTriggered && !$ranOutOfRows) {
            $completionMsg .= "[" . date('H:i:s') . "] Next starting_offset saved for resume: {$nextStartingOffset}\n";
        } elseif ($ranOutOfRows) {
            $completionMsg .= "[" . date('H:i:s') . "] Dataset exhausted - starting_offset reset to 0\n";
        }
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_result = CONCAT(COALESCE(last_result, ''), %s) WHERE id = %d",
            $completionMsg,
            $globalsId
        ));
        $wpdb->query('COMMIT'); // Final commit for completion message
        
        return [
            'success' => true,
            'message' => 'Job completed (bulk) - see last_result field for details',
            'job_name' => $jobName,
            'batches' => $batches,
            'uploaded' => $uploaded
        ];
    }
}

/* ============================================================
 * Helper Functions (used by klaviyo_write_objects)
 * ============================================================ */

/**
 * Build paginated SQL query
 */
if (!function_exists('nce_build_paged_sql')) {
    function nce_build_paged_sql(string $baseSql, int $limit, int $offset): string {
        $trimmed = rtrim($baseSql);
        $trimmed = rtrim($trimmed, "; \t\n\r\0\x0B");
        return $trimmed . " LIMIT {$limit} OFFSET {$offset}";
    }
}

/**
 * Make HTTP request to Klaviyo API with revision header
 */
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
        
        // Capture rate limit headers
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
        
        // Capture raw response for debugging
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

/**
 * Write result to last_result column and return it
 */
if (!function_exists('nce_finish_and_log')) {
    function nce_finish_and_log(array $result, int $globalsId): array {
        error_log('nce_finish_and_log: CALLED with globalsId=' . $globalsId);
        
        global $wpdb;
        $table = $wpdb->prefix . 'klaviyo_globals';
        
        error_log('nce_finish_and_log: Table name: ' . $table);
        error_log('nce_finish_and_log: Result keys: ' . implode(', ', array_keys($result)));
        
        // Add timestamp if not present
        if (!isset($result['timestamp'])) {
            $result['timestamp'] = current_time('mysql');
        }
        
        // Encode the result to JSON
        $json = wp_json_encode($result, JSON_PRETTY_PRINT);
        
        error_log('nce_finish_and_log: JSON encoding result...');
        error_log('nce_finish_and_log: JSON length: ' . strlen($json));
        error_log('nce_finish_and_log: JSON first 500 chars: ' . substr($json, 0, 500));
        
        if ($json === false) {
            error_log('nce_finish_and_log: JSON ENCODING FAILED! Error: ' . json_last_error_msg());
            $json = json_encode(['error' => 'JSON encoding failed', 'json_error' => json_last_error_msg()]);
        }
        
        // Write to last_result column
        $updated = $wpdb->update(
            $table,
            ['last_result' => $json],
            ['id' => $globalsId],
            ['%s'],
            ['%d']
        );
        
        // Log if update failed
        if ($updated === false) {
            error_log('nce_finish_and_log: Database update FAILED');
            error_log('nce_finish_and_log: wpdb error: ' . $wpdb->last_error);
            error_log('nce_finish_and_log: Globals ID: ' . $globalsId);
        } else {
            error_log('nce_finish_and_log: Database updated successfully. Rows affected: ' . $updated);
            error_log('nce_finish_and_log: Wrote ' . strlen($json) . ' bytes to database');
        }
        
        return $result;
    }
}

if (!function_exists('nce_update_starting_offset')) {
    function nce_update_starting_offset(int $globalsId, int $newOffset): void {
        global $wpdb;
        $table = $wpdb->prefix . 'klaviyo_globals';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET starting_offset = %d WHERE id = %d",
            $newOffset,
            $globalsId
        ));
        $wpdb->query('COMMIT');
        error_log("nce_update_starting_offset: Updated globals {$globalsId} starting_offset to {$newOffset}");
    }
}

