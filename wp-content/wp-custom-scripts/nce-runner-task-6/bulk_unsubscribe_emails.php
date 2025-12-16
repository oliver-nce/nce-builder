<?php // v1.1.0 - 2025-12-15
declare(strict_types=1);

// Load logging helper
require_once __DIR__ . '/../includes/nce_logging_helper.php';

/**
 * Bulk Unsubscribe Email Profiles - Task 6
 * ---
 * Fetches suppressed profiles from Klaviyo API and unsubscribes them.
 * This removes marketing consent but does NOT suppress (hard block) the emails.
 * Transactional emails can still be sent after unsubscribe.
 * 
 * - Fetches suppressed emails from Klaviyo API
 * - Batches up to 100 emails per request
 * - Uses profile-subscription-bulk-delete-jobs endpoint
 * - Only affects email marketing consent (not SMS)
 * 
 * Note: Unsubscribe ≠ Suppress
 * - Unsubscribe: Removes marketing consent, transactional emails still work
 * - Suppress: Hard block on ALL marketing emails (use only when required)
 * 
 * @param array $params Parameters from REST request:
 *                      - dry_run (optional): true to only read/validate emails without unsubscribing
 *                      - job_name (optional): defaults to 'default' (only for API key lookup)
 * @return array Summary with unsubscribe results
 * 
 * Always fetches from Klaviyo suppression list via API
 */
if (!function_exists('nce_task_bulk_unsubscribe_emails')) {
    function nce_task_bulk_unsubscribe_emails(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $dryRun = !empty($params['dry_run']) && ($params['dry_run'] === true || $params['dry_run'] === 'true' || $params['dry_run'] === 1);
        
        error_log("nce_task_bulk_unsubscribe_emails: Starting (Job: {$jobName}, Dry Run: " . ($dryRun ? 'YES' : 'NO') . ")");
        
        // Initialize log file with timestamp using helper function
        $temp_log = nce_init_log_file('task6_bulk_unsubscribe_emails');
        nce_write_log($temp_log, "[" . date('Y-m-d H:i:s') . "] BULK UNSUBSCRIBE EMAILS - Job: {$jobName}\n");
        if ($dryRun) {
            nce_write_log($temp_log, "[" . date('H:i:s') . "] 🔍 DRY RUN MODE - Will only read and validate emails (no API calls)\n");
        }
        
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
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n");
        nce_write_log($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n");
        
        // --- 2. Fetch suppressed emails from Klaviyo API ---
        $emailList = [];
        $sourceUsed = 'klaviyo_api';
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Fetching suppressed emails from Klaviyo API...\n");
        
        $suppressedEmails = nce_fetch_klaviyo_suppressed_profiles($apiKey, $apiVersion, $temp_log);
        
        if (isset($suppressedEmails['error'])) {
            return [
                'error' => $suppressedEmails['error'],
                'job_name' => $jobName,
                'source' => 'klaviyo_api'
            ];
        }
        
        $emailList = $suppressedEmails['emails'];
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Retrieved {$suppressedEmails['count']} suppressed emails from Klaviyo\n");
        
        $totalEmails = count($emailList);
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Found {$totalEmails} email addresses to unsubscribe\n");
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
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Skipped {$invalidCount} invalid email addresses\n");
        }
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] {$validCount} valid emails to process\n");
        
        if ($validCount === 0) {
            return [
                'success' => true,
                'message' => 'No valid emails to unsubscribe',
                'job_name' => $jobName,
                'dry_run' => $dryRun,
                'total_found' => $totalEmails,
                'invalid' => $invalidCount,
                'unsubscribed' => 0
            ];
        }
        
        // --- DRY RUN MODE: Return email list without unsubscribing ---
        if ($dryRun) {
            nce_write_log($temp_log, "[" . date('H:i:s') . "] DRY RUN: Returning email list without API calls\n");
            
            // Show first 20 emails as sample
            $sampleEmails = array_slice($validEmails, 0, 20);
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Sample emails (first 20):\n");
            foreach ($sampleEmails as $i => $email) {
                nce_write_log($temp_log, "[" . date('H:i:s') . "]   " . ($i + 1) . ". {$email}\n");
            }
            
            if (count($validEmails) > 20) {
                nce_write_log($temp_log, "[" . date('H:i:s') . "]   ... and " . (count($validEmails) - 20) . " more\n");
            }
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            return [
                'success' => true,
                'message' => 'DRY RUN: Email list retrieved successfully (no unsubscribe performed)',
                'job_name' => $jobName,
                'source' => $sourceUsed,
                'dry_run' => true,
                'total_found' => $totalEmails,
                'invalid_emails' => $invalidCount,
                'valid_emails' => $validCount,
                'email_list' => $validEmails,
                'sample_emails' => $sampleEmails,
                'duration_seconds' => $duration,
                'note' => 'Run without dry_run parameter to actually unsubscribe these emails'
            ];
        }
        
        // --- 4. Batch and send to Klaviyo ---
        $batchSize = 100; // Klaviyo recommendation for subscription endpoints
        $batches = array_chunk($validEmails, $batchSize);
        $totalBatches = count($batches);
        $unsubscribedCount = 0;
        $failedBatches = 0;
        $errors = [];
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize} emails each\n");
        
        foreach ($batches as $batchIndex => $batchEmails) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchEmails);
            
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} emails)...\n");
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
                nce_write_log($temp_log, "[" . date('H:i:s') . "] Sample emails: " . implode(', ', $sampleEmails) . "\n");
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
                nce_write_log($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n");
                error_log("nce_task_bulk_unsubscribe_emails: Batch {$batchNum} submitted (Job ID: {$jobId})");
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
        
        nce_write_log($temp_log, $completionMsg);
        error_log("nce_task_bulk_unsubscribe_emails: Complete - Unsubscribed: {$unsubscribedCount}, Failed: {$failedBatches}");
        
        $result = [
            'success' => true,
            'message' => 'Bulk unsubscribe completed',
            'job_name' => $jobName,
            'source' => $sourceUsed,
            'dry_run' => false,
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
 * Fetch suppressed profiles from Klaviyo API
 * 
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param string $temp_log Path to log file
 * @return array Array with 'emails' and 'count' or 'error'
 */
if (!function_exists('nce_fetch_klaviyo_suppressed_profiles')) {
    function nce_fetch_klaviyo_suppressed_profiles(string $apiKey, string $apiVersion, string $temp_log): array {
        $allEmails = [];
        $pageUrl = 'https://a.klaviyo.com/api/profiles';
        $pageCount = 0;
        $maxPages = 100; // Safety limit
        
        // Filter for suppressed profiles
        // Check if suppression timestamp exists (profile has been suppressed)
        $filter = 'greater-than(subscriptions.email.marketing.suppression.timestamp,1970-01-01T00:00:00Z)';
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Fetching suppressed profiles from Klaviyo...\n");
        
        while ($pageUrl && $pageCount < $maxPages) {
            $pageCount++;
            
            // Build URL with filter on first page
            if ($pageCount === 1) {
                $queryParams = http_build_query([
                    'filter' => $filter,
                    'fields[profile]' => 'email',
                    'page[size]' => 100
                ]);
                $fullUrl = $pageUrl . '?' . $queryParams;
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
                $errorMsg = 'Failed to fetch suppressed profiles from Klaviyo';
                if (is_wp_error($res)) {
                    $errorMsg .= ': ' . $res->get_error_message();
                } elseif (!empty($body['errors'])) {
                    $errorMsg .= ': ' . ($body['errors'][0]['detail'] ?? 'Unknown error');
                }
                
                nce_write_log($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n");
                return ['error' => $errorMsg];
            }
            
            // Extract emails from this page
            if (!empty($body['data'])) {
                foreach ($body['data'] as $profile) {
                    $email = $profile['attributes']['email'] ?? null;
                    if ($email) {
                        $allEmails[] = strtolower(trim($email));
                    }
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
        
        // Remove duplicates
        $allEmails = array_unique($allEmails);
        $totalCount = count($allEmails);
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Total suppressed profiles fetched: {$totalCount}\n");
        
        return [
            'emails' => array_values($allEmails),
            'count' => $totalCount,
            'pages' => $pageCount
        ];
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

