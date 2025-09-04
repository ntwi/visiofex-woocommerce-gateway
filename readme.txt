=== VisioFex for WooCommerce ===
Contributors: your-company
Tags: payments, checkout, woocommerce, visiofex, blocks, refunds
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.4.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Hosted checkout session for VisioFex/KonaCash with refunds and WooCommerce Blocks support.

== Description ==
- **Complete Order Integration**: Forwards all order components to VisioFex including products, coupon discounts, shipping, fees, and taxes
- **Enhanced Payment Flow**: Robust redirect fallback system handles plugin conflicts and ensures reliable checkout experience
- **Comprehensive Logging**: Detailed diagnostic logging with sensitive data protection for easy troubleshooting
- **Settings page includes**: Test mode, Secret Key, Vendor ID, Your Store Domain, and Debug logging
- **Hosted Checkout**: Creates a hosted checkout session and redirects customers to the secure VisioFex payment page
- **Full Refund Support**: Process refunds directly from the WooCommerce order screen with automatic synchronization
- **WooCommerce Blocks**: Full compatibility with modern WooCommerce block-based checkout

Requirements and tips:
- WooCommerce Cart, Checkout, and My Account pages should exist and permalinks should be set to Postname WordPress->Settings->Permalinks
- **Enhanced Debugging**: Enable debug logging for comprehensive diagnostics including order totals, coupon processing, and API communications
- **Troubleshooting**: Visit WooCommerce->Status->Logs->VisioFex for detailed logs with automatic sensitive data masking
- **Plugin Compatibility**: Built-in fallback system handles conflicts with other plugins (WhatsApp widgets, etc.)
- **Payment Flow**: The plugin redirects users during checkout - the enhanced fallback system ensures reliable redirects even with plugin interference
- **Order Processing**: Complete order details including all discounts and fees are automatically forwarded to VisioFex
- **Post-Payment**: Customers are redirected back to your site's order received page using Your Store Domain
- **Refund Management**: Orders can be refunded directly by pressing the Refund button on the Order page with real-time synchronization
- **Order Synchronization**: Click Actions->Sync with VisioFex on any order to ensure latest payment and refund status

== Installation ==
1. Upload the ZIP via Plugins > Add New > Upload Plugin and click activate.

== Configuration ==
In the VisioFex portal: (https://visiofex.com/login)
- Click Developer
- Click New App. You can name it whatever you would like.
- Locate your App ID (Should be directly below the "Manage App" this will be entered into the visiofex plugin's Vendor ID field.
- Click API Keys and note your production api key, this will be entered into visiofex plugin's Secret Key field.

In wordpress (https://yoursite/wp-admin): 
- Click Plugins and locate the VisioFex plugin and make sure it's activated
- Navigate to WooCommerce -> Settings -> Payments -> VisioFex Pay
- Paste your production key from VisioFex into the "Secret Key" field
- Paste your app ID from VisioFex into the "Vendor ID" field
- Set "Your Store Domain" to your site URL (for example: https://example.com). Do not include a trailing slash
- For production, uncheck "Enable test mode"
- **Debugging**: Check "Enable debugging" for comprehensive logging (recommended during setup, can be disabled once working)
- Check the "Enable" checkbox at the top to activate the payment method
- Save changes to enable the gateway

== Changelog ==

= 1.4.1 =
* **FIX**: Fixed "back to store" button on VisioFex portal - now correctly returns to checkout page instead of order received page
* **ENHANCED**: Improved URL logging to show return URL in debug logs

= 1.4.0 =
* **MAJOR**: Fixed coupon discounts not being forwarded to VisioFex - now sends complete order breakdown
* **NEW**: Comprehensive line items now include products, coupon discounts, shipping, fees, and taxes
* **NEW**: Added total amount validation with automatic adjustment for rounding differences
* **ENHANCED**: Line items now show final prices after all discounts are applied
* **ENHANCED**: Improved metadata sent to VisioFex includes coupon count and shipping status
* **ENHANCED**: Better logging shows complete order breakdown and line item details
* **FIX**: Discount amounts now properly calculated including discount tax

= 1.3.0 =
* **NEW**: Comprehensive logging system for customer troubleshooting and support
* **NEW**: Sensitive data masking in logs (vendor ID, secret keys automatically protected)
* **NEW**: Enhanced API request/response logging with timing and error details
* **NEW**: Detailed checkout process logging including validation, line items, and redirects
* **NEW**: Complete refund operation logging with amount validation and API responses
* **NEW**: Transaction capture logging with polling sequence and status tracking
* **NEW**: Order synchronization logging including payment status and refund reconciliation
* **ENHANCED**: Log levels support (info, debug, warning, error) for better diagnostics
* **ENHANCED**: Gateway availability logging with detailed validation messages
* **IMPROVED**: Better error messages and response structure analysis in logs

= 1.2.4 =
* **MAJOR**: Enhanced redirect fallback system for improved payment flow reliability
* **NEW**: Robust JSON extraction handles mixed HTML/JSON responses (resolves plugin conflicts)
* **NEW**: Multiple JSON parsing strategies for maximum compatibility with theme/plugin interference
* **ENHANCED**: Both fetch() and XHR request monitoring for comprehensive coverage
* **ENHANCED**: Better error handling and logging for failed redirects
* **FIX**: Resolved conflicts with WhatsApp widgets and other Must Use plugins
* **FIX**: Improved handling of contaminated API responses
* **IMPROVED**: More reliable automatic redirects to VisioFex payment pages

= 1.2.1 =
* **FIX**: PHP 8.2 compatibility - added explicit public properties to resolve dynamic property deprecation warnings
* **ENHANCED**: Better fallback script version management
* **IMPROVED**: Code structure for better maintainability

= 1.2.0 =
* Simplified setup (Secret Key, Vendor ID, Store Domain). Added refund support and Blocks integration.
