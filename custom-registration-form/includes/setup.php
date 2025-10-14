<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates/updates the custom database table.
 */
function custom_registration_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        phone_number VARCHAR(50) NOT NULL,
        image_url VARCHAR(255) DEFAULT '' NOT NULL,
        status VARCHAR(20) DEFAULT 'New' NOT NULL, -- ADDED STATUS COLUMN
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}```
**Action Required:** After updating this file, you need to **deactivate and then reactivate** the plugin from your WordPress dashboard. This will trigger the activation hook again and safely add the new `status` column to your database table.

---

### 3. Admin Menu and Submissions Page (MODIFIED)

**File:** `custom-registration-form/admin/admin-menu.php`

This file gets the biggest update. We will add the status column, a filter dropdown, and enqueue our new JavaScript file for AJAX functionality.

```php
<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues admin-specific scripts for our plugin page.
 */
function custom_registration_admin_scripts($hook) {
    // Only load our script on our plugin's page
    if ('toplevel_page_custom-registrations' != $hook) {
        return;
    }
    
    // Enqueue the JavaScript file
    wp_enqueue_script(
        'crf-admin-js',
        plugin_dir_url(__FILE__) . '../assets/js/admin.js',
        array('jquery'), // Depends on jQuery
        '1.0',
        true // Load in the footer
    );

    // Pass data to JavaScript, including the nonce for security
    wp_localize_script('crf-admin-js', 'crf_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('crf_update_status_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'custom_registration_admin_scripts');

/**
 * Adds the admin menu page.
 */
function custom_registration_admin_menu() {
    add_menu_page('Custom Registrations', 'Registrations', 'manage_options', 'custom-registrations', 'custom_registrations_page_content', 'dashicons-list-view', 25);
}
add_action('admin_menu', 'custom_registration_admin_menu');

/**
 * Renders the content for the admin submissions page.
 */
function custom_registrations_page_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';

    // Get the selected status filter, default to empty (all)
    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    $where_clause = '';
    if (!empty($filter_status)) {
        $where_clause = $wpdb->prepare("WHERE status = %s", $filter_status);
    }
    
    $results = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY submitted_at DESC");
    $statuses = ['New', 'Contacted', 'In Progress', 'Completed'];
    ?>
    <div class="wrap">
        <h1>Custom Form Submissions</h1>

        <!-- Status Filter Form -->
        <form method="get">
            <input type="hidden" name="page" value="custom-registrations">
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status) : ?>
                    <option value="<?php echo esc_attr($status); ?>" <?php selected($filter_status, $status); ?>>
                        <?php echo esc_html($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" class="button" value="Filter">
        </form>
        <br>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:15%;">Name</th>
                    <th style="width:20%;">Address</th>
                    <th style="width:15%;">Phone</th>
                    <th style="width:15%;">Image</th>
                    <th style="width:15%;">Status</th>
                    <th style="width:15%;">Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)) : ?>
                    <tr><td colspan="7">No submissions found.</td></tr>
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
                                        <img src="<?php echo esc_url($row->image_url); ?>" style="width: 80px; height: auto;" alt="Profile Image" />
                                    </a>
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="status-changer" data-id="<?php echo esc_attr($row->id); ?>">
                                    <?php foreach ($statuses as $status) : ?>
                                        <option value="<?php echo esc_attr($status); ?>" <?php selected($row->status, $status); ?>>
                                            <?php echo esc_html($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="spinner"></span>
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