<?php
defined( 'ABSPATH' ) || exit;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_VisioFex_Blocks extends AbstractPaymentMethodType {
    protected $name = 'visiofex';
    protected $settings = array();

    // Remove __construct() completely, or keep it empty:
    // public function __construct() {}

    public function initialize() {
        $this->settings = get_option( 'woocommerce_visiofex_settings', array() );
    }

    public function is_active() {
        $active = ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] === 'yes';
        return $active;
    }

    public function get_payment_method_script_handles() {
        $handle   = 'wc-visiofex-blocks';
        $rel_path = 'assets/blocks/index.js';
        $file     = VXF_WC_PLUGIN_DIR . $rel_path;
        $url      = VXF_WC_PLUGIN_URL . $rel_path;

        if ( ! file_exists( $file ) ) {
            return array(); // fail open so classic checkout still works
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
        // Get the default description from the main gateway class's form fields
        $gateway = new WC_Gateway_VisioFex();
        $default_title = $gateway->form_fields['title']['default'];
        $default_description = $gateway->form_fields['description']['default'];

        $description = $this->get_setting( 'description' );
        if ( empty( trim( $description ) ) ) {
            $description = $default_description;
        }

        $data = array(
            'title'              => $this->get_setting( 'title', $default_title ),
            'description'        => $description,
            'icon'               => esc_url( VXF_WC_PLUGIN_URL . 'assets/visiofex-logo.png' ),
            'pluginUrl'          => esc_url( VXF_WC_PLUGIN_URL ),
            'supports'           => array( 'products' ),
        );
        
        return $data;
    }

    public function get_supported_features() {
        return array( 'products' );
    }
}
