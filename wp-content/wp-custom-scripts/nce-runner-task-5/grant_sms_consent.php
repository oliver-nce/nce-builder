<?php
// LAST UPDATED: 2025-12-15
// v2.2.0 - Skip profiles already subscribed to SMS, add skip_log_clear param, timestamped logs
declare(strict_types=1);

// Load logging helper
require_once __DIR__ . '/../includes/nce_logging_helper.php';

/**
 * Grant SMS Marketing Consent for NEW Profiles - Task 5
 * ---
 * Grants SMS marketing consent ONLY for recently created profiles.
 * Designed to run after bulk_upsert_profiles.php for new signups.
 * 
 * ⚠️ LEGAL WARNING: Only run this for profiles with EXPLICIT SMS opt-in.
 * T&C acceptance alone is NOT sufficient for SMS consent under TCPA/CTIA.
 * 
 * - Uses lookback_hours parameter to find recent profiles
 * - Fetches profiles from same query as Task 3
 * - Filters to profiles created within lookback window 
 * - Filters to profiles with valid phone numbers only
 * - Batches up to 100 profiles per request
 * - Uses profile-subscription-bulk-create-jobs endpoint with SMS channel
 * - Sets historical_import: true
 * 
 * Note: Assumes existing profiles already have correct consent settings.
 * Only applies consent to NEW profiles created within lookback period.
 * 
 * SMS consent is required for ALL SMS messages (transactional and marketing).
 * Unlike email, transactional SMS does NOT bypass consent requirements.
 * 
 * @param array $params Parameters from REST request:
 *                      - lookback_hours (optional): defaults to 2 hours
 *                      - job_name (optional): defaults to 'default' (only for API key/version lookup)
 * @return array Summary with consent grant results   
 */
if (!function_exists('nce_task_grant_sms_consent')) {
    function nce_task_grant_sms_consent(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $lookbackHours = isset($params['lookback_hours']) ? (int)$params['lookback_hours'] : 2;
        $skipLogClear = !empty($params['skip_log_clear']); // Don't clear log when called from full sync
         
        // Validate lookback hours
        if ($lookbackHours < 1) {
            return [
                'error' => 'lookback_hours must be at least 1',
                'provided_value' => $lookbackHours
            ];
        }
        
        error_log("nce_task_grant_sms_consent: Starting (Job: {$jobName}, Lookback: {$lookbackHours}h)");
        
        // Initialize log file with timestamp using helper function
        $temp_log = nce_init_log_file('task5_grant_sms_consent');
        nce_write_log($temp_log, "[" . date('Y-m-d H:i:s') . "] GRANT SMS CONSENT (NEW PROFILES) - Job: {$jobName}\n");
        nce_write_log($temp_log, "[" . date('H:i:s') . "] ⚠️  SMS CONSENT: Only run for profiles with explicit SMS opt-in\n");
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Lookback window: {$lookbackHours} hours\n");
        
        global $wpdb;
        $startTime = microtime(true);
        
        // Calculate cutoff datetime
        $cutoffDatetime = date('Y-m-d H:i:s', strtotime("-{$lookbackHours} hours"));
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Cutoff datetime: {$cutoffDatetime}\n");
        
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
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n");
        nce_write_log($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n");
        
        // --- 2. Fetch NEW profiles from Klaviyo API (created within lookback window) ---
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Fetching profiles created after {$cutoffDatetime} from Klaviyo API...\n");
        
        $recentProfiles = nce_fetch_recent_profiles_for_sms($apiKey, $apiVersion, $cutoffDatetime, $temp_log);
        
        if (isset($recentProfiles['error'])) {
            return [
                'error' => $recentProfiles['error'],
                'job_name' => $jobName
            ];
        }
        
        $newProfiles = $recentProfiles['profiles'];
        $totalNew = count($newProfiles);
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Found {$totalNew} NEW profiles within lookback window\n");
        error_log("nce_task_grant_sms_consent: Found {$totalNew} new profiles from Klaviyo");
        
        // Filter to only profiles with valid phone numbers AND not already SMS subscribed
        $alreadySubscribed = 0;
        $phoneProfiles = array_filter($newProfiles, function($profile) use (&$alreadySubscribed) {
            $phone = !empty($profile['phone_number']) ? trim($profile['phone_number']) : null;
            if (!$phone) return false;
            
            // Phone must start with + and be 11-16 digits (E.164 format)
            if (!preg_match('/^\+\d{10,15}$/', $phone)) {
                return false;
            }
            
            // Skip if already has SMS marketing consent
            $smsStatus = $profile['subscriptions']['sms']['marketing']['consent'] ?? null;
            if ($smsStatus === 'SUBSCRIBED') {
                $alreadySubscribed++;
                return false;
            }
            
            return true;
        });
        
        $totalWithPhone = count($phoneProfiles);
        nce_write_log($temp_log, "[" . date('H:i:s') . "] {$totalWithPhone} new profiles have valid phone numbers (need consent)\n");
        if ($alreadySubscribed > 0) {
            nce_write_log($temp_log, "[" . date('H:i:s') . "] {$alreadySubscribed} profiles already have SMS consent (skipped)\n");
        }
        
        if ($totalWithPhone === 0) {
            return [
                'success' => true,
                'message' => 'No new profiles with phone to grant consent',
                'job_name' => $jobName,
                'lookback_hours' => $lookbackHours,
                'total_fetched' => $totalFetched,
                'new_profiles' => $totalNew,
                'granted' => 0
            ];
        }
        
        // --- 3. Batch and send to Klaviyo ---
        $batchSize = 100; // Klaviyo recommendation for subscription endpoints
        $batches = array_chunk($phoneProfiles, $batchSize);
        $totalBatches = count($batches);
        $grantedCount = 0;
        $failedBatches = 0;
        $errors = [];
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize} profiles each\n");
        
        foreach ($batches as $batchIndex => $batchProfiles) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchProfiles);
            
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} profiles)...\n");
            error_log("nce_task_grant_sms_consent: Processing batch {$batchNum}/{$totalBatches}");
            
            // Build profiles array for Klaviyo subscription endpoint
            $profilesData = [];
            foreach ($batchProfiles as $profile) {
                $phone = trim($profile['phone_number']);
                
                // Use profile's creation timestamp as consent timestamp
                $createdTimestamp = !empty($profile['created_at']) ? strtotime($profile['created_at']) : false;
                
                // If valid timestamp, use it; otherwise use current time
                if ($createdTimestamp !== false && $createdTimestamp > 0) {
                    $consentedAt = date('c', $createdTimestamp);  // ISO 8601 format
                } else {
                    $consentedAt = date('c');  // Current time as fallback
                }
                
                $profilesData[] = [
                    'type' => 'profile',
                    'attributes' => [
                        'phone_number' => $phone,
                        'subscriptions' => [
                            'sms' => [
                                'marketing' => [
                                    'consent' => 'SUBSCRIBED',
                                    'consented_at' => $consentedAt
                                ]
                            ]
                        ]
                    ]
                ];
            }
            
            // Log sample from first batch
            if ($batchNum === 1 && !empty($profilesData)) {
                $samplePhones = array_slice(array_column(array_column($profilesData, 'attributes'), 'phone_number'), 0, 5);
                nce_write_log($temp_log, "[" . date('H:i:s') . "] Sample phones: " . implode(', ', $samplePhones) . "\n");
            }
            
            // Build subscription job payload (SMS channel)
            $payload = [
                'data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => $profilesData
                        ],
                        'historical_import' => true
                    ]
                ]
            ];
            
            // Send to Klaviyo
            $url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs';
            $response = nce_klaviyo_sms_subscription_request('POST', $url, $apiKey, $apiVersion, $payload);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $jobId = $response['body']['data']['id'] ?? 'unknown';
                $grantedCount += count($profilesData);
                nce_write_log($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n");
                error_log("nce_task_grant_sms_consent: Batch {$batchNum} submitted (Job ID: {$jobId})");
            } else {
                $failedBatches++;
                $errorMsg = $response['error'] ?? 'Unknown error';
                
                nce_write_log($temp_log, "[" . date('H:i:s') . "] ✗ Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}\n");
                
                if (!empty($response['body'])) {
                    $fullError = json_encode($response['body'], JSON_PRETTY_PRINT);
                    nce_write_log($temp_log, "[" . date('H:i:s') . "] Klaviyo error body:\n{$fullError}\n");
                }
                
                $errorDetail = [
                    'batch' => $batchNum,
                    'http_status' => $response['http'],
                    'error' => $errorMsg,
                    'profile_count' => count($profilesData)
                ];
                $errors[] = $errorDetail;
                error_log("nce_task_grant_sms_consent: Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}");
            }
            
            // Rate limiting: 10/s burst, 150/min steady
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds between batches
            }
        }
        
        // --- 4. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "[" . date('H:i:s') . "] --- SMS CONSENT GRANT COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Lookback window: {$lookbackHours} hours\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total profiles fetched: {$totalFetched}\n";
        $completionMsg .= "[" . date('H:i:s') . "] New profiles found: {$totalNew}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Already SMS subscribed: {$alreadySubscribed}\n";
        $completionMsg .= "[" . date('H:i:s') . "] With valid phone (need consent): {$totalWithPhone}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches processed: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Consent granted: {$grantedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        nce_write_log($temp_log, $completionMsg);
        error_log("nce_task_grant_sms_consent: Complete - Granted: {$grantedCount}, Already subscribed: {$alreadySubscribed}, Failed: {$failedBatches}");
        
        $result = [
            'success' => true,
            'message' => 'SMS consent grant completed for new profiles',
            'job_name' => $jobName,
            'lookback_hours' => $lookbackHours,
            'cutoff_datetime' => $cutoffDatetime,
            'total_fetched' => $totalFetched,
            'new_profiles_found' => $totalNew,
            'already_subscribed' => $alreadySubscribed,
            'profiles_with_phone' => $totalWithPhone,
            'granted' => $grantedCount,
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
 * Fetch recent profiles from Klaviyo API (for SMS consent)
 * 
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param string $cutoffDatetime Cutoff datetime (Y-m-d H:i:s)
 * @param string $temp_log Path to log file
 * @return array Array with 'profiles' or 'error'
 */
if (!function_exists('nce_fetch_recent_profiles_for_sms')) {
    function nce_fetch_recent_profiles_for_sms(string $apiKey, string $apiVersion, string $cutoffDatetime, string $temp_log): array {
        $allProfiles = [];
        $pageUrl = 'https://a.klaviyo.com/api/profiles';
        $pageCount = 0;
        $maxPages = 50; // Safety limit
        
        // Convert cutoff to ISO 8601 format for Klaviyo filter
        $cutoffISO = date('c', strtotime($cutoffDatetime));
        
        // Filter for profiles created after cutoff
        $filter = "greater-than(created,{$cutoffISO})";
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Fetching profiles created after {$cutoffDatetime}...\n");
        
        while ($pageUrl && $pageCount < $maxPages) {
            $pageCount++;
            
            // Build URL with filter on first page
            if ($pageCount === 1) {
                $queryParams = http_build_query([
                    'filter' => $filter,
                    'fields[profile]' => 'email,phone_number,created',
                    'additional-fields[profile]' => 'subscriptions',
                    'page[size]' => 100
                ]);
                $fullUrl = $pageUrl . '?' . $queryParams;
                
                // DEBUG: Log the filter and full URL
                nce_write_log($temp_log, "[" . date('H:i:s') . "] DEBUG Filter: {$filter}\n");
                nce_write_log($temp_log, "[" . date('H:i:s') . "] DEBUG Full URL: {$fullUrl}\n");
            } else {
                $fullUrl = $pageUrl;
            }
            
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Fetching page {$pageCount}...\n");
            
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
                
                nce_write_log($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n");
                return ['error' => $errorMsg];
            }
            
            // Extract profiles from this page
            if (!empty($body['data'])) {
                // DEBUG: Log sample phone numbers from first page
                if ($pageCount === 1) {
                    $samplePhones = [];
                    $sampleCreated = [];
                    foreach (array_slice($body['data'], 0, 5) as $p) {
                        $samplePhones[] = $p['attributes']['phone_number'] ?? '(null)';
                        $sampleCreated[] = $p['attributes']['created'] ?? '(null)';
                    }
                    nce_write_log($temp_log, "[" . date('H:i:s') . "] DEBUG Sample phones: " . implode(', ', $samplePhones) . "\n");
                    nce_write_log($temp_log, "[" . date('H:i:s') . "] DEBUG Sample created dates: " . implode(', ', $sampleCreated) . "\n");
                }
                
                foreach ($body['data'] as $profile) {
                    $allProfiles[] = [
                        'email' => $profile['attributes']['email'] ?? null,
                        'phone_number' => $profile['attributes']['phone_number'] ?? null,
                        'created_at' => $profile['attributes']['created'] ?? null,
                        'subscriptions' => $profile['attributes']['subscriptions'] ?? null
                    ];
                }
                
                $profilesOnPage = count($body['data']);
                nce_write_log($temp_log, "[" . date('H:i:s') . "] Page {$pageCount}: {$profilesOnPage} profiles\n");
            }
            
            // Check for next page
            $pageUrl = $body['links']['next'] ?? null;
            
            if (!$pageUrl) {
                nce_write_log($temp_log, "[" . date('H:i:s') . "] No more pages, fetch complete\n");
                break;
            }
            
            // Rate limiting
            usleep(200000); // 0.2 seconds between pages
        }
        
        if ($pageCount >= $maxPages) {
            nce_write_log($temp_log, "[" . date('H:i:s') . "] WARNING: Reached max page limit ({$maxPages}), there may be more profiles\n");
        }
        
        $totalCount = count($allProfiles);
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Total recent profiles fetched: {$totalCount}\n");
        
        return [
            'profiles' => $allProfiles,
            'count' => $totalCount,
            'pages' => $pageCount
        ];
    }
}

/**
 * Make Klaviyo subscription API request (SMS-specific)
 * 
 * @param string $method HTTP method
 * @param string $url Full URL
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param array $payload Request body
 * @return array Response with http code, body, error
 */
if (!function_exists('nce_klaviyo_sms_subscription_request')) {
    function nce_klaviyo_sms_subscription_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
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

