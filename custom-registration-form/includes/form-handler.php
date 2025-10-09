<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the form submission by hooking into the 'init' action.
 */
function handle_custom_form_submission() {
    if (!isset($_POST['custom_registration_form_submitted'])) {
        return;
    }

    $current_page_url = strtok($_SERVER['REQUEST_URI'], '?');

    if (!isset($_POST['custom_form_nonce']) || !wp_verify_nonce($_POST['custom_form_nonce'], 'custom_form_submit_action')) {
        wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'nonce'], $current_page_url));
        exit;
    }

    if (empty($_POST['name']) || empty($_POST['address']) || empty($_POST['phone_number'])) {
        wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'empty'], $current_page_url));
        exit;
    }

    $name = sanitize_text_field($_POST['name']);
    $address = sanitize_textarea_field($_POST['address']);
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $image_url = '';

    // --- SECURITY ENHANCEMENT: STRICT FILE VALIDATION ---
    if (isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        
        $uploaded_file = $_FILES['profile_image'];

        // Check 1: Validate file extension
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_extensions)) {
            wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'file_type'], $current_page_url));
            exit;
        }

        // Check 2: Validate the actual MIME type of the file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
        finfo_close($finfo);
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime_type, $allowed_mime_types)) {
            wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'file_type'], $current_page_url));
            exit;
        }

        // Now that validation passed, let WordPress handle the secure upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $movefile = wp_handle_upload($uploaded_file, array('test_form' => false));
        if ($movefile && !isset($movefile['error'])) {
            $image_url = $movefile['url'];
        } else {
            wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'upload_error'], $current_page_url));
            exit;
        }
    }
    // --- END SECURITY ENHANCEMENT ---


    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $result = $wpdb->insert($table_name, ['name' => $name, 'address' => $address, 'phone_number' => $phone_number, 'image_url' => $image_url]);
    
    if ($result === false) {
        wp_safe_redirect(add_query_arg(['submission-status' => 'error', 'reason' => 'db_error'], $current_page_url));
    } else {
        $admin_email = get_option('admin_email');
        $subject = 'New Form Submission Received';
        $message_body = '<html><body>' .
            '<h2>A new registration has been submitted.</h2>' .
            '<p><strong>Name:</strong> ' . esc_html($name) . '</p>' .
            '<p><strong>Address:</strong> ' . nl2br(esc_html($address)) . '</p>' .
            '<p><strong>Phone Number:</strong> ' . esc_html($phone_number) . '</p>' .
            (!empty($image_url) ? '<p><strong>Profile Image:</strong> <a href="' . esc_url($image_url) . '">' . esc_url($image_url) . '</a></p>' : '<p><strong>Profile Image:</strong> Not provided</p>') .
            '<hr><p>You can view all submissions in your WordPress dashboard.</p>' .
            '</body></html>';
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($admin_email, $subject, $message_body, $headers);
        wp_safe_redirect(add_query_arg('submission-status', 'success', $current_page_url));
    }
    exit;
}
add_action('init', 'handle_custom_form_submission');