<?php
/**
 * AfconWave Gateway — Uninstall handler.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 * Removes plugin options. Order data is left intact (managed by WooCommerce).
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove gateway settings
delete_option('woocommerce_afconwave_settings');

// Remove any transients used for webhook idempotency / rate limiting
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_afconwave\_%' OR option_name LIKE '\_transient\_timeout\_afconwave\_%'"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
