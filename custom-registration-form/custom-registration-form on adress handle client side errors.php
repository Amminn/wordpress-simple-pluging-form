<?php
/**
 * Plugin Name:       Custom Registration Form CRM
 * Description:       A powerful plugin that creates a custom form and a full-featured CRM with multi-image support and Import/Export.
 * Version:           6.8
 * Author:            Your Name
 */

if (!defined('ABSPATH')) { exit; }

// =================================================================================
// SECTIONS 1-4 (Unchanged, omitted for brevity)
// =================================================================================
// ... (Activation, Shortcode, Form Handling, Import/Export code is the same as the previous version)
function custom_registration_create_crm_tables() { global $wpdb; $charset_collate = $wpdb->get_charset_collate(); require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); $table_submissions = $wpdb->prefix . 'custom_registrations'; $sql_submissions = "CREATE TABLE $table_submissions (id BIGINT(20) NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, address TEXT NOT NULL, phone_number VARCHAR(50) NOT NULL, note TEXT, image_url TEXT, status VARCHAR(20) DEFAULT 'New' NOT NULL, tags TEXT, flag VARCHAR(20) DEFAULT 'ok' NOT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id)) $charset_collate;"; dbDelta($sql_submissions); $table_notes = $wpdb->prefix . 'custom_registration_notes'; $sql_notes = "CREATE TABLE $table_notes (note_id BIGINT(20) NOT NULL AUTO_INCREMENT, submission_id BIGINT(20) NOT NULL, note_content TEXT NOT NULL, author_id BIGINT(20) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (note_id), KEY submission_id (submission_id)) $charset_collate;"; dbDelta($sql_notes); if (get_option('crf_master_tags') === false) { update_option('crf_master_tags', [['name' => 'VIP', 'color' => '#D1A3E8'],['name' => 'Urgent', 'color' => '#E8A3A3']]); } }
register_activation_hook(__FILE__, 'custom_registration_create_crm_tables');
function custom_registration_form_shortcode() { ob_start(); echo '<style>#custom-registration-form{max-width:600px;margin:0 auto;padding:25px;border:1px solid #ddd;border-radius:5px;background-color:#f9f9f9;box-shadow:0 2px 5px rgba(0,0,0,0.05)}#custom-registration-form .form-group{margin-bottom:20px}#custom-registration-form label{display:block;margin-bottom:8px;font-weight:bold;color:#333}#custom-registration-form input[type=text],#custom-registration-form textarea,#custom-registration-form input[type=file]{width:100%;padding:12px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}#custom-registration-form .form-submit input[type=submit]{background-color:#0073aa;color:#fff;padding:12px 25px;border:none;border-radius:4px;cursor:pointer;font-size:16px;transition:background-color .3s ease}.crf-message{padding:15px;margin-bottom:20px;border-radius:4px;max-width:600px;margin-left:auto;margin-right:auto}.crf-message.success{color:#155724;background-color:#d4edda;border:1px solid #c3e6cb}.crf-message.error{color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb}</style>'; if (isset($_GET['submission-status'])) { switch ($_GET['submission-status']) { case 'success': echo '<div class="crf-message success">The form was sent! We will get back to you soon.</div>'; break; case 'db_error': echo '<div class="crf-message error">There was a problem saving your submission. Please contact the site administrator.</div>'; break; case 'recaptcha_failed': echo '<div class="crf-message error">reCAPTCHA verification failed. Please try again.</div>'; break; case 'error': echo '<div class="crf-message error">There was an error with your submission. Please check the required fields.</div>'; break; } } $site_key = get_option('crf_recaptcha_site_key'); ?> <form id="custom-registration-form" action="" method="post" enctype="multipart/form-data"> <div class="form-group"><label for="name">Name <span style="color:red;">*</span></label><input type="text" name="name" id="name" required maxlength="255"></div> <div class="form-group"><label for="address">Address <span style="color:red;">*</span></label><input type="text" name="address" id="address" required maxlength="1000"></div> <div class="form-group"><label for="phone_number">Phone Number <span style="color:red;">*</span></label><input type="text" name="phone_number" id="phone_number" required maxlength="50"></div> <div class="form-group"><label for="note">Note</label><textarea name="note" id="note" rows="4" maxlength="2000"></textarea></div> <div class="form-group"><label for="profile_image">Profile Images (Max 2)</label><input type="file" name="profile_image[]" id="profile_image" accept="image/jpeg,image/png,image/gif" multiple></div> <?php wp_nonce_field('custom_form_submit_action', 'custom_form_nonce'); ?> <input type="hidden" name="custom_registration_form_submitted" value="1"> <?php if (!empty($site_key)): ?> <div class="form-group"> <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($site_key); ?>"></div> </div> <script src="https://www.google.com/recaptcha/api.js" async defer></script> <?php endif; ?> <div id="crf-validation-message" class="crf-message error" style="display:none;"></div> <div class="form-submit"><input type="submit" name="submit" value="Register"></div> </form> <script> document.addEventListener('DOMContentLoaded', function() { const form = document.getElementById('custom-registration-form'); const errorDiv = document.getElementById('crf-validation-message'); form.addEventListener('submit', function(event) { let errors = []; errorDiv.innerHTML = ''; const phoneInput = document.getElementById('phone_number'); const phoneRegex = /^[0-9\s\+\-\(\)]+$/; if (phoneInput.value && !phoneRegex.test(phoneInput.value)) { errors.push('Please enter a valid phone number.'); } if (document.getElementById('profile_image').files.length > 2) { errors.push('You can upload a maximum of two images.'); } if (errors.length > 0) { event.preventDefault(); let errorHtml = '<ul>'; errors.forEach(function(error) { errorHtml += '<li>' + error + '</li>'; }); errorHtml += '</ul>'; errorDiv.innerHTML = errorHtml; errorDiv.style.display = 'block'; } else { errorDiv.style.display = 'none'; } }); }); </script> <?php return ob_get_clean(); }
add_shortcode('custom_registration_form', 'custom_registration_form_shortcode');

function handle_custom_form_submission() {
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
    $data_to_insert = ['name' => sanitize_text_field($_POST['name']), 'address' => sanitize_text_field($_POST['address']), 'phone_number' => sanitize_text_field($_POST['phone_number']), 'note' => isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '', 'image_url' => implode(',', $image_urls)];
    $result = $wpdb->insert($table_name, $data_to_insert);

    if ($result) {
        // Send email notification
        $to = get_option('admin_email');
        $subject = 'New Form Submission Received';
        $body = "A new submission has been received.\n\n" .
                "Name: " . sanitize_text_field($_POST['name']) . "\n" .
                "Address: " . sanitize_text_field($_POST['address']) . "\n" .
                "Phone Number: " . sanitize_text_field($_POST['phone_number']) . "\n" .
                "Note: " . (isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : 'N/A');
        wp_mail($to, $subject, $body);

        // Set a transient to indicate a new submission
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
function crf_handle_import_export() { if (!current_user_can('manage_options')) { return; } if (isset($_GET['action']) && $_GET['action'] == 'export_csv' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'crf_export_nonce')) { global $wpdb; $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}custom_registrations", ARRAY_A); if ($data) { header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=submissions_export_' . date('Y-m-d') . '.csv'); $output = fopen('php://output', 'w'); fputcsv($output, array_keys($data[0])); foreach ($data as $row) { fputcsv($output, $row); } fclose($output); } exit; } if (isset($_POST['action']) && $_POST['action'] == 'import_csv' && isset($_FILES['import_csv_file'])) { if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'crf_import_nonce')) { wp_die('Security check failed.'); } $file = $_FILES['import_csv_file']; if ($file['type'] == 'text/csv' && $file['error'] == UPLOAD_ERR_OK) { global $wpdb; $handle = fopen($file['tmp_name'], 'r'); $header = fgetcsv($handle); $count = 0; while (($row = fgetcsv($handle)) !== false) { $data = array_combine($header, $row); $wpdb->insert($wpdb->prefix . 'custom_registrations', ['name' => isset($data['name']) ? sanitize_text_field($data['name']) : '', 'address' => isset($data['address']) ? sanitize_text_field($data['address']) : '', 'phone_number' => isset($data['phone_number']) ? sanitize_text_field($data['phone_number']) : '', 'note' => isset($data['note']) ? sanitize_textarea_field($data['note']) : '', 'image_url' => isset($data['image_url']) ? sanitize_text_field($data['image_url']) : '', 'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'New', 'tags' => isset($data['tags']) ? sanitize_text_field($data['tags']) : '', 'flag' => isset($data['flag']) ? sanitize_text_field($data['flag']) : 'ok']); $count++; } fclose($handle); set_transient('crf_import_notice', "Successfully imported {$count} submissions.", 30); } else { set_transient('crf_import_notice', "Error: Please upload a valid CSV file.", 30); } wp_safe_redirect(admin_url('admin.php?page=custom-registrations')); exit; } }
add_action('admin_init', 'crf_handle_import_export');

// =================================================================================
// 5. ADMIN AREA (CRM PAGE)
// =================================================================================
function custom_registration_admin_menu() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $new_submission_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'New'");
    $menu_title = 'Submissions';
    if ($new_submission_count > 0) {
        $menu_title .= ' <span class="awaiting-mod"><span class="pending-count">' . $new_submission_count . '</span></span>';
    }
    add_menu_page('Submissions', $menu_title, 'manage_options', 'custom-registrations', 'custom_registrations_page_content', 'dashicons-list-view', 25);
}
add_action('admin_menu', 'custom_registration_admin_menu');

function crf_show_import_notice() { if ($notice = get_transient('crf_import_notice')) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>'; delete_transient('crf_import_notice'); } }
add_action('admin_notices', 'crf_show_import_notice');

function custom_registrations_page_content() {
    // When the admin views the page, reset the counter by updating the status of 'New' submissions
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_registrations';
    $wpdb->update($table_name, ['status' => 'Viewed'], ['status' => 'New']);
    
    // ... rest of the page content function is the same
    $table_submissions = $wpdb->prefix . 'custom_registrations';
    $current_flag_view = isset($_GET['flag']) ? sanitize_text_field($_GET['flag']) : 'ok';
    $where_clauses[] = $wpdb->prepare("flag = %s", $current_flag_view);
    if (!empty($_GET['status'])) { $where_clauses[] = $wpdb->prepare("status = %s", sanitize_text_field($_GET['status'])); }
    if (!empty($_GET['tag'])) { $where_clauses[] = $wpdb->prepare("FIND_IN_SET(%s, tags) > 0", sanitize_text_field($_GET['tag'])); }
    if (!empty($_GET['s'])) { $term = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%'; $where_clauses[] = $wpdb->prepare("(name LIKE %s OR address LIKE %s OR phone_number LIKE %s OR note LIKE %s)", $term, $term, $term, $term); }
    $results = $wpdb->get_results("SELECT * FROM $table_submissions WHERE " . implode(' AND ', $where_clauses) . " ORDER BY submitted_at DESC");
    $master_tags = get_option('crf_master_tags', []);
    $statuses = ['New', 'Contacted', 'In Progress', 'Completed', 'Canceled'];
    ?>
    <div class="wrap crf-crm-wrapper">
        <h1>Submissions</h1>
        <div class="crf-page-actions"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=custom-registrations&action=export_csv'), 'crf_export_nonce')); ?>" class="button button-secondary">Export to CSV</a><button type="button" class="button button-secondary" id="import-btn">Import from CSV</button></div>
        <div id="crf-import-form" style="display:none;margin-top:15px;padding:15px;border:1px solid #ddd;background:#fff;"><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="import_csv"><?php wp_nonce_field('crf_import_nonce'); ?><input type="file" name="import_csv_file" accept=".csv"><input type="submit" class="button button-primary" value="Upload and Import"></form></div>
        <form method="get" class="crf-filters">
            <ul class="active-flagged"><li><a href="?page=custom-registrations&flag=ok" class="<?php echo $current_flag_view == 'ok' ? 'current' : ''; ?>">Active</a> |</li><li><a href="?page=custom-registrations&flag=spam" class="<?php echo $current_flag_view == 'spam' ? 'current' : ''; ?>">Flagged as Spam</a></li>
            </ul>
            <input type="hidden" name="page" value="custom-registrations"><input type="hidden" name="flag" value="<?php echo esc_attr($current_flag_view); ?>"><p class="search-box"><input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>"><input type="submit" class="button" value="Search"></p><select name="status"><option value="">All Statuses</option><?php foreach ($statuses as $s) { printf('<option value="%s" %s>%s</option>', esc_attr($s), selected(isset($_GET['status']) ? $_GET['status'] : '', $s, false), esc_html($s)); } ?></select><select name="tag"><option value="">All Tags</option><?php foreach ($master_tags as $t) { printf('<option value="%s" %s>%s</option>', esc_attr($t['name']), selected(isset($_GET['tag']) ? $_GET['tag'] : '', $t['name'], false), esc_html($t['name'])); } ?></select><input type="submit" class="button" value="Filter">
            <button type="button" class="button button-secondary" id="manage-tags-btn">Manage Tags</button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Name</th><th>Address</th><th>Note</th><th>Images</th><th>Contact</th><th>Tags</th><th>Status</th><th>Submitted</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($results)) : ?>
                    <tr><td colspan="9">No submissions found.</td></tr>
                <?php else : foreach ($results as $row) : ?>
                <tr>
                    <td><strong><?php echo esc_html($row->name); ?></strong></td>
                    <td><?php echo esc_html($row->address); ?></td>
                    <td><?php echo nl2br(esc_html($row->note)); ?></td>
                    <td><div class="crf-image-stack"><?php if (!empty($row->image_url)) { $image_urls = explode(',', $row->image_url); foreach ($image_urls as $url) { if (!empty($url)) printf('<a href="%s" target="_blank"><img src="%s" width="60" height="60" style="object-fit:cover;" alt="Submission Image"/></a>', esc_url($url), esc_url($url)); } } ?></div></td>
                    <td><?php echo esc_html($row->phone_number); ?></td>
                    <td><?php if(!empty($row->tags)){ $submission_tags = explode(',', $row->tags); foreach ($master_tags as $mt) { if(in_array($mt['name'], $submission_tags)) { printf('<span class="crf-tag" style="background-color:%s;">%s</span>', esc_attr($mt['color']), esc_html($mt['name']));}}} ?></td>
                    <td><select class="status-changer" data-id="<?php echo esc_attr($row->id); ?>"><?php foreach ($statuses as $s) { printf('<option value="%s" %s>%s</option>', esc_attr($s), selected($row->status, $s, false), esc_html($s)); } ?></select></td>
                    <td><?php echo date("M j, Y", strtotime($row->submitted_at)); ?></td>
                    <td class="action-buttons">
                        <button type="button" class="button button-secondary button-small view-notes-btn" data-id="<?php echo esc_attr($row->id); ?>">Notes</button>
                        <button type="button" class="button button-secondary button-small edit-tags-btn" data-id="<?php echo esc_attr($row->id); ?>" data-tags="<?php echo esc_attr($row->tags); ?>">Tags</button>

                        <!-- MODIFICATION START: Conditionally show Spam/Not Spam buttons -->
                        <?php if ($current_flag_view === 'ok') : ?>
                            <button type="button" class="button button-secondary button-small spam-button" data-id="<?php echo esc_attr($row->id); ?>">Spam</button>
                        <?php else : // We are in the spam view ?>
                            <button type="button" class="button button-secondary button-small spam-button not-spam-button" data-id="<?php echo esc_attr($row->id); ?>">Not Spam</button>
                        <?php endif; ?>
                        <!-- MODIFICATION END -->

                        <button type="button" class="button button-secondary button-small spam-button" data-id="<?php echo esc_attr($row->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div id="notes-modal" class="crf-modal"><div class="crf-modal-content"><span class="crf-modal-close">&times;</span><h2>Internal Notes</h2><div id="notes-list"></div><textarea id="new-note-content" placeholder="Add..."></textarea><button type="button" id="add-note-btn" class="button button-primary">Add Note</button></div></div>
    <div id="tags-modal" class="crf-modal"><div class="crf-modal-content"><span class="crf-modal-close">&times;</span><h2>Edit Tags</h2><div id="tags-checklist"></div><button type="button" id="save-tags-btn" class="button button-primary">Save Tags</button></div></div>
    <div id="manage-tags-modal" class="crf-modal"><div class="crf-modal-content"><span class="crf-modal-close">&times;</span><h2>Manage Tags</h2><div id="master-tags-list"></div><div class="crf-manage-tags-form"><input type="text" id="new-tag-name" placeholder="New Tag Name"><input type="color" id="new-tag-color" value="#cccccc"><button type="button" id="add-master-tag-btn" class="button button-primary">Add Tag</button></div></div></div>
    <?php
}

// =================================================================================
// 6. ADMIN AJAX & ASSETS
// =================================================================================
function crf_ajax_router() { check_ajax_referer('crf_crm_nonce'); if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Permission denied.'], 403); } $action = isset($_POST['route']) ? sanitize_key($_POST['route']) : ''; global $wpdb; switch ($action) { case 'update_status': $wpdb->update($wpdb->prefix . 'custom_registrations', ['status' => sanitize_text_field($_POST['status'])], ['id' => intval($_POST['id'])]); wp_send_json_success(); break; case 'update_flag': $wpdb->update($wpdb->prefix . 'custom_registrations', ['flag' => sanitize_text_field($_POST['flag'])], ['id' => intval($_POST['id'])]); wp_send_json_success(); break; case 'get_notes': $notes = $wpdb->get_results($wpdb->prepare("SELECT n.*, u.display_name FROM {$wpdb->prefix}custom_registration_notes n JOIN {$wpdb->users} u ON n.author_id = u.ID WHERE submission_id = %d ORDER BY created_at DESC", intval($_POST['id']))); wp_send_json_success($notes); break; case 'add_note': if (!empty($_POST['content'])) { $wpdb->insert($wpdb->prefix . 'custom_registration_notes', ['submission_id' => intval($_POST['id']), 'note_content' => sanitize_textarea_field($_POST['content']), 'author_id' => get_current_user_id()]); } wp_send_json_success(); break; case 'delete_note': $wpdb->delete($wpdb->prefix . 'custom_registration_notes', ['note_id' => intval($_POST['note_id'])]); wp_send_json_success(); break; case 'update_tags': $tags = isset($_POST['tags']) ? implode(',', array_map('sanitize_text_field', $_POST['tags'])) : ''; $wpdb->update($wpdb->prefix . 'custom_registrations', ['tags' => $tags], ['id' => intval($_POST['id'])]); wp_send_json_success(); break; case 'delete_submission': $id = intval($_POST['id']); $wpdb->delete($wpdb->prefix . 'custom_registrations', ['id' => $id]); $wpdb->delete($wpdb->prefix . 'custom_registration_notes', ['submission_id' => $id]); wp_send_json_success(); break; case 'manage_master_tags': $tags = isset($_POST['tags']) ? json_decode(stripslashes($_POST['tags']), true) : []; $sanitized_tags = []; foreach ($tags as $tag) { if (!empty($tag['name'])) { $sanitized_tags[] = ['name' => sanitize_text_field($tag['name']), 'color' => sanitize_hex_color($tag['color'])]; } } update_option('crf_master_tags', $sanitized_tags); wp_send_json_success($sanitized_tags); break; } wp_send_json_error(['message' => 'Invalid action.']); }
add_action('wp_ajax_crf_router', 'crf_ajax_router');

function crf_enqueue_admin_assets($hook) {
    if ($hook != 'toplevel_page_custom-registrations') {
        return;
    }
    $style_path = plugin_dir_path(__FILE__) . 'assets/css/admin-style.css';
    if (file_exists($style_path)) {
        wp_enqueue_style('crf-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], filemtime($style_path));
    }

    // Enqueue a new JS file for the notification counter
    wp_enqueue_script('crf-admin-notifications', plugin_dir_url(__FILE__) . 'assets/js/admin-notifications.js', ['jquery'], null, true);
    wp_localize_script('crf-admin-notifications', 'crf_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('crf_get_new_submission_count'),
    ]);
}
add_action('admin_enqueue_scripts', 'crf_enqueue_admin_assets');


function crf_add_admin_footer_js() {
    if (!isset(get_current_screen()->id) || get_current_screen()->id !== 'toplevel_page_custom-registrations') { return; }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        let currentSubmissionId, masterTags = <?php echo json_encode(get_option('crf_master_tags', [])); ?>;
        const ajax_url = '<?php echo admin_url('admin-ajax.php'); ?>', nonce = '<?php echo wp_create_nonce('crf_crm_nonce'); ?>';
        function doAjax(route, data, callback) { $.post(ajax_url, { action: 'crf_router', _ajax_nonce: nonce, route: route, ...data }, callback || (() => {}), 'json'); }
        $('#import-btn').on('click', () => $('#crf-import-form').slideToggle());
        $(document).on('change', '.status-changer', function() { doAjax('update_status', { id: $(this).data('id'), status: $(this).val() }); });

        $(document).on('click', '.spam-button', function() {
            if (confirm('Are you sure you want to mark this as spam?')) {
                const button = $(this);
                const row = button.closest('tr');
                doAjax('update_flag', { id: button.data('id'), flag: 'spam' }, () => row.fadeOut(500, () => row.remove()));
            }
        });

        // MODIFICATION START: Added click handler for the new "Not Spam" button.
        $(document).on('click', '.not-spam-button', function() {
            if (confirm('Are you sure you want to restore this submission to the active list?')) {
                const button = $(this);
                const row = button.closest('tr');
                doAjax('update_flag', { id: button.data('id'), flag: 'ok' }, () => row.fadeOut(500, () => row.remove()));
            }
        });
        // MODIFICATION END

        $(document).on('click', '.delete-button', function() {
            if (confirm('Are you sure you want to permanently delete this submission?\nThis action cannot be undone.')) {
                const button = $(this);
                const row = button.closest('tr');
                doAjax('delete_submission', { id: button.data('id') }, () => row.fadeOut(500, () => row.remove()));
            }
        });

        $('.crf-modal-close').on('click', () => $('.crf-modal').hide());
        $(document).on('click', '.view-notes-btn', function() { currentSubmissionId = $(this).data('id'); $('#notes-list').html('Loading...'); $('#notes-modal').show(); doAjax('get_notes', { id: currentSubmissionId }, (res) => { let html = res.success && res.data.length ? '' : '<p>No notes yet.</p>'; if (res.success) res.data.forEach(n => { html += `<div class="note" data-note-id="${n.note_id}"><p>${n.note_content.replace(/\n/g, '<br>')}</p><div class="note-meta">By ${n.display_name} on ${new Date(n.created_at).toLocaleString()} <a href="#" class="delete-note-btn">Delete</a></div></div>`; }); $('#notes-list').html(html); }); });
        $('#add-note-btn').on('click', function() { const content = $('#new-note-content').val(); if (content) doAjax('add_note', { id: currentSubmissionId, content: content }, () => { $('#new-note-content').val(''); $('.view-notes-btn[data-id="' + currentSubmissionId + '"]').click(); }); });
        $(document).on('click', '.delete-note-btn', function(e) { e.preventDefault(); if (confirm('Delete this note?')) { const noteDiv = $(this).closest('.note'); doAjax('delete_note', { note_id: noteDiv.data('note-id') }, () => noteDiv.remove()); } });
        $(document).on('click', '.edit-tags-btn', function() { currentSubmissionId = $(this).data('id'); const currentTags = ($(this).data('tags') || '').toString().split(','); let html = ''; masterTags.forEach(tag => { html += `<div><label><input type="checkbox" class="crf-tag-checkbox" value="${tag.name}" ${currentTags.includes(tag.name) ? 'checked' : ''}> ${tag.name}</label></div>`; }); $('#tags-checklist').html(html); $('#tags-modal').show(); });
        $('#save-tags-btn').on('click', () => { const tags = []; $('.crf-tag-checkbox:checked').each(function() { tags.push($(this).val()); }); doAjax('update_tags', { id: currentSubmissionId, tags: tags }, () => location.reload()); });
        function renderMasterTags() { let html = ''; masterTags.forEach((tag, i) => { html += `<div class="tag-row" data-index="${i}"><input type="text" class="master-tag-name" value="${tag.name}"><input type="color" class="master-tag-color" value="${tag.color}"><button type="button" class="button button-link-delete remove-master-tag-btn">Remove</button></div>`; }); $('#master-tags-list').html(html); }
        $('#manage-tags-btn').on('click', () => { renderMasterTags(); $('#manage-tags-modal').show(); });
        $('#add-master-tag-btn').on('click', function() { const name = $('#new-tag-name').val(); if (name) { masterTags.push({ name: name, color: $('#new-tag-color').val() }); $('#new-tag-name').val(''); renderMasterTags(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); } });
        $(document).on('click', '.remove-master-tag-btn', function() { const i = $(this).closest('.tag-row').data('index'); masterTags.splice(i, 1); renderMasterTags(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); });
        $(document).on('change', '.master-tag-name, .master-tag-color', function() { const i = $(this).closest('.tag-row').data('index'); masterTags[i].name = $(this).closest('.tag-row').find('.master-tag-name').val(); masterTags[i].color = $(this).closest('.tag-row').find('.master-tag-color').val(); doAjax('manage_master_tags', { tags: JSON.stringify(masterTags) }); });
    });
    </script>
    <?php
}
add_action('admin_footer', 'crf_add_admin_footer_js');

// New function to handle the AJAX request for the new submission count
function crf_get_new_submission_count() {
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
// ... (The reCAPTCHA settings page code is the same as the previous version)
function crf_add_settings_page() { add_options_page('Custom Form Settings', 'Custom Form CRM', 'manage_options', 'crf-settings', 'crf_render_settings_page'); }
add_action('admin_menu', 'crf_add_settings_page');
function crf_render_settings_page() { ?> <div class="wrap"> <h1>Custom Form CRM Settings</h1> <form action="options.php" method="post"> <?php settings_fields('crf_settings_group'); do_settings_sections('crf-settings'); submit_button('Save Settings'); ?> </form> </div> <?php }
function crf_register_settings() { register_setting('crf_settings_group', 'crf_recaptcha_site_key', ['sanitize_callback' => 'sanitize_text_field']); register_setting('crf_settings_group', 'crf_recaptcha_secret_key', ['sanitize_callback' => 'sanitize_text_field']); add_settings_section('crf_recaptcha_section', 'Google reCAPTCHA v2 Settings', 'crf_recaptcha_section_callback', 'crf-settings'); add_settings_field('crf_recaptcha_site_key_field', 'reCAPTCHA Site Key', 'crf_render_site_key_field', 'crf-settings', 'crf_recaptcha_section'); add_settings_field('crf_recaptcha_secret_key_field', 'reCAPTCHA Secret Key', 'crf_render_secret_key_field', 'crf-settings', 'crf_recaptcha_section'); }
add_action('admin_init', 'crf_register_settings');
function crf_recaptcha_section_callback() { echo '<p>Enter the Google reCAPTCHA v2 ("I\'m not a robot") keys for your site. You can get them from the <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA admin console</a>.</p>'; }
function crf_render_site_key_field() { $value = get_option('crf_recaptcha_site_key', ''); echo '<input type="text" name="crf_recaptcha_site_key" value="' . esc_attr($value) . '" class="regular-text">'; }
function crf_render_secret_key_field() { $value = get_option('crf_recaptcha_secret_key', ''); echo '<input type="text" name="crf_recaptcha_secret_key" value="' . esc_attr($value) . '" class="regular-text">'; }

// Create a new JavaScript file in your plugin's assets/js/ directory named admin-notifications.js
// and add the following code to it.

/*
jQuery(document).ready(function($) {
    function updateSubmissionCounter() {
        $.ajax({
            url: crf_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'crf_get_new_submission_count',
                nonce: crf_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    var count = parseInt(response.data, 10);
                    var menu_item = $('#toplevel_page_custom-registrations .wp-menu-name');
                    var counter = menu_item.find('.awaiting-mod');

                    if (count > 0) {
                        if (counter.length) {
                            counter.find('.pending-count').text(count);
                        } else {
                            menu_item.append(' <span class="awaiting-mod"><span class="pending-count">' + count + '</span></span>');
                        }
                    } else {
                        counter.remove();
                    }
                }
            }
        });
    }

    // Check for new submissions every 30 seconds
    setInterval(updateSubmissionCounter, 30000);
});
*/