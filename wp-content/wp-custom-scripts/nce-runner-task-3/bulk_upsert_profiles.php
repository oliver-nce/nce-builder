<?php // v1.5.0 - 2025-11-27 21:10
declare(strict_types=1);

/**
 * Bulk Upsert Klaviyo Profiles - Task 3
 * ---
 * Syncs profiles from wp_klaviyo_profiles table to Klaviyo using Bulk Import API.
 * - Validates query and column structure first
 * - Batches up to 10,000 profiles per request
 * - Creates/updates profiles with identifiers, names, and location
 * - Updates last_run_datetime on success
 * 
 * Note: Marketing consent cannot be set via bulk import API.
 * Use subscription endpoints or list management separately if needed.
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'profiles'
 * @return array Summary with sync results
 */
if (!function_exists('nce_task_upsert_klaviyo_profiles')) {
    function nce_task_upsert_klaviyo_profiles(array $params = []): array {
        // Allow up to 30 minutes of runtime and 512MB memory
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'profiles';
        
        error_log("nce_task_upsert_klaviyo_profiles: Starting sync (Job: {$jobName})");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] BULK UPSERT PROFILES - Job: {$jobName}\n", FILE_APPEND);
        
        global $wpdb;
        $startTime = microtime(true);
        
        // --- 1. Get configuration from database ---
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
        $queryString = trim((string)($g['query'] ?? ''));
        $globalsId = (int)$g['id'];
        
        if ($apiKey === '') {
            return ['error' => 'Missing api_key in configuration', 'job_name' => $jobName];
        }
        
        if ($queryString === '') {
            return ['error' => 'Missing query in configuration', 'job_name' => $jobName];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] API Version: {$apiVersion}\n", FILE_APPEND);
        
        // --- 2. Validate query with LIMIT 3 ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Validating query structure...\n", FILE_APPEND);
        error_log("nce_task_upsert_klaviyo_profiles: Validating query with LIMIT 3");
        
        // Add LIMIT 3 to query for validation
        $validationQuery = rtrim($queryString, "; \t\n\r\0\x0B") . " LIMIT 3";
        $testRows = $wpdb->get_results($validationQuery, ARRAY_A);
        
        if ($wpdb->last_error) {
            $errorMsg = "Query validation failed: " . $wpdb->last_error;
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n", FILE_APPEND);
            return [
                'error' => $errorMsg,
                'job_name' => $jobName,
                'query' => $queryString
            ];
        }
        
        if (empty($testRows)) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] No profiles to sync\n", FILE_APPEND);
            return [
                'success' => true,
                'message' => 'No profiles found to sync',
                'job_name' => $jobName,
                'synced' => 0
            ];
        }
        
        // Define valid Klaviyo profile columns
        $validColumns = [
            // Identifiers
            'email', 'phone_number', 'external_id',
            // Basic fields
            'first_name', 'last_name', 'organization', 'title', 'image',
            // Location fields
            'city', 'region', 'country', 'zip', 'address1', 'address2',
            'latitude', 'longitude', 'timezone',
            // Metadata
            'created_at', 'updated_at'
        ];
        
        $firstRow = $testRows[0];
        $availableColumns = array_keys($firstRow);
        
        // Filter to only valid Klaviyo columns
        $usableColumns = array_intersect($availableColumns, $validColumns);
        $ignoredColumns = array_diff($availableColumns, $validColumns);
        
        // Check for at least one identifier
        $identifierColumns = ['email', 'phone_number', 'external_id'];
        $hasIdentifier = !empty(array_intersect($usableColumns, $identifierColumns));
        
        if (!$hasIdentifier) {
            $errorMsg = "Query must return at least one identifier column: " . implode(', ', $identifierColumns);
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n", FILE_APPEND);
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Available columns: " . implode(', ', $availableColumns) . "\n", FILE_APPEND);
            return [
                'error' => $errorMsg,
                'job_name' => $jobName,
                'available_columns' => $availableColumns,
                'required_identifiers' => $identifierColumns
            ];
        }
        
        // Log column validation results
        file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Query validation passed\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Usable columns: " . implode(', ', $usableColumns) . "\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Note: Invalid emails/phones will be skipped automatically\n", FILE_APPEND);
        
        if (!empty($ignoredColumns)) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Ignored columns (not valid for Klaviyo): " . implode(', ', $ignoredColumns) . "\n", FILE_APPEND);
        }
        
        // Recommend additional useful columns if missing
        $recommendedColumns = ['phone_number', 'external_id', 'first_name', 'last_name'];
        $missingRecommended = array_diff($recommendedColumns, $usableColumns);
        if (!empty($missingRecommended)) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Note: Missing recommended columns: " . implode(', ', $missingRecommended) . "\n", FILE_APPEND);
        }
        
        error_log("nce_task_upsert_klaviyo_profiles: Validation passed, proceeding with full sync");
        
        // --- 3. Fetch all profiles ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching all profiles...\n", FILE_APPEND);
        
        $allProfiles = $wpdb->get_results($queryString, ARRAY_A);
        
        if ($wpdb->last_error) {
            $errorMsg = "Failed to fetch profiles: " . $wpdb->last_error;
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR: {$errorMsg}\n", FILE_APPEND);
            return [
                'error' => $errorMsg,
                'job_name' => $jobName
            ];
        }
        
        $totalProfiles = count($allProfiles);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Found {$totalProfiles} profiles to sync\n", FILE_APPEND);
        error_log("nce_task_upsert_klaviyo_profiles: Found {$totalProfiles} profiles");
        
        if ($totalProfiles === 0) {
            return [
                'success' => true,
                'message' => 'No profiles to sync',
                'job_name' => $jobName,
                'synced' => 0
            ];
        }
        
        // --- 4. Batch profiles and send to Klaviyo ---
        $batchSize = 10000; // Klaviyo limit
        $batches = array_chunk($allProfiles, $batchSize);
        $totalBatches = count($batches);
        $syncedProfiles = 0;
        $failedBatches = 0;
        $skippedProfiles = 0;
        $errors = [];
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize} profiles each\n", FILE_APPEND);
        
        foreach ($batches as $batchIndex => $batchProfiles) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchProfiles);
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Processing batch {$batchNum}/{$totalBatches} ({$batchCount} profiles)...\n", FILE_APPEND);
            error_log("nce_task_upsert_klaviyo_profiles: Processing batch {$batchNum}/{$totalBatches}");
            
            // Build profiles array for Klaviyo
            $profilesData = [];
            $batchSkipped = 0;
            foreach ($batchProfiles as $profile) {
                $profileData = nce_build_klaviyo_profile($profile, $usableColumns);
                if ($profileData !== null) {
                    $profilesData[] = $profileData;
                } else {
                    $batchSkipped++;
                }
            }
            
            $skippedProfiles += $batchSkipped;
            
            if ($batchSkipped > 0) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch {$batchNum}: Skipped {$batchSkipped} invalid profiles\n", FILE_APPEND);
            }
            
            if (empty($profilesData)) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Batch {$batchNum}: No valid profiles to sync\n", FILE_APPEND);
                continue;
            }
            
            // Log sample profiles from first batch for debugging
            if ($batchNum === 1 && !empty($profilesData)) {
                // Log sample emails
                $sampleEmails = array_slice(array_filter(array_map(function($p) {
                    return $p['attributes']['email'] ?? null;
                }, $profilesData)), 0, 5);
                
                if (!empty($sampleEmails)) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample emails being sent: " . implode(', ', $sampleEmails) . "\n", FILE_APPEND);
                }
                
                // Log first 2 complete profiles for structure inspection
                $sampleProfiles = array_slice($profilesData, 0, 2);
                $sampleJson = json_encode($sampleProfiles, JSON_PRETTY_PRINT);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Sample profile structures:\n{$sampleJson}\n", FILE_APPEND);
            }
            
            // Build bulk import job payload
            $payload = [
                'data' => [
                    'type' => 'profile-bulk-import-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => $profilesData
                        ]
                    ]
                ]
            ];
            
            // Send to Klaviyo
            $url = 'https://a.klaviyo.com/api/profile-bulk-import-jobs';
            $response = nce_klaviyo_bulk_request('POST', $url, $apiKey, $apiVersion, $payload);
            
            if ($response['http'] >= 200 && $response['http'] < 300) {
                $jobId = $response['body']['data']['id'] ?? 'unknown';
                $syncedProfiles += count($profilesData);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Batch {$batchNum} submitted successfully (Job ID: {$jobId})\n", FILE_APPEND);
                error_log("nce_task_upsert_klaviyo_profiles: Batch {$batchNum} submitted (Job ID: {$jobId})");
            } else {
                $failedBatches++;
                $errorMsg = $response['error'] ?? 'Unknown error';
                
                // Log detailed error info with raw response
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}\n", FILE_APPEND);
                
                // Log formatted error body
                if (!empty($response['body'])) {
                    $fullError = json_encode($response['body'], JSON_PRETTY_PRINT);
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] Klaviyo error body:\n{$fullError}\n", FILE_APPEND);
                }
                
                // Log raw response for complete debugging
                if (!empty($response['raw_body'])) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] Raw response body:\n{$response['raw_body']}\n", FILE_APPEND);
                }
                
                // Extract all error details from Klaviyo's errors array
                $allErrors = [];
                if (!empty($response['body']['errors'])) {
                    foreach ($response['body']['errors'] as $err) {
                        $allErrors[] = [
                            'title' => $err['title'] ?? '',
                            'detail' => $err['detail'] ?? '',
                            'source' => $err['source'] ?? null,
                            'meta' => $err['meta'] ?? null
                        ];
                    }
                }
                
                $errorDetail = [
                    'batch' => $batchNum,
                    'http_status' => $response['http'],
                    'error' => $errorMsg,
                    'profile_count' => count($profilesData),
                    'klaviyo_errors' => $allErrors
                ];
                $errors[] = $errorDetail;
                error_log("nce_task_upsert_klaviyo_profiles: Batch {$batchNum} failed - HTTP {$response['http']}: {$errorMsg}");
            }
            
            // Rate limiting: pause between batches (10/s burst, 150/m steady)
            if ($batchNum < $totalBatches) {
                sleep(1); // 1 second between batches to respect rate limits
            }
        }
        
        // --- 5. Update last_run_datetime if successful ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        if ($failedBatches === 0) {
            $now = current_time('mysql');
            $wpdb->update(
                $table,
                ['last_run_datetime' => $now],
                ['id' => $globalsId],
                ['%s'],
                ['%d']
            );
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Updated last_run_datetime to {$now}\n", FILE_APPEND);
            error_log("nce_task_upsert_klaviyo_profiles: Updated last_run_datetime");
        }
        
        // --- 6. Summary ---
        $completionMsg = "[" . date('H:i:s') . "] --- SYNC COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total profiles found: {$totalProfiles}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Skipped (invalid): {$skippedProfiles}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Batches processed: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Profiles synced: {$syncedProfiles}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed batches: {$failedBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_upsert_klaviyo_profiles: Complete - Synced: {$syncedProfiles}, Failed: {$failedBatches}");
        
        $result = [
            'success' => true,
            'message' => 'Profile sync completed',
            'job_name' => $jobName,
            'total_found' => $totalProfiles,
            'skipped_invalid' => $skippedProfiles,
            'synced' => $syncedProfiles,
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
 * Build Klaviyo profile payload from database row
 * 
 * @param array $profile Database row
 * @param array $usableColumns List of valid Klaviyo column names to use
 * @return array|null Profile payload or null if invalid
 */
if (!function_exists('nce_build_klaviyo_profile')) {
    function nce_build_klaviyo_profile(array $profile, array $usableColumns): ?array {
        // Must have at least one identifier
        // Clean and normalize email
        $email = !empty($profile['email']) ? strtolower(trim($profile['email'])) : null;
        $phone = !empty($profile['phone_number']) ? trim($profile['phone_number']) : null;
        $externalId = !empty($profile['external_id']) ? trim($profile['external_id']) : null;
        
        // Validate email if present (Klaviyo has strict requirements)
        if ($email) {
            // 0. Check for placeholder/null values
            $invalidPatterns = ['null', 'none', 'n/a', 'na', 'test', '(null)', 'undefined', 'email'];
            if (in_array($email, $invalidPatterns) || strlen($email) < 3) {
                error_log("nce_build_klaviyo_profile: Skipping placeholder email: {$email}");
                return null;
            }
            
            // 1. Basic format check
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("nce_build_klaviyo_profile: Skipping invalid email format: {$email}");
                return null;
            }
            
            // 2. Must have @ and domain with TLD
            if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                error_log("nce_build_klaviyo_profile: Skipping email (no valid domain): {$email}");
                return null;
            }
            
            // 3. TLD must be at least 2 characters (e.g., .com not .c)
            if (!preg_match('/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/', $email)) {
                error_log("nce_build_klaviyo_profile: Skipping email (invalid TLD): {$email}");
                return null;
            }
            
            // 4. No spaces or special invalid characters
            if (preg_match('/[\s\(\)\[\]\{\}<>]/', $email)) {
                error_log("nce_build_klaviyo_profile: Skipping email (invalid characters): {$email}");
                return null;
            }
            
            // 5. Check for obviously fake domains
            $fakeDomains = ['test.com', 'example.com', 'localhost', 'test', 'none', 'null', 'n/a'];
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            if (in_array($domain, $fakeDomains) || empty($domain)) {
                error_log("nce_build_klaviyo_profile: Skipping email (fake/empty domain): {$email}");
                return null;
            }
            
            // 6. Email must not be longer than 254 characters (RFC limit)
            if (strlen($email) > 254) {
                error_log("nce_build_klaviyo_profile: Skipping email (too long): {$email}");
                return null;
            }
        }
        
        // Validate phone if present (must start with +)
        if ($phone && !preg_match('/^\+\d{10,15}$/', $phone)) {
            error_log("nce_build_klaviyo_profile: Skipping invalid phone: {$phone}");
            return null; // Skip profiles with invalid phone
        }
        
        if (empty($email) && empty($phone) && empty($externalId)) {
            return null; // Skip profiles without any identifier
        }
        
        // Build attributes
        $attributes = [];
        
        // Add identifiers
        if ($email) $attributes['email'] = $email;
        if ($phone) $attributes['phone_number'] = $phone;
        if ($externalId) $attributes['external_id'] = $externalId;
        
        // Add basic fields
        if (in_array('first_name', $usableColumns) && !empty($profile['first_name'])) {
            $attributes['first_name'] = trim($profile['first_name']);
        }
        if (in_array('last_name', $usableColumns) && !empty($profile['last_name'])) {
            $attributes['last_name'] = trim($profile['last_name']);
        }
        
        // Build location object
        $location = [];
        if (in_array('city', $usableColumns) && !empty($profile['city'])) {
            $location['city'] = trim($profile['city']);
        }
        if (in_array('region', $usableColumns) && !empty($profile['region'])) {
            $location['region'] = trim($profile['region']);
        }
        if (in_array('country', $usableColumns) && !empty($profile['country'])) {
            $location['country'] = trim($profile['country']);
        }
        if (in_array('zip', $usableColumns) && !empty($profile['zip'])) {
            $location['zip'] = trim($profile['zip']);
        }
        
        if (!empty($location)) {
            $attributes['location'] = $location;
        }
        
        // Note: subscriptions/consent cannot be set via bulk import API
        // Marketing consent must be managed separately via subscription endpoints
        
        // Return profile payload
        return [
            'type' => 'profile',
            'attributes' => $attributes
        ];
    }
}

/**
 * Make Klaviyo bulk API request
 * 
 * @param string $method HTTP method
 * @param string $url Full URL
 * @param string $apiKey Klaviyo API key
 * @param string $apiVersion API revision
 * @param array $payload Request body
 * @return array Response with http code, body, error
 */
if (!function_exists('nce_klaviyo_bulk_request')) {
    function nce_klaviyo_bulk_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => "Klaviyo-API-Key {$apiKey}",
                'Accept'        => 'application/vnd.api+json',
                'Content-Type'  => 'application/vnd.api+json',
                'revision'      => $apiVersion,
            ],
            'timeout' => 60, // Longer timeout for bulk operations
        ];
        
        if (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }
        
        $res  = wp_remote_request($url, $args);
        $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
        $body = is_wp_error($res) ? ['error' => $res->get_error_message()] : json_decode($rawBody, true);
        
        // Extract detailed error message
        $errorMsg = null;
        if (is_wp_error($res)) {
            $errorMsg = $res->get_error_message();
        } elseif (!empty($body['errors'])) {
            // Klaviyo returns errors array with detailed information
            $firstError = $body['errors'][0];
            $errorMsg = $firstError['detail'] ?? 'Unknown error';
            
            // Add source information if available (tells which field caused the error)
            if (!empty($firstError['source'])) {
                $errorMsg .= ' (Source: ' . json_encode($firstError['source']) . ')';
            }
            
            // Add meta information if available
            if (!empty($firstError['meta'])) {
                $errorMsg .= ' (Meta: ' . json_encode($firstError['meta']) . ')';
            }
        }
        
        return [
            'http'     => $http,
            'body'     => $body,
            'error'    => $errorMsg,
            'raw_body' => $rawBody, // For full debugging
        ];
    }
}

