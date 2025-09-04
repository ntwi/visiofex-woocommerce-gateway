<?php
// Declare HPOS (High-Performance Order Storage) compatibility
// HPOS compatibility: declare plugin compatibility (does not toggle site feature)
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
/**
 * QUICK CONFIG (defaults shown in the Settings page)
 * If you prefer not to hardcode anything, leave these blank and configure in WooCommerce > Settings > Payments > VisioFex Pay.
 */
define('VXF_DEFAULT_TESTMODE', true);
define('VXF_DEFAULT_SECRET_KEY', '');
define('VXF_DEFAULT_VENDOR_ID', '');
/** Your store's public base URL (used to prefill success/return/cancel). */
define('VXF_DEFAULT_STORE_DOMAIN', 'https://yourdomain.com');
// Deprecated settings removed: public key, webhook secret, API base override, checkout mode

/**
 * Plugin Name: VisioFex for WooCommerce
 * Plugin URI:  https://example.com/visiofex-woocommerce
 * Description: VisioFex/KonaCash hosted checkout for WooCommerce with refunds, Blocks support, and easy settings for keys, vendor id, and URLs.
 * Author:      NexaFlow Payments
 * Author URI:  https://nexaflowpayments.com
 * Version:     1.4.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.2
 * License:     GPLv3
 * Text Domain: visiofex-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VXF_WC_VERSION', '1.4.0' );
define( 'VXF_WC_PLUGIN_FILE', __FILE__ );
define( 'VXF_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VXF_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Make sure WooCommerce is active.
 */
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>VisioFex for WooCommerce</strong> requires WooCommerce to be active.</p></div>';
        } );
        return;
    }
}, 1 );

/**
 * Register gateway.
 */
add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
    $gateways[] = 'WC_Gateway_VisioFex';
    return $gateways;
} );

/**
 * Add Settings link on Plugins page.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=visiofex' );
    $links[] = '<a href="' . esc_url( $url ) . '">Settings</a>';
    return $links;
} );

/**
 * Enqueue redirect fallback script on checkout pages.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
        wp_enqueue_script(
            'visiofex-redirect-fallback',
            VXF_WC_PLUGIN_URL . 'assets/js/visiofex-redirect-fallback.js',
            array(),
            VXF_WC_VERSION,
            true
        );
    }
} );

/**
 * Gateway class.
 */
add_action( 'plugins_loaded', function() {

    class WC_Gateway_VisioFex extends WC_Payment_Gateway {
        // Declare properties to fix PHP 8.2+ deprecation warnings
        public $testmode;
        public $secret_key;
        public $vendor_id;
        public $store_domain;
        public $api_base;
        public $mode;
        public $logging;

        public function __construct() {
            $this->id                 = 'visiofex';
            // Use the icon property — Woo renders this properly as an <img>
            $this->icon = VXF_WC_PLUGIN_URL . 'assets/visiofex-logo.png';//plugins_url( 'assets/visiofex-logo.png', __FILE__ );
            // No on-page fields; we redirect to hosted checkout
            $this->has_fields = false;
            $this->method_title       = __( 'VisioFex Pay', 'visiofex-woocommerce' );
            $this->method_description = __( 'Redirect customers to VisioFex/KonaCash hosted checkout. Supports refunds.', 'visiofex-woocommerce' );
            $this->supports           = array( 'products', 'refunds' );

            $this->init_form_fields();
            $this->init_settings();

            // Settings values with top-of-file defaults as fallback
            $this->enabled        = $this->get_option( 'enabled', 'no' );
            $this->title          = $this->get_option( 'title', 'VisioFex Pay' );
            $this->description    = $this->get_option( 'description', '' );
            $this->testmode       = 'yes' === $this->get_option( 'testmode', VXF_DEFAULT_TESTMODE ? 'yes' : 'no' );
            $this->secret_key     = $this->get_option( 'secret_key', VXF_DEFAULT_SECRET_KEY );
            $this->vendor_id      = $this->get_option( 'vendor_id', VXF_DEFAULT_VENDOR_ID );
            $this->store_domain   = rtrim( $this->get_option( 'store_domain', VXF_DEFAULT_STORE_DOMAIN ), '/' );
            $this->api_base       = '';
            $this->mode           = 'payment';
            $this->logging        = 'yes' === $this->get_option( 'logging', 'yes' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Webhook endpoint disabled for simplified setup
            // add_action( 'woocommerce_api_wc_gateway_visiofex', array( $this, 'handle_webhook' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'vxf_capture_transaction_on_return' ), 10, 1 );

        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'visiofex-woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable VisioFex Pay', 'visiofex-woocommerce' ),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __( 'Title', 'visiofex-woocommerce' ),
                    'type'        => 'text',
                    'default'     => __( 'VisioFex Pay', 'visiofex-woocommerce' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'visiofex-woocommerce' ),
                    'type'        => 'textarea',
                    'default'     => __( 'Pay securely with VisioFex. Please ensure that pop-ups are enabled. Once you click "Place Order" the payment will be processed securely through our payment gateway. This is linked to our merchant account.', 'visiofex-woocommerce' ),
                    'description' => __( 'This text will be displayed to customers during checkout.', 'visiofex-woocommerce' ),
                ),
                'testmode' => array(
                    'title'       => __( 'Test mode', 'visiofex-woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable test (sandbox) mode', 'visiofex-woocommerce' ),
                    'default'     => VXF_DEFAULT_TESTMODE ? 'yes' : 'no',
                ),
                'secret_key' => array(
                    'title'       => __( 'Secret Key', 'visiofex-woocommerce' ),
                    'type'        => 'password',
                    'default'     => VXF_DEFAULT_SECRET_KEY,
                ),
                'vendor_id' => array(
                    'title'       => __( 'Vendor ID', 'visiofex-woocommerce' ),
                    'type'        => 'text',
                    'default'     => VXF_DEFAULT_VENDOR_ID,
                    'description' => __( 'Provided by VisioFex/KonaCash.', 'visiofex-woocommerce' ),
                ),
                'store_domain' => array(
                    'title'       => __( 'Your Store Domain', 'visiofex-woocommerce' ),
                    'type'        => 'text',
                    'default'     => VXF_DEFAULT_STORE_DOMAIN,
                    'description' => __( 'Used to prefill success/return/cancel URLs.', 'visiofex-woocommerce' ),
                ),
                'logging' => array(
                    'title'       => __( 'Debug log', 'visiofex-woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable logging (WooCommerce > Status > Logs)', 'visiofex-woocommerce' ),
                    'default'     => 'yes',
                ),
            );
        }

        protected function get_api_base() {
            if ( ! empty( $this->api_base ) ) return untrailingslashit( $this->api_base );
            return $this->testmode ? 'https://api.konacash.com/v1' : 'https://api.konacash.com/v1'; // adjust if live differs
        }

        // Change signature
        protected function request( $method, $path, $body = null, $idempotency_key = null ) {
            $this->log( 'API Request - Method: ' . $method . ', Path: ' . $path . ', Has body: ' . ( $body ? 'yes' : 'no' ) );
            
            $headers = array(
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->secret_key,
                'User-Agent'   => 'VisioFex-WooCommerce/' . VXF_WC_VERSION,
            );
            if ( $idempotency_key && in_array( strtoupper( $method ), array('POST','PUT','PATCH','DELETE'), true ) ) {
                $headers['Idempotency-Key'] = $idempotency_key;
                $this->log( 'API Request - Using idempotency key: ' . substr( $idempotency_key, 0, 8 ) . '...' );
            }

            // Validate configuration
            if ( empty( $this->secret_key ) ) {
                $this->log( 'API Request warning: No secret key configured', 'warning' );
            }

            $args = array(
                'timeout' => 45,
                'headers' => $headers,
                'method'  => $method,
            );
            if ( $body !== null ) { 
                $args['body'] = wp_json_encode( $body );
                $masked_body = $this->mask_sensitive_data( $body );
                $this->log( 'API Request body (masked): ' . wp_json_encode( $masked_body ) );
            }

            $url = trailingslashit( $this->get_api_base() ) . ltrim( $path, '/' );
            $this->log( 'API Request URL: ' . $url );

            $start_time = microtime( true );
            $res = wp_remote_request( $url, $args );
            $duration = round( ( microtime( true ) - $start_time ) * 1000, 2 );
            
            if ( is_wp_error( $res ) ) {
                $error_message = $res->get_error_message();
                $this->log( 'API Request failed - Error: ' . $error_message . ', Duration: ' . $duration . 'ms', 'error' );
                return array( 'code' => 0, 'body' => null, 'headers' => array() );
            }
            
            $code = wp_remote_retrieve_response_code( $res );
            $response_body = wp_remote_retrieve_body( $res );
            $body_decoded = json_decode( $response_body, true );
            $hdrs = wp_remote_retrieve_headers( $res );
            
            $this->log( 'API Response - Code: ' . $code . ', Duration: ' . $duration . 'ms, Body size: ' . strlen( $response_body ) . ' bytes' );
            
            if ( $code >= 400 ) {
                $masked_response = $this->mask_sensitive_data( $body_decoded );
                $this->log( 'API Error response (masked): ' . wp_json_encode( $masked_response ), 'error' );
            } else {
                // Log successful responses at debug level (less verbose)
                if ( $this->logging ) {
                    $this->log( 'API Success - Response structure: ' . $this->get_response_structure( $body_decoded ), 'debug' );
                }
            }
            
            return array( 'code' => $code, 'body' => $body_decoded, 'headers' => $hdrs );
        }

        /**
         * Get a summary of response structure for logging (without sensitive data)
         */
        private function get_response_structure( $data ) {
            if ( ! is_array( $data ) ) {
                return gettype( $data );
            }
            
            $structure = array();
            foreach ( $data as $key => $value ) {
                if ( is_array( $value ) ) {
                    $structure[$key] = 'array(' . count( $value ) . ')';
                } else {
                    $structure[$key] = gettype( $value );
                }
            }
            
            return wp_json_encode( $structure );
        }

        public function vxf_capture_transaction_on_return( $order_id ) {
            $this->log( 'Transaction capture initiated for order #' . $order_id );
            
            $order = wc_get_order( $order_id );
            if ( ! $order || $order->get_payment_method() !== $this->id ) {
                if ( ! $order ) {
                    $this->log( 'Transaction capture failed: Invalid order #' . $order_id, 'error' );
                } else {
                    $this->log( 'Transaction capture skipped: Order #' . $order_id . ' uses different payment method (' . $order->get_payment_method() . ')' );
                }
                return;
            }

            $this->log( 'Order found - Status: ' . $order->get_status() . ', Total: ' . $order->get_total() );

            // Ensure we have the session id (meta or ?sessionID= on the return URL)
            $session_id = $order->get_meta( '_visiofex_session_id' );
            if ( ! $session_id && isset( $_GET['sessionID'] ) ) {
                $session_id = sanitize_text_field( wp_unslash( $_GET['sessionID'] ) );
                $order->update_meta_data( '_visiofex_session_id', $session_id );
                $this->log( 'Session ID obtained from URL parameter: ' . $session_id );
            }
            
            if ( ! $session_id ) { 
                $this->log( 'Transaction capture failed: Missing session ID', 'error' );
                $order->add_order_note( 'VisioFex: missing session id on return.' ); 
                return; 
            }

            $this->log( 'Starting polling for session: ' . $session_id );

            // Short polling: immediate → 0.5s → 1.5s → 3s (stop as soon as it’s paid)
            foreach ( array(0, 0.5, 1.5, 3.0) as $attempt => $delay ) {
                if ( $delay ) { 
                    usleep( (int) ( $delay * 1e6 ) );
                    $this->log( 'Polling attempt ' . ($attempt + 1) . ' after ' . $delay . 's delay' );
                } else {
                    $this->log( 'Polling attempt 1 (immediate)' );
                }

                $res   = $this->request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
                $body  = is_array( $res['body'] ) ? $res['body'] : array();
                $sess  = $body['data']['session'] ?? array();
                $txn   = $body['data']['transaction'] ?? array();

                $status   = strtolower( $sess['paymentStatus'] ?? '' );
                $txn_id   = $txn['_id'] ?? '';
                $last4    = $txn['paymentMethod']['last4']   ?? '';
                $network  = $txn['paymentMethod']['network'] ?? '';
                $amount_i = $txn['amount'] ?? null;

                $this->log( 'Polling result - Status: ' . ( $status ?: 'none' ) . ', Transaction ID: ' . ( $txn_id ?: 'none' ) . ', Amount: ' . ( $amount_i !== null ? $amount_i : 'none' ) );

                if ( $txn_id ) {
                    $order->update_meta_data( '_visiofex_payment_id', sanitize_text_field( $txn_id ) );
                    if ( $last4 )   { $order->update_meta_data( '_visiofex_last4',   sanitize_text_field( $last4 ) ); }
                    if ( $network ) { $order->update_meta_data( '_visiofex_network', sanitize_text_field( $network ) ); }
                    if ( ! is_null( $amount_i ) ) { $order->update_meta_data( '_visiofex_tx_amount', $amount_i ); }
                    
                    $this->log( 'Transaction metadata updated - Payment ID: ' . $txn_id . ', Last4: ' . ( $last4 ?: 'none' ) . ', Network: ' . ( $network ?: 'none' ) );
                }

                if ( in_array( $status, array( 'paid', 'succeeded' ), true ) ) {
                    $this->log( 'Payment successful - Completing order' );
                    $order->payment_complete( $txn_id ?: '' );
                    $order->add_order_note( 'VisioFex: paid (verified via session).' );
                    $order->save();
                    return;
                } else if ( in_array( $status, array( 'failed', 'cancelled', 'expired' ), true ) ) {
                    $this->log( 'Payment failed with status: ' . $status, 'warning' );
                    $order->add_order_note( 'VisioFex: payment ' . $status . ' (verified via session).' );
                    if ( $status === 'failed' ) {
                        $order->update_status( 'failed', 'VisioFex payment failed.' );
                    }
                    $order->save();
                    return;
                }
            }

            $this->log( 'Polling completed - Payment status still pending after 4 attempts', 'warning' );
            $order->add_order_note( 'VisioFex: session not yet paid after return; order left pending.' );
        }

        /** Public: Sync a single order from VisioFex (session/transaction/refunds). */
        public function sync_order_from_visiofex( WC_Order $order ): void {
            $this->log( 'Starting order sync for order #' . $order->get_id() );
            
            if ( $order->get_payment_method() !== $this->id ) {
                $this->log( 'Order sync skipped: Order #' . $order->get_id() . ' uses different payment method (' . $order->get_payment_method() . ')' );
                return;
            }

            $this->log( 'Order sync validated - Status: ' . $order->get_status() . ', Total: ' . $order->get_total() );

            // 1) Ensure we know the session id
            $session_id = $order->get_meta('_visiofex_session_id');
            if ( ! $session_id && isset($_GET['sessionID']) ) {
                $session_id = sanitize_text_field( wp_unslash($_GET['sessionID']) );
                $order->update_meta_data('_visiofex_session_id', $session_id);
                $this->log( 'Session ID obtained from URL parameter: ' . $session_id );
            }

            if ( ! $session_id ) {
                $this->log( 'Order sync failed: No session ID found', 'warning' );
                return;
            }

            $this->log( 'Syncing with session ID: ' . $session_id );

            // 2) If we have a session, hydrate latest session + transaction
            $session = $tx = array();
            if ( $session_id ) {
                $r  = $this->request('GET', 'checkout/sessions/' . rawurlencode($session_id));
                $session = $r['body']['data']['session'] ?? array();
                $tx      = $r['body']['data']['transaction'] ?? array();
                
                $this->log( 'Session data retrieved - Status: ' . ( $session['paymentStatus'] ?? 'none' ) . ', Has transaction: ' . ( ! empty( $tx ) ? 'yes' : 'no' ) );
            }

            // 3) If no tx yet, try by stored payment_id or by listing transaction directly
            $txn_id = $order->get_meta('_visiofex_payment_id') ?: ($tx['_id'] ?? '');
            $this->log( 'Transaction ID resolution - From order meta: ' . ( $order->get_meta('_visiofex_payment_id') ?: 'none' ) . ', From session: ' . ( $tx['_id'] ?? 'none' ) );
            if ( ! $txn_id && ! empty($session['paymentIntent']) ) {
                // If your API uses paymentIntent as the canonical tx id (you showed it null earlier, so likely not)
                $txn_id = $session['paymentIntent'];
            }
            if ( ! $txn_id && ! empty($session['_id ']) ) {
                // Sometimes there’s a way to filter by session: /transactions?sessionId=
                $r2 = $this->request('GET', 'transactions?sessionId=' . rawurlencode($session['_id']));
                $list = $r2['body']['data']['transactions'] ?? $r2['body']['data'] ?? array();
                if ( is_array($list) && ! empty($list) && isset($list[0]['_id']) ) {
                    $tx = $list[0];
                    $txn_id = $tx['_id'];
                }
            }
            if ( $txn_id && empty($tx) ) {
                $r3 = $this->request('GET', 'transactions/' . rawurlencode($txn_id));
                $tx = $r3['body']['data']['transaction'] ?? $r3['body']['data'] ?? array();
            }

            // Save handy bits
            if ( $txn_id ) {
                $order->update_meta_data('_visiofex_payment_id', sanitize_text_field($txn_id));
                $this->log( 'Transaction ID saved to order meta: ' . $txn_id );
            }
            $status = strtolower( $session['paymentStatus'] ?? '' );
            if ( in_array($status, array('paid','succeeded'), true) && ! $order->has_status(array('processing','completed')) ) {
                $this->log( 'Payment confirmed - Completing order with status: ' . $status );
                $order->payment_complete( $txn_id ?: '' );
                $order->add_order_note('VisioFex: synced → marked paid.');
            } else {
                $this->log( 'Payment status: ' . ( $status ?: 'none' ) . ', Order status: ' . $order->get_status() );
            }

            // 4) Sync refunds
            $this->log( 'Starting refund sync for order #' . $order->get_id() );
            $refunded_total_api = 0.0;
            $refunds = $tx['refunds'] ?? array(); // if present
            if ( is_array($refunds) && $refunds ) {
                foreach ( $refunds as $r ) {
                    $refunded_total_api += (float) ( $r['amount'] ?? 0 );
                }
            } else {
                // Try common shapes
                if ( isset($tx['refundedAmount']) ) {
                    $refunded_total_api = (float) $tx['refundedAmount'];
                } else if ( $txn_id ) {
                    // Last resort: GET /refunds?transactionId=...
                    $r4 = $this->request('GET', 'refunds?transactionId=' . rawurlencode($txn_id));
                    $refunds_list = $r4['body']['data']['refunds'] ?? $r4['body']['data'] ?? array();
                    if ( is_array($refunds_list) ) {
                        foreach ( $refunds_list as $r ) {
                            $refunded_total_api += (float) ( $r['amount'] ?? 0 );
                        }
                    }
                }
            }

            // Already reflected in Woo?
            $already_refunded = (float) wc_format_decimal( $order->get_total_refunded(), 2 );
            $delta = round( $refunded_total_api - $already_refunded, 2 );

            $this->log( 'Refund sync analysis - API refunded: ' . $refunded_total_api . ', WC refunded: ' . $already_refunded . ', Delta: ' . $delta );

            if ( $delta > 0 ) {
                $this->log( 'Creating WooCommerce refund for: ' . wc_price( $delta ) );
                $created = wc_create_refund( array(
                    'order_id'       => $order->get_id(),
                    'amount'         => $delta,
                    'reason'         => 'Synced from VisioFex',
                    'refund_payment' => false,      // do not call gateway again
                    'restock_items'  => true,       // or make this a setting
                ) );
                if ( is_wp_error($created) ) {
                    $this->log( 'Refund creation failed: ' . $created->get_error_message(), 'error' );
                    $order->add_order_note('VisioFex: sync found ' . wc_price($delta) . ' refunded, but Woo refund creation failed.');
                } else {
                    $this->log( 'Refund created successfully: ' . wc_price( $delta ) );
                    $order->add_order_note('VisioFex: synced refund ' . wc_price($delta) . '.');
                }
            } else if ( $delta < 0 ) {
                $this->log( 'WC has more refunds than API - Delta: ' . $delta, 'warning' );
            }

            $order->update_meta_data('_visiofex_last_synced_at', gmdate('c'));
            $order->save();
            $this->log( 'Order sync completed for order #' . $order->get_id() );
}
        public function is_available() {
            $this->log( 'Checking gateway availability - Enabled: ' . $this->enabled . ', SSL: ' . ( is_ssl() ? 'yes' : 'no' ) . ', Test mode: ' . ( $this->testmode ? 'yes' : 'no' ) );
            
            if ( 'yes' !== $this->enabled ) { 
                $this->log( 'Gateway not available: Disabled in settings', 'debug' );
                return false; 
            }
            
            if ( ! is_ssl() && ! $this->testmode ) { 
                $this->log( 'Gateway not available: SSL required for live mode', 'debug' );
                return false; 
            }
            
            // Allow showing in test mode even if keys are empty (helps setup); in live require a key
            if ( empty( $this->secret_key ) ) {
                if ( $this->testmode ) { 
                    $this->log( 'Gateway available in test mode without secret key', 'debug' );
                    return true; 
                }
                $this->log( 'Gateway not available: Secret key required for live mode', 'debug' );
                return false;
            }
            
            $this->log( 'Gateway available - All requirements met', 'debug' );
            return true;
        }

        /**
         * Build comprehensive line items including products, coupons, shipping, fees, and taxes
         */
        private function build_line_items( $order ) {
            $this->log( 'Using simplified single line item approach' );
            
            $order_total = floatval( $order->get_total() );
            $order_id = $order->get_id();
            
            // Create one simple line item with the total order amount
            $store_name = get_bloginfo( 'name' );
            $product_name = $store_name ? $store_name . ' Order Total' : 'Order Total';
            
            $line_items = array(
                array(
                    'productName' => $product_name,
                    'unitPrice'   => number_format( $order_total, 2, '.', '' ),
                    'quantity'    => 1,
                    'type'        => 'order'
                )
            );
            
            $this->log( 'Created single line item - Order #' . $order_id . ': $' . $order_total );
            
            return array(
                'line_items' => $line_items,
                'calculated_total' => $order_total
            );
        }

        public function process_payment( $order_id ) {
            $this->log( 'Starting payment process for order #' . $order_id );
            
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                $this->log( 'Invalid order ID: ' . $order_id, 'error' );
                wc_add_notice( __( 'Invalid order. Please try again.', 'visiofex-woocommerce' ), 'error' );
                return array( 'result' => 'fail' );
            }

            $this->log( 'Order found - Currency: ' . $order->get_currency() . ', Total: ' . $order->get_total() );

            // Validate gateway configuration
            if ( empty( $this->secret_key ) && ! $this->testmode ) {
                $this->log( 'Gateway misconfiguration: Secret key missing in live mode', 'error' );
                wc_add_notice( __( 'Payment gateway is not properly configured.', 'visiofex-woocommerce' ), 'error' );
                return array( 'result' => 'fail' );
            }

            if ( empty( $this->vendor_id ) ) {
                $this->log( 'Gateway misconfiguration: Vendor ID missing', 'error' );
                wc_add_notice( __( 'Payment gateway is not properly configured.', 'visiofex-woocommerce' ), 'error' );
                return array( 'result' => 'fail' );
            }

            // Build comprehensive lineItems from WC order including products, discounts, shipping, taxes
            $line_item_result = $this->build_line_items( $order );
            $line_items = $line_item_result['line_items'];
            $calculated_total = $line_item_result['calculated_total'];
            $order_total = floatval( $order->get_total() );
            
            $this->log( 'Testing empty line items approach - Line items count: ' . count( $line_items ) . ', WC Total: ' . $order_total );
            
            $order_key = $order->get_order_key();
            $success   = $this->store_domain . '/checkout/order-received/' . $order->get_id() . '/?key=' . $order_key;
            $return    = $this->store_domain . '/checkout/';
            $cancel    = $this->store_domain . '/checkout/?cancel_order=' . $order_key;

            $this->log( 'Generated URLs - Success: ' . $success . ', Return: ' . $return . ', Cancel: ' . $cancel );

            $payload = array(
                'vendor'      => $this->vendor_id ?: null,
                'env'         => ( $this->testmode ? 'test' : 'live' ),
                'mode'        => $this->mode ?: 'payment',
                'currency'    => strtolower( $order->get_currency() ),
                'amount'      => number_format( $order_total, 2, '.', '' ), // Total amount after all discounts/additions
                'lineItems'   => $line_items,
                'successURL'  => $success,
                'returnURL'   => $return,
                'cancelURL'   => $cancel,
                'metadata'    => array(
                    'order_id'       => (string) $order->get_id(),
                    'site_url'       => home_url(),
                    'wc_total'       => $order_total,
                    'calculated_total' => $calculated_total,
                    'coupon_count'   => count( $order->get_coupons() ),
                    'has_shipping'   => count( $order->get_shipping_methods() ) > 0,
                ),
                // You can also send shipping/billing if required by your account
            );

            $this->log( 'API payload prepared - Environment: ' . ( $this->testmode ? 'test' : 'live' ) . ', Amount: ' . $order_total . ', Items: ' . count( $line_items ) . ' (products: ' . count( $order->get_items() ) . ', coupons: ' . count( $order->get_coupons() ) . ')' );

            // Idempotency key recommended
            add_filter( 'http_request_args', function( $args, $url ) {
                if ( ! isset( $args['headers']['Idempotency-Key'] ) ) {
                    $args['headers']['Idempotency-Key'] = wp_generate_uuid4();
                }
                return $args;
            }, 10, 2 );

            // Create hosted checkout session
            $idk = 'create_' . $order->get_id() . '_' . wp_generate_uuid4();
            $this->log( 'Making API request to create checkout session for order #' . $order_id );

            $res = $this->request( 'POST', 'checkout/sessions/create', $payload );
            $code = $res['code']; $body = $res['body'];

            $this->log( 'API response received - Code: ' . $code . ', Has paymentURL: ' . ( ! empty( $body['data']['paymentURL'] ) ? 'yes' : 'no' ) );

            if ( $code >= 200 && $code < 300 && ! empty( $body['data']['paymentURL'] ) ) {
                $session = isset( $body['data']['session'] ) ? $body['data']['session'] : array();
                $request_id = ! empty( $body['data']['requestID'] ) ? sanitize_text_field( $body['data']['requestID'] ) : '';
                $session_id = ! empty( $session['_id'] ) ? sanitize_text_field( $session['_id'] ) : '';
                
                $this->log( 'Session created successfully - RequestID: ' . ( $request_id ?: 'none' ) . ', SessionID: ' . ( $session_id ?: 'none' ) );
                
                $order->update_meta_data( '_visiofex_request_id', $request_id );
                $order->update_meta_data( '_visiofex_session_id', $session_id );
                $order->update_meta_data( '_visiofex_api_base', esc_url_raw( $this->get_api_base() ) );
                $order->save();

                $payment_url = esc_url_raw( $body['data']['paymentURL'] );
                $this->log( 'Payment process successful - Redirecting to: ' . $payment_url );

                return array(
                    'result'   => 'success',
                    'redirect' => $payment_url,
                );
            }

            // Log detailed error information
            $error_details = array(
                'code' => $code,
                'body' => $this->mask_sensitive_data( $body ),
                'expected_keys' => array( 'data.paymentURL' )
            );
            $this->log( 'Payment process failed - ' . wp_json_encode( $error_details ), 'error' );

            wc_add_notice( __( 'Could not start VisioFex checkout. Please try again.', 'visiofex-woocommerce' ), 'error' );
            return array( 'result' => 'fail' );
        }

        /** Refund support */
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $this->log( 'Starting refund process for order #' . $order_id . ' - Amount: ' . ( $amount ?: 'N/A' ) . ', Reason: ' . ( $reason ?: 'No reason provided' ) );
            
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                $this->log( 'Refund failed: Invalid order ID #' . $order_id, 'error' );
                return new WP_Error( 'invalid_order', 'Order not found' );
            }

            $this->log( 'Order found - Payment method: ' . $order->get_payment_method() . ', Status: ' . $order->get_status() . ', Total: ' . $order->get_total() );

            $payment_id = $order->get_meta( '_visiofex_payment_id' );

            // Check if this order was paid with VisioFex
            if ( $order->get_payment_method() !== $this->id ) {
                $this->log( 'Refund validation failed: Order not paid with VisioFex (method: ' . $order->get_payment_method() . ')', 'error' );
                return new WP_Error( 'invalid_payment_gateway', 'Order was not paid with VisioFex' );
            }

            // Check if gateway is properly configured
            if ( empty( $this->secret_key ) ) {
                $this->log( 'Refund validation failed: Gateway not configured (missing secret key)', 'error' );
                return new WP_Error( 'invalid_payment_gateway', 'VisioFex gateway is not properly configured' );
            }

            if ( empty( $payment_id ) ) {
                $this->log( 'Refund validation failed: Payment ID not found in order meta', 'error' );
                $this->log( 'Order meta keys: ' . implode( ', ', array_keys( $order->get_meta_data() ) ) );
                return new WP_Error( 'missing_payment_id', 'Payment ID not found - order may not be fully processed yet' );
            }

            $this->log( 'Refund validation passed - Payment ID: ' . $payment_id );

            // Validate refund amount
            $order_total = (float) $order->get_total();
            $already_refunded = (float) $order->get_total_refunded();
            $remaining_refundable = $order_total - $already_refunded;
            $refund_amount = $amount ? (float) $amount : $remaining_refundable;

            $this->log( 'Refund amount validation - Order total: ' . $order_total . ', Already refunded: ' . $already_refunded . ', Remaining: ' . $remaining_refundable . ', Requested: ' . $refund_amount );

            if ( $refund_amount > $remaining_refundable ) {
                $this->log( 'Refund validation failed: Amount (' . $refund_amount . ') exceeds remaining refundable amount (' . $remaining_refundable . ')', 'error' );
                return new WP_Error( 'invalid_refund_amount', 'Refund amount exceeds remaining refundable amount' );
            }

            if ( $refund_amount <= 0 ) {
                $this->log( 'Refund validation failed: Invalid refund amount (' . $refund_amount . ')', 'error' );
                return new WP_Error( 'invalid_refund_amount', 'Refund amount must be greater than zero' );
            }

            $payload = array(
                'payment_id' => $payment_id,
                'amount'     => $refund_amount,
                'reason'     => $reason,
            );

            $this->log( 'Refund payload prepared - Masked: ' . wp_json_encode( $this->mask_sensitive_data( $payload ) ) );

            // Use the known correct refund endpoint
            $endpoint = 'transactions/' . $payment_id . '/refund';
            $this->log( 'Making refund API request to endpoint: ' . $endpoint );
            
            $res = $this->request( 'POST', $endpoint, $payload );
            $code = $res['code'];
            $body = $res['body'];

            $this->log( 'Refund API response - Code: ' . $code . ', Body length: ' . strlen( wp_json_encode( $body ) ) . ' chars' );

            if ( $code >= 200 && $code < 300 ) {
                $this->log( 'Refund processed successfully' );
                $order->add_order_note( sprintf( 'VisioFex refund processed: %s. Reason: %s', wc_price( $refund_amount ), $reason ) );
                return true;
            } else {
                // Log the full response for debugging (with sensitive data masked)
                $masked_body = $this->mask_sensitive_data( $body );
                $this->log( 'Refund failed - Code: ' . $code . ', Body: ' . wp_json_encode( $masked_body ), 'error' );

                // Try different error message formats
                $error_msg = 'Unknown error';
                if ( isset( $body['error'] ) ) {
                    $error_msg = is_array( $body['error'] ) ? wp_json_encode( $body['error'] ) : $body['error'];
                } elseif ( isset( $body['message'] ) ) {
                    $error_msg = $body['message'];
                } elseif ( isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
                    $error_msg = implode( ', ', $body['errors'] );
                } elseif ( is_string( $body ) ) {
                    $error_msg = $body;
                } elseif ( $code === 400 ) {
                    $error_msg = 'Invalid request data';
                } elseif ( $code === 401 ) {
                    $error_msg = 'Authentication failed - check gateway credentials';
                } elseif ( $code === 404 ) {
                    $error_msg = 'Payment not found - transaction may not exist';
                } elseif ( $code === 500 ) {
                    $error_msg = 'Server error - please try again later';
                }

                $this->log( 'Refund error message: ' . $error_msg, 'error' );
                $order->add_order_note( 'VisioFex refund failed: ' . $error_msg );
                
                return new WP_Error( 'refund_failed', 'Refund failed: ' . $error_msg );
            }
        }

    // Webhook endpoint removed in simplified plugin

        protected function log( $msg, $level = 'info', $context = array() ) {
            if ( ! $this->logging ) return;
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $context['source'] = 'visiofex';
                $logger->log( $level, $msg, $context );
            }
        }

        /**
         * Mask sensitive data in logs
         */
        private function mask_sensitive_data( $data ) {
            if ( is_string( $data ) ) {
                return $data;
            }
            
            if ( ! is_array( $data ) ) {
                return $data;
            }
            
            $masked = $data;
            $sensitive_keys = array( 'vendor', 'secret_key', 'password', 'token', 'key', 'authorization' );
            
            foreach ( $sensitive_keys as $key ) {
                if ( isset( $masked[$key] ) ) {
                    $value = (string) $masked[$key];
                    if ( strlen( $value ) > 8 ) {
                        $masked[$key] = substr( $value, 0, 4 ) . '****' . substr( $value, -4 );
                    } else {
                        $masked[$key] = '****';
                    }
                }
            }
            
            // Recursively mask nested arrays
            foreach ( $masked as $key => $value ) {
                if ( is_array( $value ) ) {
                    $masked[$key] = $this->mask_sensitive_data( $value );
                }
            }
            
            return $masked;
        }
    }
}, 5 );

/** WooCommerce Blocks registration (simple; server-side processing handles the charge) */
add_action( 'woocommerce_blocks_loaded', function() {
    if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }
    require_once VXF_WC_PLUGIN_DIR . 'includes/class-wc-gateway-visiofex-blocks.php';
    add_action( 'woocommerce_blocks_payment_method_type_registration', function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
        $blocks_instance = new \WC_Gateway_VisioFex_Blocks();
        $registry->register( $blocks_instance );
    } );
} );


register_activation_hook( __FILE__, function() {} );
register_deactivation_hook( __FILE__, function() {} );

add_action( 'woocommerce_admin_order_data_after_order_details', 'vxf_admin_order_panel', 10, 1 );

function vxf_admin_order_panel( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // Always expose the gateway for your admin JS
    echo '<input type="hidden" id="vxf_order_gateway" value="' . esc_attr( $order->get_payment_method() ) . '"/>';

    // Only show VisioFex specifics for VisioFex orders
    if ( $order->get_payment_method() !== 'visiofex' ) {
        return;
    }

    $txn_id     = $order->get_meta('_visiofex_payment_id') ?: '—';
    $session_id = $order->get_meta('_visiofex_session_id') ?: '—';

    echo '<p><strong>VisioFex Transaction:</strong> ' . esc_html( $txn_id ) . '</p>';
    echo '<p><strong>VisioFex Session:</strong> ' . esc_html( $session_id ) . '</p>';
    
    // Add refund reason dropdown for VisioFex orders
    ?>
    <script>
    jQuery(function($){
        if ($('#vxf_order_gateway').val() === 'visiofex') {
            
            function addRefundDropdown() {
                var $ta = $('#refund_reason, textarea[name="refund_reason"]');
                
                if ($ta.length && !$('#vxf_refund_reason').length) {
                    var $wrap = $('<p class="form-field vxf-refund-reason-field" style="margin: 10px 0;"></p>');
                    var $label = $('<label for="vxf_refund_reason" style="font-weight: bold;">VisioFex refund reason</label>');
                    var $sel = $('<select id="vxf_refund_reason" name="vxf_refund_reason" style="width: 100%; margin-top: 5px;"></select>');
                    
                    $sel.append('<option value="requested_by_customer">Requested by customer</option>');
                    $sel.append('<option value="duplicate">Duplicate</option>');
                    $sel.append('<option value="fraudulent">Fraudulent</option>');
                    
                    $wrap.append($label).append('<br/>').append($sel);
                    $ta.after($wrap);
                    $ta.prop('readonly', true).attr('placeholder', 'VisioFex requires a predefined reason; use the dropdown below.');
                    
                    var sync = function(){ $ta.val($sel.val()); };
                    $sel.on('change', sync);
                    sync();
                }
            }
            
            // Watch for DOM changes to handle AJAX refreshes after refund failures
            function setupMutationObserver() {
                if (window.MutationObserver) {
                    var observer = new MutationObserver(function(mutations) {
                        var shouldCheck = false;
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) { // Element node
                                        if ($(node).find('#refund_reason, textarea[name="refund_reason"]').length ||
                                            $(node).is('#refund_reason, textarea[name="refund_reason"]')) {
                                            shouldCheck = true;
                                        }
                                    }
                                });
                            }
                        });
                        if (shouldCheck) {
                            setTimeout(addRefundDropdown, 100);
                        }
                    });
                    
                    // Observe the entire order data area
                    var orderData = $('#order_data, .woocommerce-order-data, body').get(0);
                    if (orderData) {
                        observer.observe(orderData, {
                            childList: true,
                            subtree: true
                        });
                    }
                }
            }
            
            // Handle refund button clicks
            $(document).on('click', '.refund-items, .do-manual-refund, button[name="refund_amount"]', function() {
                setTimeout(addRefundDropdown, 100);
                setTimeout(addRefundDropdown, 500);
                setTimeout(addRefundDropdown, 1000); // Extra delay for slower systems
            });
            
            // Watch for error messages that might indicate refund failure
            $(document).on('DOMNodeInserted', function(e) {
                var $target = $(e.target);
                if ($target.hasClass('notice') || $target.hasClass('error') || 
                    $target.hasClass('woocommerce-message') || $target.find('.notice, .error, .woocommerce-message').length) {
                    // When error/notice appears, refund UI might be refreshed
                    setTimeout(addRefundDropdown, 500);
                    setTimeout(addRefundDropdown, 1500);
                }
            });
            
            // Periodic check with longer intervals to catch edge cases
            var checkInterval = setInterval(function() {
                if ($('#refund_reason:visible, textarea[name="refund_reason"]:visible').length && !$('#vxf_refund_reason').length) {
                    addRefundDropdown();
                }
            }, 2000);
            
            // Initial setup
            addRefundDropdown();
            setupMutationObserver();
            
            // Stop periodic checking after 5 minutes
            setTimeout(function() {
                clearInterval(checkInterval);
            }, 300000);
        }
    });
    </script>
    <?php
}


add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( $screen && $screen->post_type === 'shop_order' ) {
            // Refund dropdown is now handled inline in vxf_admin_order_panel()
            // No external JS file needed
        }
    }
} );

// Add the action to the dropdown in Order actions
add_filter('woocommerce_order_actions', function($actions){
    $actions['vxf_sync_status'] = 'Sync VisioFex status';
    return $actions;
});

// Handle it: fetch gateway instance and call the method above
add_action('woocommerce_order_action_vxf_sync_status', function($order){
    if ( ! $order instanceof WC_Order ) return;
    $pgs = wc()->payment_gateways()->payment_gateways();
    if ( empty($pgs['visiofex']) ) { $order->add_order_note('VisioFex: gateway not available.'); return; }
    /** @var WC_Gateway_VisioFex $gw */
    $gw = $pgs['visiofex'];
    $gw->sync_order_from_visiofex( $order );
});
