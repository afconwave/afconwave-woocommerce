# AfconWave Secure Gateway

Accept mobile money and card payments in Africa securely via the official AfconWave gateway for WooCommerce.

Contributors: afconwave
Tags: woocommerce, mobile-money, cards, payments, africa
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

---

## Features

- ✅ **Mobile Money** — MTN MoMo, Orange Money, Moov, Wave
- 💳 **Card Payments** — Visa & Mastercard with 3D Secure 2.0
- 🔒 **PCI-DSS Compliant** — No sensitive card data stored on your server
- 🔔 **Auto Webhooks** — Signed webhook endpoint auto-registered at setup
- 🧪 **Sandbox Mode** — Full test environment toggle in WP Admin
- 📦 **Zero Dependencies** — Ships as a self-contained plugin

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8 or higher |
| WooCommerce | 6.0 or higher |
| PHP | 7.4 or higher |
| SSL Certificate | Required (HTTPS) |

---

## Installation

### Option 1 — WordPress Admin (Recommended)

1. Go to **Plugins → Add New** in your WP Admin.
2. Search for `AfconWave Secure Gateway`.
3. Click **Install Now**, then **Activate**.

### Option 2 — Manual Upload

1. Download the plugin `.zip` file from [docs.afconwave.com/wordpress](https://docs.afconwave.com/wordpress).
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the `.zip` file and click **Install Now**, then **Activate**.

### Option 3 — WP-CLI

```bash
wp plugin install afconwave-secure-gateway --activate
```

---

## Setup Guide

### 1. Open Plugin Settings

Go to:

```
WooCommerce → Settings → Payments → AfconWave Secure Gateway
```

Click **Manage**.

### 2. Enter Your API Keys

| Field | Description |
|---|---|
| **Live Secret Key** | `afc_sk_live_...` — Used in production. |
| **Test Secret Key** | `afc_sk_test_...` — Used in sandbox mode. |
| **Live Public Key** | `afc_pk_live_...` — Used for Checkout JS. |
| **Test Public Key** | `afc_pk_test_...` — Used in sandbox Checkout. |

Find these keys in your [AfconWave Merchant Dashboard](https://dashboard.afconwave.com/api-keys).

### 3. Configure the Webhook

The plugin automatically registers a webhook endpoint at:

```
https://your-site.com/wc-api/afconwave_webhook
```

Copy this URL and add it in your [AfconWave Merchant Dashboard](https://dashboard.afconwave.com/webhooks) under **Webhooks**. Set the event types to:

- `payment.success`
- `payment.failed`
- `refund.success`

Copy the **Webhook Secret** shown in the dashboard and paste it into the plugin settings field.

### 4. Enable Sandbox Mode

Toggle **Sandbox Mode** to `ON` for testing. Your WooCommerce store will use the Test API keys and no real money will be processed.

### 5. Save and Test

1. Save settings.
2. Visit your WooCommerce store and add an item to your cart.
3. Proceed to checkout and select **AfconWave** as the payment method.
4. Complete a test payment using sandbox credentials.
5. Verify the order status updates correctly in **WooCommerce → Orders**.

---

## WooCommerce Order Flow

```
Customer selects AfconWave at checkout
        ↓
Plugin creates a payment via AfconWave API
        ↓
Customer redirected to AfconWave Checkout page
        ↓
Customer completes payment (Mobile Money / Card)
        ↓
AfconWave sends webhook → Plugin receives it
        ↓
Order status updated: Pending → Processing (or Failed)
        ↓
Customer redirected to Order Confirmation page
```

---

## Supported Payment Methods

| Method | Countries |
|---|---|
| MTN Mobile Money | Cameroon, Ghana, Uganda, Ivory Coast |
| Orange Money | Cameroon, Ivory Coast, Senegal |
| Wave | Senegal, Ivory Coast |
| Moov Money | Togo, Benin, Burkina Faso |
| Visa / Mastercard | All supported countries |

---

## Shortcode Reference

Display a standalone payment button anywhere on your site:

```
[afconwave_pay amount="5000" currency="XAF" description="Donation"]
```

| Attribute | Required | Description |
|---|---|---|
| `amount` | ✅ | Amount in minor units |
| `currency` | ✅ | ISO 4217 currency code |
| `description` | ✗ | Payment description shown to customer |
| `callback_url` | ✗ | Override redirect URL after payment |

---

## Advanced: Custom Hooks

Extend the plugin's behavior with WordPress action and filter hooks:

```php
// Trigger custom logic after successful payment
add_action('afconwave_payment_success', function($order_id, $payment_data) {
    // $order_id = WooCommerce order ID
    // $payment_data = AfconWave API response
    error_log("AfconWave payment confirmed for order: $order_id");
}, 10, 2);

// Trigger custom logic after payment failure
add_action('afconwave_payment_failed', function($order_id, $error) {
    error_log("Payment failed for order $order_id: $error");
}, 10, 2);
```

---

## Troubleshooting

| Issue | Solution |
|---|---|
| Orders stay "Pending" | Verify webhook URL is correctly added in AfconWave Dashboard |
| "Invalid Signature" errors | Ensure Webhook Secret matches in both plugin and Dashboard |
| Payment page not loading | Confirm HTTPS is active on your site |
| Sandbox not working | Ensure Sandbox Mode is ON and Test Keys are entered |

Enable WordPress debug mode for detailed logs:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `wp-content/debug.log` for AfconWave-related entries.

---

---

## Sandbox & Test Mode (Stripe-style)

To implement a robust sandbox environment like Stripe without complex subdomains:

1. **API Key Prefixing**: Ensure your test keys start with `afc_sk_test_` and live keys with `afc_sk_live_`.
2. **Backend Logic**: In your Node.js backend (Render), check the incoming `Authorization` header. If the key contains `_test_`, route the transaction to a test database or a mock payment provider.
3. **Indicator**: The WordPress plugin now automatically shows a "TEST MODE ACTIVE" badge in the checkout when sandbox mode is enabled, providing immediate feedback to customers and testers.

---

## Documentation

Full setup guide and API reference: [docs.afconwave.com/wordpress](https://docs.afconwave.com/wordpress)

---

## License

GPL v2 © AfconWave
