<?php
/**
 * AfconWave Gateway
 *
 * @package           AfconWaveGateway
 * @author            AfconWave Team
 * @copyright         2026 AfconWave
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AfconWave Gateway
 * Plugin URI:        https://afconwave.com/docs/wordpress
 * Description:       Securely accept Mobile Money and Card payments globally via the AfconWave gateway.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Author:            AfconWave Team
 * Author URI:        https://afconwave.com
 * Text Domain:       afconwave-gateway
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    return;
}

/**
 * Initialize the gateway
 */
function afconwave_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    require_once plugin_dir_path(__FILE__) . 'includes/class-afconwave-gateway.php';

    add_filter('woocommerce_payment_gateways', 'afconwave_add_gateway_class');
}
add_action('plugins_loaded', 'afconwave_gateway_init');

function afconwave_add_gateway_class($methods)
{
    $methods[] = 'AfconWave_Gateway';
    return $methods;
}

/**
 * Declare WooCommerce feature compatibility (HPOS, Cart/Checkout Blocks).
 * Required by WooCommerce 8.0+ — without this the plugin shows as "incompatible"
 * in the WooCommerce → Settings → Advanced → Features admin screen.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});

/**
 * Register the gateway with WooCommerce Blocks (Cart/Checkout).
 */
add_action('woocommerce_blocks_loaded', function () {
    if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }
    require_once plugin_dir_path(__FILE__) . 'includes/class-afconwave-blocks-support.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ($payment_method_registry) {
            $payment_method_registry->register(new AfconWave_Blocks_Support());
        }
    );
});

/**
 * Add settings link to plugins page
 */
function afconwave_gateway_settings_link($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=afconwave')) . '">' . esc_html__('Settings', 'afconwave-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'afconwave_gateway_settings_link');

/**
 * Shortcode for standalone AfconWave payment button
 * [afconwave_pay amount="5000" currency="XAF" description="Donation"]
 */
function afconwave_pay_shortcode_handler($atts)
{
    $atts = shortcode_atts(array(
        'amount' => '0',
        'currency' => 'USD',
        'description' => 'Payment',
        'callback_url' => home_url(),
    ), $atts, 'afconwave_pay');

    if (floatval($atts['amount']) <= 0) {
        return '<p style="color:red;">' . esc_html__('Invalid payment amount.', 'afconwave-gateway') . '</p>';
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
        <?php
        /* translators: %s: payment amount and currency */
        $button_label = sprintf(esc_html__('Pay %s with AfconWave', 'afconwave-gateway'), esc_html($atts['amount'] . ' ' . $atts['currency']));
        ?>
        <button type="submit" class="button afconwave-pay-btn"
            style="background-color: #047857; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            <?php echo $button_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('afconwave_pay', 'afconwave_pay_shortcode_handler');

/**
 * Handle the standalone payment form submission
 */
function afconwave_handle_standalone_pay()
{
    if (!isset($_POST['afconwave_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['afconwave_nonce'])), 'afconwave_pay_nonce')) {
        wp_die(esc_html__('Invalid security token.', 'afconwave-gateway'), esc_html__('Error', 'afconwave-gateway'), array('response' => 403));
    }

    $amount = isset($_POST['amount']) ? floatval(wp_unslash($_POST['amount'])) : 0;
    $currency = isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : '';
    $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
    $callback_url = isset($_POST['callback_url']) ? esc_url_raw(wp_unslash($_POST['callback_url'])) : '';

    if ($amount <= 0) {
        wp_die(esc_html__('Invalid payment amount.', 'afconwave-gateway'));
    }

    $settings = get_option('woocommerce_afconwave_settings');
    $test_mode = isset($settings['test_mode']) && $settings['test_mode'] === 'yes';
    $secret_key = $test_mode ? $settings['test_secret_key'] : $settings['live_secret_key'];
    $api_base_url = 'https://api.afconwave.com/api/v1';

    if (empty($secret_key)) {
        wp_die(esc_html__('Payment gateway is not fully configured.', 'afconwave-gateway'), esc_html__('Error', 'afconwave-gateway'), array('response' => 500));
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
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($payload),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        wp_die(esc_html__('Payment error: ', 'afconwave-gateway') . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['checkout_url'])) {
        wp_safe_redirect($data['checkout_url']);
        exit;
    }

    wp_die(esc_html__('Failed to initialize payment.', 'afconwave-gateway'));
}
add_action('admin_post_nopriv_afconwave_standalone_pay', 'afconwave_handle_standalone_pay');
add_action('admin_post_afconwave_standalone_pay', 'afconwave_handle_standalone_pay');
