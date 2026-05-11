<?php
/**
 * WooCommerce Blocks (Cart/Checkout) integration for AfconWave gateway.
 * Registers the gateway as a payment method on the Block-based checkout.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_AfconWave_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'afconwave';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_afconwave_settings', array());
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        $script_path = '/assets/js/blocks-checkout.js';
        $script_url  = plugins_url($script_path, dirname(__FILE__));
        $script_asset_path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/blocks-checkout.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array('dependencies' => array('wc-blocks-registry', 'wp-element', 'wp-html-entities'), 'version' => '1.0.0');

        wp_register_script(
            'wc-afconwave-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return array('wc-afconwave-blocks-integration');
    }

    public function get_payment_method_data()
    {
        return array(
            'title'       => $this->get_setting('title', 'AfconWave (Mobile Money / Card)'),
            'description' => $this->get_setting('description', 'Pay securely with Mobile Money or your card.'),
            'supports'    => array('products'),
            'icon'        => plugins_url('assets/afconwave_woo_commerce_logo.png', dirname(__FILE__)),
        );
    }
}
