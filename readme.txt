=== AfconWave Gateway ===
Contributors: afconwave
Tags: woocommerce, mobile money, payments, global
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Mobile Money and Card payments globally with the official AfconWave gateway for WooCommerce.

== Description ==

AfconWave Gateway lets WooCommerce stores accept payments from MTN Mobile Money, Orange Money, Moov, Wave, Visa and Mastercard across Africa, Asia, UAE, USA and more.

**Features**

* Mobile Money — MTN, Orange, Moov, Wave
* Card payments with 3-D Secure 2.0 (Visa, Mastercard)
* PCI-DSS compliant — no card data ever touches your server
* Signed webhooks with HMAC-SHA256 + replay protection (5-minute tolerance)
* WooCommerce Blocks (Cart/Checkout) compatible
* High-Performance Order Storage (HPOS) compatible
* Sandbox / live mode toggle in WP Admin
* `[afconwave_pay]` shortcode for standalone payment buttons
* Action hooks for custom post-payment logic

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via Plugins → Add New.
2. Activate the plugin through the Plugins screen.
3. Go to WooCommerce → Settings → Payments → AfconWave and enter your API keys (get them from https://dashboard.afconwave.com/api-keys).
4. Copy your store webhook URL (`https://your-site.com/wc-api/afconwave_webhook`) into the AfconWave dashboard and paste back the webhook secret.

== Frequently Asked Questions ==

= Do I need a Stripe-style PCI scope? =
No. All card data is captured on the AfconWave hosted checkout — your server never sees it.

= Does this support WooCommerce Blocks checkout? =
Yes. The plugin registers a Blocks integration and works on both legacy and Block-based checkouts.

= Is HPOS supported? =
Yes. The plugin declares full compatibility with WooCommerce High-Performance Order Storage.

= Can I use the gateway without WooCommerce? =
Use the `[afconwave_pay amount="50" currency="USD"]` shortcode for standalone payment buttons.

== Privacy ==

This plugin utilizes the AfconWave API to process payments. When a customer checks out:
* Order details (amount, currency, customer name, email, and phone) are sent to AfconWave servers to initiate the transaction.
* Payment processing occurs on AfconWave's secure hosted page. No credit card or sensitive financial data is stored or processed on your WordPress server.
* Webhook data is received from AfconWave to update order statuses.

For more information, please see [AfconWave's Privacy Policy](https://afconwave.com/legal/privacy).

== Screenshots ==

1. Payment method selection at checkout.
2. Plugin settings page with API keys and sandbox toggle.

== Changelog ==

= 1.0.0 =
* Initial public release.
* WooCommerce Blocks support.
* HPOS compatibility declaration.
* Webhook signature verification with replay protection.

== Upgrade Notice ==

= 1.0.0 =
First public release.
