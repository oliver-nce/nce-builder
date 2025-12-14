<?php
// LAST UPDATED: 2025-12-14
// v5.0.0 - Fetch from segment "email-non-subscribed" (SpnQ4w), no consented_at
declare(strict_types=1);

/**
 * Grant Email Marketing Consent - Task 4
 * ---
 * Fetches profiles from segment "email-non-subscribed" (SpnQ4w),
 * filters by joined_group_at date, and grants consent one at a time.
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'default'
 *                      - lookback_days (optional): defaults to 0 (today since midnight UTC)
 *                      - limit (optional): defaults to 0 (no limit)
 * @return array Summary with consent grant results   
 */
if (!function_exists('nce_task_grant_email_consent')) {
    function nce_task_grant_email_consent(array $params = []): array {
        @ini_set('max_execution_time', '3600');
        @ini_set('memory_limit', '512M');
        @set_time_limit(3600);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $lookbackDays = isset($params['lookback_days']) ? (int)$params['lookback_days'] : 0; // 0 = today since midnight UTC
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0; // 0 = no limit
        $skipLogClear = !empty($params['skip_log_clear']);
        
        // Segment ID for "email-non-subscribed"
        $segmentId = 'SpnQ4w';
        
        error_log("nce_task_grant_email_consent: Starting (Segment: {$segmentId}, Lookback: {$lookbackDays} days)");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        if (!$skipLogClear) {
            file_put_contents($temp_log, "");
        }
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] GRANT EMAIL CONSENT - Segment: {$segmentId}, Lookback: {$lookbackDays} days, Limit: {$limit}\n", FILE_APPEND);
        
        global $wpdb;
        $startTime = microtime(true);
        
        // --- 1. Get API configuration ---
        $table = $wpdb->prefix . 'klaviyo_globals';
        $g = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE job_name = %s LIMIT 1",
            $jobName
        ), ARRAY_A);
        
        if (!$g) {
            return ['error' => "No configuration found for job_name: {$jobName}"];
        }
        
        $apiKey = trim((string)($g['api_key'] ?? ''));
        $apiVersion = trim((string)($g['api_version'] ?? '2025-10-15'));
        
        if ($apiKey === '') {
            return ['error' => 'Missing api_key'];
        }
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Configuration loaded, API version: {$apiVersion}\n", FILE_APPEND);
        
        // --- 2. Calculate cutoff (midnight UTC X days ago) ---
        // 0 days = today midnight UTC
        // 1 day = yesterday midnight UTC
        $utcMidnight = gmmktime(0, 0, 0); // Today midnight UTC
        $cutoffTimestamp = $utcMidnight - ($lookbackDays * 86400);
        $cutoff = gmdate('Y-m-d\TH:i:s\Z', $cutoffTimestamp);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Cutoff: {$cutoff} (profiles joined segment after this)\n", FILE_APPEND);
        
        // --- 3. Fetch profiles from segment with joined_group_at filter ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Fetching profiles from segment {$segmentId}...\n", FILE_APPEND);
        
        $profiles = [];
        $segmentUrl = "https://a.klaviyo.com/api/segments/{$segmentId}/profiles?filter=greater-than(joined_group_at,{$cutoff})&fields[profile]=email&page[size]=100";
        $page = 0;
        
        while ($segmentUrl && $page < 100) {
            $page++;
            $res = wp_remote_get($segmentUrl, [
                'headers' => [
                    'Authorization' => "Klaviyo-API-Key {$apiKey}",
                    'Accept' => 'application/vnd.api+json',
                    'revision' => $apiVersion,
                ],
                'timeout' => 30,
            ]);
            
            if (is_wp_error($res)) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR fetching segment: " . $res->get_error_message() . "\n", FILE_APPEND);
                break;
            }
            
            $http = (int) wp_remote_retrieve_response_code($res);
            $body = json_decode(wp_remote_retrieve_body($res), true);
            
            if ($http < 200 || $http >= 300) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ERROR HTTP {$http}: " . wp_remote_retrieve_body($res) . "\n", FILE_APPEND);
                break;
            }
            
            if (!empty($body['data'])) {
                foreach ($body['data'] as $p) {
                    $e = $p['attributes']['email'] ?? null;
                    if ($e) $profiles[] = strtolower(trim($e));
                }
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Page {$page}: " . count($body['data']) . " profiles\n", FILE_APPEND);
            }
            
            $segmentUrl = $body['links']['next'] ?? null;
        }
        
        $totalProfiles = count($profiles);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Total profiles from segment: {$totalProfiles}\n", FILE_APPEND);
        
        if ($totalProfiles === 0) {
            $duration = round(microtime(true) - $startTime, 2);
            return [
                'success' => true,
                'message' => 'No profiles in segment matching criteria',
                'segment_id' => $segmentId,
                'cutoff' => $cutoff,
                'total_fetched' => 0,
                'granted' => 0,
                'duration_seconds' => $duration
            ];
        }
        
        // Apply limit
        if ($limit > 0 && $totalProfiles > $limit) {
            $profiles = array_slice($profiles, 0, $limit);
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Limited to {$limit} profile(s) for this run\n", FILE_APPEND);
        }
        $toProcess = count($profiles);
        
        // --- 4. Grant consent one at a time (no consented_at) ---
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Granting consent to {$toProcess} profile(s)...\n", FILE_APPEND);
        
        $granted = 0;
        $failed = 0;
        $url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs';
        
        foreach ($profiles as $index => $email) {
            // Log progress every 50
            if ($index > 0 && $index % 50 === 0) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Progress: {$index}/{$toProcess} (granted: {$granted}, failed: {$failed})\n", FILE_APPEND);
            }
            
            $payload = [
                'data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => [
                        'profiles' => [
                            'data' => [
                                [
                                    'type' => 'profile',
                                    'attributes' => [
                                        'email' => $email,
                                        'subscriptions' => [
                                            'email' => [
                                                'marketing' => [
                                                    'consent' => 'SUBSCRIBED'
                                                    // No consented_at - let Klaviyo handle it
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'historical_import' => false // Not historical - grant now
                    ]
                ]
            ];
            
            $res = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => "Klaviyo-API-Key {$apiKey}",
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                    'revision' => $apiVersion,
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 30,
            ]);
            
            $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
            $responseBody = is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res);
            
            if ($http >= 200 && $http < 300) {
                $granted++;
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✓ Granted consent to: {$email}\n", FILE_APPEND);
            } else {
                $failed++;
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ✗ FAILED for {$email} - HTTP {$http}\n", FILE_APPEND);
                file_put_contents($temp_log, "[" . date('H:i:s') . "] Response: {$responseBody}\n", FILE_APPEND);
            }
            
            // Small delay to respect rate limits
            usleep(100000); // 100ms
        }
        
        // --- 5. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "[" . date('H:i:s') . "] --- EMAIL CONSENT COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Segment: {$segmentId}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Cutoff: {$cutoff}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Lookback: {$lookbackDays} days\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total from segment: {$totalProfiles}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Processed: {$toProcess}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Granted: {$granted}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed: {$failed}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        file_put_contents($temp_log, $completionMsg, FILE_APPEND);
        
        return [
            'success' => true,
            'message' => 'Email consent grant completed',
            'segment_id' => $segmentId,
            'cutoff' => $cutoff,
            'lookback_days' => $lookbackDays,
            'limit' => $limit,
            'total_from_segment' => $totalProfiles,
            'processed' => $toProcess,
            'granted' => $granted,
            'failed' => $failed,
            'remaining' => $totalProfiles - $toProcess,
            'duration_seconds' => $duration
        ];
    }
}
