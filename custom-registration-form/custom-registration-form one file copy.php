<?php
/**
 * Plugin Name:       Custom Registration Form CRM
 * Description:       A powerful plugin that creates a custom form and a full-featured CRM with multi-image support and Import/Export.
 * Version:           6.0
 * Author:            Your Name
 */

if (!defined('ABSPATH')) { exit; }

// =================================================================================
// 1. PLUGIN ACTIVATION (DATABASE SETUP)
// =================================================================================
function custom_registration_create_crm_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $table_submissions = $wpdb->prefix . 'custom_registrations';
    $sql_submissions = "CREATE TABLE $table_submissions (id BIGINT(20) NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, address TEXT NOT NULL, phone_number VARCHAR(50) NOT NULL, image_url TEXT, status VARCHAR(20) DEFAULT 'New' NOT NULL, tags TEXT, flag VARCHAR(20) DEFAULT 'ok' NOT NULL, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id)) $charset_collate;";
    dbDelta($sql_submissions);
    $table_notes = $wpdb->prefix . 'custom_registration_notes';
    $sql_notes = "CREATE TABLE $table_notes (note_id BIGINT(20) NOT NULL AUTO_INCREMENT, submission_id BIGINT(20) NOT NULL, note_content TEXT NOT NULL, author_id BIGINT(20) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (note_id), KEY submission_id (submission_id)) $charset_collate;";
    dbDelta($sql_notes);
    if (get_option('crf_master_tags') === false) { update_option('crf_master_tags', [['name' => 'VIP', 'color' => '#D1A3E8'],['name' => 'Urgent', 'color' => '#E8A3A3']]); }
}
register_activation_hook(__FILE__, 'custom_registration_create_crm_tables');

// =================================================================================
// 2. FRONT-END FORM (SHORTCODE AND STYLING)
// =================================================================================
function custom_registration_form_shortcode() {
    ob_start();
    echo '<style>#custom-registration-form{max-width:600px;margin:0 auto;padding:25px;border:1px solid #ddd;border-radius:5px;background-color:#f9f9f9;box-shadow:0 2px 5px rgba(0,0,0,0.05)}#custom-registration-form .form-group{margin-bottom:20px}#custom-registration-form label{display:block;margin-bottom:8px;font-weight:bold;color:#333}#custom-registration-form input[type=text],#custom-registration-form textarea,#custom-registration-form input[type=file]{width:100%;padding:12px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}#custom-registration-form .form-submit input[type=submit]{background-color:#0073aa;color:#fff;padding:12px 25px;border:none;border-radius:4px;cursor:pointer;font-size:16px;transition:background-color .3s ease}.crf-message{padding:15px;margin-bottom:20px;border-radius:4px;max-width:600px;margin-left:auto;margin-right:auto}.crf-message.success{color:#155724;background-color:#d4edda;border:1px solid #c3e6cb}.crf-message.error{color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb}</style>';
    if (isset($_GET['submission-status'])) { if ($_GET['submission-status'] == 'success') { echo '<div class="crf-message success">The form was sent! We will get back to you soon.</div>'; } }
    ?>
    <form id="custom-registration-form" action="" method="post" enctype="multipart/form-data">
        <div class="form-group"><label for="name">Name <span style="color:red;">*</span></label><input type="text" name="name" id="name" required></div>
        <div class="form-group"><label for="address">Address <span style="color:red;">*</span></label><textarea name="address" id="address" rows="4" required></textarea></div>
        <div class="form-group"><label for="phone_number">Phone Number <span style="color:red;">*</span></label><input type="text" name="phone_number" id="phone_number" required></div>
        <div class="form-group"><label for="profile_image">Profile Images</label><input type="file" name="profile_image[]" id="profile_image" accept="image/jpeg,image/png,image/gif" multiple></div>
        <?php wp_nonce_field('custom_form_submit_action', 'custom_form_nonce'); ?>
        <input type="hidden" name="custom_registration_form_submitted" value="1">
        <div class="form-submit"><input type="submit" name="submit" value="Register"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_registration_form', 'custom_registration_form_shortcode');

// =================================================================================
// 3. FORM SUBMISSION HANDLING
// =================================================================================
function handle_custom_form_submission() {
    if (!isset($_POST['custom_registration_form_submitted'])) { return; }
    $current_page_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!isset($_POST['custom_form_nonce']) || !wp_verify_nonce($_POST['custom_form_nonce'], 'custom_form_submit_action')) { wp_safe_redirect(add_query_arg('submission-status', 'error', $current_page_url)); exit; }
    if (empty($_POST['name']) || empty($_POST['address']) || empty($_POST['phone_number'])) { wp_safe_redirect(add_query_arg('submission-status', 'error', $current_page_url)); exit; }
    
    $image_urls = [];
    if (isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name'][0])) {
        if (!function_exists('wp_handle_upload')) { require_once(ABSPATH . 'wp-admin/includes/file.php'); }
        $files = $_FILES['profile_image'];
        foreach ($files['name'] as $key => $value) {
            if ($files['name'][$key]) {
                $file = ['name' => $files['name'][$key], 'type' => $files['type'][$key], 'tmp_name' => $files['tmp_name'][$key], 'error' => $files['error'][$key], 'size' => $files['size'][$key]];
                $movefile = wp_handle_upload($file, ['test_form' => false]);
                if ($movefile && !isset($movefile['error'])) { $image_urls[] = $movefile['url']; }
            }
        }
    }
    
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'custom_registrations', [
        'name' => sanitize_text_field($_POST['name']),
        'address' => sanitize_textarea_field($_POST['address']),
        'phone_number' => sanitize_text_field($_POST['phone_number']),
        'image_url' => implode(',', $image_urls)
    ]);
    
    wp_safe_redirect(add_query_arg('submission-status', 'success', $current_page_url));
    exit;
}
add_action('init', 'handle_custom_form_submission');

// =================================================================================
// 4. IMPORT / EXPORT HANDLING
// =================================================================================
function crf_handle_import_export() {
    if (!current_user_can('manage_options')) { return; }
    if (isset($_GET['action']) && $_GET['action'] == 'export_csv' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'crf_export_nonce')) {
        global $wpdb; $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}custom_registrations", ARRAY_A);
        if ($data) {
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=submissions_export_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w'); fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) { fputcsv($output, $row); } fclose($output);
        }
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] == 'import_csv' && isset($_FILES['import_csv_file'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'crf_import_nonce')) { wp_die('Security check failed.'); }
        $file = $_FILES['import_csv_file'];
        if ($file['type'] == 'text/csv' && $file['error'] == UPLOAD_ERR_OK) {
            global $wpdb; $handle = fopen($file['tmp_name'], 'r'); $header = fgetcsv($handle); $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                $wpdb->insert($wpdb->prefix . 'custom_registrations', ['name' => sanitize_text_field($data['name']), 'address' => sanitize_textarea_field($data['address']), 'phone_number' => sanitize_text_field($data['phone_number']), 'image_url' => sanitize_text_field($data['image_url']), 'status' => sanitize_text_field($data['status']), 'tags' => sanitize_text_field($data['tags']), 'flag' => sanitize_text_field($data['flag'])]);
                $count++;
            }
            fclose($handle); set_transient('crf_import_notice', "Successfully imported {$count} submissions.", 30);
        } else { set_transient('crf_import_notice', "Error: Please upload a valid CSV file.", 30); }
        wp_safe_redirect(admin_url('admin.php?page=custom-registrations')); exit;
    }
}
add_action('admin_init', 'crf_handle_import_export');

// =================================================================================
// 5. ADMIN AREA (CRM PAGE)
// =================================================================================
function custom_registration_admin_menu() { add_menu_page('Submissions', 'Submissions', 'manage_options', 'custom-registrations', 'custom_registrations_page_content', 'dashicons-list-view', 25); }
add_action('admin_menu', 'custom_registration_admin_menu');

function crf_show_import_notice() { if ($notice = get_transient('crf_import_notice')) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>'; delete_transient('crf_import_notice'); } }
add_action('admin_notices', 'crf_show_import_notice');

function custom_registrations_page_content() {
    global $wpdb;
    $table_submissions = $wpdb->prefix . 'custom_registrations';
    $current_flag_view = isset($_GET['flag']) ? sanitize_text_field($_GET['flag']) : 'ok';
    $where_clauses[] = $wpdb->prepare("flag = %s", $current_flag_view);
    if (!empty($_GET['status'])) { $where_clauses[] = $wpdb->prepare("status = %s", sanitize_text_field($_GET['status'])); }
    if (!empty($_GET['tag'])) { $where_clauses[] = $wpdb->prepare("FIND_IN_SET(%s, tags) > 0", sanitize_text_field($_GET['tag'])); }
    if (!empty($_GET['s'])) { $term = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%'; $where_clauses[] = $wpdb->prepare("(name LIKE %s OR address LIKE %s OR phone_number LIKE %s)", $term, $term, $term); }
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
            <thead><tr><th>Name / Address</th><th>Images</th><th>Contact</th><th>Tags</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($results)) : ?> <tr><td colspan="7">No submissions found.</td></tr> <?php else : foreach ($results as $row) : ?>
                <tr>
                    <td><strong><?php echo esc_html($row->name); ?></strong><br><small><?php echo esc_html($row->address); ?></small></td>
                    <td><div class="crf-image-stack"><?php if (!empty($row->image_url)) { $image_urls = explode(',', $row->image_url); foreach ($image_urls as $url) { if (!empty($url)) printf('<a href="%s" target="_blank"><img src="%s" width="60" height="60" style="object-fit:cover;" alt="Submission Image"/></a>', esc_url($url), esc_url($url)); } } ?></div></td>
                    <td><?php echo esc_html($row->phone_number); ?></td>
                    <td><?php if(!empty($row->tags)){ $submission_tags = explode(',', $row->tags); foreach ($master_tags as $mt) { if(in_array($mt['name'], $submission_tags)) { printf('<span class="crf-tag" style="background-color:%s;">%s</span>', esc_attr($mt['color']), esc_html($mt['name']));}}} ?></td>
                    <td><select class="status-changer" data-id="<?php echo esc_attr($row->id); ?>"><?php foreach ($statuses as $s) { printf('<option value="%s" %s>%s</option>', esc_attr($s), selected($row->status, $s, false), esc_html($s)); } ?></select></td>
                    <td><?php echo date("M j, Y", strtotime($row->submitted_at)); ?></td>
                    <td class="action-buttons"><button type="button" class="button button-secondary button-small view-notes-btn" data-id="<?php echo esc_attr($row->id); ?>">Notes</button><button type="button" class="button button-secondary button-small edit-tags-btn" data-id="<?php echo esc_attr($row->id); ?>" data-tags="<?php echo esc_attr($row->tags); ?>">Tags</button><?php if ($current_flag_view === 'ok') : ?><button type="button" class="button button-link-delete button-secondary button-small view-notes-btn spam-button" data-id="<?php echo esc_attr($row->id); ?>">Spam</button><?php endif; ?></td>
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
function crf_ajax_router() {
    check_ajax_referer('crf_crm_nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'Permission denied.'], 403); }
    $action = isset($_POST['route']) ? sanitize_key($_POST['route']) : '';
    global $wpdb;
    switch ($action) {
        case 'update_status': $wpdb->update($wpdb->prefix . 'custom_registrations', ['status' => sanitize_text_field($_POST['status'])], ['id' => intval($_POST['id'])]); wp_send_json_success(); break;
        case 'update_flag': $wpdb->update($wpdb->prefix . 'custom_registrations', ['flag' => sanitize_text_field($_POST['flag'])], ['id' => intval($_POST['id'])]); wp_send_json_success(); break;
        case 'get_notes': $notes = $wpdb->get_results($wpdb->prepare("SELECT n.*, u.display_name FROM {$wpdb->prefix}custom_registration_notes n JOIN {$wpdb->users} u ON n.author_id = u.ID WHERE submission_id = %d ORDER BY created_at DESC", intval($_POST['id']))); wp_send_json_success($notes); break;
        case 'add_note': if (!empty($_POST['content'])) { $wpdb->insert($wpdb->prefix . 'custom_registration_notes', ['submission_id' => intval($_POST['id']), 'note_content' => sanitize_textarea_field($_POST['content']), 'author_id' => get_current_user_id()]); } wp_send_json_success(); break;
        case 'delete_note': $wpdb->delete($wpdb->prefix . 'custom_registration_notes', ['note_id' => intval($_POST['note_id'])]); wp_send_json_success(); break;
        case 'update_tags': $tags = isset($_POST['tags']) ? implode(',', array_map('sanitize_text_field', $_POST['tags'])) : ''; $wpdb->update($wpdb->prefix . 'custom_registrations', ['tags' => $tags], ['id' => intval($_POST['id'])]); wp_send_json_success(); break;
        case 'manage_master_tags': $tags = isset($_POST['tags']) ? json_decode(stripslashes($_POST['tags']), true) : []; $sanitized_tags = []; foreach ($tags as $tag) { if (!empty($tag['name'])) { $sanitized_tags[] = ['name' => sanitize_text_field($tag['name']), 'color' => sanitize_hex_color($tag['color'])]; } } update_option('crf_master_tags', $sanitized_tags); wp_send_json_success($sanitized_tags); break;
    }
    wp_send_json_error(['message' => 'Invalid action.']);
}
add_action('wp_ajax_crf_router', 'crf_ajax_router');

function crf_enqueue_admin_assets($hook) {
    if ($hook != 'toplevel_page_custom-registrations') {
        return;
    }

    wp_enqueue_style(
        'crf-admin-style',
        plugin_dir_url(__FILE__) . './assets/css/admin-style.css', // no need for ./ before assets
        [],
        filemtime(plugin_dir_path(__FILE__) . './assets/css/admin-style.css') // auto-cache busting
    );
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
        $(document).on('click', '.flag-spam-btn', function() { if (confirm('Flag as spam?')) { const row = $(this).closest('tr'); doAjax('update_flag', { id: $(this).data('id'), flag: 'spam' }, () => row.fadeOut(500)); } });
        $('.crf-modal-close').on('click', () => $('.crf-modal').hide());
        $(document).on('click', '.view-notes-btn', function() {
            currentSubmissionId = $(this).data('id'); $('#notes-list').html('Loading...'); $('#notes-modal').show();
            doAjax('get_notes', { id: currentSubmissionId }, (res) => {
                let html = res.success && res.data.length ? '' : '<p>No notes yet.</p>';
                if (res.success) res.data.forEach(n => { html += `<div class="note" data-note-id="${n.note_id}"><p>${n.note_content.replace(/\n/g, '<br>')}</p><div class="note-meta">By ${n.display_name} on ${new Date(n.created_at).toLocaleString()} <a href="#" class="delete-note-btn">Delete</a></div></div>`; });
                $('#notes-list').html(html);
            });
        });
        $('#add-note-btn').on('click', function() { const content = $('#new-note-content').val(); if (content) doAjax('add_note', { id: currentSubmissionId, content: content }, () => { $('#new-note-content').val(''); $('.view-notes-btn[data-id="' + currentSubmissionId + '"]').click(); }); });
        $(document).on('click', '.delete-note-btn', function(e) { e.preventDefault(); if (confirm('Delete this note?')) { const noteDiv = $(this).closest('.note'); doAjax('delete_note', { note_id: noteDiv.data('note-id') }, () => noteDiv.remove()); } });
        $(document).on('click', '.edit-tags-btn', function() {
            currentSubmissionId = $(this).data('id'); const currentTags = ($(this).data('tags') || '').toString().split(','); let html = '';
            masterTags.forEach(tag => { html += `<div><label><input type="checkbox" class="crf-tag-checkbox" value="${tag.name}" ${currentTags.includes(tag.name) ? 'checked' : ''}> ${tag.name}</label></div>`; });
            $('#tags-checklist').html(html); $('#tags-modal').show();
        });
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