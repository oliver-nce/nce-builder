<?php
// LAST UPDATED: 2025-12-15
// v1.1.0 - Send new_enrollment events to Klaviyo, timestamped logs
declare(strict_types=1);

// Load logging helper
require_once __DIR__ . '/../includes/nce_logging_helper.php';

/**
 * 
 * 
 * 
 * 
 * 
 * Send New Enrollment Events to Klaviyo - Task 10
 * ---
 * Checks wp_zoho_orders for records where event_sent_to_crm <> 1
 * and sends a 'new_enrollment' event to Klaviyo for each.
 * 
 * - Queries wp_zoho_orders for unsent events
 * - Sends event with SKU, player_id, order_date attributes
 * - Updates record to mark as sent (event_sent_to_crm = 1)
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'default' (for API key lookup)
 *                      - batch_size (optional): defaults to 100
 *                      - limit (optional): max records to process (0 = no limit)
 * @return array Summary with event sending results
 */
if (!function_exists('nce_task_send_enrollment_events')) {
    function nce_task_send_enrollment_events(array $params = []): array {
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        @set_time_limit(1800);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $batchSize = isset($params['batch_size']) ? (int)$params['batch_size'] : 100;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
        $skipLogClear = !empty($params['skip_log_clear']);
        
        error_log("nce_task_send_enrollment_events: Starting (Job: {$jobName})");
        
        // Initialize log file with timestamp using helper function
        $temp_log = nce_init_log_file('task10_send_enrollment_events');
        nce_write_log($temp_log, "[" . date('Y-m-d H:i:s') . "] SEND ENROLLMENT EVENTS - Task 10\n");
        
        global $wpdb;
        $startTime = microtime(true);
        
        // --- 1. Get API configuration ---
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
        
        // --- 2. Fetch orders that need events sent ---
        $ordersTable = $wpdb->prefix . 'zoho_orders';
        
        $limitClause = $limit > 0 ? "LIMIT {$limit}" : "";
        
        $query = "SELECT * FROM {$ordersTable} 
                  WHERE event_sent_to_crm IS NULL 
                     OR event_sent_to_crm <> 1 
                  ORDER BY order_date ASC
                  {$limitClause}";
        
        $orders = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            nce_write_log($temp_log, "[" . date('H:i:s') . "] SQL ERROR: " . $wpdb->last_error . "\n");
            return [
                'error' => 'SQL error: ' . $wpdb->last_error,
                'job_name' => $jobName
            ];
        }
        
        $totalOrders = count($orders);
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Found {$totalOrders} orders needing events\n");
        
        if ($totalOrders === 0) {
            return [
                'success' => true,
                'message' => 'No orders need event sending',
                'job_name' => $jobName,
                'processed' => 0
            ];
        }
        
        // --- 3. Process orders in batches ---
        $batches = array_chunk($orders, $batchSize);
        $totalBatches = count($batches);
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];
        
        nce_write_log($temp_log, "[" . date('H:i:s') . "] Processing {$totalBatches} batch(es) of up to {$batchSize}\n");
        
        foreach ($batches as $batchIndex => $batchOrders) {
            $batchNum = $batchIndex + 1;
            $batchCount = count($batchOrders);
            
            nce_write_log($temp_log, "[" . date('H:i:s') . "] Batch {$batchNum}/{$totalBatches} ({$batchCount} orders)...\n");
            
            // Build events array for bulk create
            $events = [];
            $orderIds = [];
            
            foreach ($batchOrders as $order) {
                $orderId = $order['order_id'] ?? null;
                $orderItemId = $order['order_item_id'] ?? null;
                $email = $order['user_email'] ?? $order['email'] ?? $order['family_email'] ?? null;
                $sku = $order['SKU'] ?? $order['sku'] ?? null;
                $playerId = $order['player_id'] ?? null;
                $orderDate = $order['order_date'] ?? null;
                
                // Skip if no email (can't send event without profile identifier)
                if (empty($email)) {
                    nce_write_log($temp_log, "[" . date('H:i:s') . "]   Skipping order {$orderId}/{$orderItemId} - no email\n");
                    $failedCount++;
                    continue;
                }
                
                $orderIds[] = ['order_id' => $orderId, 'order_item_id' => $orderItemId];
                
                // Build event payload
                $events[] = [
                    'type' => 'event',
                    'attributes' => [
                        'metric' => [
                            'data' => [
                                'type' => 'metric',
                                'attributes' => [
                                    'name' => 'new_enrollment'
                                ]
                            ]
                        ],
                        'profile' => [
                            'data' => [
                                'type' => 'profile',
                                'attributes' => [
                                    'email' => strtolower(trim($email))
                                ]
                            ]
                        ],
                        'properties' => [
                            'SKU' => $sku,
                            'player_id' => $playerId,
                            'order_date' => $orderDate,
                            'order_id' => $orderId,
                            'order_item_id' => $orderItemId,
                            'user_email' => $email
                        ],
                        'time' => $orderDate ? date('c', strtotime($orderDate)) : date('c')
                    ]
                ];
            }
            
            if (empty($events)) {
                nce_write_log($temp_log, "[" . date('H:i:s') . "]   No valid events in batch\n");
                continue;
            }
            
            // Log sample from first batch
            if ($batchNum === 1 && !empty($events)) {
                $sampleEvent = json_encode($events[0], JSON_PRETTY_PRINT);
                nce_write_log($temp_log, "[" . date('H:i:s') . "] Sample event:\n{$sampleEvent}\n");
            }
            
            // Send events to Klaviyo (one at a time for now - bulk events API has different format)
            $batchSent = 0;
            $batchFailed = 0;
            
            foreach ($events as $eventIndex => $event) {
                $orderKey = $orderIds[$eventIndex] ?? ['order_id' => 'unknown', 'order_item_id' => 'unknown'];
                $eventOrderId = $orderKey['order_id'];
                $eventOrderItemId = $orderKey['order_item_id'];
                
                $payload = ['data' => $event];
                $url = 'https://a.klaviyo.com/api/events';
                
                $response = nce_klaviyo_event_request('POST', $url, $apiKey, $apiVersion, $payload);
                
                if ($response['http'] >= 200 && $response['http'] < 300) {
                    $batchSent++;
                    
                    // Update order to mark event as sent (using composite key)
                    $wpdb->update(
                        $ordersTable,
                        ['event_sent_to_crm' => 1],
                        [
                            'order_id' => $eventOrderId,
                            'order_item_id' => $eventOrderItemId
                        ],
                        ['%d'],
                        ['%d', '%d']
                    );
                } else {
                    $batchFailed++;
                    $errorMsg = $response['error'] ?? "HTTP {$response['http']}";
                    
                    if (count($errors) < 10) { // Only store first 10 errors
                        $errors[] = [
                            'order_id' => $eventOrderId,
                            'order_item_id' => $eventOrderItemId,
                            'error' => $errorMsg
                        ];
                    }
                }
                
                // Small delay to respect rate limits
                usleep(50000); // 50ms between events
            }
            
            $sentCount += $batchSent;
            $failedCount += $batchFailed;
            
            nce_write_log($temp_log, "[" . date('H:i:s') . "]   Sent: {$batchSent}, Failed: {$batchFailed}\n");
            
            // Pause between batches
            if ($batchNum < $totalBatches) {
                sleep(1);
            }
        }
        
        // --- 4. Summary ---
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $completionMsg = "[" . date('H:i:s') . "] --- ENROLLMENT EVENTS COMPLETE ---\n";
        $completionMsg .= "[" . date('H:i:s') . "] Total orders found: {$totalOrders}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Events sent: {$sentCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Failed: {$failedCount}\n";
        $completionMsg .= "[" . date('H:i:s') . "] Duration: {$duration}s\n";
        
        nce_write_log($temp_log, $completionMsg);
        error_log("nce_task_send_enrollment_events: Complete - Sent: {$sentCount}, Failed: {$failedCount}");
        
        $result = [
            'success' => true,
            'message' => 'Enrollment events processing complete',
            'job_name' => $jobName,
            'total_orders' => $totalOrders,
            'events_sent' => $sentCount,
            'failed' => $failedCount,
            'duration_seconds' => $duration
        ];
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }
        
        return $result;
    }
}

/**
 * Make Klaviyo API request for event operations
 */
if (!function_exists('nce_klaviyo_event_request')) {
    function nce_klaviyo_event_request(string $method, string $url, string $apiKey, string $apiVersion, array $payload = []): array {
        $args = [
            'method' => strtoupper($method),
            'headers' => [
                'Authorization' => "Klaviyo-API-Key {$apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'revision' => $apiVersion,
            ],
            'timeout' => 30,
        ];
        
        if (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }
        
        $res = wp_remote_request($url, $args);
        $http = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $rawBody = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
        $body = is_wp_error($res) ? ['error' => $res->get_error_message()] : json_decode($rawBody, true);
        
        $errorMsg = null;
        if (is_wp_error($res)) {
            $errorMsg = $res->get_error_message();
        } elseif (!empty($body['errors'])) {
            $firstError = $body['errors'][0];
            $errorMsg = $firstError['detail'] ?? 'Unknown error';
        }
        
        return [
            'http' => $http,
            'body' => $body,
            'error' => $errorMsg,
            'raw_body' => $rawBody,
        ];
    }
}

