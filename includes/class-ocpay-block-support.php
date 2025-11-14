<?php
/**
 * OCPay Block Payment Support
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Block_Support class
 *
 * Adds support for WooCommerce Blocks Checkout
 */
class OCPay_Block_Support {

	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'ocpay';

	/**
	 * Settings from the WP options table
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Get gateway settings
		$gateways         = WC()->payment_gateways->payment_gateways();
		$this->settings   = isset( $gateways['ocpay'] ) ? $gateways['ocpay']->settings : array();
	}

	/**
	 * Get the payment method name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns if this payment method should be active
	 *
	 * @return boolean
	 */
	public function is_active() {
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway  = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;

		if ( ! $gateway ) {
			return false;
		}

		return $gateway->is_available();
	}

	/**
	 * Returns an array of script handles for this payment method
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_asset_path = OCPAY_WOOCOMMERCE_PATH . 'assets/js/blocks-payment-method.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(
					'wp-element',
					'wp-i18n',
					'wp-html-entities',
					'wc-blocks-registry',
					'wc-settings'
				),
				'version'      => OCPAY_WOOCOMMERCE_VERSION,
			);

		wp_register_script(
			'ocpay-blocks-integration',
			OCPAY_WOOCOMMERCE_URL . 'assets/js/blocks-payment-method.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// Localize with payment data
		$payment_data = $this->get_payment_method_data();
		wp_localize_script(
			'ocpay-blocks-integration',
			'ocpayBlocksData',
			$payment_data
		);

		return array( 'ocpay-blocks-integration' );
	}

	/**
	 * Get payment method data
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway  = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;

		if ( $gateway ) {
			$payment_data = array(
				'title'       => $gateway->get_title(),
				'description' => $gateway->get_description(),
				'supports'    => array_filter( $gateway->supports, array( $gateway, 'supports' ) ),
				'logo_url'    => OCPAY_WOOCOMMERCE_URL . 'assets/images/ocpay-logo.png',
			);
		} else {
			$payment_data = array(
				'title'       => __( 'OCPay - OneClick Payment', 'ocpay-woocommerce' ),
				'description' => __( 'Pay securely using OCPay - powered by SATIM bank-grade security.', 'ocpay-woocommerce' ),
				'logo_url'    => OCPAY_WOOCOMMERCE_URL . 'assets/images/ocpay-logo.png',
			);
		}

		return $payment_data;
	}

	/**
	 * Returns an array of supported features
	 *
	 * @return array
	 */
	public function get_supported_features() {
		return array( 'products' );
	}

	/**
	 * Initialize block support - static method for hooks
	 *
	 * @return void
	 */
	public static function init() {
		// Enqueue scripts on checkout/cart pages
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_block_scripts' ), 100 );
	}

	/**
	 * Enqueue block payment method script (fallback)
	 *
	 * @return void
	 */
	public static function enqueue_block_scripts() {
		// Only load on checkout or cart pages with blocks
		global $post;
		if ( ! $post ) {
			return;
		}

		// Check if this page has WooCommerce blocks
		if ( ! ( has_block( 'woocommerce/checkout', $post ) || has_block( 'woocommerce/cart', $post ) ) ) {
			return;
		}

		// Get the asset file for dependencies
		$script_asset_path = OCPAY_WOOCOMMERCE_PATH . 'assets/js/blocks-payment-method.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(
					'wp-element',
					'wp-i18n',
					'wp-html-entities',
					'wc-blocks-registry',
					'wc-settings'
				),
				'version'      => OCPAY_WOOCOMMERCE_VERSION,
			);

		// Register and enqueue the script
		wp_register_script(
			'ocpay-blocks-integration',
			OCPAY_WOOCOMMERCE_URL . 'assets/js/blocks-payment-method.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_enqueue_script( 'ocpay-blocks-integration' );

		// Get payment data
		$payment_data = ( new self() )->get_payment_method_data();

		// Localize script with payment data
		wp_localize_script(
			'ocpay-blocks-integration',
			'ocpayBlocksData',
			$payment_data
		);

		error_log( 'OCPay: Blocks payment method script enqueued' );
	}
}
