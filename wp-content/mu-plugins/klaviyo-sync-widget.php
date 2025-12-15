<?php
/**
 * Plugin Name: Klaviyo Sync Widget Shortcode
 * Description: Adds [klaviyo_sync] shortcode that loads the widget from nce-runner-task-9/widget.html
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit; 

add_shortcode('klaviyo_sync', function() {
    $widget_path = ABSPATH . 'wp-content/wp-custom-scripts/nce-runner-task-9/widget.html';
    
    if (!file_exists($widget_path)) {
        return '<p style="color:red;">Widget file not found: ' . esc_html($widget_path) . '</p>';
    }
    
    $contents = file_get_contents($widget_path);
    
    if ($contents === false) {
        return '<p style="color:red;">Unable to load widget file.</p>';
    }
    
    return $contents;
});

 