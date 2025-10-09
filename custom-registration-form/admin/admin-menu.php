<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds the admin menu page to view submissions.
 */
function custom_registration_admin_menu() {
    add_menu_page(
        'Custom Registrations',
        'Registrations',
        'manage_options',
        'custom-registrations',
        'custom_registrations_page_content',
        'dashicons-list-view',
        25
    );
}
add_action('admin_menu', 'custom_registration_admin_menu');

/**
 * Renders the content for the admin submissions page.
 */
function custom_registrations_page_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");
    ?>
    <div class="wrap">
        <h1>Custom Form Submissions</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:15%;">Name</th>
                    <th style="width:25%;">Address</th>
                    <th style="width:15%;">Phone Number</th>
                    <th style="width:20%;">Profile Image</th>
                    <th style="width:20%;">Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)) : ?>
                    <tr><td colspan="6">No submissions found.</td></tr>
                <?php else : ?>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->id); ?></td>
                            <td><?php echo esc_html($row->name); ?></td>
                            <td><?php echo esc_html($row->address); ?></td>
                            <td><?php echo esc_html($row->phone_number); ?></td>
                            <td>
                                <?php if (!empty($row->image_url)) : ?>
                                    <a href="<?php echo esc_url($row->image_url); ?>" target="_blank">
                                        <img src="<?php echo esc_url($row->image_url); ?>" style="width: 100px; height: auto;" alt="Profile Image" />
                                    </a>
                                <?php else : ?>
                                    No Image Uploaded
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row->submitted_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}