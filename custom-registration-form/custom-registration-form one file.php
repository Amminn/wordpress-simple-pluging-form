<?php
/**
 * Plugin Name:       Custom User Registration Form
 * Description:       A modular plugin that creates a styled custom registration form with email notifications and status management.
 * Version:           2.1
 * Author:            Your Name
 */

// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

define('CUSTOM_REG_FORM_PATH', plugin_dir_path(__FILE__));

// Includes
require_once(CUSTOM_REG_FORM_PATH . 'includes/setup.php');
require_once(CUSTOM_REG_FORM_PATH . 'includes/form-handler.php');
require_once(CUSTOM_REG_FORM_PATH . 'includes/shortcode-form.php');
require_once(CUSTOM_REG_FORM_PATH . 'includes/ajax-handler.php'); // <-- ADD THIS LINE
require_once(CUSTOM_REG_FORM_PATH . 'admin/admin-menu.php');

// Hooks
register_activation_hook(__FILE__, 'custom_registration_create_table');