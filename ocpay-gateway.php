<?php
/**
 * Plugin Name: OCPay WooCommerce Gateway
 * Plugin URI: https://oneclickdz.com
 * Description: A simple WooCommerce payment gateway for OCPay.
 * Version: 2.1.0
 * Author: OneClickDz - ZH
 * Author URI: https://oneclickdz.com
 * Text Domain: ocpay-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OCPAY_VERSION', '2.1.0' );
define( 'OCPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Initialize the gateway.
 */
function ocpay_init_gateway_class() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once OCPAY_PLUGIN_DIR . 'class-ocpay-gateway.php';
}
add_action( 'plugins_loaded', 'ocpay_init_gateway_class' );

/**
 * Add the gateway to WooCommerce.
 */
function ocpay_add_gateway_class( $methods ) {
	$methods[] = 'WC_Gateway_OCPay';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'ocpay_add_gateway_class' );

/**
 * Register Blocks Support.
 */
function ocpay_gateway_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once OCPAY_PLUGIN_DIR . 'includes/blocks/class-ocpay-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_OCPay_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'ocpay_gateway_block_support' );

/**
 * Add settings link on plugin page.
 */
function ocpay_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ocpay' ) . '">' . __( 'Settings', 'ocpay-gateway' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ocpay_add_settings_link' );
