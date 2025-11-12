<?php
/**
 * OCPay Error Handler
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Error_Handler class
 *
 * Handles error tracking and user-friendly error messages
 */
class OCPay_Error_Handler {

	/**
	 * Error handler instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Logger instance
	 *
	 * @var OCPay_Logger
	 */
	private $logger;

	/**
	 * Error messages map
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Get error handler instance (Singleton)
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->logger = OCPay_Logger::get_instance();
		$this->init_error_messages();
		$this->init_hooks();
	}

	/**
	 * Initialize error messages
	 *
	 * @return void
	 */
	private function init_error_messages() {
		$this->error_messages = array(
			// API Errors
			'api_connection_error'     => esc_html__( 'Unable to connect to OCPay. Please check your internet connection and try again.', 'ocpay-woocommerce' ),
			'api_timeout'              => esc_html__( 'OCPay connection timeout. Please try again later.', 'ocpay-woocommerce' ),
			'api_invalid_response'     => esc_html__( 'Received invalid response from OCPay. Please contact support.', 'ocpay-woocommerce' ),
			'api_key_invalid'          => esc_html__( 'Invalid OCPay API key. Please check your settings.', 'ocpay-woocommerce' ),
			'api_key_missing'          => esc_html__( 'OCPay API key is not configured. Please contact the store administrator.', 'ocpay-woocommerce' ),
			'api_rate_limit'           => esc_html__( 'Too many requests to OCPay. Please try again later.', 'ocpay-woocommerce' ),

			// Payment Errors
			'payment_failed'           => esc_html__( 'Payment failed. Please try again or use a different payment method.', 'ocpay-woocommerce' ),
			'payment_cancelled'        => esc_html__( 'Payment was cancelled. Please try again.', 'ocpay-woocommerce' ),
			'payment_expired'          => esc_html__( 'Payment link expired. Please try again.', 'ocpay-woocommerce' ),
			'payment_invalid_amount'   => esc_html__( 'Invalid payment amount. Please try again.', 'ocpay-woocommerce' ),
			'payment_currency_invalid' => esc_html__( 'Currency not supported. Please contact support.', 'ocpay-woocommerce' ),

			// Order Errors
			'order_not_found'          => esc_html__( 'Order not found. Please contact support.', 'ocpay-woocommerce' ),
			'order_invalid_status'     => esc_html__( 'Invalid order status for payment. Please contact support.', 'ocpay-woocommerce' ),

			// Validation Errors
			'validation_error'         => esc_html__( 'Please fill in all required fields.', 'ocpay-woocommerce' ),
			'nonce_verification_error' => esc_html__( 'Security verification failed. Please try again.', 'ocpay-woocommerce' ),

			// Generic Errors
			'unknown_error'            => esc_html__( 'An unknown error occurred. Please try again or contact support.', 'ocpay-woocommerce' ),
		);
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Handle PHP errors and exceptions
		set_error_handler( array( $this, 'handle_php_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
	}

	/**
	 * Handle PHP errors
	 *
	 * @param int    $errno Error number.
	 * @param string $errstr Error string.
	 * @param string $errfile Error file.
	 * @param int    $errline Error line.
	 * @return bool
	 */
	public function handle_php_error( $errno, $errstr, $errfile, $errline ) {
		// Only handle OCPay errors
		if ( strpos( $errfile, 'ocpay-woocommerce' ) === false ) {
			return false;
		}

		$error_context = array(
			'errno'   => $errno,
			'errstr'  => $errstr,
			'errfile' => $errfile,
			'errline' => $errline,
		);

		// Log based on error level
		switch ( $errno ) {
			case E_WARNING:
			case E_USER_WARNING:
				$this->logger->warning( 'PHP Warning: ' . $errstr, $error_context );
				break;

			case E_ERROR:
			case E_USER_ERROR:
				$this->logger->error( 'PHP Error: ' . $errstr, $error_context );
				break;

			default:
				$this->logger->debug( 'PHP Notice: ' . $errstr, $error_context );
				break;
		}

		return true;
	}

	/**
	 * Handle exceptions
	 *
	 * @param Exception $exception Exception object.
	 * @return void
	 */
	public function handle_exception( $exception ) {
		$this->logger->error( 'Uncaught Exception: ' . $exception->getMessage(), array(
			'exception' => get_class( $exception ),
			'file'      => $exception->getFile(),
			'line'      => $exception->getLine(),
			'trace'     => $exception->getTraceAsString(),
		) );

		// Only display user-friendly message if NOT during plugin initialization
		// Let WooCommerce handle plugin load errors naturally
		if ( did_action( 'woocommerce_loaded' ) && ! doing_action( 'woocommerce_payment_gateways' ) ) {
			wp_die( esc_html__( 'An error occurred. Please try again later.', 'ocpay-woocommerce' ), 'Error', array( 'response' => 500 ) );
		}
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param string $error_code Error code.
	 * @param mixed  $context Additional context or custom message.
	 * @return string
	 */
	public function get_error_message( $error_code, $context = null ) {
		if ( isset( $this->error_messages[ $error_code ] ) ) {
			$message = $this->error_messages[ $error_code ];
		} else {
			$message = $this->error_messages['unknown_error'];
		}

		// Allow context to be a custom message
		if ( is_string( $context ) && ! empty( $context ) ) {
			$message = $context;
		}

		return $message;
	}

	/**
	 * Handle WP_Error and convert to log entry
	 *
	 * @param WP_Error $error Error object.
	 * @param string   $context Error context.
	 * @return void
	 */
	public function handle_wp_error( $error, $context = '' ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		$error_data = array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'context' => $context,
		);

		// Add error data if available
		$error_detail = $error->get_error_data();
		if ( ! empty( $error_detail ) ) {
			$error_data['details'] = $error_detail;
		}

		$this->logger->error( 'WP_Error: ' . $error->get_error_message(), $error_data );
	}

	/**
	 * Log API error
	 *
	 * @param int    $response_code HTTP response code.
	 * @param string $response_body HTTP response body.
	 * @param string $endpoint API endpoint.
	 * @return string Error code for get_error_message().
	 */
	public function log_api_error( $response_code, $response_body, $endpoint = '' ) {
		$error_code = 'unknown_error';

		$context = array(
			'response_code' => $response_code,
			'response_body' => $response_body,
			'endpoint'      => $endpoint,
		);

		// Determine error code from response code
		switch ( $response_code ) {
			case 400:
				$error_code = 'validation_error';
				break;

			case 401:
			case 403:
				$error_code = 'api_key_invalid';
				break;

			case 404:
				$error_code = 'order_not_found';
				break;

			case 429:
				$error_code = 'api_rate_limit';
				break;

			case 500:
			case 502:
			case 503:
			case 504:
				$error_code = 'api_connection_error';
				break;

			default:
				$error_code = 'api_invalid_response';
				break;
		}

		$this->logger->error( 'API Error: ' . $response_code, $context );

		return $error_code;
	}

	/**
	 * Create breadcrumb for debugging
	 *
	 * @param string $category Category.
	 * @param string $message Message.
	 * @param array  $data Additional data.
	 * @return void
	 */
	public function add_breadcrumb( $category, $message, $data = array() ) {
		$breadcrumb = array(
			'timestamp' => current_time( 'mysql' ),
			'category'  => $category,
			'message'   => $message,
			'data'      => $data,
		);

		$this->logger->debug( $message, $breadcrumb );
	}

	/**
	 * Get all error messages
	 *
	 * @return array
	 */
	public function get_all_error_messages() {
		return $this->error_messages;
	}
}

// Initialize error handler on plugins_loaded
// Disabled temporarily to debug payment gateway initialization issues
// add_action( 'plugins_loaded', function() {
// 	OCPay_Error_Handler::get_instance();
// } );
