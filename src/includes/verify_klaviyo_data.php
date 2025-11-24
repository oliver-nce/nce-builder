<?php
/**
 * Shared Utility: Verify Klaviyo Data Source Records
 * 
 * Quick script to check if records exist in Klaviyo data source.
 * Can be used by any task that needs to verify data uploads.
 */

if (!defined('ABSPATH')) {
    // Allow running standalone for testing
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

/**
 * Verify records exist in Klaviyo data source
 * 
 * @return array Success or error details
 */
function verify_klaviyo_data_source(): array {
    global $wpdb;
    
    // Get configuration
    $table = $wpdb->prefix . 'klaviyo_globals';
    $globals = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
    
    if (!$globals) {
        return ['error' => 'No globals found'];
    }
    
    $apiKey = trim($globals['api_key'] ?? '');
    $dsId = trim($globals['object_ds_id'] ?? '');
    $apiVersion = trim($globals['api_version'] ?? '2025-10-15');
    
    if ($apiKey === '' || $dsId === '') {
        return ['error' => 'Missing API key or Data Source ID'];
    }
    
    echo "Checking Klaviyo Data Source...\n";
    echo "Data Source ID: {$dsId}\n";
    echo "API Version: {$apiVersion}\n\n";
    
    // Query first page
    $url = "https://a.klaviyo.com/api/data-source-records/?filter=equals(data_source_id,\"{$dsId}\")&page[size]=100";
    
    $args = [
        'method' => 'GET',
        'headers' => [
            'Authorization' => "Klaviyo-API-Key {$apiKey}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'revision' => $apiVersion,
        ],
        'timeout' => 30,
    ];
    
    echo "Fetching records from Klaviyo...\n";
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    echo "HTTP Status: {$code}\n";
    
    if ($code !== 200) {
        echo "Error Response: {$body}\n";
        return [
            'error' => 'API returned error',
            'http_code' => $code,
            'response' => $data,
        ];
    }
    
    $records = $data['data'] ?? [];
    $recordCount = count($records);
    
    echo "\n✅ SUCCESS!\n";
    echo "Records found in first page: {$recordCount}\n";
    
    // Show sample record
    if (!empty($records)) {
        echo "\nSample Record (first one):\n";
        echo "  ID: " . ($records[0]['id'] ?? 'N/A') . "\n";
        if (isset($records[0]['attributes']['record'])) {
            $sampleData = $records[0]['attributes']['record'];
            echo "  Sample data keys: " . implode(', ', array_keys($sampleData)) . "\n";
            echo "  First few values:\n";
            $count = 0;
            foreach ($sampleData as $key => $value) {
                if ($count++ >= 3) break;
                $displayValue = is_string($value) ? substr($value, 0, 50) : json_encode($value);
                echo "    {$key}: {$displayValue}\n";
            }
        }
    }
    
    // Check for pagination
    $hasMore = isset($data['links']['next']);
    echo "\nMore records available: " . ($hasMore ? 'YES' : 'NO') . "\n";
    
    if ($hasMore) {
        echo "Next page URL: " . $data['links']['next'] . "\n";
    }
    
    return [
        'success' => true,
        'records_in_page' => $recordCount,
        'has_more' => $hasMore,
        'sample_record' => $records[0] ?? null,
    ];
}

// Run if called directly
if (php_sapi_name() === 'cli' || !empty($_GET['verify'])) {
    if (!function_exists('wp_remote_request')) {
        // Load WordPress
        require_once ABSPATH . 'wp-load.php';
    }
    
    $result = verify_klaviyo_data_source();
    
    if (isset($result['error'])) {
        echo "\n❌ ERROR: " . $result['error'] . "\n";
        exit(1);
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Verification complete!\n";
    exit(0);
}

