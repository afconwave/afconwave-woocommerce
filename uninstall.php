<?php
/**
 * AfconWave Secure Gateway — Uninstall handler.
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
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_afconwave_%' OR option_name LIKE '_transient_timeout_afconwave_%'"
);
