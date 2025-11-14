<?php
/**
 * OCPay Payment Block Integration
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * OCPay Payment Block class
 */
class OCPay_Payment_Block extends AbstractPaymentMethodType {

	/**
	 * The gateway instance
	 *
	 * @var WC_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'ocpay';

	/**
	 * Constructor
	 */
	public function __construct() {
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
		$this->settings = $this->gateway ? $this->gateway->settings : array();
	}

	/**
	 * Initializes the payment method type for use in checkout blocks.
	 */
	public function initialize() {
		// This is where you can add any initialization logic
	}

	/**
	 * Returns if this payment method should be active
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway ? $this->gateway->is_available() : false;
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
	 * Returns an array of supported features
	 *
	 * @return array
	 */
	public function get_supported_features() {
		return array( 'products' );
	}

	/**
	 * Get payment method data
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( $this->gateway ) {
			return array(
				'title'       => $this->gateway->get_title(),
				'description' => $this->gateway->get_description(),
				'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
				'logo_url'    => OCPAY_WOOCOMMERCE_URL . 'assets/images/ocpay-logo.png',
			);
		}

		return array(
			'title'       => __( 'OCPay - OneClick Payment', 'ocpay-woocommerce' ),
			'description' => __( 'Pay securely using OCPay - powered by SATIM bank-grade security.', 'ocpay-woocommerce' ),
			'logo_url'    => OCPAY_WOOCOMMERCE_URL . 'assets/images/ocpay-logo.png',
		);
	}
}
