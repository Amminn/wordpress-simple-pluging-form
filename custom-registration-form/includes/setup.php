<?php
/* 
    This file's only responsibility is creating the database table.
*/
?>


<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the custom database table.
 * This function is called by the activation hook in the main plugin file.
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
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}