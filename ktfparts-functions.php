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
        plugin_dir_url(__FILE__) . '../ktfparts.js',
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

// AJAX: update quantity only
add_action('wp_ajax_ktfparts_update_quantity', 'ktfparts_update_quantity');
function ktfparts_update_quantity() {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }
    $part_id = intval($_POST['part_id'] ?? 0);
    $newQty  = intval($_POST['quantity'] ?? 0);
    if (!$part_id) {
        wp_send_json_error('Invalid Part ID');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ktf_parts';
    $update = $wpdb->update(
        $table,
        ['quantity' => $newQty, 'updated_at' => current_time('mysql')],
        ['part_id' => $part_id, 'owner_user_id' => get_current_user_id()]
    );
    if ($update !== false) {
        wp_send_json_success('Quantity updated');
    } else {
        wp_send_json_error('Quantity update failed');
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
    // Highlight sortable headers
    echo '<style>.ktf-sort{background:#f9f9f9;padding:8px;} .ktf-sort:hover{background:#e0e0e0;}</style>';

    // Add New Part button
    // Add New Part button
echo '<p><a class="button" href="' . esc_url(site_url('/add-parts/')) . '">Add New Part</a></p>';
// Download CSV button
echo '<p><a class="button" href="' . esc_url(admin_url('admin-ajax.php?action=ktfparts_export_csv&nonce=' . wp_create_nonce('ktfparts_export_csv'))) . '">Download CSV</a></p>';
// Import CSV form
echo '<form id="ktfparts-import-form" enctype="multipart/form-data" style="margin-top:10px;">';
echo '<p><input type="file" name="csv_file" accept=".csv" required> <button type="submit" class="button">Import CSV</button></p>';
echo '</form>';;
    // Search box
    echo '<input type="text" id="ktfparts-search" placeholder="Search parts..." style="width:100%;padding:6px;margin-bottom:10px;">';
    // Table
    echo '<table class="ktf-parts-table" style="width:100%;border-collapse:collapse;"><thead><tr>';;
    echo '<th class="ktf-sort" data-index="0">Name</th>';
    echo '<th class="ktf-sort" data-index="1">Part #</th>';
    echo '<th class="ktf-sort" data-index="2">Qty</th>';
    echo '<th class="ktf-sort" data-index="3">Location</th>';
    echo '<th class="ktf-sort" data-index="4">Updated</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead><tbody>';
    foreach ($parts as $p) {
        $edit_url = site_url('/add-parts/?edit=' . intval($p->part_id));
        echo '<tr>';
        echo '<td>' . esc_html($p->name) . '</td>';
        echo '<td>' . esc_html($p->part_number) . '</td>';
        echo '<td><a href="#" class="ktf-qty-link" data-part-id="' . intval($p->part_id) . '" data-part-number="' . esc_attr($p->part_number) . '" data-current-qty="' . intval($p->quantity) . '">' . intval($p->quantity) . '</a></td>';  
        echo '<td>' . esc_html($p->location_label) . '</td>';
        echo '<td>' . esc_html( date('y/m/d', strtotime($p->updated_at)) ) . '</td>';
        echo '<td><a href="' . esc_url($edit_url) . '">Edit</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    ?>
<script>
jQuery(function($) {
    // CSV import handler
    $('#ktfparts-import-form').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'ktfparts_import_csv');
        formData.append('nonce', KTFPartsAjax.nonce);
        $.ajax({
            url: KTFPartsAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    var imp = response.data.processed;
                    var del = response.data.deleted;
                    if (confirm('Imported ' + imp + ' items and deleted ' + del + ' items. Refresh page?')) {
                        location.reload();
                    }
                } else {
                    alert('Import failed: ' + response.data);
                }
            }
        });
    });

    // Append quantity modal once
    if (!$('#ktf-qty-modal').length) {
        $('body').append(
            '<div id="ktf-qty-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;border:1px solid #ccc;z-index:10000;">'
          + '<h3>Adjust Quantity</h3>'
          + '<p id="ktf-qty-part"></p>'
          + '<button id="ktf-qty-dec">-</button>'
          + '<input type="text" id="ktf-qty-input" style="width:40px;text-align:center;" readonly>'
          + '<button id="ktf-qty-inc">+</button>'
          + '<p><button id="ktf-qty-save">Save</button> <button id="ktf-qty-cancel">Cancel</button></p>'
          + '</div>'
        );
    }

    // Quantity hyperlink click
    $('.ktf-qty-link').off('click').on('click', function(e) {
        e.preventDefault();
        var partId = $(this).data('part-id');
        var qty = $(this).data('current-qty');
        var partNum = $(this).data('part-number');
        $('#ktf-qty-part').text('Part #: ' + partNum + ' (ID: ' + partId + ') - Qty: ' + qty);
        $('#ktf-qty-input').val(qty);
        $('#ktf-qty-modal').data('part-id', partId).show();
    });

    // Modal buttons
    $('#ktf-qty-inc').off('click').on('click', function() {
        var val = parseInt($('#ktf-qty-input').val(), 10) + 1;
        $('#ktf-qty-input').val(val);
    });
    $('#ktf-qty-dec').off('click').on('click', function() {
        var val = parseInt($('#ktf-qty-input').val(), 10) - 1;
        if (val < 0) val = 0;
        $('#ktf-qty-input').val(val);
    });
    $('#ktf-qty-cancel').off('click').on('click', function() {
        $('#ktf-qty-modal').hide();
    });
    $('#ktf-qty-save').off('click').on('click', function() {
        var partId = $('#ktf-qty-modal').data('part-id');
        var newQty = parseInt($('#ktf-qty-input').val(), 10);
        $.post(
            KTFPartsAjax.ajaxurl,
            {
                action: 'ktfparts_update_quantity',
                nonce: KTFPartsAjax.nonce,
                part_id: partId,
                quantity: newQty
            },
            function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Quantity update failed: ' + res.data);
                }
            }
        );
    });

    // Search handler
    $('#ktfparts-search').off('keyup').on('keyup', function() {
        var val = $(this).val().toLowerCase();
        $('.ktf-parts-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    });

    // Column sort handler
    $('.ktf-sort').off('click').css('cursor','pointer').on('click', function() {
        var table = $(this).closest('table'),
            tbody = table.find('tbody'),
            rows = tbody.find('tr').get(),
            idx = $(this).data('index'),
            asc = !$(this).data('asc');
        rows.sort(function(a, b) {
            var A = $(a).children('td').eq(idx).text().toUpperCase();
            var B = $(b).children('td').eq(idx).text().toUpperCase();
            if ($.isNumeric(A) && $.isNumeric(B)) {
                return (A - B) * (asc ? 1 : -1);
            }
            if (A < B) return asc ? -1 : 1;
            if (A > B) return asc ? 1 : -1;
            return 0;
        });
        $.each(rows, function(i, row) { tbody.append(row); });
        $('.ktf-sort').data('asc', false);
        $(this).data('asc', asc);
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
        <p><label>Quantity<br>
            <button type="button" class="ktf-qty-btn" data-delta="-1">-</button>
            <input type="number" name="quantity" value="<?php echo esc_attr($part->quantity ?? '0'); ?>" required style="width:60px;text-align:center;">
            <button type="button" class="ktf-qty-btn" data-delta="1">+</button>
        </label></p>
        <p><label>Condition<br><select name="condition_status" required>
            <?php foreach (['New','Serviceable','Overhauled','Used','As Removed'] as $c): ?>
                <option value="<?php echo esc_attr($c); ?>" <?php selected($part->condition_status ?? '', $c); ?>><?php echo esc_html($c); ?></option>
            <?php endforeach; ?>
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

// AJAX: export CSV
add_action('wp_ajax_ktfparts_export_csv', function() {
    check_ajax_referer('ktfparts_export_csv', 'nonce');
    if (!is_user_logged_in()) wp_die('Permission denied');
    $parts = ktfparts_get_user_parts(get_current_user_id());
    if (empty($parts)) wp_die('No data');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="parts-' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    // Header with Delete column
    fputcsv($output, ['ID','Name','Part Number','Category','Quantity','Condition','Location','Notes','Created At','Updated At','Delete']);
    foreach ($parts as $p) {
        fputcsv($output, [
            $p->part_id,
            $p->name,
            $p->part_number,
            $p->category,
            $p->quantity,
            $p->condition_status,
            $p->location_label,
            str_replace(["\r\n","\n"], [' ', ' '], $p->notes),
            $p->created_at,
            $p->updated_at,
            '' // Delete placeholder
        ]);
    }
    fclose($output);
    exit;
});




// AJAX: import CSV
add_action('wp_ajax_ktfparts_import_csv', function() {
    check_ajax_referer('ktfparts_add_part', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Permission denied');
    }
    if (empty($_FILES['csv_file']['tmp_name'])) {
        wp_send_json_error('No file uploaded');
    }
    $fp = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$fp) {
        wp_send_json_error('Cannot open file');
    }

    $row = 0;
    $processed = 0;
    $deleted = 0;
    global $wpdb;
    $table = $wpdb->prefix . 'ktf_parts';

    // Read header
    while (($data = fgetcsv($fp)) !== FALSE) {
        if ($row++ === 0) continue; // skip header

        $id          = intval($data[0] ?? 0);
        $name        = sanitize_text_field($data[1] ?? '');
        $part_number = sanitize_text_field($data[2] ?? '');
        $category    = sanitize_text_field($data[3] ?? '');
        $quantity    = intval($data[4] ?? 0);
        $condition   = sanitize_text_field($data[5] ?? '');
        $location    = sanitize_text_field($data[6] ?? '');
        $notes       = sanitize_textarea_field($data[7] ?? '');
        $deleteFlag  = trim(strtolower($data[10] ?? ''));

        // Delete-flag support
        if ($deleteFlag === 'd' && $id) {
            if ($wpdb->delete(
                $table,
                ['part_id' => $id, 'owner_user_id' => get_current_user_id()]
            )) {
                $deleted++;
            }
            continue;
        }

        // Compare existing vs incoming
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name, part_number, category, quantity, condition_status, location_label, notes FROM $table WHERE part_id = %d AND owner_user_id = %d",
                $id,
                get_current_user_id()
            ),
            ARRAY_A
        );
        $incoming = [
            'name'             => $name,
            'part_number'      => $part_number,
            'category'         => $category,
            'quantity'         => $quantity,
            'condition_status' => $condition,
            'location_label'   => $location,
            'notes'            => $notes,
        ];

        if ($existing) {
            $unchanged = true;
            foreach ($incoming as $field => $value) {
                if ((string)$existing[$field] !== (string)$value) {
                    $unchanged = false;
                    break;
                }
            }
            if ($unchanged) continue;
        }

        // Prepare entry
        $entry = $incoming;
        $entry['updated_at'] = current_time('mysql');

        if ($id && $existing) {
            if ($wpdb->update($table, $entry, ['part_id' => $id, 'owner_user_id' => get_current_user_id()]) !== false) {
                $processed++;
            }
        } else {
            $entry['owner_user_id'] = get_current_user_id();
            $entry['created_at']    = current_time('mysql');
            if ($wpdb->insert($table, $entry)) {
                $processed++;
            }
        }
    }
    fclose($fp);
    wp_send_json_success(['processed' => $processed, 'deleted' => $deleted]);
});
