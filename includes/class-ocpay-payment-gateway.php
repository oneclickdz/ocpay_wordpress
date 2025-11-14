<?php
/**
 * OCPay Payment Gateway
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Payment_Gateway class
 *
 * Main payment gateway class extending WooCommerce WC_Payment_Gateway
 */
class OCPay_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * API Client instance
	 *
	 * @var OCPay_API_Client
	 */
	private $api_client;

	/**
	 * Logger instance
	 *
	 * @var OCPay_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'ocpay';
		$this->icon               = OCPAY_WOOCOMMERCE_URL . 'assets/images/ocpay-logo.png';
		$this->has_fields         = false;
		$this->method_title       = esc_html__( 'OCPay', 'ocpay-woocommerce' );
		$this->method_description = esc_html__( 'Accept secure online payments in Algeria via OCPay powered by SATIM.', 'ocpay-woocommerce' );
		
		// Supports array - compatible with WooCommerce 10.3.4 and earlier versions
		$this->supports = array(
			'products',
		);

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Get user set variable values
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		// Initialize logger
		$this->logger = OCPay_Logger::get_instance();

		// Initialize API client only if api_key exists
		$api_key = $this->get_option( 'api_key' );
		if ( $api_key ) {
			$this->api_client = new OCPay_API_Client(
				$api_key,
				$this->get_option( 'api_mode' )
			);
		}

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// WooCommerce Blocks support
		add_filter( 'woocommerce_rest_check_permissions', array( $this, 'blocks_rest_permission_check' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_blocks_support' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only load on checkout page
		if ( ! is_checkout() ) {
			return;
		}

		// Enqueue frontend stylesheet
		wp_enqueue_style(
			'ocpay-frontend',
			OCPAY_WOOCOMMERCE_URL . 'assets/css/frontend.css',
			array(),
			OCPAY_WOOCOMMERCE_VERSION
		);

		// Enqueue frontend JavaScript
		wp_enqueue_script(
			'ocpay-frontend',
			OCPAY_WOOCOMMERCE_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wc-checkout' ),
			OCPAY_WOOCOMMERCE_VERSION,
			true
		);

		// Localize script with translations
		wp_localize_script(
			'ocpay-frontend',
			'ocpayFrontend',
			array(
				'gatewayTitle'       => $this->title,
				'gatewayDescription' => $this->description,
			)
		);
	}

	/**
	 * Validate payment gateway fields on checkout
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( ! $this->get_option( 'api_key' ) ) {
			wc_add_notice( esc_html__( 'OCPay payment gateway is not properly configured. Please contact the store administrator.', 'ocpay-woocommerce' ), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Check if payment gateway is available
	 *
	 * @return bool
	 */
	public function is_available() {
		try {
			// Enhanced debug information
			$debug_info = [
				'time' => current_time('mysql'),
				'is_admin' => is_admin(),
				'enabled' => isset($this->enabled) ? $this->enabled : 'not set',
				'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'WC not loaded',
				'wc_loaded' => class_exists('WooCommerce') ? 'yes' : 'no',
				'cart_initialized' => (isset(WC()->cart) && is_object(WC()->cart)) ? 'yes' : 'no',
				'is_checkout' => (function_exists('is_checkout') && is_checkout()) ? 'yes' : 'no',
				'is_checkout_pay_page' => (function_exists('is_checkout_pay_page') && is_checkout_pay_page()) ? 'yes' : 'no',
				'current_filter' => current_filter(),
				'api_key_set' => !empty($this->get_option('api_key')) ? 'yes' : 'no',
				'api_mode' => $this->get_option('api_mode', 'not set'),
				'wp_debug' => defined('WP_DEBUG') && WP_DEBUG ? 'yes' : 'no',
			];
			
			// Log the debug info
			error_log('OCPay: is_available() called. ' . print_r($debug_info, true));

			// Must be enabled
			if ( 'yes' !== $this->enabled ) {
				error_log('OCPay: Gateway is NOT available - not enabled. enabled=' . $this->enabled);
				
				// In debug mode, allow gateway even if not enabled (for testing blocks)
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('OCPay: Debug mode - allowing gateway even when disabled');
					return true;
				}
				return false;
			}
			
			// Must have API key configured (relaxed for testing)
			if ( ! $this->get_option( 'api_key' ) ) {
				error_log('OCPay: Gateway is NOT available - no API key');
				
				// In debug mode, allow gateway even without API key (for testing blocks UI)
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('OCPay: Debug mode - allowing gateway even without API key');
					return true;
				}
				return false;
			}

			error_log('OCPay: Gateway IS available - all checks passed');
			return true;

		} catch ( Exception $e ) {
			// Log any exceptions that occur during availability check
			error_log('OCPay: Error checking gateway availability: ' . $e->getMessage());
			
			// In debug mode, return true even on errors for testing
			if (defined('WP_DEBUG') && WP_DEBUG) {
				return true;
			}
			return false;
		}
	}

	/**
	 * Initialize form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'       => array(
				'title'       => esc_html__( 'Enable/Disable', 'ocpay-woocommerce' ),
				'label'       => esc_html__( 'Enable OCPay Payment Gateway', 'ocpay-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'         => array(
				'title'       => esc_html__( 'Title', 'ocpay-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'ocpay-woocommerce' ),
				'default'     => esc_html__( 'OCPay - OneClick Payment', 'ocpay-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => esc_html__( 'Description', 'ocpay-woocommerce' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'ocpay-woocommerce' ),
				'default'     => esc_html__( 'Pay securely using OCPay - powered by SATIM bank-grade security.', 'ocpay-woocommerce' ),
			),
			'api_section'   => array(
				'title'       => esc_html__( 'API Settings', 'ocpay-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Configure your OCPay API credentials', 'ocpay-woocommerce' ),
			),
			'api_key'       => array(
				'title'       => esc_html__( 'API Key', 'ocpay-woocommerce' ),
				'type'        => 'password',
				'description' => esc_html__( 'Enter your OCPay API key from your merchant dashboard.', 'ocpay-woocommerce' ),
				'desc_tip'    => true,
			),
			'api_mode'      => array(
				'title'       => esc_html__( 'API Mode', 'ocpay-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Select Sandbox for testing or Live for production.', 'ocpay-woocommerce' ),
				'default'     => 'sandbox',
				'desc_tip'    => true,
				'options'     => array(
					'sandbox' => esc_html__( 'Sandbox (Testing)', 'ocpay-woocommerce' ),
					'live'    => esc_html__( 'Live (Production)', 'ocpay-woocommerce' ),
				),
			),
			'fee_mode'      => array(
				'title'       => esc_html__( 'Fee Mode', 'ocpay-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Choose who pays the transaction fees.', 'ocpay-woocommerce' ),
				'default'     => 'NO_FEE',
				'desc_tip'    => true,
				'options'     => array(
					'NO_FEE'      => esc_html__( 'No Fee (You pay all fees)', 'ocpay-woocommerce' ),
					'SPLIT_FEE'   => esc_html__( 'Split Fee (50/50 split)', 'ocpay-woocommerce' ),
					'CUSTOMER_FEE' => esc_html__( 'Customer Fee (Customer pays all)', 'ocpay-woocommerce' ),
				),
			),
			'redirect_url'  => array(
				'title'       => esc_html__( 'Redirect URL', 'ocpay-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Customer will be redirected to this URL after payment. Leave empty to use order-received page.', 'ocpay-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'debug_section' => array(
				'title'       => esc_html__( 'Debug Settings', 'ocpay-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Enable detailed logging for troubleshooting', 'ocpay-woocommerce' ),
			),
			'debug_mode'    => array(
				'title'       => esc_html__( 'Debug Mode', 'ocpay-woocommerce' ),
				'label'       => esc_html__( 'Enable Debug Logging', 'ocpay-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Enable detailed logging for debugging purposes.', 'ocpay-woocommerce' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$this->logger->info( 'Processing payment for order', array( 'order_id' => $order_id ) );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->logger->error( 'Order not found', array( 'order_id' => $order_id ) );
			wc_add_notice( esc_html__( 'Order not found.', 'ocpay-woocommerce' ), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( esc_html__( 'Order not found.', 'ocpay-woocommerce' ) ),
			);
		}

		// Prepare payment link data
		$payment_data = array(
			'title'       => 'Order #' . $order->get_order_number() . ' - ' . get_bloginfo( 'name' ),
			'amount'      => (float) $order->get_total(),
			'currency'    => $order->get_currency(),
			'redirectUrl' => $this->get_return_url( $order ),
			'feeMode'     => $this->get_option( 'fee_mode', 'NO_FEE' ),
		);

		// Check if API client is initialized
		if ( ! $this->api_client ) {
			$this->logger->error( 'API client not initialized - missing API key', array( 'order_id' => $order_id ) );
			wc_add_notice( esc_html__( 'Payment gateway not properly configured. Please contact support.', 'ocpay-woocommerce' ), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( esc_html__( 'Payment gateway not properly configured.', 'ocpay-woocommerce' ) ),
			);
		}

		// Create payment link via API
		$response = $this->api_client->create_payment_link( $payment_data );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to create payment link', array(
				'order_id' => $order_id,
				'error'    => $response->get_error_message(),
			) );
			wc_add_notice( esc_html__( 'Payment gateway error. Please try again.', 'ocpay-woocommerce' ), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( esc_html__( 'Payment gateway error. Please try again.', 'ocpay-woocommerce' ) ),
			);
		}

		// Extract payment details from response
		$payment_url = isset( $response['paymentUrl'] ) ? $response['paymentUrl'] : null;
		$payment_ref = isset( $response['paymentRef'] ) ? $response['paymentRef'] : null;

		if ( ! $payment_url || ! $payment_ref ) {
			$this->logger->error( 'Invalid API response - missing paymentUrl or paymentRef', array(
				'order_id' => $order_id,
				'response' => $response,
			) );
			wc_add_notice( esc_html__( 'Payment gateway error. Please try again.', 'ocpay-woocommerce' ), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( esc_html__( 'Payment gateway error. Please try again.', 'ocpay-woocommerce' ) ),
			);
		}

		// Save payment reference to order meta
		$order->update_meta_data( '_ocpay_payment_ref', $payment_ref );
		$order->update_meta_data( '_ocpay_payment_url', $payment_url );
		$order->set_status( 'pending', esc_html__( 'Awaiting OCPay payment confirmation.', 'ocpay-woocommerce' ) );
		$order->save();

		// Add order note
		$order->add_order_note( sprintf(
			esc_html__( 'OCPay payment initiated. Payment Reference: %s', 'ocpay-woocommerce' ),
			$payment_ref
		) );

		$this->logger->info( 'Payment link created successfully', array(
			'order_id'    => $order_id,
			'payment_ref' => $payment_ref,
		) );

		// Redirect to payment URL
		return array(
			'result'   => 'success',
			'redirect' => $payment_url,
		);
	}

	/**
	 * Process refund
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$this->logger->info( 'Processing refund', array(
			'order_id' => $order_id,
			'amount'   => $amount,
			'reason'   => $reason,
		) );

		// Refund functionality will be implemented in Phase 9
		return new WP_Error( 'not_implemented', esc_html__( 'Refunds are not yet implemented.', 'ocpay-woocommerce' ) );
	}

	/**
	 * Get payment method data for WooCommerce Blocks
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_title(),
			'description' => $this->get_description(),
			'supports'    => array_filter( $this->supports, array( $this, 'supports' ) ),
			'logo_url'    => $this->icon,
		);
	}

	/**
	 * Get payment method script data for WooCommerce Blocks
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = 'assets/js/blocks-payment-method.js';
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
		$script_url        = OCPAY_WOOCOMMERCE_URL . $script_path;

		wp_register_script(
			'ocpay-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// Enqueue the data for the blocks
		wp_localize_script(
			'ocpay-blocks-integration',
			'ocpayBlocksData',
			$this->get_payment_method_data()
		);

		return array( 'ocpay-blocks-integration' );
	}

	/**
	 * Add blocks support by exposing payment method data to wc.wcSettings
	 *
	 * @return void
	 */
	public function add_blocks_support() {
		if ( function_exists( 'wc_get_page_id' ) && 
			 ( is_checkout() || is_cart() || is_page( wc_get_page_id( 'checkout' ) ) || is_page( wc_get_page_id( 'cart' ) ) ) ) {
			
			wp_add_inline_script(
				'wc-settings',
				sprintf(
					'window.wc = window.wc || {}; window.wc.wcSettings = window.wc.wcSettings || {}; window.wc.wcSettings.setSetting( "ocpay_data", %s );',
					wp_json_encode( $this->get_payment_method_data() )
				),
				'after'
			);
		}
	}

	/**
	 * Allow blocks to access payment gateway data via REST API
	 *
	 * @param bool   $permission Original permission check result.
	 * @param string $context    Context for the permission check.
	 * @param int    $object_id  Object ID being checked.
	 * @param string $object_type Object type being checked.
	 * @return bool
	 */
	public function blocks_rest_permission_check( $permission, $context, $object_id, $object_type ) {
		// Allow read access to payment gateway data for blocks checkout
		if ( 'read' === $context && 'payment_gateway' === $object_type ) {
			return true;
		}
		return $permission;
	}
}
