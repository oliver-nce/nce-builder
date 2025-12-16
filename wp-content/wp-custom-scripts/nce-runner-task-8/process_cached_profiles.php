<?php
// LAST UPDATED: 2025-11-28 19:45:00
// v1.2.0 - 2025-11-28 (Auto-retry failed batches one phone at a time)
declare(strict_types=1);
  
// Version constant for tracking deployed code
define('NCE_TASK_8_VERSION', '1.2.0');
define('NCE_TASK_8_UPDATED', '2025-11-28 19:45:00');

/**
 * Process Cached Profiles - Grant SMS Consent - Task 8
 * ---
 * STEP 2 of SMS Consent Backfill: Reads cached profiles from Task 7
 * and grants SMS marketing consent in batches.
 * 
 * ⚠️ LEGAL WARNING: Only run this after Task 7 has cached profiles.
 * Ensures ALL cached users have provided explicit SMS opt-in consent.
 *  
 * - Reads profiles from wp_klaviyo_globals.control_param (cached by Task 7)
 * - Processes in batches of 100 profiles
 * - Uses start_from and max_batches for chunked processing
 * - Fits within 60-second timeout per run
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'default' (for API key/version and cache reading)
 *                      - start_from (optional): which batch to start from (default: 1)
 *                      - max_batches (optional): max batches to process this run (default: 25)
 * @return array Summary with consent grant results
 */
if (!function_exists('nce_task_process_cached_profiles')) {
    function nce_task_process_cached_profiles(array $params = []): array {
        @ini_set('max_execution_time', '60');
        @ini_set('memory_limit', '256M');
        @set_time_limit(60);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $startFrom = isset($params['start_from']) ? max(1, intval($params['start_from'])) : 1;
        $maxBatches = isset($params['max_batches']) ? max(1, intval($params['max_batches'])) : 25;
        
        error_log("nce_task_process_cached_profiles: Starting v" . NCE_TASK_8_VERSION . " (Job: {$jobName}, Start: {$startFrom}, Max: {$maxBatches})");
        
        // Initialize temp log file
        $logs_dir = ABSPATH . 'wp-content/wp-custom-scripts/logs/';
        if (!is_dir($logs_dir)) { @mkdir($logs_dir, 0755, true); }
        $temp_log = $logs_dir . 'task8_process_cached_' . date('Y-m-d_H-i-s') . '.log';
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] PROCESS CACHED PROFILES - Job: {$jobName}\n");
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Script version: " . NCE_TASK_8_VERSION . " (updated: " . NCE_TASK_8_UPDATED . ")\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch range: Starting from batch {$startFrom}, max {$maxBatches} batches this run\n", FILE_APPEND);
        
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
                'version' => NCE_TASK_8_VERSION,
                'job_name' => $jobName
            ];
        }
        
        $apiKey = trim((string)($g['api_key'] ?? ''));
        $apiVersion = trim((string)($g['api_version'] ?? '2025-10-15'));
        $controlParam = trim((string)($g['control_param'] ?? ''));
        
        if ($apiKey === '') {
            return [
                'error' => 'Missing api_key in configuration',
                'version' => NCE_TASK_8_VERSION,
                'job_name' => $jobName
            ];
        }
        
        if ($controlParam === '') {
            return [
                'error' => 'No cached profiles found. Run Task 7 first to fetch and cache profiles.',
                'version' => NCE_TASK_8_VERSION,
                'job_name' => $jobName,
                'next_step' => '?task=7'
            ];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n", FILE_APPEND);
        
        // --- 2. Parse cached profiles ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Reading cached profiles from database...\n", FILE_APPEND);
        
        $cacheData = json_decode($controlParam, true);
        if (!is_array($cacheData) || !isset($cacheData['profiles']) || !is_array($cacheData['profiles'])) {
            return [
                'error' => 'Invalid cache format in control_param. Run Task 7 to rebuild cache.',
                'version' => NCE_TASK_8_VERSION,
                'job_name' => $jobName
            ];
        }
        
        $cachedProfiles = $cacheData['profiles'];
        $cachedAt = $cacheData['cached_at'] ?? 'unknown';
        $totalCached = count($cachedProfiles);
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Found {$totalCached} cached profiles (cached at: {$cachedAt})\n", FILE_APPEND);
        
        if ($totalCached === 0) {
            return [
                'error' => 'No profiles in cache. Run Task 7 to fetch and cache profiles.',
                'version' => NCE_TASK_8_VERSION,
                'job_name' => $jobName,
                'next_step' => '?task=7'
            ];
        }
        
        // --- 3. Batch and send to Klaviyo ---
        $klaviyoBatchSize = 100;
        $batches = array_chunk($cachedProfiles, $klaviyoBatchSize);
        $totalBatches = count($batches);
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Total Klaviyo batches available: {$totalBatches}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Will process batches {$startFrom} to " . min($startFrom + $maxBatches - 1, $totalBatches) . " (max {$maxBatches} this run)\n", FILE_APPEND);
        
        $grantedCount = 0;
        $failedBatches = 0;
        $errors = [];
        $batchesProcessed = 0;
        $batchesSkipped = 0;
        
        foreach ($batches as $batchIndex => $batchProfiles) {
            $batchNum = $batchIndex + 1;
            
            // Skip batches before start_from
            if ($batchNum < $startFrom) {
                $batchesSkipped++;
                continue;
            }
            
            // Stop if we've processed max_batches
            if ($batchesProcessed >= $maxBatches) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Reached max_batches limit ({$maxBatches}), stopping\n", FILE_APPEND);
                break;
            }
            
            $batchCount = count($batchProfiles);
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} profiles)...\n", FILE_APPEND);
            error_log("nce_task_process_cached_profiles: Klaviyo batch {$batchNum}/{$totalBatches}");
            
            // Deduplicate and validate phone numbers within this batch
            $uniquePhonesInBatch = [];
            $invalidCount = 0;
            foreach ($batchProfiles as $profile) {
                $phone = trim($profile['phone_number']);
                
                // Skip if already seen in this batch
                if (isset($uniquePhonesInBatch[$phone])) {
                    continue;
                }
                
                // Validate US phone format: +1 followed by 10 digits
                if (!preg_match('/^\+1\d{10}$/', $phone)) {
                    $invalidCount++;
                    continue;
                }
                
                // Mark as seen
                $uniquePhonesInBatch[$phone] = true;
            }
            
            if ($invalidCount > 0) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Filtered out {$invalidCount} invalid phone numbers from batch {$batchNum}\n", FILE_APPEND);
            }
            
            // Build profiles array for Klaviyo subscription endpoint (deduplicated)
            $profilesData = [];
            foreach (array_keys($uniquePhonesInBatch) as $phone) {
                $profilesData[] = [
                    'type' => 'profile',
                    'attributes' => [
                        'phone_number' => $phone,
                        'subscriptions' => [
                            'sms' => [
                                'marketing' => [
                                    'consent' => 'SUBSCRIBED'
                                ]
                            ]
                        ]
                    ]
                ];
            }
            
            $duplicatesInBatch = count($batchProfiles) - count($profilesData);
            if ($duplicatesInBatch > 0) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Removed {$duplicatesInBatch} duplicates from batch {$batchNum}\n", FILE_APPEND);
            }
            
            // Log sample from first batch
            if ($batchNum === $startFrom && !empty($profilesData)) {
                $samplePhones = array_slice(array_column(array_column($profilesData, 'attributes'), 'phone_number'), 0, 5);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample phones: " . implode(', ', $samplePhones) . "\n", FILE_APPEND);
            }
            
            // Build subscription job payload
            $payload = [
                'data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => $profilesData
                        ]
                    ]
                ]
            ];
            
            // Send to Klaviyo
            $url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs';
            $response = nce_klaviyo_subscription_request('POST', $url, $apiKey, $apiVersion, $payload);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $jobId = $response['body']['data']['id'] ?? 'unknown';
                $grantedCount += count($profilesData);
                $batchesProcessed++;
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n", FILE_APPEND);
                error_log("nce_task_process_cached_profiles: Batch {$batchNum} submitted (Job ID: {$jobId})");
            } else {
                $errorMsg = $response['error'] ?? 'Unknown error';
                
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}\n", FILE_APPEND);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Retrying batch {$batchNum} one phone at a time to identify bad number(s)...\n", FILE_APPEND);
                
                // Retry each phone individually
                $retriedSuccess = 0;
                $retriedFailed = 0;
                $badPhones = [];
                
                foreach ($profilesData as $index => $singleProfile) {
                    $phone = $singleProfile['attributes']['phone_number'];
                    
                    // Build single-phone payload
                    $singlePayload = [
                        'data' => [
                            'type' => 'profile-subscription-bulk-create-job',
                            'attributes' => [
                                'profiles' => [
                                    'data' => [$singleProfile]
                                ]
                            ]
                        ]
                    ];
                    
                    // Send single phone
                    $singleResponse = nce_klaviyo_subscription_request('POST', $url, $apiKey, $apiVersion, $singlePayload);
                    
                    if ($singleResponse['http'] >= 200 && $singleResponse['http'] < 300) {
                        $retriedSuccess++;
                        $grantedCount++;
                    } else {
                        $retriedFailed++;
                        $badPhones[] = $phone;
                        $singleError = $singleResponse['error'] ?? 'Unknown error';
                        file_put_contents($temp_log, "[" . date('H:i:s') . "]   ✗ Failed: {$phone} - {$singleError}\n", FILE_APPEND);
                    }
                    
                    // Rate limit between individual retries
                    usleep(100000); // 0.1 seconds
                }
                
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Retry complete: {$retriedSuccess} succeeded, {$retriedFailed} failed\n", FILE_APPEND);
                
                if ($retriedFailed > 0) {
                    $failedBatches++;
                    $errorDetail = [
                        'batch' => $batchNum,
                        'http_status' => $response['http'],
                        'error' => $errorMsg,
                        'total_in_batch' => count($profilesData),
                        'retry_succeeded' => $retriedSuccess,
                        'retry_failed' => $retriedFailed,
                        'bad_phones' => $badPhones
                    ];
                    $errors[] = $errorDetail;
                }
                
                $batchesProcessed++;
                error_log("nce_task_process_cached_profiles: Batch {$batchNum} retry complete - {$retriedSuccess} ok, {$retriedFailed} failed");
            }
            
            // Rate limiting: 10/s burst, 150/min steady
            usleep(500000); // 0.5 seconds between batches
        }
        
        // --- 4. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $remainingBatches = max(0, $totalBatches - $startFrom - $batchesProcessed + 1);
        $isComplete = ($remainingBatches === 0);
        
        $completionMsg = "\n[" . date('H:i:s') . "] === SMS CONSENT PROCESSING RUN COMPLETE ===\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total cached profiles: {$totalCached}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Cached at: {$cachedAt}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total Klaviyo batches available: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches skipped (before start_from): {$batchesSkipped}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches processed this run: {$batchesProcessed}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Consent granted: {$grantedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        if (!$isComplete) {
            $nextStartFrom = $startFrom + $batchesProcessed;
            $completionMsg .= "[" . date('H:i:s') . "] ⚠️  INCOMPLETE: {$remainingBatches} batches remaining\n";
            $completionMsg .= "[" . date('H:i:s') . "] Next run: ?task=8&start_from={$nextStartFrom}\n";
        } else {
            $completionMsg .= "[" . date('H:i:s') . "] ✅ ALL BATCHES COMPLETE!\n";
        }
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_process_cached_profiles: Run complete - {$grantedCount} granted, {$failedBatches} failed");
        
        $result = [
            'success' => true,
            'message' => $isComplete ? 'SMS consent backfill COMPLETE - all batches processed!' : "SMS consent run complete - {$remainingBatches} batches remaining",
            'version' => NCE_TASK_8_VERSION,
            'updated' => NCE_TASK_8_UPDATED,
            'job_name' => $jobName,
            'start_from' => $startFrom,
            'max_batches' => $maxBatches,
            'total_cached_profiles' => $totalCached,
            'cached_at' => $cachedAt,
            'total_batches_available' => $totalBatches,
            'batches_skipped' => $batchesSkipped,
            'batches_processed_this_run' => $batchesProcessed,
            'profiles_granted' => $grantedCount,
            'failed_batches' => $failedBatches,
            'duration_seconds' => $duration,
            'complete' => $isComplete
        ];
        
        if (!$isComplete) {
            $result['next_run'] = "?task=8&start_from=" . ($startFrom + $batchesProcessed);
        }
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
}

/**
 * Make a Klaviyo subscription API request (bulk create/delete jobs)
 * 
 * @param string $method HTTP method (POST, DELETE)
 * @param string $url Full API endpoint URL
 * @param string $apiKey Klaviyo private API key
 * @param string $apiVersion API revision
 * @param array $payload Request body data
 * @return array Response with 'http', 'body', 'error'
 */
if (!function_exists('nce_klaviyo_subscription_request')) {
    function nce_klaviyo_subscription_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload): array {
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => "Klaviyo-API-Key {$apiKey}",
                'Content-Type'  => 'application/vnd.api+json',
                'Accept'        => 'application/vnd.api+json',
                'revision'      => $apiVersion,
            ],
            'body'    => json_encode($payload),
            'timeout' => 30,
        ];
        
        $res = wp_remote_request($url, $args);
        $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
        $body = is_wp_error($res) ? null : json_decode($rawBody, true);
        
        $result = [
            'http' => $http,
            'body' => $body
        ];
        
        if (is_wp_error($res)) {
            $result['error'] = $res->get_error_message();
        } elseif ($http < 200 || $http >= 300) {
            $result['error'] = !empty($body['errors']) ? ($body['errors'][0]['detail'] ?? 'Unknown error') : 'HTTP error';
        }
        
        return $result;
    }
}

