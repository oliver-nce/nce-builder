<?php
// LAST UPDATED: 2025-11-28 18:10:00
// v2.2.0 - 2025-11-28 (Deduplicate each batch before write)
declare(strict_types=1);

// Version constant for tracking deployed code
define('NCE_TASK_7_VERSION', '2.2.0');
define('NCE_TASK_7_UPDATED', '2025-11-28 18:10:00');

/**
 * Backfill SMS Marketing Consent for ALL Profiles - Task 7
 * ---
 * ONE-TIME BACKFILL: Finds ALL profiles with phone numbers,
 * then grants SMS marketing consent to all of them.
 * 
 * ⚠️ LEGAL WARNING: Only run this if ALL your users with phone numbers
 * have provided explicit SMS opt-in consent. This is a BULK operation.
 *  
 * - Fetches ALL profiles with phone_number 
 * - Processes in chunks to avoid timeouts 
 * - Uses max_profiles parameter to limit scope per run
 * - Uses profile-subscription-bulk-create-jobs endpoint
 * - Creates new consent events (allows re-subscribing previously unsubscribed profiles)
 * 
 * @param array $params Parameters from REST request:
 *                      - dry_run (optional): if true, only counts profiles without granting consent
 *                      - job_name (optional): defaults to 'default' (only for API key/version lookup)
 * @return array Summary with consent grant results
 * 
 * Note: This task fetches ALL profiles at once, then batches the Klaviyo writes.
 * Validates US phone numbers only (+1XXXXXXXXXX format).
 */
if (!function_exists('nce_task_backfill_sms_consent')) {
    function nce_task_backfill_sms_consent(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $dryRun = isset($params['dry_run']) && ($params['dry_run'] === 'true' || $params['dry_run'] === true);
        
        error_log("nce_task_backfill_sms_consent: Starting v" . NCE_TASK_7_VERSION . " (Job: {$jobName}, Dry Run: " . ($dryRun ? 'YES' : 'NO') . ")");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] BACKFILL SMS CONSENT (ALL PROFILES) - Job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Script version: " . NCE_TASK_7_VERSION . " (updated: " . NCE_TASK_7_UPDATED . ")\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Mode: Fetch ALL profiles, batch writes to Klaviyo\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] US phone validation: +1XXXXXXXXXX format only\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Dry run mode: " . ($dryRun ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
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
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName
            ];
        }
        
        $apiKey = trim((string)($g['api_key'] ?? ''));
        $apiVersion = trim((string)($g['api_version'] ?? '2025-10-15'));
        
        if ($apiKey === '') {
            return [
                'error' => 'Missing api_key in configuration',
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName
            ];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n", FILE_APPEND);
        
        // --- 2. Fetch ALL profiles at once ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching ALL profiles from Klaviyo (no limit)...\n", FILE_APPEND);
        
        $profilesResult = nce_fetch_all_profiles_with_phones($apiKey, $apiVersion, $temp_log);
        
        if (isset($profilesResult['error'])) {
            return [
                'error' => $profilesResult['error'],
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName
            ];
        }
        
        $allProfiles = $profilesResult['profiles'];
        $totalFetched = count($allProfiles);
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetched {$totalFetched} total profiles from Klaviyo\n", FILE_APPEND);
        error_log("nce_task_backfill_sms_consent: Fetched {$totalFetched} profiles");
        
        // --- 3. Filter for valid US phone numbers ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Filtering for valid US phone numbers...\n", FILE_APPEND);
        
        $validUSProfiles = array_filter($allProfiles, function($profile) {
            $phone = !empty($profile['phone_number']) ? trim($profile['phone_number']) : null;
            if (!$phone) return false;
            
            // Basic validation
            $invalidPatterns = ['null', 'none', 'n/a', 'na', 'test', '(null)', 'undefined'];
            if (in_array(strtolower($phone), $invalidPatterns)) {
                return false;
            }
            
            // US phone number validation
            // Must start with +1 and have 11 digits total
            if (!preg_match('/^\+1\d{10}$/', $phone)) {
                return false;
            }
            
            return true;
        });
        
        $totalValidUS = count($validUSProfiles);
        $totalInvalid = $totalFetched - $totalValidUS;
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] {$totalValidUS} profiles have valid US phone numbers\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] {$totalInvalid} profiles filtered out (invalid/non-US)\n", FILE_APPEND);
        
        if ($totalValidUS === 0) {
            return [
                'success' => false,
                'error' => 'No profiles with valid US phone numbers found',
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName,
                'total_fetched' => $totalFetched,
                'valid_us_phones' => 0
            ];
        }
        
        // --- 4. Save to database control_param ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Saving {$totalValidUS} profiles to database cache...\n", FILE_APPEND);
        
        // Build cache data
        $cacheData = [
            'profiles' => array_map(function($p) {
                return ['phone_number' => $p['phone_number']];
            }, $validUSProfiles),
            'cached_at' => date('Y-m-d H:i:s'),
            'count' => $totalValidUS,
            'task_version' => NCE_TASK_7_VERSION
        ];
        
        $cacheJson = json_encode($cacheData);
        
        // Update control_param in wp_klaviyo_globals
        $table = $wpdb->prefix . 'klaviyo_globals';
        $updated = $wpdb->update(
            $table,
            ['control_param' => $cacheJson],
            ['job_name' => $jobName],
            ['%s'],
            ['%s']
        );
        
        if ($updated === false) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: Failed to save cache to database\n", FILE_APPEND);
            return [
                'success' => false,
                'error' => 'Failed to save profiles to database cache',
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName
            ];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] ✅ Successfully cached {$totalValidUS} profiles\n", FILE_APPEND);
        
        // --- 5. Batch and send to Klaviyo ---
        $klaviyoBatchSize = 100; // Klaviyo recommendation for subscription endpoints
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing profiles in Klaviyo batches of {$klaviyoBatchSize}...\n", FILE_APPEND);
        $batches = array_chunk($validUSProfiles, $klaviyoBatchSize);
        $totalBatches = count($batches);
        $grantedCount = 0;
        $failedBatches = 0;
        $errors = [];
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Total Klaviyo batches: {$totalBatches}\n", FILE_APPEND);
        
        foreach ($batches as $batchIndex => $batchProfiles) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchProfiles);
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} profiles)...\n", FILE_APPEND);
            error_log("nce_task_backfill_sms_consent: Klaviyo batch {$batchNum}/{$totalBatches}");
            
            // Deduplicate phone numbers within this batch
            $uniquePhonesInBatch = [];
            foreach ($batchProfiles as $profile) {
                $phone = trim($profile['phone_number']);
                
                // Skip if already seen in this batch
                if (isset($uniquePhonesInBatch[$phone])) {
                    continue;
                }
                
                // Mark as seen
                $uniquePhonesInBatch[$phone] = true;
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
            if ($batchNum === 1 && !empty($profilesData)) {
                $samplePhones = array_slice(array_column(array_column($profilesData, 'attributes'), 'phone_number'), 0, 5);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample phones: " . implode(', ', $samplePhones) . "\n", FILE_APPEND);
                
                // Log first profile's complete structure for debugging
                $firstProfile = json_encode($profilesData[0], JSON_PRETTY_PRINT);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] First profile structure:\n{$firstProfile}\n", FILE_APPEND);
            }
            
            // Build subscription job payload
            $payload = [
                'data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => $profilesData
                        ]
                        // NOT using historical_import - creates NEW consent event
                        // This allows re-subscribing profiles that previously unsubscribed
                    ]
                ]
            ];
            
            // Send to Klaviyo
            $url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs';
            $response = nce_klaviyo_subscription_request('POST', $url, $apiKey, $apiVersion, $payload);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $jobId = $response['body']['data']['id'] ?? 'unknown';
                $grantedCount += count($profilesData);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n", FILE_APPEND);
                error_log("nce_task_backfill_sms_consent: Batch {$batchNum} submitted (Job ID: {$jobId})");
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
                    'profile_count' => count($profilesData)
                ];
                $errors[] = $errorDetail;
                error_log("nce_task_backfill_sms_consent: Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}");
            }
            
            // Rate limiting: 10/s burst, 150/min steady
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds between batches
            }
        }
        
        // --- 6. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "\n[" . date('H:i:s') . "] === SMS CONSENT BACKFILL COMPLETE ===\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total profiles fetched: {$totalFetched}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Valid US phones: {$totalValidUS}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Filtered out (invalid/non-US): {$totalInvalid}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Klaviyo batches: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Consent granted: {$grantedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_backfill_sms_consent: Complete - {$grantedCount} granted, {$failedBatches} failed");
        
        $result = [
            'success' => true,
            'message' => 'SMS consent backfill completed - processed ALL profiles',
            'version' => NCE_TASK_7_VERSION,
            'updated' => NCE_TASK_7_UPDATED,
            'job_name' => $jobName,
            'total_fetched' => $totalFetched,
            'valid_us_phones' => $totalValidUS,
            'invalid_filtered' => $totalInvalid,
            'total_granted' => $grantedCount,
            'klaviyo_batches_processed' => $totalBatches,
            'failed_batches' => $failedBatches,
            'duration_seconds' => $duration,
            'completed' => true
        ];
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
}

/**
 * Fetch ALL profiles with phone numbers from Klaviyo
 * 
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param string $temp_log Path to log file
 * @return array Array with 'profiles' or 'error'
 */
if (!function_exists('nce_fetch_all_profiles_with_phones')) {
    function nce_fetch_all_profiles_with_phones(string $apiKey, string $apiVersion, string $temp_log): array {
        $allProfiles = [];
        $pageUrl = 'https://a.klaviyo.com/api/profiles';
        $pageCount = 0;
        $maxPages = 100; // Allow up to 10K profiles (100 pages * 100 per page)
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching ALL profiles from Klaviyo (no limit)...\n", FILE_APPEND);
        
        while ($pageUrl && $pageCount < $maxPages) {
            $pageCount++;
            
            // Build URL - only request phone_number and created fields
            if ($pageCount === 1) {
                $queryParams = http_build_query([
                    'fields[profile]' => 'phone_number,created',
                    'page[size]' => 100
                ]);
                $fullUrl = $pageUrl . '?' . $queryParams;
            } else {
                $fullUrl = $pageUrl;
            }
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching page {$pageCount}...\n", FILE_APPEND);
            
            $args = [
                'method'  => 'GET',
                'headers' => [
                    'Authorization' => "Klaviyo-API-Key {$apiKey}",
                    'Accept'        => 'application/vnd.api+json',
                    'revision'      => $apiVersion,
                ],
                'timeout' => 30,
            ];
            
            $res = wp_remote_request($fullUrl, $args);
            $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
            $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
            $body = is_wp_error($res) ? null : json_decode($rawBody, true);
            
            if ($http < 200 || $http >= 300) {
                $errorMsg = 'Failed to fetch profiles from Klaviyo';
                if (is_wp_error($res)) {
                    $errorMsg .= ': ' . $res->get_error_message();
                } elseif (!empty($body['errors'])) {
                    $errorMsg .= ': ' . ($body['errors'][0]['detail'] ?? 'Unknown error');
                }
                
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n", FILE_APPEND);
                return ['error' => $errorMsg];
            }
            
            // Extract profiles from this page - collect all with phone_number
            if (!empty($body['data'])) {
                foreach ($body['data'] as $profile) {
                    $phone = $profile['attributes']['phone_number'] ?? null;
                    
                    // Only add profiles WITH phone_number
                    if (!empty($phone) && trim($phone) !== '') {
                        $allProfiles[] = [
                            'phone_number' => $phone,
                            'created_at' => $profile['attributes']['created'] ?? null
                        ];
                    }
                }
                
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Page {$pageCount}: " . count($body['data']) . " profiles (" . count($allProfiles) . " with phone so far)\n", FILE_APPEND);
            }
            
            // Check for next page
            $pageUrl = $body['links']['next'] ?? null;
            
            if (!$pageUrl) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] No more pages, fetch complete\n", FILE_APPEND);
                break;
            }
            
            // Rate limiting
            usleep(200000); // 0.2 seconds between pages
        }
        
        if ($pageCount >= $maxPages) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] WARNING: Reached max page limit ({$maxPages}), there may be more profiles\n", FILE_APPEND);
        }
        
        $totalCount = count($allProfiles);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetch complete: {$totalCount} profiles with phone_number ({$pageCount} pages)\n", FILE_APPEND);
        
        return [
            'profiles' => $allProfiles,
            'count' => $totalCount,
            'pages' => $pageCount
        ];
    }
}

/**
 * Make Klaviyo subscription API request
 * 
 * @param string $method HTTP method
 * @param string $url Full URL
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param array $payload Request body
 * @return array Response with http code, body, error
 */
if (!function_exists('nce_klaviyo_subscription_request')) {
    function nce_klaviyo_subscription_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
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

