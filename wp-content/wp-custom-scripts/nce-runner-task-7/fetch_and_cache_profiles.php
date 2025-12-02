<?php
// LAST UPDATED: 2025-11-28 19:00:00
// v3.0.0 - 2025-11-28 (Fetch & cache only - processing moved to Task 8)
declare(strict_types=1);

// Version constant for tracking deployed code
define('NCE_TASK_7_VERSION', '3.0.0');
define('NCE_TASK_7_UPDATED', '2025-11-28 19:00:00');
 
/** 
 * Fetch & Cache All Profiles with Phone Numbers - Task 7
 * ---
 * STEP 1 of SMS Consent Backfill: Fetches ALL profiles with valid US phone numbers
 * from Klaviyo and caches them in wp_klaviyo_globals.control_param for Task 8.
 * 
 * ⚠️ LEGAL WARNING: Only run this if ALL your users with phone numbers
 * have provided explicit SMS opt-in consent.
 *  
 * - Fetches ALL profiles with phone_number from Klaviyo
 * - Validates US phone numbers (+1XXXXXXXXXX format)
 * - Caches to database (wp_klaviyo_globals.control_param)
 * - Does NOT grant consent (use Task 8 for that)
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'default' (for API key/version and cache storage)
 *                      - refresh (optional): if true, forces re-fetch even if cache exists
 * @return array Summary with profile count and cache status
 */
if (!function_exists('nce_task_fetch_and_cache_profiles')) {
    function nce_task_fetch_and_cache_profiles(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $refresh = isset($params['refresh']) && ($params['refresh'] === 'true' || $params['refresh'] === true);
        
        error_log("nce_task_fetch_and_cache_profiles: Starting v" . NCE_TASK_7_VERSION . " (Job: {$jobName}, Refresh: " . ($refresh ? 'YES' : 'NO') . ")");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] FETCH & CACHE PROFILES - Job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Script version: " . NCE_TASK_7_VERSION . " (updated: " . NCE_TASK_7_UPDATED . ")\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Mode: Fetch ALL profiles and cache to database\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] US phone validation: +1XXXXXXXXXX format only\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Refresh mode: " . ($refresh ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
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
            }, array_values($validUSProfiles)),
            'cached_at' => date('Y-m-d H:i:s'),
            'count' => $totalValidUS,
            'task_version' => NCE_TASK_7_VERSION
        ];
        
        $cacheJson = json_encode($cacheData);
        
        // Update control_param in wp_klaviyo_globals
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
                'error' => 'Failed to save profiles to database cache: ' . $wpdb->last_error,
                'version' => NCE_TASK_7_VERSION,
                'job_name' => $jobName
            ];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] ✅ Successfully cached {$totalValidUS} profiles\n", FILE_APPEND);
        
        // --- 5. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $totalBatches = ceil($totalValidUS / 100);
        
        $completionMsg = "\n[" . date('H:i:s') . "] === FETCH & CACHE COMPLETE ===\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total profiles fetched: {$totalFetched}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Valid US phones cached: {$totalValidUS}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Filtered out (invalid/non-US): {$totalInvalid}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Estimated batches for processing: {$totalBatches}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        $completionMsg .= "[" . date('H:i:s') . "] Next step: Run Task 8 to process profiles\n";
        $completionMsg .= "[" . date('H:i:s') . "] Example: ?task=8&start_from=1\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        error_log("nce_task_fetch_and_cache_profiles: Complete - {$totalValidUS} profiles cached");
        
        return [
            'success' => true,
            'message' => "Successfully cached {$totalValidUS} profiles with valid US phone numbers",
            'version' => NCE_TASK_7_VERSION,
            'updated' => NCE_TASK_7_UPDATED,
            'job_name' => $jobName,
            'total_fetched' => $totalFetched,
            'valid_us_phones_cached' => $totalValidUS,
            'invalid_filtered' => $totalInvalid,
            'estimated_batches' => $totalBatches,
            'cached_at' => $cacheData['cached_at'],
            'duration_seconds' => $duration,
            'next_step' => '?task=8&start_from=1'
        ];
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
        
        return ['profiles' => $allProfiles];
    }
}

