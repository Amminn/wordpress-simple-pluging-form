<?php
/**
 * Plugin Name:       Custom User Registration Form
 * Description:       A modular plugin that creates a styled custom registration form with email notifications.
 * Version:           2.0
 * Author:            Your Name
 */

// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

// Define a constant for the plugin directory path
define('CUSTOM_REG_FORM_PATH', plugin_dir_path(__FILE__));

// 1. Include the setup file for database table creation
require_once(CUSTOM_REG_FORM_PATH . 'includes/setup.php');

// 2. Include the file that handles form submission
require_once(CUSTOM_REG_FORM_PATH . 'includes/form-handler.php');

// 3. Include the file that defines the shortcode for the form
require_once(CUSTOM_REG_FORM_PATH . 'includes/shortcode-form.php');

// 4. Include the file that creates the admin menu and submissions page
require_once(CUSTOM_REG_FORM_PATH . 'admin/admin-menu.php');

// Register the activation hook to create the database table
register_activation_hook(__FILE__, 'custom_registration_create_table');