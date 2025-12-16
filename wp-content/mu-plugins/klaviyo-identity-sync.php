<?php
/**
 * Plugin Name: Klaviyo Identity Sync
 * Description: Deterministic Klaviyo profile identification at login/register time.
 *              Sends profile create/update request immediately during browser request
 *              to link anonymous Klaviyo cookie with real email identity.
 * Version: 1.0.0
 * Author: NCE
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook into WordPress login event
 * Fires immediately when user successfully logs in
 */
add_action('wp_login', 'nce_klaviyo_identity_on_login', 10, 2);

function nce_klaviyo_identity_on_login($user_login, $user) {
    nce_klaviyo_assert_identity($user->ID, 'login');
}

/**
 * Hook into WordPress user registration
 * Fires immediately when new user account is created
 */
add_action('user_register', 'nce_klaviyo_identity_on_register', 10, 1);

function nce_klaviyo_identity_on_register($user_id) {
    nce_klaviyo_assert_identity($user_id, 'register');
}

// REMOVED: woocommerce_created_customer and profile_update hooks
// Purpose of this plugin: Link user email to Klaviyo cookie on login/register
// so WooCommerce activity gets attached to the correct profile

/**
 * Main function: Assert user identity to Klaviyo
 * 
 * Sends minimal profile create/update request to Klaviyo.
 * This is idempotent - safe to call multiple times.
 * 
 * Because this runs during the same browser request that has the Klaviyo cookie,
 * Klaviyo will link the anonymous browser profile to this email identity.
 * 
 * @param int $user_id WordPress user ID
 * @param string $trigger What triggered this (for logging)
 */
function nce_klaviyo_assert_identity($user_id, $trigger = 'unknown') {
    // Get user data
    $user = get_userdata($user_id);
    if (!$user || empty($user->user_email)) {
        error_log("nce_klaviyo_identity: No user or email for user_id={$user_id}");
        return;
    }
    
    // Only run for users with 'parent' role
    if (!in_array('parent', (array) $user->roles, true)) {
        error_log("nce_klaviyo_identity: Skipping user_id={$user_id} - not a 'parent' role");
        return;
    }
    
    // Block spam profiles - must have last_name in usermeta
    $last_name = get_user_meta($user_id, 'last_name', true);
    if (empty($last_name)) {
        error_log("nce_klaviyo_identity: Skipping user_id={$user_id} - no last_name (spam filter)");
        return;
    }
    
    $email = strtolower(trim($user->user_email));
    $wp_user_id = (string)$user_id; // Store as custom property, not identifier
    
    // Get API credentials - check official Klaviyo plugin first, then custom table
    $api_key = null;
    $api_version = '2025-10-15';
    
    // Method 1: Official Klaviyo WordPress plugin settings
    $klaviyo_settings = get_option('klaviyo_settings');
    if (!empty($klaviyo_settings['api_key'])) {
        $api_key = trim($klaviyo_settings['api_key']);
    }
    
    // Method 2: Fall back to custom wp_klaviyo_globals table
    if (empty($api_key)) {
        global $wpdb;
        $table = $wpdb->prefix . 'klaviyo_globals';
        $config = $wpdb->get_row("SELECT api_key, api_version FROM {$table} LIMIT 1", ARRAY_A);
        
        if (!empty($config['api_key'])) {
            $api_key = trim($config['api_key']);
            $api_version = trim($config['api_version'] ?? '2025-10-15');
        }
    }
    
    if (empty($api_key)) {
        error_log("nce_klaviyo_identity: No API key found in Klaviyo plugin or klaviyo_globals table");
        return;
    }
    
    // Build minimal profile payload
    // This is an identity assertion only - profiles job will enrich with full data
    // Match ONLY by email (no external_id to prevent duplicates)
    $payload = [
        'data' => [
            'type' => 'profile',
            'attributes' => [
                'email' => $email,
                'properties' => [
                    'wp_user_id' => $wp_user_id  // Store as custom property, not identifier
                ]
            ]
        ]
    ];
    
    // Send to Klaviyo Profile Create/Update API
    $url = 'https://a.klaviyo.com/api/profile-import';
    
    $args = [
        'method' => 'POST',
        'headers' => [
            'Authorization' => "Klaviyo-API-Key {$api_key}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'revision' => $api_version,
        ],
        'body' => wp_json_encode($payload),
        'timeout' => 5, // Short timeout - don't block page load
    ];
    
    $response = wp_remote_request($url, $args);
    
    // Log result (don't block on errors)
    if (is_wp_error($response)) {
        error_log("nce_klaviyo_identity: API error for {$email} ({$trigger}): " . $response->get_error_message());
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            error_log("nce_klaviyo_identity: ✓ Identity asserted for {$email} ({$trigger}) - HTTP {$http_code}");
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log("nce_klaviyo_identity: API failed for {$email} ({$trigger}) - HTTP {$http_code}: {$body}");
        }
    }
}

