<?php
// LAST UPDATED: 2025-11-28 16:30:00
// v2.0.0 - 2025-11-28
declare(strict_types=1);

/**
 * Grant Email Marketing Consent for NEW Profiles - Task 4
 * ---
 * Grants email marketing consent ONLY for recently created profiles.
 * Designed to run after bulk_upsert_profiles.php for new signups.
 * 
 * - Uses lookback_hours parameter to find recent profiles
 * - Fetches profiles from same query as Task 3
 * - Filters to profiles created within lookback window
 * - Batches up to 100 profiles per request
 * - Uses profile-subscription-bulk-create-jobs endpoint
 * - Sets historical_import: true to avoid confirmation emails
 * 
 * Note: Assumes existing profiles already have correct consent settings.
 * Only applies consent to NEW profiles created within lookback period.
 * 
 * @param array $params Parameters from REST request:
 *                      - lookback_hours (optional): defaults to 2 hours
 *                      - job_name (optional): defaults to 'default' (only for API key/version lookup)
 * @return array Summary with consent grant results   
 */
if (!function_exists('nce_task_grant_email_consent')) {
    function nce_task_grant_email_consent(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $lookbackHours = isset($params['lookback_hours']) ? (int)$params['lookback_hours'] : 2;
        
        // Validate lookback hours
        if ($lookbackHours < 1) {
            return [
                'error' => 'lookback_hours must be at least 1',
                'provided_value' => $lookbackHours
            ]; 
        }
        
        error_log("nce_task_grant_email_consent: Starting (Job: {$jobName}, Lookback: {$lookbackHours}h)");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] GRANT EMAIL CONSENT (NEW PROFILES) - Job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Lookback window: {$lookbackHours} hours\n", FILE_APPEND);
        
        global $wpdb;
        $startTime = microtime(true);
        
        // Calculate cutoff datetime
        $cutoffDatetime = date('Y-m-d H:i:s', strtotime("-{$lookbackHours} hours"));
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Cutoff datetime: {$cutoffDatetime}\n", FILE_APPEND);
        
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
        
        // --- 2. Fetch NEW profiles from Klaviyo API (created within lookback window) ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching profiles created after {$cutoffDatetime} from Klaviyo API...\n", FILE_APPEND);
        
        $recentProfiles = nce_fetch_recent_profiles_from_klaviyo($apiKey, $apiVersion, $cutoffDatetime, $temp_log);
        
        if (isset($recentProfiles['error'])) {
            return [
                'error' => $recentProfiles['error'],
                'job_name' => $jobName
            ];
        }
        
        $newProfiles = $recentProfiles['profiles'];
        $totalNew = count($newProfiles);
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Found {$totalNew} NEW profiles within lookback window\n", FILE_APPEND);
        error_log("nce_task_grant_email_consent: Found {$totalNew} new profiles from Klaviyo");
        
        // Filter to only profiles with valid email
        $emailProfiles = array_filter($newProfiles, function($profile) {
            $email = !empty($profile['email']) ? strtolower(trim($profile['email'])) : null;
            if (!$email) return false;
            
            // Basic email validation
            $invalidPatterns = ['null', 'none', 'n/a', 'na', 'test', '(null)', 'undefined', 'email'];
            if (in_array($email, $invalidPatterns) || strlen($email) < 3) {
                return false;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            
            return true;
        });
        
        $totalWithEmail = count($emailProfiles);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] {$totalWithEmail} new profiles have valid email addresses\n", FILE_APPEND);
        
        if ($totalWithEmail === 0) {
            return [
                'success' => true,
                'message' => 'No new profiles with email to grant consent',
                'job_name' => $jobName,
                'lookback_hours' => $lookbackHours,
                'total_fetched' => $totalFetched,
                'new_profiles' => $totalNew,
                'granted' => 0
            ];
        }
        
        // --- 3. Batch and send to Klaviyo ---
        $batchSize = 100; // Klaviyo recommendation for subscription endpoints
        $batches = array_chunk($emailProfiles, $batchSize);
        $totalBatches = count($batches);
        $grantedCount = 0;
        $failedBatches = 0;
        $errors = [];
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize} profiles each\n", FILE_APPEND);
        
        foreach ($batches as $batchIndex => $batchProfiles) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchProfiles);
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} profiles)...\n", FILE_APPEND);
            error_log("nce_task_grant_email_consent: Processing batch {$batchNum}/{$totalBatches}");
            
            // Build profiles array for Klaviyo subscription endpoint
            $profilesData = [];
            foreach ($batchProfiles as $profile) {
                $email = strtolower(trim($profile['email']));
                
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
                        'email' => $email,
                        'subscriptions' => [
                            'email' => [
                                'marketing' => [
                                    'consent' => 'SUBSCRIBED',
                                    'consented_at' => $consentedAt
                                ]
                            ]
                        ]
                    ]
                ];
            }
            
            // Log sample from first batch with full structure
            if ($batchNum === 1 && !empty($profilesData)) {
                $sampleEmails = array_slice(array_column(array_column($profilesData, 'attributes'), 'email'), 0, 5);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample emails: " . implode(', ', $sampleEmails) . "\n", FILE_APPEND);
                
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
                        ],
                        'historical_import' => true // Prevents confirmation emails
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
                error_log("nce_task_grant_email_consent: Batch {$batchNum} submitted (Job ID: {$jobId})");
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
                error_log("nce_task_grant_email_consent: Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}");
            }
            
            // Rate limiting: 10/s burst, 150/min steady
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds between batches
            }
        }
        
        // --- 4. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "[" . date('H:i:s') . "] --- EMAIL CONSENT GRANT COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Lookback window: {$lookbackHours} hours\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total profiles fetched: {$totalFetched}\n";
        $completionMsg .= "[" . date('H:i:s') . "] New profiles found: {$totalNew}\n";
        $completionMsg .= "[" . date('H:i:s') . "] With valid email: {$totalWithEmail}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches processed: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Consent granted: {$grantedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_grant_email_consent: Complete - Granted: {$grantedCount}, Failed: {$failedBatches}");
        
        $result = [
            'success' => true,
            'message' => 'Email consent grant completed for new profiles',
            'job_name' => $jobName,
            'lookback_hours' => $lookbackHours,
            'cutoff_datetime' => $cutoffDatetime,
            'total_fetched' => $totalFetched,
            'new_profiles_found' => $totalNew,
            'profiles_with_email' => $totalWithEmail,
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
 * Fetch recent profiles from Klaviyo API
 * 
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param string $cutoffDatetime Cutoff datetime (Y-m-d H:i:s)
 * @param string $temp_log Path to log file
 * @return array Array with 'profiles' or 'error'
 */
if (!function_exists('nce_fetch_recent_profiles_from_klaviyo')) {
    function nce_fetch_recent_profiles_from_klaviyo(string $apiKey, string $apiVersion, string $cutoffDatetime, string $temp_log): array {
        $allProfiles = [];
        $pageUrl = 'https://a.klaviyo.com/api/profiles';
        $pageCount = 0;
        $maxPages = 50; // Safety limit
        
        // Convert cutoff to ISO 8601 format for Klaviyo filter
        $cutoffISO = date('c', strtotime($cutoffDatetime));
        
        // Filter for profiles created after cutoff
        $filter = "greater-than(created,{$cutoffISO})";
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching profiles created after {$cutoffDatetime}...\n", FILE_APPEND);
        
        while ($pageUrl && $pageCount < $maxPages) {
            $pageCount++;
            
            // Build URL with filter on first page
            if ($pageCount === 1) {
                $queryParams = http_build_query([
                    'filter' => $filter,
                    'fields[profile]' => 'email,phone_number,created',
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
            
            // Extract profiles from this page
            if (!empty($body['data'])) {
                foreach ($body['data'] as $profile) {
                    $allProfiles[] = [
                        'email' => $profile['attributes']['email'] ?? null,
                        'phone_number' => $profile['attributes']['phone_number'] ?? null,
                        'created_at' => $profile['attributes']['created'] ?? null
                    ];
                }
                
                $profilesOnPage = count($body['data']);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Page {$pageCount}: {$profilesOnPage} profiles\n", FILE_APPEND);
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
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Total recent profiles fetched: {$totalCount}\n", FILE_APPEND);
        
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

