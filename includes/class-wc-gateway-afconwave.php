<?php
/**
 * AfconWave WooCommerce Gateway Class
 */

class WC_Gateway_AfconWave extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'afconwave';
        $this->icon = apply_filters('woocommerce_afconwave_icon', plugins_url('assets/afconwave_woo_commerce_logo.png', dirname(__FILE__)));
        $this->has_fields = false;
        $this->method_title = 'AfconWave';
        $this->method_description = 'Accept payments via Mobile Money and Cards in Africa.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->test_mode = 'yes' === $this->get_option('test_mode');
        $this->secret_key = $this->test_mode ? $this->get_option('test_secret_key') : $this->get_option('live_secret_key');
        $this->public_key = $this->test_mode ? $this->get_option('test_public_key') : $this->get_option('live_public_key');
        $this->webhook_secret = $this->get_option('webhook_secret');
        $this->api_base_url = $this->test_mode ? 'https://sandbox.api.afconwave.com/v1' : 'https://api.afconwave.com/v1';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_afconwave_webhook', array($this, 'handle_webhook'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable AfconWave Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'default' => 'AfconWave (Mobile Money/Cards)'
            ),
            'test_mode' => array(
                'title' => 'Test Mode',
                'type' => 'checkbox',
                'label' => 'Enable Test Mode',
                'default' => 'yes'
            ),
            'test_secret_key' => array(
                'title' => 'Test Secret Key',
                'type' => 'password'
            ),
            'test_public_key' => array(
                'title' => 'Test Public Key',
                'type' => 'text'
            ),
            'live_secret_key' => array(
                'title' => 'Live Secret Key',
                'type' => 'password'
            ),
            'live_public_key' => array(
                'title' => 'Live Public Key',
                'type' => 'text'
            ),
            'webhook_secret' => array(
                'title' => 'Webhook Secret',
                'type' => 'password',
                'description' => 'Enter the webhook secret from your AfconWave dashboard to verify incoming events.',
                'desc_tip' => true,
            )
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $logger = wc_get_logger();

        if (!$order) {
            $logger->error('AfconWave: Invalid order ID ' . $order_id, array('source' => 'afconwave'));
            wc_add_notice('Invalid order. Please try again.', 'error');
            return array('result' => 'fail');
        }

        $payload = array(
            'amount' => (int) round($order->get_total() * 100), // convert to minor units
            'currency' => $order->get_currency(),
            'customer_email' => $order->get_billing_email(),
            'description' => 'Payment for Order #' . $order->get_order_number(),
            'callback_url' => $this->get_return_url($order),
            'metadata' => array(
                'order_id' => $order_id
            )
        );

        $response = wp_remote_post($this->api_base_url . '/payments', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $logger->error('AfconWave API Error for Order ' . $order_id . ': ' . $response->get_error_message(), array('source' => 'afconwave'));
            wc_add_notice('Payment processing failed. Please try again or contact support.', 'error');
            return array('result' => 'fail');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $logger->error('AfconWave API HTTP Error for Order ' . $order_id . ': ' . $response_code . ' - ' . $body, array('source' => 'afconwave'));
            wc_add_notice('Payment gateway is temporarily unavailable. Please try again later.', 'error');
            return array('result' => 'fail');
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('AfconWave: Invalid JSON response for Order ' . $order_id . ': ' . $body, array('source' => 'afconwave'));
            wc_add_notice('Payment gateway error. Please try again later.', 'error');
            return array('result' => 'fail');
        }

        if (!empty($data['checkout_url'])) {
            $logger->info('AfconWave: Payment initiated for Order ' . $order_id . ', redirecting to ' . $data['checkout_url'], array('source' => 'afconwave'));
            return array(
                'result' => 'success',
                'redirect' => $data['checkout_url']
            );
        }

        $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
        $logger->error('AfconWave: Payment failed for Order ' . $order_id . ': ' . $error_message, array('source' => 'afconwave'));
        wc_add_notice('Payment could not be processed: ' . $error_message, 'error');
        return array('result' => 'fail');
    }

    public function handle_webhook() {
        $logger = wc_get_logger();
        $payload = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_AFCONWAVE_SIGNATURE']) ? $_SERVER['HTTP_X_AFCONWAVE_SIGNATURE'] : '';

        if (empty($payload) || empty($signature)) {
            $logger->warning('AfconWave Webhook: Missing payload or signature', array('source' => 'afconwave'));
            status_header(400);
            exit('Missing payload or signature');
        }

        $expected_signature = hash_hmac('sha256', $payload, $this->webhook_secret);

        if (!hash_equals($expected_signature, $signature)) {
            $logger->warning('AfconWave Webhook: Invalid signature', array('source' => 'afconwave'));
            status_header(401);
            exit('Invalid signature');
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) {
            $logger->warning('AfconWave Webhook: Invalid JSON payload - ' . $payload, array('source' => 'afconwave'));
            status_header(400);
            exit('Invalid JSON payload');
        }

        $order_id = isset($event['data']['metadata']['order_id']) ? intval($event['data']['metadata']['order_id']) : 0;
        if (!$order_id) {
            $logger->info('AfconWave Webhook: No order ID provided in event ' . $event['type'], array('source' => 'afconwave'));
            status_header(200);
            exit('No order ID provided');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $logger->error('AfconWave Webhook: Order ' . $order_id . ' not found', array('source' => 'afconwave'));
            status_header(404);
            exit('Order not found');
        }

        $logger->info('AfconWave Webhook: Processing event ' . $event['type'] . ' for Order ' . $order_id, array('source' => 'afconwave'));

        switch ($event['type']) {
            case 'payment.success':
                if (!$order->has_status('processing') && !$order->has_status('completed')) {
                    $order->payment_complete($event['data']['id']);
                    $order->add_order_note('AfconWave payment successful. Transaction ID: ' . $event['data']['id']);
                    $logger->info('AfconWave Webhook: Payment completed for Order ' . $order_id, array('source' => 'afconwave'));
                    do_action('afconwave_payment_success', $order_id, $event['data']);
                } else {
                    $logger->info('AfconWave Webhook: Payment already processed for Order ' . $order_id, array('source' => 'afconwave'));
                }
                break;
            case 'payment.failed':
                $order->update_status('failed', 'AfconWave payment failed.');
                $logger->warning('AfconWave Webhook: Payment failed for Order ' . $order_id, array('source' => 'afconwave'));
                do_action('afconwave_payment_failed', $order_id, $event['data']);
                break;
            default:
                $logger->info('AfconWave Webhook: Unhandled event type ' . $event['type'] . ' for Order ' . $order_id, array('source' => 'afconwave'));
                break;
        }

        status_header(200);
        exit('Webhook handled');
    }
}
