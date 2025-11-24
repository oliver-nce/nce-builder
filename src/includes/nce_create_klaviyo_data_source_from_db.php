<?php
declare(strict_types=1);

/**
 * Shared Utility: Create Klaviyo Data Source
 * 
 * Creates a new Klaviyo Data Source each time it runs
 * and updates wp_klaviyo_globals with its name + id.
 * 
 * Can be used by any task that needs to create a new data source.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a new Klaviyo Data Source using the latest globals record.
 * 
 * @return array Success or error details
 */
function nce_create_klaviyo_data_source_from_db(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'klaviyo_globals';

    // --- 1. Fetch latest globals record ---
    $globals = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
    if (!$globals) {
        return ['error' => 'No record found in wp_klaviyo_globals'];
    }

    $apiKey    = trim((string) ($globals['api_key'] ?? ''));
    $baseName  = trim((string) ($globals['object_ds_name'] ?? 'default_ds'));
    $revision  = trim((string) ($globals['api_version'] ?? '2025-10-15'));
    $url       = 'https://a.klaviyo.com/api/data-sources';

    if ($apiKey === '') {
        return ['error' => 'API key missing in wp_klaviyo_globals'];
    }
    
    if ($revision === '') {
        return ['error' => 'API version missing in wp_klaviyo_globals'];
    }

    // --- 2. Always create a new timestamped Data Source name ---
    $newTitle = $baseName . '_' . date('Ymd_His');

    // --- 3. Build payload ---
    $payload = [
        'data' => [
            'type'       => 'data-source',
            'attributes' => [
                'title'       => $newTitle,
                'visibility'  => 'private',
                'description' => 'Auto-generated ' . current_time('mysql'),
            ],
        ],
    ];

    // --- 4. Prepare request ---
    $args = [
        'headers' => [
            'Authorization' => "Klaviyo-API-Key {$apiKey}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'revision'      => $revision,
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
        'method'  => 'POST',
    ];

    // --- 5. Execute request ---
    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) {
        return ['error' => $res->get_error_message()];
    }

    $code    = (int) wp_remote_retrieve_response_code($res);
    $body    = wp_remote_retrieve_body($res);
    $decoded = json_decode($body, true);

    // --- 6. On success, store new DS info in DB ---
    if ($code >= 200 && $code < 300 && isset($decoded['data']['id'])) {
        $wpdb->update(
            $table,
            [
                'object_ds_name' => $newTitle,
                'object_ds_id'   => $decoded['data']['id'],
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => (int) $globals['id']],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return [
            'info'            => 'New data source created successfully',
            'object_ds_id'    => $decoded['data']['id'],
            'object_ds_name'  => $newTitle,
            'http_status'     => $code,
            'api_response'    => $decoded,
        ];
    }

    // --- 7. Handle API error ---
    return [
        'error'        => 'Failed to create data source',
        'http_status'  => $code,
        'response_raw' => $decoded,
    ];
}

