<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues the stylesheet for the form.
 */
function custom_registration_enqueue_styles() {
    wp_register_style('custom-reg-form-style', plugin_dir_url(__FILE__) . '../assets/css/style.css');
    wp_enqueue_style('custom-reg-form-style');
}
add_action('wp_enqueue_scripts', 'custom_registration_enqueue_styles');

/**
 * Renders the registration form via a shortcode.
 */
function custom_registration_form_shortcode() {
    ob_start();

    // Display success or error messages
    if (isset($_GET['submission-status'])) {
        if ($_GET['submission-status'] == 'success') {
            echo '<div class="crf-message success">The form was sent! We will get back to you soon.</div>';
        } elseif ($_GET['submission-status'] == 'error') {
            $error_message = 'An unknown error occurred. Please try again.';
            if (isset($_GET['reason'])) {
                $reason = sanitize_text_field(wp_unslash($_GET['reason']));
                switch ($reason) {
                    case 'empty': $error_message = 'Please fill out all required fields.'; break;
                    case 'nonce': $error_message = 'Security check failed. Please try again.'; break;
                    case 'db_error': $error_message = 'Could not save your submission. Please try again.'; break;
                    case 'upload_error': $error_message = 'There was an error uploading your image.'; break;
                    // ENHANCEMENT: Add new error message for invalid file types
                    case 'file_type': $error_message = 'Invalid file type. Please upload a JPG, PNG, or GIF image.'; break;
                }
            }
            echo '<div class="crf-message error">' . esc_html($error_message) . '</div>';
        }
    }

    ?>
    <form id="custom-registration-form" action="" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Name <span style="color:red;">*</span></label>
            <input type="text" name="name" id="name" required>
        </div>
        <div class="form-group">
            <label for="address">Address <span style="color:red;">*</span></label>
            <textarea name="address" id="address" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label for="phone_number">Phone Number <span style="color:red;">*</span></label>
            <input type="text" name="phone_number" id="phone_number" required>
        </div>
        <div class="form-group">
            <label for="profile_image">Profile Image</label>
            <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/gif">
        </div>

        <?php wp_nonce_field('custom_form_submit_action', 'custom_form_nonce'); ?>
        <input type="hidden" name="custom_registration_form_submitted" value="1">

        <div class="form-submit">
            <input type="submit" name="submit" value="Register">
        </div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_registration_form', 'custom_registration_form_shortcode');