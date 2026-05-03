<?php
/**
 * Plugin Name: AfconWave Secure Gateway
 * Plugin URI: https://afconwave.com
 * Description: Secure payment gateway for WooCommerce by AfconWave.
 * Version: 1.0.0
 * Author: AfconWave Team
 * Author URI: https://afconwave.com
 * License: GPLv2 or later
 * Text Domain: afconwave-secure-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Auto-Update from GitHub Releases ────────────────────────────────────────
// This lets merchants who installed this plugin directly (via ZIP) receive
// automatic "Update Available" notifications in their WP Admin dashboard
// whenever a new release is published on GitHub.
//
// Requires the plugin-update-checker library (bundled in /lib/).
// Library source: https://github.com/YahnisElsts/plugin-update-checker
//
// HOW IT WORKS:
//  1. When a merchant visits WP Admin → Plugins, WordPress checks for updates.
//  2. This checker queries https://github.com/afconwave/afconwave-woocommerce/releases
//  3. It compares the latest release tag (e.g. v1.2.0) with the installed Version above.
//  4. If a newer version exists, WordPress shows "Update Available" automatically.
//  5. The merchant clicks "Update Now" — WP downloads the new ZIP from GitHub and installs it.
//
// TO RELEASE A NEW VERSION:
//  1. Bump the `Version:` header at the top of this file (e.g. 1.0.0 → 1.1.0)
//  2. Push to main — the GitHub Action syncs it to the public repo automatically.
//  3. Create a new GitHub Release tagged `v1.1.0` on the afconwave-woocommerce repo.
//  4. Attach the plugin ZIP as a release asset named `afconwave-secure-gateway.zip`.
//  5. All merchants with the plugin installed will see the update within 12 hours.
// ─────────────────────────────────────────────────────────────────────────────
if (file_exists(plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php')) {
    require plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';

    $afconwave_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/afconwave/afconwave-woocommerce',
        __FILE__,
        'afconwave-secure-gateway'
    );

    // Tell the checker to look at GitHub Releases (not the readme.txt)
    $afconwave_update_checker->getVcsApi()->enableReleaseAssets();
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Initialize the gateway
 */
function afconwave_secure_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-afconwave.php';

    add_filter('woocommerce_payment_gateways', 'afconwave_add_gateway_class');
}
add_action('plugins_loaded', 'afconwave_secure_gateway_init');

function afconwave_add_gateway_class($methods) {
    $methods[] = 'WC_Gateway_AfconWave';
    return $methods;
}

/**
 * Add settings link to plugins page
 */
function afconwave_secure_gateway_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=afconwave">' . __('Settings', 'afconwave-secure-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'afconwave_secure_gateway_settings_link');

/**
 * Shortcode for standalone AfconWave payment button
 * [afconwave_pay amount="5000" currency="XAF" description="Donation"]
 */
function afconwave_pay_shortcode_handler($atts) {
    $atts = shortcode_atts(array(
        'amount' => '0',
        'currency' => 'XAF',
        'description' => 'Payment',
        'callback_url' => home_url(),
    ), $atts, 'afconwave_pay');

    if (floatval($atts['amount']) <= 0) {
        return '<p style="color:red;">Invalid payment amount.</p>';
    }

    ob_start();
    ?>
    <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="afconwave-pay-form">
        <input type="hidden" name="action" value="afconwave_standalone_pay">
        <input type="hidden" name="amount" value="<?php echo esc_attr($atts['amount']); ?>">
        <input type="hidden" name="currency" value="<?php echo esc_attr($atts['currency']); ?>">
        <input type="hidden" name="description" value="<?php echo esc_attr($atts['description']); ?>">
        <input type="hidden" name="callback_url" value="<?php echo esc_attr($atts['callback_url']); ?>">
        <?php wp_nonce_field('afconwave_pay_nonce', 'afconwave_nonce'); ?>
        <button type="submit" class="button afconwave-pay-btn" style="background-color: #047857; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            Pay <?php echo esc_html($atts['amount'] . ' ' . $atts['currency']); ?> with AfconWave
        </button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('afconwave_pay', 'afconwave_pay_shortcode_handler');

/**
 * Handle the standalone payment form submission
 */
function afconwave_handle_standalone_pay() {
    if (!isset($_POST['afconwave_nonce']) || !wp_verify_nonce($_POST['afconwave_nonce'], 'afconwave_pay_nonce')) {
        wp_die('Invalid security token.', 'Error', array('response' => 403));
    }

    $amount = floatval($_POST['amount']);
    $currency = sanitize_text_field($_POST['currency']);
    $description = sanitize_text_field($_POST['description']);
    $callback_url = esc_url_raw($_POST['callback_url']);

    $settings = get_option('woocommerce_afconwave_settings');
    $test_mode = isset($settings['test_mode']) && $settings['test_mode'] === 'yes';
    $secret_key = $test_mode ? $settings['test_secret_key'] : $settings['live_secret_key'];
    $api_base_url = $test_mode ? 'https://sandbox.api.afconwave.com/v1' : 'https://api.afconwave.com/v1';

    if (empty($secret_key)) {
        wp_die('Payment gateway is not fully configured.', 'Error', array('response' => 500));
    }

    $payload = array(
        'amount' => (int) round($amount * 100), // minor units
        'currency' => $currency,
        'description' => $description,
        'callback_url' => $callback_url,
    );

    $response = wp_remote_post($api_base_url . '/payments', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        wp_die('Payment error: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['checkout_url'])) {
        wp_redirect($data['checkout_url']);
        exit;
    }

    wp_die('Failed to initialize payment.');
}
add_action('admin_post_nopriv_afconwave_standalone_pay', 'afconwave_handle_standalone_pay');
add_action('admin_post_afconwave_standalone_pay', 'afconwave_handle_standalone_pay');
