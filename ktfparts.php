<?php
/*
Plugin Name: KTF Parts Inventory
Description: Manage personal parts and tools inventory with user-specific access.
Version: 1.0.0
Author: Marc M
*/

// Prevent direct access to the file
defined('ABSPATH') or die('No script kiddies please!');

// Define paths to included files
$install_file   = plugin_dir_path(__FILE__) . 'includes/ktfparts-install.php';
$functions_file = plugin_dir_path(__FILE__) . 'includes/ktfparts-functions.php';

// Include installation logic if the file exists
if (file_exists($install_file)) {
    require_once $install_file;
} else {
    error_log('KTF Parts Inventory Plugin: Missing install file.');
}

// Include function logic if the file exists
if (file_exists($functions_file)) {
    require_once $functions_file;
} else {
    error_log('KTF Parts Inventory Plugin: Missing functions file.');
}

// Register activation hook to create custom table
if (function_exists('ktfparts_install')) {
    register_activation_hook(__FILE__, 'ktfparts_install');
} else {
    error_log('KTF Parts Inventory Plugin: Install function not found.');
}
