<?php
declare(strict_types=1);

/**
 * Shared Utility: Create Klaviyo Data Source
 * 
 * Creates a new Klaviyo Data Source for a specific job.
 * Looks up configuration from wp_klaviyo_globals WHERE job_name = $jobName.
 * The data source name will include the job_name: {base_name}_{job_name}_{timestamp}
 * Updates the corresponding row in wp_klaviyo_globals with the new DS name + id.
 * 
 * Can be used by any task that needs to create a new data source.
 */

if (!defined('ABSPATH')) {
    exit;
    
}

/**
 * Create a new Klaviyo Data Source for a specific job.
 * 
 * @param string $jobName Job name to look up in wp_klaviyo_globals (defaults to 'default')
 * @return array Success or error details
 */
function nce_create_klaviyo_data_source_from_db(string $jobName = 'default'): array {
    global $wpdb;
    $table = $wpdb->prefix . 'klaviyo_globals';

    // --- 1. Fetch globals record by job_name ---
    $globals = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE job_name = %s LIMIT 1",
        $jobName
    ), ARRAY_A);
    
    if (!$globals) {
        return [
            'error' => "No configuration found in wp_klaviyo_globals for job_name: {$jobName}",
            'job_name' => $jobName
        ];
    }

    $apiKey    = trim((string) ($globals['api_key'] ?? ''));
    $baseName  = trim((string) ($globals['object_ds_name'] ?? 'default_ds'));
    $revision  = trim((string) ($globals['api_version'] ?? '2025-10-15'));
    $url       = 'https://a.klaviyo.com/api/data-sources';

    if ($apiKey === '') {
        return [
            'error' => 'API key missing in wp_klaviyo_globals',
            'job_name' => $jobName
        ];
    }
    
    if ($revision === '') {
        return [
            'error' => 'API version missing in wp_klaviyo_globals',
            'job_name' => $jobName
        ];
    }

    // --- 2. Always create a new timestamped Data Source name with job_name ---
    $newTitle = $baseName . '_' . $jobName . '_' . date('Ymd_His');

    // --- 3. Build payload ---
    $payload = [
        'data' => [
            'type'       => 'data-source',
            'attributes' => [
                'title'       => $newTitle,
                'visibility'  => 'private',
                'description' => "Auto-generated for job: {$jobName} at " . current_time('mysql'),
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
        return [
            'error' => $res->get_error_message(),
            'job_name' => $jobName
        ];
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
            'job_name'        => $jobName,
            'object_ds_id'    => $decoded['data']['id'],
            'object_ds_name'  => $newTitle,
            'http_status'     => $code,
            'api_response'    => $decoded,
        ];
    }

    // --- 7. Handle API error ---
    return [
        'error'        => 'Failed to create data source',
        'job_name'     => $jobName,
        'http_status'  => $code,
        'response_raw' => $decoded,
    ];
}

