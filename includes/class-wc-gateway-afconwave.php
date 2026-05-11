<?php
/**
 * AfconWave WooCommerce Gateway Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_AfconWave extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'afconwave';
        $this->icon = apply_filters('woocommerce_afconwave_icon', plugins_url('assets/afconwave_woo_commerce_logo.png', dirname(__FILE__)));
        $this->has_fields = false;
        $this->supports   = array( 'products' );
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
        $this->api_base_url = 'https://api.afconwave.com/api/v1';

        if ($this->test_mode) {
            $this->description .= '<br/><span class="afconwave-test-mode-badge" style="background-color: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; border: 1px solid #fcd34d; display: inline-block; margin-top: 8px;">TEST MODE ACTIVE</span>';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_afconwave_webhook', array($this, 'handle_webhook'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable AfconWave Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title'    => 'Title',
                'type'     => 'text',
                'default'  => 'AfconWave (Mobile Money/Cards)',
                'desc_tip' => true,
                'description' => 'Payment method name shown to the customer at checkout.'
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'default'     => 'Pay securely with Mobile Money (MTN, Orange, Wave) or your Visa/Mastercard.',
                'desc_tip'    => true,
                'description' => 'Payment method description shown to the customer at checkout.'
            ),
            'test_mode' => array(
                'title' => 'Test Mode',
                'type' => 'checkbox',
                'label' => 'Enable Test Mode',
                'default' => 'yes',
                'description' => 'In test mode, the gateway uses your Test API Keys and no real money is processed.'
            ),
            'test_secret_key' => array(
                'title' => 'Test Secret Key',
                'type' => 'password',
                'description' => 'Find your keys in the AfconWave Dashboard → API Keys.'
            ),
            'test_public_key' => array(
                'title' => 'Test Public Key',
                'type' => 'text'
            ),
            'live_secret_key' => array(
                'title' => 'Live Secret Key',
                'type' => 'password',
                'description' => 'Used for real transactions. Keep this key secure.'
            ),
            'live_public_key' => array(
                'title' => 'Live Public Key',
                'type' => 'text'
            ),
            'webhook_secret' => array(
                'title' => 'Webhook Secret',
                'type' => 'password',
                'description' => 'Used to verify that webhook events are actually sent from AfconWave.',
                'desc_tip' => true,
            )
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $logger = wc_get_logger();

        if (!$order) {
            $logger->error('AfconWave: Invalid order ID ' . $order_id, array('source' => 'afconwave'));
            wc_add_notice(esc_html__('Invalid order. Please try again.', 'afconwave-secure-gateway'), 'error');
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
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $logger->error('AfconWave API Error for Order ' . $order_id . ': ' . $response->get_error_message(), array('source' => 'afconwave'));
            wc_add_notice(esc_html__('Payment processing failed. Please try again or contact support.', 'afconwave-secure-gateway'), 'error');
            return array('result' => 'fail');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code < 200 || $response_code >= 300) {
            $logger->error('AfconWave API HTTP Error for Order ' . $order_id . ': ' . $response_code . ' - ' . $body, array('source' => 'afconwave'));
            wc_add_notice(esc_html__('Payment gateway is temporarily unavailable. Please try again later.', 'afconwave-secure-gateway'), 'error');
            return array('result' => 'fail');
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('AfconWave: Invalid JSON response for Order ' . $order_id . ': ' . $body, array('source' => 'afconwave'));
            wc_add_notice(esc_html__('Payment gateway error. Please try again later.', 'afconwave-secure-gateway'), 'error');
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
        wc_add_notice(sprintf(esc_html__('Payment could not be processed: %s', 'afconwave-secure-gateway'), esc_html($error_message)), 'error');
        return array('result' => 'fail');
    }

    public function handle_webhook()
    {
        $logger = wc_get_logger();
        $payload = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_AFCONWAVE_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_AFCONWAVE_SIGNATURE'])) : '';

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

        // 🛡️ Replay Protection (Security Hardened)
        if (isset($event['timestamp'])) {
            $current_time = time();
            $webhook_time = (int) ($event['timestamp'] / 1000); // ms to s
            $age = abs($current_time - $webhook_time);

            if ($age > 300) { // 5 minute tolerance
                $logger->warning('AfconWave Webhook: Rejected due to timestamp age (' . $age . 's)', array('source' => 'afconwave'));
                status_header(401);
                exit('Webhook rejected: Timestamp tolerance exceeded');
            }
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
                    $order->add_order_note(sprintf(esc_html__('AfconWave payment successful. Transaction ID: %s', 'afconwave-secure-gateway'), esc_html($event['data']['id'])));
                    $logger->info('AfconWave Webhook: Payment completed for Order ' . $order_id, array('source' => 'afconwave'));
                    do_action('afconwave_payment_success', $order_id, $event['data']);
                } else {
                    $logger->info('AfconWave Webhook: Payment already processed for Order ' . $order_id, array('source' => 'afconwave'));
                }
                break;
            case 'payment.failed':
                $order->update_status('failed', esc_html__('AfconWave payment failed.', 'afconwave-secure-gateway'));
                $logger->warning('AfconWave Webhook: Payment failed for Order ' . $order_id, array('source' => 'afconwave'));
                do_action('afconwave_payment_failed', $order_id, $event['data']);
                break;
            case 'payment.refunded':
                $refund_amount = isset($event['data']['amount']) ? (floatval($event['data']['amount']) / 100) : null;
                $reason        = isset($event['data']['reason']) ? sanitize_text_field($event['data']['reason']) : '';
                if ($refund_amount !== null) {
                    wc_create_refund(array(
                        'order_id'       => $order_id,
                        'amount'         => $refund_amount,
                        'reason'         => $reason ?: esc_html__('Refunded via AfconWave', 'afconwave-secure-gateway'),
                        'refund_payment' => false, // money already returned by AfconWave
                    ));
                }
                $order->add_order_note(sprintf(esc_html__('AfconWave refund processed. Transaction ID: %s', 'afconwave-secure-gateway'), esc_html($event['data']['id'] ?? '')));
                $logger->info('AfconWave Webhook: Refund processed for Order ' . $order_id, array('source' => 'afconwave'));
                do_action('afconwave_payment_refunded', $order_id, $event['data']);
                break;
            default:
                $logger->info('AfconWave Webhook: Unhandled event type ' . $event['type'] . ' for Order ' . $order_id, array('source' => 'afconwave'));
                break;
        }

        status_header(200);
        exit('Webhook handled');
    }
}
