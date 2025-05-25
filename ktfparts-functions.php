<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

// Get user-specific parts
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

// Enqueue scripts and localize AJAX params
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'ktfparts-ajax',
        plugin_dir_url(__FILE__) . 'ktfparts.js',
        ['jquery'],
        null,
        true
    );
    wp_localize_script('ktfparts-ajax', 'KTFPartsAjax', [
        'ajaxurl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('ktfparts_add_part'),
        'inventoryUrl' => site_url('/my-parts-inventory/')
    ]);
});

// AJAX: add/update part
add_action('wp_ajax_ktfparts_add_part', 'ktfparts_ajax_add_part');
function ktfparts_ajax_add_part() {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }
    $user_id = get_current_user_id();
    $edit_id = intval($_POST['edit_id'] ?? 0);

    $data = [
        'name'             => sanitize_text_field($_POST['name']),
        'part_number'      => sanitize_text_field($_POST['part_number']),
        'category'         => sanitize_text_field($_POST['category']),
        'quantity'         => intval($_POST['quantity']),
        'condition_status' => sanitize_text_field($_POST['condition_status']),
        'location_label'   => sanitize_text_field($_POST['location_label']),
        'notes'            => sanitize_textarea_field($_POST['notes']),
        'updated_at'       => current_time('mysql')
    ];
    global $wpdb;
    $table = $wpdb->prefix . 'ktf_parts';

    if ($edit_id) {
        $success = $wpdb->update(
            $table,
            $data,
            [ 'part_id' => $edit_id, 'owner_user_id' => $user_id ]
        );
        if ($success !== false) {
            wp_send_json_success('Part updated successfully');
        } else {
            wp_send_json_error('Update failed');
        }
    } else {
        $data['owner_user_id'] = $user_id;
        $data['created_at']    = current_time('mysql');
        $insert = $wpdb->insert($table, $data);
        if ($insert) {
            wp_send_json_success('Part added successfully');
        } else {
            wp_send_json_error('Database insert failed');
        }
    }
}

// AJAX: delete part
add_action('wp_ajax_ktfparts_delete_part', 'ktfparts_ajax_delete_part');
function ktfparts_ajax_delete_part() {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }
    $part_id = intval($_POST['part_id'] ?? 0);
    if (!$part_id) {
        wp_send_json_error('Invalid Part ID');
    }
    global $wpdb;
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'ktf_parts',
        ['part_id' => $part_id, 'owner_user_id' => get_current_user_id()]
    );
    if ($deleted) {
        wp_send_json_success('Part deleted');
    } else {
        wp_send_json_error('Delete failed');
    }
}

// Shortcode: list inventory
function ktfparts_list_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your inventory.</p>';
    }
    $parts = ktfparts_get_user_parts(get_current_user_id());
    if (empty($parts)) {
        return '<p>No parts found in your inventory.</p>';
    }

    ob_start();
    // Add New Part button
    echo '<p><a class="button" href="' . esc_url(site_url('/add-parts/')) . '">Add New Part</a></p>';
    // Search box
    echo '<input type="text" id="ktfparts-search" placeholder="Search parts..." style="width:100%;padding:6px;margin-bottom:10px;">';
    // Table
    echo '<table class="ktf-parts-table" style="width:100%;border-collapse:collapse;"><thead><tr>';
    echo '<th>Part #</th><th>Name</th><th>Location</th><th>Qty</th><th>Updated</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    foreach ($parts as $p) {
        $edit_url = site_url('/add-parts/?edit=' . intval($p->part_id));
        echo '<tr>';
        echo '<td>' . esc_html($p->part_number) . '</td>';
        echo '<td>' . esc_html($p->name) . '</td>';
        echo '<td>' . esc_html($p->location_label) . '</td>';
        echo '<td>' . intval($p->quantity) . '</td>';
        echo '<td>' . esc_html(date('Y-m-d', strtotime($p->updated_at))) . '</td>';
        echo '<td><a href="' . esc_url($edit_url) . '">Edit</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    ?>
    <script>
    jQuery(function($) {
        $('#ktfparts-search').on('keyup', function() {
            var val = $(this).val().toLowerCase();
            $('.ktf-parts-table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ktfparts_list', 'ktfparts_list_shortcode');

// Shortcode: add/edit form
function ktfparts_add_part_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to add or edit parts.</p>';
    }
    $is_edit = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $part    = null;
    if ($is_edit) {
        global $wpdb;
        $tbl  = $wpdb->prefix . 'ktf_parts';
        $part = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tbl WHERE part_id = %d AND owner_user_id = %d", $is_edit, get_current_user_id())
        );
    }
    ob_start();
    // Back to inventory
    echo '<p><a class="button" href="' . esc_url(site_url('/my-parts-inventory/')) . '">Back to Inventory</a></p>';
    ?>
    <form id="ktfparts-add-form" style="max-width:400px;">
        <input type="hidden" name="edit_id" value="<?php echo esc_attr($part->part_id ?? ''); ?>">
        <p><label>Part Name<br><input type="text" name="name" value="<?php echo esc_attr($part->name ?? ''); ?>" required></label></p>
        <p><label>Part Number<br><input type="text" name="part_number" value="<?php echo esc_attr($part->part_number ?? ''); ?>" required></label></p>
        <p><label>Category<br><input type="text" name="category" value="<?php echo esc_attr($part->category ?? ''); ?>" required></label></p>
        <p><label>Quantity<br><input type="number" name="quantity" value="<?php echo esc_attr($part->quantity ?? '0'); ?>" required></label></p>
        <p><label>Condition<br><select name="condition_status" required>
            <?php
            $conds = ['New','Serviceable','Overhauled','Used','As Removed'];
            foreach ($conds as $c) {
                $sel = ($part && $part->condition_status === $c) ? 'selected' : '';
                echo "<option value=\"{$c}\" {$sel}>{$c}</option>";
            }
            ?>
        </select></label></p>
        <p><label>Location<br><input type="text" name="location_label" value="<?php echo esc_attr($part->location_label ?? ''); ?>"></label></p>
        <p><label>Notes<br><textarea name="notes"><?php echo esc_textarea($part->notes ?? ''); ?></textarea></label></p>
        <p><button type="submit"><?php echo $is_edit ? 'Update Part' : 'Add Part'; ?></button></p>
        <div id="ktfparts-form-result"></div>
    </form>
    <?php if ($is_edit): ?>
    <form id="ktfparts-delete-form" method="post">
        <input type="hidden" name="action" value="ktfparts_delete_part">
        <input type="hidden" name="part_id" value="<?php echo esc_attr($part->part_id); ?>">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ktfparts_add_part'); ?>">
        <p><button type="submit" style="background:#c00;color:#fff;">Delete Part</button></p>
    </form>
    <?php endif; ?>
    <script>
    jQuery(function($) {
        // Add/Edit submission
        $('#ktfparts-add-form').on('submit', function(e) {
            e.preventDefault();
            var data = $(this).serialize() + '&action=ktfparts_add_part&nonce=' + KTFPartsAjax.nonce;
            $.post(KTFPartsAjax.ajaxurl, data, function(res) {
                if (res.success) {
                    $('#ktfparts-form-result').html('<p style="color:green;">'+res.data+'</p>');
                    <?php if ($is_edit): ?>
                        window.location.href = KTFPartsAjax.inventoryUrl;
                    <?php else: ?>
                        $('#ktfparts-add-form')[0].reset();
                    <?php endif; ?>
                } else {
                    $('#ktfparts-form-result').html('<p style="color:red;">'+res.data+'</p>');
                }
            });
        });
        // Delete submission
        $('#ktfparts-delete-form').on('submit', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this part?')) return;
            var data = $(this).serialize();
            $.post(KTFPartsAjax.ajaxurl, data, function(res) {
                if (res.success) {
                    window.location.href = KTFPartsAjax.inventoryUrl;
                } else {
                    alert(res.data);
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ktfparts_add_form', 'ktfparts_add_part_form_shortcode');
