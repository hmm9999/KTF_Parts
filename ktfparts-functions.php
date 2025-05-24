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

    $user_id = get_current_user_id();
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

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

    if ($edit_id) {
        $updated = $wpdb->update(
            $table,
            [
                'name' => $name,
                'part_number' => $part_number,
                'category' => $category,
                'quantity' => $quantity,
                'condition_status' => $condition_status,
                'location_label' => $location_label,
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            [
                'part_id' => $edit_id,
                'owner_user_id' => $user_id
            ]
        );

        if ($updated !== false) {
            wp_send_json_success('Part updated successfully');
        } else {
            wp_send_json_error('Update failed');
        }
    } else {
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
}

add_action('wp_ajax_ktfparts_delete_part', function () {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }
    $id = intval($_POST['part_id'] ?? 0);
    if (!$id) {
        wp_send_json_error('Invalid ID');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ktf_parts';
    $deleted = $wpdb->delete($table, ['part_id' => $id, 'owner_user_id' => get_current_user_id()]);
    if ($deleted) {
        wp_send_json_success('Deleted');
    } else {
        wp_send_json_error('Delete failed');
    }
});

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
echo '<input type="text" id="ktfparts-search" placeholder="Search parts..." style="margin-bottom:10px;width:100%;padding:8px;">';
echo '<table class="ktf-parts-table" style="width:100%;border-collapse:collapse;">';
    echo '<thead><tr>
            <th>Part #</th><th>Name</th><th>Location</th><th>Qty</th><th>Updated</th><th>Actions</th>
          </tr></thead><tbody>';
    foreach ($parts as $part) {
        echo '<tr>';
        echo '<td>' . esc_html($part->part_number) . '</td>';
        echo '<td>' . esc_html($part->name) . '</td>';
        echo '<td>' . esc_html($part->location_label) . '</td>';
        echo '<td><button class="ktf-qty-btn" data-id="' . esc_attr($part->part_id) . '" data-delta="-1">-</button> ' . intval($part->quantity) . ' <button class="ktf-qty-btn" data-id="' . esc_attr($part->part_id) . '" data-delta="1">+</button></td>';
        echo '<td>' . esc_html(date('Y-m-d', strtotime($part->updated_at))) . '</td>';
        echo '<td><a href="?edit=' . esc_attr($part->part_id) . '">Edit</a> | <button class="ktf-delete-part" data-id="' . esc_attr($part->part_id) . '">Delete</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
echo '<script>
jQuery(document).ready(function($) {
  $("#ktfparts-search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $(".ktf-parts-table tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
  });

  $(".ktf-delete-part").on("click", function() {
    if (!confirm("Delete this part?")) return;
    var partId = $(this).data("id");
    $.post(KTFPartsAjax.ajaxurl, {
      action: "ktfparts_delete_part",
      part_id: partId,
      nonce: KTFPartsAjax.nonce
    }, function(response) {
      if (response.success) {
        $("button[data-id=\'" + partId + "\']").closest("tr").remove();
      } else {
        alert(response.data);
      }
    });
  });

  $(document).on("click", ".ktf-qty-btn", function() {
    var button = $(this);
    var partId = button.data("id");
    var delta = parseInt(button.data("delta"));
    var row = button.closest("tr");
    var qtyCell = row.find("td:nth-child(4)");
    var qtyText = qtyCell.text().match(/\\d+/);
    var currentQty = qtyText ? parseInt(qtyText[0]) : 0;
    var newQty = currentQty + delta;
    if (newQty < 0) return;
    qtyCell.html("<button class=\\"ktf-qty-btn\\" data-id=\\"" + partId + "\\" data-delta=\\"-1\\">-</button> " + newQty + " <button class=\\"ktf-qty-btn\\" data-id=\\"" + partId + "\\" data-delta=\\"1\\">+</button>");
  });
});
</script>';

    return ob_get_clean();
}

add_shortcode('ktfparts_list', 'ktfparts_list_shortcode');

function ktfparts_add_part_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to add parts.</p>';
    }

    $is_edit = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $part = null;
    if ($is_edit) {
        global $wpdb;
        $table = $wpdb->prefix . 'ktf_parts';
        $part = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE part_id = %d AND owner_user_id = %d", $is_edit, get_current_user_id()));
    }

    ob_start(); ?>
    <form id="ktfparts-add-form">
        <input type="hidden" name="edit_id" value="<?php echo esc_attr($part->part_id ?? ''); ?>">
        <p><label>Part Name: <input type="text" name="name" value="<?php echo esc_attr($part->name ?? ''); ?>" required></label></p>
        <p><label>Part Number: <input type="text" name="part_number" value="<?php echo esc_attr($part->part_number ?? ''); ?>" required></label></p>
        <p><label>Category: <input type="text" name="category" value="<?php echo esc_attr($part->category ?? ''); ?>" required></label></p>
        <p><label>Quantity: <input type="number" name="quantity" value="<?php echo esc_attr($part->quantity ?? ''); ?>" required></label></p>
        <p><label>Condition:
            <select name="condition_status" required>
                <?php
                $conditions = ['New', 'Serviceable', 'Overhauled', 'Used', 'As Removed'];
                foreach ($conditions as $cond) {
                    $selected = ($part && $part->condition_status === $cond) ? 'selected' : '';
                    echo "<option value='$cond' $selected>$cond</option>";
                }
                ?>
            </select></label>
        </p>
        <p><label>Location: <input type="text" name="location_label" value="<?php echo esc_attr($part->location_label ?? ''); ?>"></label></p>
        <p><label>Notes:<br><textarea name="notes"><?php echo esc_textarea($part->notes ?? ''); ?></textarea></label></p>
        <p><button type="submit"><?php echo $is_edit ? 'Update Part' : 'Add Part'; ?></button></p>
        <div id="ktfparts-form-result"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('ktfparts_add_form', 'ktfparts_add_part_form_shortcode');
