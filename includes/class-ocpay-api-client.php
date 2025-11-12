<?php
/**
 * OCPay API Client
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_API_Client class
 *
 * Handles communication with OCPay API
 */
class OCPay_API_Client {

	/**
	 * OCPay API Base URL
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.oneclickdz.com/v3';

	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API mode (sandbox or live)
	 *
	 * @var string
	 */
	private $api_mode;

	/**
	 * Logger instance
	 *
	 * @var OCPay_Logger
	 */
	private $logger;

	/**
	 * Request timeout (seconds)
	 *
	 * @var int
	 */
	private $request_timeout = 30;

	/**
	 * Constructor
	 *
	 * @param string $api_key API Key.
	 * @param string $api_mode API mode (sandbox or live).
	 */
	public function __construct( $api_key = '', $api_mode = 'sandbox' ) {
		$this->api_key   = $api_key;
		$this->api_mode  = $api_mode;
		$this->logger    = OCPay_Logger::get_instance();
	}

	/**
	 * Create payment link
	 *
	 * @param array $args Payment link arguments.
	 *     @type string $title Product title/description.
	 *     @type float  $amount Payment amount.
	 *     @type string $currency Currency code (default: DZD).
	 *     @type string $redirectUrl Redirect URL after payment.
	 *     @type string $feeMode Fee mode (NO_FEE, SPLIT_FEE, CUSTOMER_FEE).
	 *
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function create_payment_link( $args ) {
		$defaults = array(
			'title'       => '',
			'amount'      => 0,
			'currency'    => 'DZD',
			'redirectUrl' => home_url(),
			'feeMode'     => 'NO_FEE',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate amount
		$amount_validation = OCPay_Validator::validate_amount( $args['amount'] );
		if ( true !== $amount_validation ) {
			$this->logger->error( 'Invalid payment amount', array( 'amount' => $args['amount'] ) );
			return new WP_Error( 'invalid_amount', $amount_validation );
		}

		// Validate currency
		$currency_validation = OCPay_Validator::validate_currency( $args['currency'] );
		if ( true !== $currency_validation ) {
			$this->logger->error( 'Invalid currency', array( 'currency' => $args['currency'] ) );
			return new WP_Error( 'invalid_currency', $currency_validation );
		}

		// Validate redirect URL
		$url_validation = OCPay_Validator::validate_redirect_url( $args['redirectUrl'] );
		if ( true !== $url_validation ) {
			$this->logger->error( 'Invalid redirect URL', array( 'url' => $args['redirectUrl'] ) );
			return new WP_Error( 'invalid_redirect_url', $url_validation );
		}

		// Validate fee mode
		$fee_validation = OCPay_Validator::validate_fee_mode( $args['feeMode'] );
		if ( true !== $fee_validation ) {
			$this->logger->error( 'Invalid fee mode', array( 'fee_mode' => $args['feeMode'] ) );
			return new WP_Error( 'invalid_fee_mode', $fee_validation );
		}

		// Prepare request body
		$body = array(
			'productInfo' => array(
				'title'    => OCPay_Validator::sanitize_description( $args['title'] ),
				'amount'   => (float) $args['amount'],
				'currency' => strtoupper( $args['currency'] ),
			),
			'redirectUrl' => esc_url_raw( $args['redirectUrl'] ),
			'feeMode'     => strtoupper( $args['feeMode'] ),
		);

		// Make API request
		$response = $this->make_request(
			'POST',
			'/ocpay/createLink',
			$body
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to create payment link', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		// Sanitize and log successful response
		$response = OCPay_Validator::sanitize_api_response( $response );
		$this->logger->info( 'Payment link created successfully', array( 'response' => $response ) );

		return $response;
	}

	/**
	 * Check payment status
	 *
	 * @param string $payment_ref Payment reference.
	 *
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function check_payment_status( $payment_ref ) {
		if ( empty( $payment_ref ) ) {
			$this->logger->error( 'Invalid payment reference' );
			return new WP_Error(
				'invalid_payment_ref',
				esc_html__( 'Invalid payment reference.', 'ocpay-woocommerce' )
			);
		}

		$payment_ref = sanitize_text_field( $payment_ref );

		// Make API request
		$response = $this->make_request(
			'GET',
			'/ocpay/checkPayment/' . $payment_ref,
			array()
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to check payment status', array( 
				'payment_ref' => $payment_ref,
				'error'       => $response->get_error_message(),
			) );
			return $response;
		}

		$this->logger->info( 'Payment status checked', array( 
			'payment_ref' => $payment_ref,
			'status'      => isset( $response['status'] ) ? $response['status'] : 'unknown',
		) );

		return $response;
	}

	/**
	 * Make HTTP request to OCPay API
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body for POST requests.
	 *
	 * @return array|WP_Error Response data or WP_Error on failure.
	 */
	private function make_request( $method, $endpoint, $body = array() ) {
		if ( empty( $this->api_key ) ) {
			$this->logger->error( 'API key is not configured' );
			return new WP_Error(
				'api_key_missing',
				esc_html__( 'OCPay API key is not configured.', 'ocpay-woocommerce' )
			);
		}

		// Prepare request URL
		$url = self::API_BASE_URL . $endpoint;

		// Prepare headers
		$headers = array(
			'Content-Type'     => 'application/json',
			'X-Access-Token'   => $this->api_key,
			'User-Agent'       => 'OCPay-WooCommerce/' . OCPAY_WOOCOMMERCE_VERSION,
		);

		// Prepare request arguments
		$request_args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => $this->request_timeout,
			'sslverify'   => true,
		);

		// Add body for POST requests
		if ( 'POST' === $method && ! empty( $body ) ) {
			$request_args['body'] = wp_json_encode( $body );
		}

		// Log request
		$this->logger->debug( 'Making API request', array(
			'method'   => $method,
			'endpoint' => $endpoint,
			'url'      => $url,
		) );

		// Make HTTP request
		$response = wp_remote_request( $url, $request_args );

		// Handle connection errors
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API request failed', array(
				'error' => $response->get_error_message(),
				'url'   => $url,
			) );
			return $response;
		}

		// Get response code and body
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Parse JSON response
		$data = json_decode( $response_body, true );

		// Log response
		$this->logger->debug( 'API response received', array(
			'code' => $response_code,
			'body' => $data,
		) );

		// Handle HTTP errors
		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error';
			$this->logger->error( 'API request returned error', array(
				'code'    => $response_code,
				'message' => $error_message,
			) );
			return new WP_Error(
				'api_error_' . $response_code,
				$error_message
			);
		}

		// Extract data from response
		if ( isset( $data['data'] ) ) {
			return $data['data'];
		}

		return $data;
	}

	/**
	 * Set API key
	 *
	 * @param string $api_key API key.
	 *
	 * @return void
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Set API mode
	 *
	 * @param string $api_mode API mode (sandbox or live).
	 *
	 * @return void
	 */
	public function set_api_mode( $api_mode ) {
		$this->api_mode = $api_mode;
	}

	/**
	 * Test API connection
	 *
	 * @return array|WP_Error Test result.
	 */
	public function test_connection() {
		$this->logger->info( 'Testing API connection' );

		// Try to get a simple endpoint response
		$response = $this->make_request( 'GET', '/ocpay/checkPayment/test-ref', array() );

		if ( is_wp_error( $response ) ) {
			// Even if payment ref is invalid, if we got a response from API, connection is working
			if ( strpos( $response->get_error_message(), 'API request failed' ) === false ) {
				return array(
					'success' => true,
					'message' => esc_html__( 'API connection successful!', 'ocpay-woocommerce' ),
				);
			}
			return $response;
		}

		return array(
			'success' => true,
			'message' => esc_html__( 'API connection successful!', 'ocpay-woocommerce' ),
		);
	}
}
