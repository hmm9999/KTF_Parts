KTF Parts Inventory Plugin

A lightweight WordPress plugin that allows users to manage a personal parts and tools inventory. Supports front-end CRUD operations, CSV import/export, search, sorting, and AJAX-driven quantity adjustments.

Features

Custom Database Table for storing part records (including expiration, quantity, cost, condition, location).

Front-end Shortcodes:

[ktfparts_list] – Display a sortable, searchable table of the user’s parts.

[ktfparts_add_form] – Show an Add/Edit form for parts with instant AJAX save and delete.

AJAX Actions:

ktfparts_add_part – Create or update a part.

ktfparts_delete_part – Delete a part.

ktfparts_update_quantity – Increment/decrement quantity via ± buttons.

ktfparts_export_csv – Download user’s parts as a CSV (includes ID for sync).

ktfparts_import_csv – Bulk import/update from CSV (matches by ID).

CSV Import/Export with ID-based record matching to prevent duplicates.

Search and sorting on the parts list.

AJAX-driven Qty +/- buttons both on list and on the Add/Edit form.

Fully sandboxed per-user: each user sees only their own inventory.

Installation

Copy the ktfparts folder into your WordPress plugin directory (wp-content/plugins/).

Activate KTF Parts Inventory in Plugins.

Upon activation, a custom table {$wpdb->prefix}ktf_parts will be created.

Shortcodes

[ktfparts_list]

Displays the parts inventory table:

Search box

Sortable headers (click to sort ascending/descending)

Quantity column with + / - buttons (AJAX update)

Add New Part and Download CSV buttons

CSV Import form

Usage:

[ktfparts_list]

[ktfparts_add_form]

Renders the Add/Edit form:

Back to Inventory link

Fields: Name, Part #, Category, Quantity (with ± buttons), Condition, Location, Notes

Ajax-driven Add / Update / Delete

Usage:

[ktfparts_add_form]

AJAX Endpoints

All AJAX calls require the nonce parameter matching ktfparts_add_part.

action=ktfparts_add_part — Create or update a part.

action=ktfparts_delete_part — Delete by part_id.

action=ktfparts_update_quantity — Adjust quantity by delta (+1 or -1).

action=ktfparts_export_csv — Triggers CSV download.

action=ktfparts_import_csv — Processes uploaded CSV file.

CSV Format

Header row (10 columns):

ID,Name,Part Number,Category,Quantity,Condition,Location,Notes,Created At,Updated At

If ID matches an existing part_id, that row is updated; otherwise, a new record is created.

Developer Notes

Table schema defined in ktfparts-install.php. Uses dbDelta for versioning.

Functions and AJAX handlers are in includes/ktfparts-functions.php.

JavaScript in ktfparts.js handles all front-end AJAX interactions.

To customize URLs, update the localized inventoryUrl or shortcode output.

Changelog

1.0.0 – Initial release
