<?php

// Set the current database version
define('KTFPARTS_DB_VERSION', '1.0.0');

function ktfparts_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ktf_parts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        part_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        notes TEXT,
        part_number VARCHAR(100),
        serial_number VARCHAR(100),
        category VARCHAR(100),
        life_limited TINYINT(1) DEFAULT 0,
        life_expiry_date DATE DEFAULT NULL,
        quantity INT DEFAULT 0,
        unit_cost DECIMAL(10,2) DEFAULT 0.00,
        location_label VARCHAR(255),
        condition_status ENUM('New', 'Serviceable', 'Overhauled', 'Used', 'As Removed'),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Store the DB version in WP options
    add_option('ktfparts_db_version', KTFPARTS_DB_VERSION);
}

// Check for DB schema updates on plugin load
function ktfparts_update_check() {
    $installed_ver = get_option('ktfparts_db_version');

    if ($installed_ver != KTFPARTS_DB_VERSION) {
        ktfparts_install(); // re-run installer to apply changes
        update_option('ktfparts_db_version', KTFPARTS_DB_VERSION);
    }
}
add_action('plugins_loaded', 'ktfparts_update_check');
