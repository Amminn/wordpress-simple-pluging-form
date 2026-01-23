<?php
/**
 * Plugin Name:       Custom Registration Form CRM
 * Description:       A powerful plugin that creates a custom form and a full-featured CRM with multi-image support, role-based permissions, and Import/Export.
 * Version:           7.2
 * Author:           Amin
 */

if (!defined('ABSPATH')) {
    exit;
}

// =================================================================================
// 1. ACTIVATION HOOK
// =================================================================================
function custom_registration_create_crm_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Database table creation
    $table_submissions = $wpdb->prefix . 'custom_registrations';
    $sql_submissions = "CREATE TABLE $table_submissions (id BIGINT(20) NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, address TEXT NOT NULL, phone_number VARCHAR(50) NOT NULL, note TEXT, image_url TEXT, status VARCHAR(20) DEFAULT 'New' NOT NULL, tags TEXT, flag VARCHAR(20) DEFAULT 'ok' NOT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id)) $charset_collate;";
    dbDelta($sql_submissions);
    $table_notes = $wpdb->prefix . 'custom_registration_notes';
    $sql_notes = "CREATE TABLE $table_notes (note_id BIGINT(20) NOT NULL AUTO_INCREMENT, submission_id BIGINT(20) NOT NULL, note_content TEXT NOT NULL, author_id BIGINT(20) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (note_id), KEY submission_id (submission_id)) $charset_collate;";
    dbDelta($sql_notes);

    // Default tags setup
    if (get_option('crf_master_tags') === false) {
        update_option('crf_master_tags', [
            ['name' => 'VIP', 'color' => '#D1A3E8'],
            ['name' => 'Urgent', 'color' => '#E8A3A3'],
            ['name' => 'Repeat Client', 'color' => '#A3E8D1']
        ]);
    }

    // Add Custom Role and Capabilities
    $capability = 'delete_submissions';

    // Add the "High Level" user role if it doesn't exist.
    add_role(
        'high_level_user',
        __('High Level'),
        [
            'read' => true, // Basic access to the dashboard
            $capability => true,
        ]
    );

    // Grant the 'delete_submissions' capability to the Administrator role.
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap($capability);
    }
}
register_activation_hook(__FILE__, 'custom_registration_create_crm_tables');


// =================================================================================
// SECTIONS 2-4 (Unchanged)
// =================================================================================
function custom_registration_form_shortcode()
{
    ob_start();

    if (isset($_GET['submission-status'])) {
        switch ($_GET['submission-status']) {
            case 'success':
                echo '<div class="crf-message success">The form was sent! We will get back to you soon.</div>';
                break;
            case 'db_error':
                echo '<div class="crf-message error">There was a problem saving your submission. Please contact the site administrator.</div>';
                break;
            case 'recaptcha_failed':
                echo '<div class="crf-message error">reCAPTCHA verification failed. Please try again.</div>';
                break;
            case 'error':
                echo '<div class="crf-message error">There was an error with your submission. Please check the required fields.</div>';
                break;
        }
    }

    $site_key = get_option('crf_recaptcha_site_key');
    // Optionally, add a debug log or display to confirm the key:
    // error_log('CRF site key: ' . print_r($site_key, true));

    ?>
    <?php
    // Helper to get icon path easily
    $icon_path = plugin_dir_url(__FILE__) . 'assets/images/icons/';
    ?>

    <form id="custom-registration-form" class="crf-form-modern" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>"
        method="post" enctype="multipart/form-data">

        <!-- Form Header -->
        <div class="crf-header">
            <h2>Get Your Free Quote</h2>
            <p>Complete the details below for a fast response</p>
        </div>

        <!-- SECTION 1: Contact Info -->
        <div class="crf-section">
            <div class="crf-section-title">
                <!-- Using person-pin.svg as the Contact Info header icon -->
                <img src="<?php echo $icon_path . 'person-pin.svg'; ?>" alt="icon">
                <span>CONTACT INFO</span>
            </div>

            <!-- Name Field -->
            <div class="form-group crf-input-wrapper">
                <img src="<?php echo $icon_path . 'mdi_user.svg'; ?>" class="crf-field-icon" alt="user">
                <input type="text" name="name" id="name" class="crf-input" placeholder="Your Full Name" required
                    maxlength="255">
            </div>

            <!-- Phone Field (Reordered to match screenshot) -->
            <div class="form-group crf-input-wrapper">
                <img src="<?php echo $icon_path . 'phone.svg'; ?>" class="crf-field-icon" alt="phone">
                <input type="text" name="phone_number" id="phone_number" class="crf-input" placeholder="Your Phone" required
                    maxlength="50">
            </div>

            <!-- Address Field -->
            <div class="form-group crf-input-wrapper">
                <img src="<?php echo $icon_path . 'location-filled.svg'; ?>" class="crf-field-icon" alt="location">
                <input type="text" name="address" id="address" class="crf-input" placeholder="Property Address" required
                    maxlength="1000">
            </div>
        </div>

        <!-- SECTION 2: Project Details -->
        <div class="crf-section">
            <div class="crf-section-title">
                <img src="<?php echo $icon_path . 'roofing-rounded.svg'; ?>" alt="icon">
                <span>Project Details</span>
            </div>

            <!-- Note/Description Field -->
            <div class="form-group crf-input-wrapper">
                <img src="<?php echo $icon_path . 'mdi_user.svg'; ?>" class="crf-field-icon top-aligned" alt="icon">
                <textarea name="note" id="note" class="crf-input" rows="4" placeholder="Describe your roofing issue...."
                    maxlength="2000"></textarea>
            </div>
        </div>

        <!-- File Upload (Dashed Style) -->
        <div class="form-group crf-upload-container">
            <div class="crf-upload-label">
                <img src="<?php echo $icon_path . 'camera-filled.svg'; ?>" alt="camera">
                <span>Upload Image (Optional)</span>
            </div>
            <input type="file" name="profile_image[]" id="profile_image" class="crf-file-input"
                accept="image/jpeg,image/png,image/gif" multiple>
        </div>

        <!-- Hidden Fields -->
        <?php wp_nonce_field('custom_form_submit_action', 'custom_form_nonce'); ?>
        <input type="hidden" name="custom_registration_form_submitted" value="1">
        <div id="crf-validation-message" class="crf-message error" style="display:none;"></div>

        <!-- Submit Button -->
        <div class="form-submit crf-form-submit">
            <button type="submit" id="crf-submit-btn" class="crf-submit-btn">
                GET FREE QUOTE <img src="<?php echo $icon_path . 'arrow-right.svg'; ?>" class="crf-submit-icon"
                    alt="arrow-right">
            </button>
        </div>

        <!-- Privacy Footer -->
        <div class="crf-privacy-footer">
            <img src="<?php echo $icon_path . 'lock.svg'; ?>" class="crf-footer-icon" alt="lock"> 100% Privacy Protected &
            secure Data
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('custom-registration-form');
            const errorDiv = document.getElementById('crf-validation-message');
            const siteKey = "<?php echo esc_js($site_key); ?>";
            const submitButton = document.getElementById('crf-submit-btn');

            form.addEventListener('submit', function (event) {
                event.preventDefault(); // stop normal submission
                errorDiv.style.display = 'none';
                errorDiv.innerHTML = '';

                // Custom validation
                const phoneInput = document.getElementById('phone_number');
                const phoneRegex = /^[0-9\s\+\-\(\)]+$/;
                let errors = [];

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                if (phoneInput.value && !phoneRegex.test(phoneInput.value)) {
                    errors.push('Please enter a valid phone number.');
                }
                if (document.getElementById('profile_image').files.length > 2) {
                    errors.push('You can upload a maximum of two images.');
                }

                if (errors.length > 0) {
                    let errorHtml = '<ul>';
                    errors.forEach(e => errorHtml += `<li>${e}</li>`);
                    errorHtml += '</ul>';
                    errorDiv.innerHTML = errorHtml;
                    errorDiv.style.display = 'block';
                    return;
                }

                // Execute reCAPTCHA (Invisible v2)
                grecaptcha.ready(function () {
                    grecaptcha.execute(siteKey, { action: 'submit' }).then(function (token) {
                        // Append token and submit
                        let input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'g-recaptcha-response';
                        input.value = token;
                        form.appendChild(input);
                        form.submit();
                    }).catch(function (err) {
                        errorDiv.innerHTML = 'Error executing reCAPTCHA. Please try again.';
                        errorDiv.style.display = 'block';
                        console.error(err);
                    });
                });
            });
        });
    </script>

    <?php

    return ob_get_clean();
}

add_shortcode('custom_registration_form', 'custom_registration_form_shortcode');
function handle_custom_form_submission()
{
    if (!isset($_POST['custom_registration_form_submitted'])) {
        return;
    }
    $current_page_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!isset($_POST['custom_form_nonce']) || !wp_verify_nonce($_POST['custom_form_nonce'], 'custom_form_submit_action')) {
        wp_safe_redirect(add_query_arg('submission-status', 'error', $current_page_url));
        exit;
    }
    if (empty($_POST['name']) || empty($_POST['address']) || empty($_POST['phone_number'])) {
        wp_safe_redirect(add_query_arg('submission-status', 'error', $current_page_url));
        exit;
    }
    $secret_key = get_option('crf_recaptcha_secret_key');
    if (!empty($secret_key)) {
        if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
            wp_safe_redirect(add_query_arg('submission-status', 'recaptcha_failed', $current_page_url));
            exit;
        }
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', ['body' => ['secret' => $secret_key, 'response' => sanitize_text_field($_POST['g-recaptcha-response']), 'remoteip' => $_SERVER['REMOTE_ADDR'],],]);
        if (is_wp_error($response)) {
            wp_safe_redirect(add_query_arg('submission-status', 'recaptcha_failed', $current_page_url));
            exit;
        }
        $response_body = json_decode(wp_remote_retrieve_body($response));
        if (!$response_body || !$response_body->success) {
            wp_safe_redirect(add_query_arg('submission-status', 'recaptcha_failed', $current_page_url));
            exit;
        }
    }
    $image_urls = [];
    if (isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name'][0])) {
        if (count($_FILES['profile_image']['name']) > 2) {
            wp_safe_redirect(add_query_arg('submission-status', 'error', $current_page_url));
            exit;
        }
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $files = $_FILES['profile_image'];
        foreach ($files['name'] as $key => $value) {
            if ($files['name'][$key]) {
                $file = ['name' => $files['name'][$key], 'type' => $files['type'][$key], 'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key], 'size' => $files['size'][$key]];
                $movefile = wp_handle_upload($file, ['test_form' => false]);
                if ($movefile && !isset($movefile['error'])) {
                    $image_urls[] = $movefile['url'];
                }
            }
        }
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $existing_submission_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE phone_number = %s", $phone_number));
    $tags_to_apply = '';
    if ($existing_submission_count > 0) {
        $tags_to_apply = 'Repeat Client';
    }
    $data_to_insert = ['name' => sanitize_text_field($_POST['name']), 'address' => sanitize_text_field($_POST['address']), 'phone_number' => $phone_number, 'note' => isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '', 'image_url' => implode(',', $image_urls), 'tags' => $tags_to_apply];
    $result = $wpdb->insert($table_name, $data_to_insert);
    if ($result) {
        $to = get_option('admin_email');
        $subject = 'New Form Submission Received';
        $body = "A new submission has been received.\n\n" . "Name: " . sanitize_text_field($_POST['name']) . "\n" . "Address: " . sanitize_text_field($_POST['address']) . "\n" . "Phone Number: " . $phone_number . "\n" . "Note: " . (isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : 'N/A');
        wp_mail($to, $subject, $body);
        set_transient('crf_new_submission', true, 30 * MINUTE_IN_SECONDS);
    }
    if ($result === false) {
        wp_safe_redirect(add_query_arg('submission-status', 'db_error', $current_page_url));
        exit;
    }
    wp_safe_redirect(add_query_arg('submission-status', 'success', $current_page_url));
    exit;
}
add_action('init', 'handle_custom_form_submission');
function crf_handle_import_export()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['action']) && $_GET['action'] == 'export_csv' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'crf_export_nonce')) {
        global $wpdb;
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}custom_registrations", ARRAY_A);
        if ($data) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=submissions_export_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] == 'import_csv' && isset($_FILES['import_csv_file'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'crf_import_nonce')) {
            wp_die('Security check failed.');
        }
        $file = $_FILES['import_csv_file'];
        if ($file['type'] == 'text/csv' && $file['error'] == UPLOAD_ERR_OK) {
            global $wpdb;
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle);
            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                $wpdb->insert($wpdb->prefix . 'custom_registrations', ['name' => isset($data['name']) ? sanitize_text_field($data['name']) : '', 'address' => isset($data['address']) ? sanitize_text_field($data['address']) : '', 'phone_number' => isset($data['phone_number']) ? sanitize_text_field($data['phone_number']) : '', 'note' => isset($data['note']) ? sanitize_textarea_field($data['note']) : '', 'image_url' => isset($data['image_url']) ? sanitize_text_field($data['image_url']) : '', 'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'New', 'tags' => isset($data['tags']) ? sanitize_text_field($data['tags']) : '', 'flag' => isset($data['flag']) ? sanitize_text_field($data['flag']) : 'ok']);
                $count++;
            }
            fclose($handle);
            set_transient('crf_import_notice', "Successfully imported {$count} submissions.", 30);
        } else {
            set_transient('crf_import_notice', "Error: Please upload a valid CSV file.", 30);
        }
        wp_safe_redirect(admin_url('admin.php?page=custom-registrations'));
        exit;
    }
}
add_action('admin_init', 'crf_handle_import_export');


// =================================================================================
// 5. ADMIN AREA (CRM PAGE)
// =================================================================================
function custom_registration_admin_menu()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $new_submission_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'New'");
    $menu_title = 'Submissions';
    if ($new_submission_count > 0) {
        $menu_title .= ' <span class="awaiting-mod"><span class="pending-count">' . $new_submission_count . '</span></span>';
    }
    add_menu_page('Submissions', $menu_title, 'read', 'custom-registrations', 'custom_registrations_page_content', 'dashicons-list-view', 25);
}
add_action('admin_menu', 'custom_registration_admin_menu');
function crf_show_import_notice()
{
    if ($notice = get_transient('crf_import_notice')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        delete_transient('crf_import_notice');
    }
}
add_action('admin_notices', 'crf_show_import_notice');
function custom_registrations_page_content()
{
    global $wpdb;
    $table_submissions = $wpdb->prefix . 'custom_registrations';
    $per_page = 15;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    $where_clauses = [];
    $current_flag_view = isset($_GET['flag']) ? sanitize_text_field($_GET['flag']) : 'ok';
    $where_clauses[] = $wpdb->prepare("flag = %s", $current_flag_view);
    if (!empty($_GET['status'])) {
        $where_clauses[] = $wpdb->prepare("status = %s", sanitize_text_field($_GET['status']));
    }
    if (!empty($_GET['tag'])) {
        $where_clauses[] = $wpdb->prepare("FIND_IN_SET(%s, tags) > 0", sanitize_text_field($_GET['tag']));
    }
    if (!empty($_GET['s'])) {
        $term = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
        $where_clauses[] = $wpdb->prepare("(name LIKE %s OR address LIKE %s OR phone_number LIKE %s OR note LIKE %s)", $term, $term, $term, $term);
    }
    $where_sql = implode(' AND ', $where_clauses);
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_submissions WHERE $where_sql");
    $results = $wpdb->get_results("SELECT * FROM $table_submissions WHERE $where_sql ORDER BY submitted_at DESC LIMIT $per_page OFFSET $offset");
    $master_tags = get_option('crf_master_tags', []);
    $statuses = ['New', 'Contacted', 'In Progress', 'Completed', 'Canceled'];
    ?>
    <div class="wrap crf-crm-wrapper">
        <h1>Submissions</h1>
        <div class="crf-page-actions"><a
                href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=custom-registrations&action=export_csv'), 'crf_export_nonce')); ?>"
                class="button button-secondary">Export to CSV</a><button type="button" class="button button-secondary"
                id="import-btn">Import from CSV</button></div>
        <div id="crf-import-form" style="display:none;margin-top:15px;padding:15px;border:1px solid #ddd;background:#fff;">
            <form method="post" enctype="multipart/form-data"><input type="hidden" name="action"
                    value="import_csv"><?php wp_nonce_field('crf_import_nonce'); ?><input type="file" name="import_csv_file"
                    accept=".csv"><input type="submit" class="button button-primary" value="Upload and Import"></form>
        </div>
        <form method="get" class="crf-filters">
            <ul class="subsubsub">
                <li><a href="?page=custom-registrations&flag=ok"
                        class="<?php echo $current_flag_view == 'ok' ? 'current' : ''; ?>">Active</a> |</li>
                <li><a href="?page=custom-registrations&flag=spam"
                        class="<?php echo $current_flag_view == 'spam' ? 'current' : ''; ?>">Flagged as Spam</a></li>
            </ul>
            <input type="hidden" name="page" value="custom-registrations"><input type="hidden" name="flag"
                value="<?php echo esc_attr($current_flag_view); ?>">
            <p class="search-box"><input type="search" name="s"
                    value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>"><input type="submit" class="button"
                    value="Search"></p><select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($s), selected(isset($_GET['status']) ? $_GET['status'] : '', $s, false), esc_html($s));
                } ?>
            </select><select name="tag">
                <option value="">All Tags</option>
                <?php foreach ($master_tags as $t) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($t['name']), selected(isset($_GET['tag']) ? $_GET['tag'] : '', $t['name'], false), esc_html($t['name']));
                } ?>
            </select><input type="submit" class="button" value="Filter">
            <button type="button" class="button button-secondary" id="manage-tags-btn">Manage Tags</button>
        </form>
        <div class="tablenav top">
            <div class="tablenav-pages"><span class="displaying-num"><?php echo $total_items; ?> items</span><span
                    class="pagination-links"><?php echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'prev_text' => __('&laquo;'), 'next_text' => __('&raquo;'), 'total' => ceil($total_items / $per_page), 'current' => $current_page]); ?></span>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Note</th>
                    <th>Images</th>
                    <th>Contact</th>
                    <th>Tags</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="9">No submissions found.</td>
                    </tr>
                <?php else:
                    foreach ($results as $row): ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->name); ?></strong></td>
                            <td><?php echo esc_html($row->address); ?></td>
                            <td><?php echo nl2br(esc_html($row->note)); ?></td>
                            <td>
                                <div class="crf-image-stack">
                                    <?php if (!empty($row->image_url)) {
                                        $image_urls = explode(',', $row->image_url);
                                        foreach ($image_urls as $url) {
                                            if (!empty($url))
                                                printf('<a href="%s" target="_blank"><img src="%s" width="60" height="60" style="object-fit:cover;" alt="Submission Image"/></a>', esc_url($url), esc_url($url));
                                        }
                                    } ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($row->phone_number); ?></td>
                            <td><?php if (!empty($row->tags)) {
                                $submission_tags = explode(',', $row->tags);
                                foreach ($master_tags as $mt) {
                                    if (in_array($mt['name'], $submission_tags)) {
                                        printf('<span class="crf-tag" style="background-color:%s;">%s</span>', esc_attr($mt['color']), esc_html($mt['name']));
                                    }
                                }
                            } ?>
                            </td>
                            <td><select class="status-changer" data-id="<?php echo esc_attr($row->id); ?>"><?php foreach ($statuses as $s) {
                                   printf('<option value="%s" %s>%s</option>', esc_attr($s), selected($row->status, $s, false), esc_html($s));
                               } ?></select>
                            </td>
                            <td><?php echo date("M j, Y", strtotime($row->submitted_at)); ?></td>
                            <td class="action-buttons">
                                <button type="button" class="button button-secondary button-small view-notes-btn"
                                    data-id="<?php echo esc_attr($row->id); ?>">Notes</button>
                                <button type="button" class="button button-secondary button-small edit-tags-btn"
                                    data-id="<?php echo esc_attr($row->id); ?>"
                                    data-tags="<?php echo esc_attr($row->tags); ?>">Tags</button>
                                <?php if ($current_flag_view === 'ok'): ?><button type="button"
                                        class="button button-secondary button-small spam-button"
                                        data-id="<?php echo esc_attr($row->id); ?>">Spam</button><?php else: ?><button type="button"
                                        class="button button-secondary button-small not-spam-button"
                                        data-id="<?php echo esc_attr($row->id); ?>">Not Spam</button><?php endif; ?>
                                <?php if (current_user_can('delete_submissions')): ?>
                                    <button type="button" class="button button-danger button-small delete-button"
                                        data-id="<?php echo esc_attr($row->id); ?>">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
        <div class="tablenav bottom">
            <div class="tablenav-pages"><span class="displaying-num"><?php echo $total_items; ?> items</span><span
                    class="pagination-links"><?php echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'prev_text' => __('&laquo;'), 'next_text' => __('&raquo;'), 'total' => ceil($total_items / $per_page), 'current' => $current_page]); ?></span>
            </div>
        </div>
    </div>
    <div id="notes-modal" class="crf-modal">
        <div class="crf-modal-content"><span class="crf-modal-close">&times;</span>
            <h2>Internal Notes</h2>
            <div id="notes-list"></div><textarea id="new-note-content" placeholder="Add..."></textarea><button type="button"
                id="add-note-btn" class="button button-primary">Add Note</button>
        </div>
    </div>
    <div id="tags-modal" class="crf-modal">
        <div class="crf-modal-content"><span class="crf-modal-close">&times;</span>
            <h2>Edit Tags</h2>
            <div id="tags-checklist"></div><button type="button" id="save-tags-btn" class="button button-primary">Save
                Tags</button>
        </div>
    </div>
    <div id="manage-tags-modal" class="crf-modal">
        <div class="crf-modal-content"><span class="crf-modal-close">&times;</span>
            <h2>Manage Tags</h2>
            <div id="master-tags-list"></div>
            <div class="crf-manage-tags-form"><input type="text" id="new-tag-name" placeholder="New Tag Name"><input
                    type="color" id="new-tag-color" value="#cccccc"><button type="button" id="add-master-tag-btn"
                    class="button button-primary">Add Tag</button></div>
        </div>
    </div>
    <?php
}

// =================================================================================
// 6. ADMIN AJAX & ASSETS
// =================================================================================
function crf_ajax_router()
{
    check_ajax_referer('crf_crm_nonce');
    if (!current_user_can('read')) { // A base capability to access the dashboard area
        wp_send_json_error(['message' => 'Permission denied.'], 403);
    }

    $action = isset($_POST['route']) ? sanitize_key($_POST['route']) : '';
    global $wpdb;

    switch ($action) {
        case 'update_status':
            $wpdb->update($wpdb->prefix . 'custom_registrations', ['status' => sanitize_text_field($_POST['status'])], ['id' => intval($_POST['id'])]);
            wp_send_json_success();
            break;
        case 'update_flag':
            $wpdb->update($wpdb->prefix . 'custom_registrations', ['flag' => sanitize_text_field($_POST['flag'])], ['id' => intval($_POST['id'])]);
            wp_send_json_success();
            break;
        case 'get_notes':
            $notes = $wpdb->get_results($wpdb->prepare("SELECT n.*, u.display_name FROM {$wpdb->prefix}custom_registration_notes n JOIN {$wpdb->users} u ON n.author_id = u.ID WHERE submission_id = %d ORDER BY created_at DESC", intval($_POST['id'])));
            wp_send_json_success($notes);
            break;
        case 'add_note':
            if (!empty($_POST['content'])) {
                $wpdb->insert($wpdb->prefix . 'custom_registration_notes', ['submission_id' => intval($_POST['id']), 'note_content' => sanitize_textarea_field($_POST['content']), 'author_id' => get_current_user_id()]);
            }
            wp_send_json_success();
            break;
        case 'delete_note':
            $wpdb->delete($wpdb->prefix . 'custom_registration_notes', ['note_id' => intval($_POST['note_id'])]);
            wp_send_json_success();
            break;
        case 'update_tags':
            $tags = isset($_POST['tags']) ? implode(',', array_map('sanitize_text_field', $_POST['tags'])) : '';
            $wpdb->update($wpdb->prefix . 'custom_registrations', ['tags' => $tags], ['id' => intval($_POST['id'])]);
            wp_send_json_success();
            break;
        case 'manage_master_tags':
            $tags = isset($_POST['tags']) ? json_decode(stripslashes($_POST['tags']), true) : [];
            $sanitized_tags = [];
            foreach ($tags as $tag) {
                if (!empty($tag['name'])) {
                    $sanitized_tags[] = ['name' => sanitize_text_field($tag['name']), 'color' => sanitize_hex_color($tag['color'])];
                }
            }
            update_option('crf_master_tags', $sanitized_tags);
            wp_send_json_success($sanitized_tags);
            break;

        case 'delete_submission':
            if (!current_user_can('delete_submissions')) {
                wp_send_json_error(['message' => 'You do not have permission to delete submissions.'], 403);
                return;
            }
            $id = intval($_POST['id']);
            $wpdb->delete($wpdb->prefix . 'custom_registrations', ['id' => $id]);
            $wpdb->delete($wpdb->prefix . 'custom_registration_notes', ['submission_id' => $id]);
            wp_send_json_success();
            break;
    }
    wp_send_json_error(['message' => 'Invalid action.']);
}
add_action('wp_ajax_crf_router', 'crf_ajax_router');

function crf_enqueue_admin_assets($hook)
{
    if ($hook != 'toplevel_page_custom-registrations') {
        return;
    }
    $style_path = plugin_dir_path(__FILE__) . 'assets/css/admin-style.css';
    if (file_exists($style_path)) {
        wp_enqueue_style('crf-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], filemtime($style_path));
    }
    wp_enqueue_script('crf-admin-notifications', plugin_dir_url(__FILE__) . 'assets/js/admin-notifications.js', ['jquery'], null, true);
    wp_localize_script('crf-admin-notifications', 'crf_ajax_object', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('crf_get_new_submission_count'),]);
}
add_action('admin_enqueue_scripts', 'crf_enqueue_admin_assets');

function crf_enqueue_form_assets()
{
    $style_path = plugin_dir_path(__FILE__) . 'assets/css/form-style.css';
    if (file_exists($style_path)) {
        wp_enqueue_style('crf-form-style', plugin_dir_url(__FILE__) . 'assets/css/form-style.css', [], filemtime($style_path));
    }
}
add_action('wp_enqueue_scripts', 'crf_enqueue_form_assets');

function crf_add_admin_footer_js()
{
    if (!isset(get_current_screen()->id) || get_current_screen()->id !== 'toplevel_page_custom-registrations') {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            let currentSubmissionId, masterTags = <?php echo json_encode(get_option('crf_master_tags', [])); ?>;
            const ajax_url = '<?php echo admin_url('admin-ajax.php'); ?>', nonce = '<?php echo wp_create_nonce('crf_crm_nonce'); ?>';
            function doAjax(route, data, callback) { $.post(ajax_url, { action: 'crf_router', _ajax_nonce: nonce, route: route, ...data }, callback || (() => { }), 'json').fail(function (xhr) { if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) { alert('Error: ' + xhr.responseJSON.data.message); } else { alert('An unknown error occurred.'); } }); }
            $('#import-btn').on('click', () => $('#crf-import-form').slideToggle());
            $(document).on('change', '.status-changer', function () { doAjax('update_status', { id: $(this).data('id'), status: $(this).val() }); });
            $(document).on('click', '.spam-button', function () { if (confirm('Are you sure you want to mark this as spam?')) { const button = $(this); const row = button.closest('tr'); doAjax('update_flag', { id: button.data('id'), flag: 'spam' }, () => row.fadeOut(500, () => row.remove())); } });
            $(document).on('click', '.not-spam-button', function () { if (confirm('Are you sure you want to restore this submission to the active list?')) { const button = $(this); const row = button.closest('tr'); doAjax('update_flag', { id: button.data('id'), flag: 'ok' }, () => row.fadeOut(500, () => row.remove())); } });
            $(document).on('click', '.delete-button', function () { if (confirm('Are you sure you want to permanently delete this submission?\nThis action cannot be undone.')) { const button = $(this); const row = button.closest('tr'); doAjax('delete_submission', { id: button.data('id') }, () => row.fadeOut(500, () => row.remove())); } });
            $('.crf-modal-close').on('click', () => $('.crf-modal').hide());
            $(document).on('click', '.view-notes-btn', function () { currentSubmissionId = $(this).data('id'); $('#notes-list').html('Loading...'); $('#notes-modal').show(); doAjax('get_notes', { id: currentSubmissionId }, (res) => { let html = res.success && res.data.length ? '' : '<p>No notes yet.</p>'; if (res.success) res.data.forEach(n => { html += `<div class="note" data-note-id="${n.note_id}"><p>${n.note_content.replace(/\n/g, '<br>')}</p><div class="note-meta">By ${n.display_name} on ${new Date(n.created_at).toLocaleString()} <a href="#" class="delete-note-btn">Delete</a></div></div>`; }); $('#notes-list').html(html); }); });
            $('#add-note-btn').on('click', function () { const content = $('#new-note-content').val(); if (content) doAjax('add_note', { id: currentSubmissionId, content: content }, () => { $('#new-note-content').val(''); $('.view-notes-btn[data-id="' + currentSubmissionId + '"]').click(); }); });
            $(document).on('click', '.delete-note-btn', function (e) { e.preventDefault(); if (confirm('Delete this note?')) { const noteDiv = $(this).closest('.note'); doAjax('delete_note', { note_id: noteDiv.data('note-id') }, () => noteDiv.remove()); } });
            $(document).on('click', '.edit-tags-btn', function () { currentSubmissionId = $(this).data('id'); const currentTags = ($(this).data('tags') || '').toString().split(','); let html = ''; masterTags.forEach(tag => { html += `<div><label><input type="checkbox" class="crf-tag-checkbox" value="${tag.name}" ${currentTags.includes(tag.name) ? 'checked' : ''}> ${tag.name}</label></div>`; }); $('#tags-checklist').html(html); $('#tags-modal').show(); });
            $('#save-tags-btn').on('click', () => { const tags = []; $('.crf-tag-checkbox:checked').each(function () { tags.push($(this).val()); }); doAjax('update_tags', { id: currentSubmissionId, tags: tags }, () => location.reload()); });
            function renderMasterTags() { let html = ''; masterTags.forEach((tag, i) => { html += `<div class="tag-row" data-index="${i}"><input type="text" class="master-tag-name" value="${tag.name}"><input type="color" class="master-tag-color" value="${tag.color}"><button type="button" class="button button-link-delete remove-master-tag-btn">Remove</button></div>`; }); $('#master-tags-list').html(html); }
            $('#manage-tags-btn').on('click', () => { renderMasterTags(); $('#manage-tags-modal').show(); });
            $('#add-master-tag-btn').on('click', function () { const name = $('#new-tag-name').val(); if (name) { masterTags.push({ name: name, color: $('#new-tag-color').val() }); $('#new-tag-name').val(''); renderMasterTags(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); } });
            $(document).on('click', '.remove-master-tag-btn', function () { const i = $(this).closest('.tag-row').data('index'); masterTags.splice(i, 1); renderMasterTags(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); });
            $(document).on('change', '.master-tag-name, .master-tag-color', function () { const i = $(this).closest('.tag-row').data('index'); masterTags[i].name = $(this).closest('.tag-row').find('.master-tag-name').val(); masterTags[i].color = $(this).closest('.tag-row').find('.master-tag-color').val(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); });
        });
    </script>
    <?php
}
add_action('admin_footer', 'crf_add_admin_footer_js');
function crf_get_new_submission_count()
{
    check_ajax_referer('crf_get_new_submission_count', 'nonce');
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'New'");
    wp_send_json_success($count);
}
add_action('wp_ajax_crf_get_new_submission_count', 'crf_get_new_submission_count');

// =================================================================================
// 7. RECAPTCHA SETTINGS PAGE (Unchanged)
// =================================================================================
function crf_add_settings_page()
{
    add_options_page('Custom Form Settings', 'Custom Form CRM', 'manage_options', 'crf-settings', 'crf_render_settings_page');
}
add_action('admin_menu', 'crf_add_settings_page');
function crf_render_settings_page()
{ ?>
    <div class="wrap">
        <h1>Custom Form CRM Settings</h1>
        <form action="options.php" method="post">
            <?php settings_fields('crf_settings_group');
            do_settings_sections('crf-settings');
            submit_button('Save Settings'); ?>
        </form>
    </div> <?php }
function crf_register_settings()
{
    register_setting('crf_settings_group', 'crf_recaptcha_site_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('crf_settings_group', 'crf_recaptcha_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
    add_settings_section('crf_recaptcha_section', 'Google reCAPTCHA v2 Settings', 'crf_recaptcha_section_callback', 'crf-settings');
    add_settings_field('crf_recaptcha_site_key_field', 'reCAPTCHA Site Key', 'crf_render_site_key_field', 'crf-settings', 'crf_recaptcha_section');
    add_settings_field('crf_recaptcha_secret_key_field', 'reCAPTCHA Secret Key', 'crf_render_secret_key_field', 'crf-settings', 'crf_recaptcha_section');
}
add_action('admin_init', 'crf_register_settings');
function crf_recaptcha_section_callback()
{
    echo '<p>Enter the Google reCAPTCHA v2 ("I\'m not a robot") keys for your site. You can get them from the <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA admin console</a>.</p>';
}
function crf_render_site_key_field()
{
    $value = get_option('crf_recaptcha_site_key', '');
    echo '<input type="text" name="crf_recaptcha_site_key" value="' . esc_attr($value) . '" class="regular-text">';
}
function crf_render_secret_key_field()
{
    $value = get_option('crf_recaptcha_secret_key', '');
    echo '<input type="text" name="crf_recaptcha_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
}
// =================================================================================
// 8. ENQUEUE RECAPTCHA SCRIPTS
// =================================================================================
function crf_enqueue_recaptcha_script()
{
    $site_key = get_option('crf_recaptcha_site_key');
    if (empty($site_key))
        return; // Don't load if not configured

    // Enqueue Google's reCAPTCHA v2 Invisible API
    wp_enqueue_script(
        'google-recaptcha',
        'https://www.google.com/recaptcha/api.js?onload=crfRecaptchaCallback&render=explicit',
        [],
        null,
        true
    );

    // Inline script to initialize the reCAPTCHA
    wp_add_inline_script('google-recaptcha', "
        function crfRecaptchaCallback() {
            const btns = document.querySelectorAll('.g-recaptcha');
            btns.forEach(btn => {
                const siteKey = btn.getAttribute('data-sitekey');
                grecaptcha.render(btn, {
                    'sitekey': siteKey,
                    'callback': function(token) {
                        document.getElementById('custom-registration-form').submit();
                    }
                });
            });
        }
    ");
}
add_action('wp_enqueue_scripts', 'crf_enqueue_recaptcha_script');