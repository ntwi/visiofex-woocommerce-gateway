<?php
defined( 'ABSPATH' ) || exit;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_VisioFex_Blocks extends AbstractPaymentMethodType {
    protected $name = 'visiofex';
    protected $settings = array();

    public function initialize() {
        $this->settings = get_option( 'woocommerce_visiofex_settings', array() );
    }

    public function is_active() {
        return ( ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] === 'yes' );
    }

    public function get_payment_method_script_handles() {
        $handle   = 'wc-visiofex-blocks';
        $rel_path = 'assets/blocks/index.js';
        $file     = VXF_WC_PLUGIN_DIR . $rel_path;
        $url      = VXF_WC_PLUGIN_URL . $rel_path;
        if ( ! file_exists( $file ) ) {
            return array();
        }
        wp_register_script(
            $handle,
            $url,
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
            VXF_WC_VERSION,
            true
        );
        return array( $handle );
    }

    public function get_payment_method_data() {
        // Instantiate gateway only to read defaults (safe: no API call here)
        $gateway = new WC_Gateway_VisioFex();
        $default_title       = $gateway->form_fields['title']['default'];
        $default_description = $gateway->form_fields['description']['default'];

        $description = $this->get_setting( 'description' );
        if ( trim( (string) $description ) === '' ) {
            $description = $default_description;
        }

        return array(
            'title'       => $this->get_setting( 'title', $default_title ),
            'description' => $description,
            // No logo/icon exposed anymore
            'pluginUrl'   => esc_url( VXF_WC_PLUGIN_URL ),
            'supports'    => array( 'products' ),
        );
    }

    public function get_supported_features() {
        return array( 'products' );
    }
}
