<?php
/*
Plugin Name: Pi Network Payment Gateway
Plugin URI: https://salla-shop.com
Description: A  WooCommerce payment gateway for Pi Network.
Version: 1.0
Author: MoaazElsharkawy
Author URI: https://salla-shop.com
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once plugin_dir_path(__FILE__) . 'includes/PiNetwork.php';

add_action('wp_enqueue_scripts', 'enqueue_pi_network_styles');
add_action('admin_enqueue_scripts', 'enqueue_pi_network_styles');

function enqueue_pi_network_styles() {
    wp_enqueue_style(
        'pi-network-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        [],
        '1.0.0'
    );
}



// تسجيل البوابة في WooCommerce
add_filter('woocommerce_payment_gateways', 'add_pi_network_gateway');
function add_pi_network_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Pi_Network';
    return $gateways;
}

add_action('plugins_loaded', 'init_pi_network_gateway');
function init_pi_network_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Pi_Network extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'pi_network';
            $this->method_title = 'Pi Network';
            $this->method_description = 'Accept payments via Pi Network.';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->api_key = $this->get_option('api_key');
            $this->wallet_private_seed = $this->get_option('wallet_private_seed');

            // حفظ الإعدادات
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Pi Network Payment',
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title seen by the user during checkout.',
                    'default' => 'Pi Network Payment',
                ],
                'description' => [
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description seen by the user during checkout.',
                    'default' => 'Pay with Pi Network.',
                ],
                'api_key' => [
                    'title' => 'API Key',
                    'type' => 'text',
                    'description' => 'Enter your Pi Network API Key.',
                ],
                'wallet_private_seed' => [
                    'title' => 'Wallet Private Seed',
                    'type' => 'text',
                    'description' => 'Enter your wallet private seed.',
                ],
            ];
        }

       public function process_payment($order_id) {
    $order = wc_get_order($order_id);

    $piNetwork = new \Get2\A2uphp\PiNetwork($this->api_key, $this->wallet_private_seed);

    $paymentData = [
        'amount' => $order->get_total(),
        'memo' => 'Order #' . $order_id,
        'metadata' => ['order_id' => $order_id],
        'uid' => $order->get_user_id()
    ];

    $paymentId = $piNetwork->createPayment($paymentData);

    if ($paymentId) {
        $order->update_meta_data('pi_payment_id', $paymentId);
        $order->save();

        $paymentLink = "pi://payment/" . $paymentId;

        return [
            'result' => 'success',
            'redirect' => $paymentLink, 
        ];
    } else {
        wc_add_notice('Failed to create payment. Please try again.', 'error');
        return;
    }
}

    }
}
