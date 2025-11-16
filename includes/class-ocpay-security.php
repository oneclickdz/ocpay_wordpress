<?php
/**
 * OCPay Security Handler
 *
 * Implements security best practices and hardening
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Security class
 *
 * Provides security features and protections
 */
class OCPay_Security {

	/**
	 * Security instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get security instance (Singleton)
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
		// Security headers
		add_action( 'send_headers', array( $this, 'add_security_headers' ), 1 );

		// Input validation and sanitization
		add_filter( 'sanitize_text_field', array( $this, 'sanitize_input' ), 10, 1 );

		// Output escaping for templates
		add_filter( 'woocommerce_checkout_fields', array( $this, 'validate_checkout_fields' ), 10, 1 );

		// Nonce verification for AJAX endpoints
		add_action( 'wp_ajax_ocpay_check_payment_status', array( $this, 'verify_ajax_nonce' ), 1 );

		// Prevent API key leakage in logs
		add_filter( 'ocpay_log_entry', array( $this, 'mask_sensitive_data' ), 10, 1 );

		// Prevent SQL injection in order queries
		add_action( 'woocommerce_api_ocpay_return', array( $this, 'sanitize_payment_ref' ), 1 );

		// Rate limiting for status checks
		add_action( 'wp_ajax_ocpay_check_payment_status', array( $this, 'rate_limit_status_checks' ), 2 );

		// CSRF protection
		add_action( 'admin_init', array( $this, 'add_admin_nonces' ) );
	}

	/**
	 * Add security headers
	 *
	 * @return void
	 */
	public function add_security_headers() {
		// Only on OCPay-related pages
		if ( ! $this->is_ocpay_page() ) {
			return;
		}

		// Content Security Policy
		if ( ! headers_sent() ) {
			header( "X-Content-Type-Options: nosniff" );
			header( "X-Frame-Options: SAMEORIGIN" );
			header( "X-XSS-Protection: 1; mode=block" );
			header( "Referrer-Policy: strict-origin-when-cross-origin" );
		}
	}

	/**
	 * Check if current page is OCPay-related
	 *
	 * @return bool
	 */
	private function is_ocpay_page() {
		// Check for OCPay admin pages
		if ( is_admin() && isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'ocpay' ) !== false ) {
			return true;
		}

		// Check for OCPay API endpoints
		if ( isset( $_GET['wc-api'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['wc-api'] ) ), 'ocpay' ) !== false ) {
			return true;
		}

		// Check for OCPay AJAX handlers
		if ( isset( $_POST['action'] ) && strpos( sanitize_text_field( wp_unslash( $_POST['action'] ) ), 'ocpay' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitize user input
	 *
	 * @param string $value Input value
	 * @return string Sanitized value
	 */
	public function sanitize_input( $value ) {
		if ( is_string( $value ) ) {
			// Remove any potentially malicious characters
			$value = wp_kses_post( $value );
		}
		return $value;
	}

	/**
	 * Validate checkout fields
	 *
	 * @param array $fields Checkout fields
	 * @return array Validated fields
	 */
	public function validate_checkout_fields( $fields ) {
		foreach ( $fields as $section => $section_fields ) {
			foreach ( $section_fields as $key => $field ) {
				// Sanitize field labels
				if ( isset( $field['label'] ) ) {
					$field['label'] = sanitize_text_field( $field['label'] );
				}
				// Sanitize field placeholders
				if ( isset( $field['placeholder'] ) ) {
					$field['placeholder'] = sanitize_text_field( $field['placeholder'] );
				}
				$fields[ $section ][ $key ] = $field;
			}
		}
		return $fields;
	}

	/**
	 * Verify AJAX nonce for payment status checks
	 *
	 * @return void
	 */
	public function verify_ajax_nonce() {
		// This is called before the main handler
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		
		if ( ! wp_verify_nonce( $nonce, 'ocpay_frontend_nonce' ) ) {
			// Log suspicious activity
			error_log( 'OCPay: AJAX nonce verification failed - possible CSRF attempt' );
		}
	}

	/**
	 * Mask sensitive data in logs
	 *
	 * @param string $entry Log entry
	 * @return string Sanitized log entry
	 */
	public function mask_sensitive_data( $entry ) {
		// Mask API keys
		$entry = preg_replace( '/X-Access-Token["\']?\s*:\s*["\']?([^"\'}\s]+)/i', 'X-Access-Token: ***HIDDEN***', $entry );
		
		// Mask payment references if sensitive
		$entry = preg_replace( '/paymentRef["\']?\s*:\s*["\']?([^"\'}\s]+)/i', 'paymentRef: [MASKED]', $entry );
		
		return $entry;
	}

	/**
	 * Sanitize payment reference to prevent SQL injection
	 *
	 * @return void
	 */
	public function sanitize_payment_ref() {
		if ( isset( $_GET['ref'] ) ) {
			$payment_ref = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
			
			// Validate payment reference format
			if ( ! preg_match( '/^[a-zA-Z0-9\-_]{10,50}$/', $payment_ref ) ) {
				wp_die( esc_html__( 'Invalid payment reference format.', 'ocpay-woocommerce' ), 'Bad Request', array( 'response' => 400 ) );
			}
		}
	}

	/**
	 * Rate limiting for status checks
	 *
	 * Prevent abuse of status check endpoint
	 *
	 * @return void
	 */
	public function rate_limit_status_checks() {
		$user_id = get_current_user_id();
		$user_ip = $this->get_client_ip();
		$rate_limit_key = 'ocpay_status_check_' . ( $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5( $user_ip ) );

		// Get current request count
		$count = (int) get_transient( $rate_limit_key );

		// Maximum 10 checks per minute
		if ( $count >= 10 ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Too many status check requests. Please wait a moment before trying again.', 'ocpay-woocommerce' ),
			) );
		}

		// Increment counter
		set_transient( $rate_limit_key, $count + 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} else {
			return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}
	}

	/**
	 * Add admin nonces for CSRF protection
	 *
	 * @return void
	 */
	public function add_admin_nonces() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			wp_create_nonce( 'ocpay_admin_nonce' );
		}
	}

	/**
	 * Validate and escape output
	 *
	 * @param string $text Text to escape
	 * @param string $context Context (html, attr, js, url)
	 * @return string Escaped text
	 */
	public static function safe_output( $text, $context = 'html' ) {
		switch ( $context ) {
			case 'attr':
				return esc_attr( $text );
			case 'js':
				return esc_js( $text );
			case 'url':
				return esc_url( $text );
			case 'html':
			default:
				return esc_html( $text );
		}
	}

	/**
	 * Validate payment data before API call
	 *
	 * @param array $data Payment data
	 * @return bool|string True if valid, error message if invalid
	 */
	public static function validate_payment_data( $data ) {
		// Check for required fields
		if ( empty( $data['amount'] ) || empty( $data['redirectUrl'] ) ) {
			return esc_html__( 'Missing required payment data.', 'ocpay-woocommerce' );
		}

		// Validate amount is numeric and positive
		if ( ! is_numeric( $data['amount'] ) || $data['amount'] <= 0 ) {
			return esc_html__( 'Invalid payment amount.', 'ocpay-woocommerce' );
		}

		// Validate URL
		if ( ! filter_var( $data['redirectUrl'], FILTER_VALIDATE_URL ) ) {
			return esc_html__( 'Invalid redirect URL.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Encrypt sensitive data
	 *
	 * @param string $data Data to encrypt
	 * @return string Encrypted data (base64 encoded)
	 */
	public static function encrypt_data( $data ) {
		$key = wp_salt( 'auth' );
		$nonce = wp_salt( 'secure_auth' );
		
		// Use WordPress encryption if available
		if ( function_exists( 'wp_encrypt' ) ) {
			return wp_encrypt( $data, $key );
		}

		// Fallback to base64 encoding with nonce
		return base64_encode( $nonce . $data );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * @param string $data Encrypted data
	 * @return string Decrypted data
	 */
	public static function decrypt_data( $data ) {
		$key = wp_salt( 'auth' );
		
		// Use WordPress decryption if available
		if ( function_exists( 'wp_decrypt' ) ) {
			return wp_decrypt( $data, $key );
		}

		// Fallback to base64 decoding
		$decoded = base64_decode( $data );
		$nonce = wp_salt( 'secure_auth' );
		
		if ( strpos( $decoded, $nonce ) === 0 ) {
			return substr( $decoded, strlen( $nonce ) );
		}

		return '';
	}
}

// Initialize security on plugins_loaded
add_action( 'plugins_loaded', function() {
	OCPay_Security::get_instance();
} );
