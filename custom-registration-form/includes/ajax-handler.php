<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the AJAX request to update submission status.
 */
function update_submission_status_callback() {
    // 1. Security Check: Verify the nonce
    check_ajax_referer('crf_update_status_nonce');

    // 2. Security Check: Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
        return;
    }

    // 3. Sanitize inputs
    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
    
    // 4. Validate inputs
    $allowed_statuses = ['New', 'Contacted', 'In Progress', 'Completed'];
    if ($submission_id <= 0 || !in_array($new_status, $allowed_statuses)) {
        wp_send_json_error(array('message' => 'Invalid data provided.'), 400);
        return;
    }

    // 5. Update the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $result = $wpdb->update(
        $table_name,
        array('status' => $new_status), // Data to update
        array('id' => $submission_id),  // WHERE clause
        array('%s'),                    // Data format
        array('%d')                     // WHERE format
    );

    // 6. Send the response
    if ($result === false) {
        wp_send_json_error(array('message' => 'Database update failed.'));
    } else {
        wp_send_json_success(array('message' => 'Status updated successfully.'));
    }
}

// Hook our function into WordPress's AJAX system
add_action('wp_ajax_update_submission_status', 'update_submission_status_callback');