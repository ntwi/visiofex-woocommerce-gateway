<?php
/**
 * Class Visiofex_Gateway
 *
 * This class implements the payment gateway functionality for the Visiofex WooCommerce gateway plugin.
 */
class Visiofex_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'visiofex';
        $this->icon = ''; // URL of the icon that will be displayed on the checkout page
        $this->has_fields = true; // If you have custom fields, set this to true
        $this->method_title = __('Visiofex', 'visiofex-woocommerce-gateway');
        $this->method_description = __('Description of the Visiofex payment gateway', 'visiofex-woocommerce-gateway');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'visiofex-woocommerce-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Visiofex Payment Gateway', 'visiofex-woocommerce-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'visiofex-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'visiofex-woocommerce-gateway'),
                'default'     => __('Visiofex Payment', 'visiofex-woocommerce-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'visiofex-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'visiofex-woocommerce-gateway'),
                'default'     => __('Pay securely using Visiofex.', 'visiofex-woocommerce-gateway'),
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Implement payment processing logic here

        // Mark the order as processing or completed
        $order->payment_complete();

        // Return thank you page redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    public function refund_order($order_id, $amount = null, $reason = '') {
        // Implement refund logic here
    }

    public function admin_options() {
        echo '<h2>' . __('Visiofex Payment Gateway', 'visiofex-woocommerce-gateway') . '</h2>';
        echo '<p>' . __('Configure the settings for the Visiofex payment gateway.', 'visiofex-woocommerce-gateway') . '</p>';
        parent::admin_options();
    }
}