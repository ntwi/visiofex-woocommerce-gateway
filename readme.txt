=== VisioFex for WooCommerce ===
Contributors: your-company
Tags: payments, checkout, woocommerce, visiofex, blocks, refunds
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Hosted checkout session for VisioFex/KonaCash with refunds and WooCommerce Blocks support.

== Description ==
- Settings page includes: Test mode, Secret Key, Vendor ID, Your Store Domain, and Debug logging
- Creates a hosted checkout session and redirects the customer to the VisioFex payment page
- Refunds are supported from the WooCommerce order screen

== Installation ==
1. Upload the ZIP via Plugins > Add New > Upload Plugin and click activate.

== Configuration ==
In the VisioFex portal:
- Generate a Production API Key and copy it into the plugin's Secret Key field.
- Locate your App ID and copy it into the Vendor ID field.

- in wordpress find the visiofex plugon and make sure its activated, then click settings
- Paste your production key from visiofex into Secret Key
- Paste your app id from visiofex into Vendor ID.
- Set Your Store Domain to your site URL (for example: https://example.com). Do not include a trailing slash.
- For production, uncheck Enable test.
- click the enable checkbox at thr topnof the plugin
- Save changes and enable the gateway.

Requirements and tips:
- WooCommerce Cart, Checkout, and My Account pages should exist and permalinks should be set to Post name.
- After payment, customers are redirected back to your site’s order received page using Your Store Domain.

== Changelog ==
= 1.2.0 =
* Simplified setup (Secret Key, Vendor ID, Store Domain). Added refund support and Blocks integration.
