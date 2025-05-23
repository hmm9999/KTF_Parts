<?php

function ktfparts_get_user_parts($user_id) {
    global $wpdb;

    $user_id = absint($user_id);
    if (!$user_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ktf_parts';
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE owner_user_id = %d", $user_id)
    );
}

add_action('wp_enqueue_scripts', function () {
   wp_enqueue_script('ktfparts-ajax', plugin_dir_url(dirname(__FILE__)) . 'ktfparts.js', ['jquery'], null, true);
    wp_localize_script('ktfparts-ajax', 'KTFPartsAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ktfparts_add_part')
    ]);
});

add_action('wp_ajax_ktfparts_add_part', 'ktfparts_ajax_add_part');
function ktfparts_ajax_add_part() {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }

    $name = sanitize_text_field($_POST['name']);
    $part_number = sanitize_text_field($_POST['part_number']);
    $category = sanitize_text_field($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $condition_status = sanitize_text_field($_POST['condition_status']);
    $location_label = sanitize_text_field($_POST['location_label']);
    $notes = sanitize_textarea_field($_POST['notes']);

    if (empty($name) || empty($part_number) || empty($category) || $quantity <= 0 || empty($condition_status)) {
        wp_send_json_error('Invalid form submission');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ktf_parts';
    $user_id = get_current_user_id();

    $inserted = $wpdb->insert(
        $table,
        [
            'owner_user_id' => $user_id,
            'name' => $name,
            'part_number' => $part_number,
            'category' => $category,
            'quantity' => $quantity,
            'condition_status' => $condition_status,
            'location_label' => $location_label,
            'notes' => $notes,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]
    );

    if ($inserted) {
        wp_send_json_success('Part added successfully');
    } else {
        wp_send_json_error('Database insert failed');
    }
}

// Shortcode: [ktfparts_list]
function ktfparts_list_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your parts inventory.</p>';
    }

    $user_id = get_current_user_id();
    $parts = ktfparts_get_user_parts($user_id);

    if (empty($parts)) {
        return '<p>No parts found in your inventory.</p>';
    }

    ob_start();
    echo '<table class="ktf-parts-table" style="width:100%;border-collapse:collapse;">';
    echo '<thead>
            <tr>
                <th>Name</th><th>Part #</th><th>Qty</th><th>Condition</th><th>Location</th><th>Updated</th>
            </tr>
        </thead><tbody>';
    foreach ($parts as $part) {
        echo '<tr>';
        echo '<td>' . esc_html($part->name) . '</td>';
        echo '<td>' . esc_html($part->part_number) . '</td>';
        echo '<td>' . intval($part->quantity) . '</td>';
        echo '<td>' . esc_html($part->condition_status) . '</td>';
        echo '<td>' . esc_html($part->location_label) . '</td>';
        echo '<td>' . esc_html(date('Y-m-d', strtotime($part->updated_at))) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode('ktfparts_list', 'ktfparts_list_shortcode');

// Shortcode: [ktfparts_add_form]
function ktfparts_add_part_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to add parts.</p>';
    }

    ob_start(); ?>
    <form id="ktfparts-add-form">
        <p><label>Part Name: <input type="text" name="name" required></label></p>
        <p><label>Part Number: <input type="text" name="part_number" required></label></p>
        <p><label>Category: <input type="text" name="category" required></label></p>
        <p><label>Quantity: <input type="number" name="quantity" required></label></p>
        <p><label>Condition:
            <select name="condition_status" required>
                <option value="New">New</option>
                <option value="Serviceable">Serviceable</option>
                <option value="Overhauled">Overhauled</option>
                <option value="Used">Used</option>
                <option value="As Removed">As Removed</option>
            </select></label>
        </p>
        <p><label>Location: <input type="text" name="location_label"></label></p>
        <p><label>Notes:<br><textarea name="notes"></textarea></label></p>
        <p><button type="submit">Add Part</button></p>
        <div id="ktfparts-form-result"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('ktfparts_add_form', 'ktfparts_add_part_form_shortcode');
