<?php
/**
 * OCPay Validation Helper
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Validator class
 *
 * Provides validation utilities for OCPay operations
 */
class OCPay_Validator {

	/**
	 * Validate payment amount
	 *
	 * @param float $amount Payment amount.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_amount( $amount ) {
		$amount = (float) $amount;

		// OCPay requires minimum 500 DZD
		if ( $amount < 500 ) {
			return esc_html__( 'Payment amount must be at least 500 DZD.', 'ocpay-woocommerce' );
		}

		// Check for reasonable max amount (e.g., 100,000,000 DZD)
		if ( $amount > 100000000 ) {
			return esc_html__( 'Payment amount exceeds maximum limit.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate currency code
	 *
	 * @param string $currency Currency code.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_currency( $currency ) {
		// OCPay only supports DZD (Algerian Dinar)
		$currency = strtoupper( $currency );

		if ( 'DZD' !== $currency ) {
			return sprintf(
				esc_html__( 'OCPay only supports DZD currency. You are using %s.', 'ocpay-woocommerce' ),
				esc_html( $currency )
			);
		}

		return true;
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return esc_html__( 'API key is required.', 'ocpay-woocommerce' );
		}

		if ( strlen( $api_key ) < 20 ) {
			return esc_html__( 'API key appears to be invalid (too short).', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate payment reference
	 *
	 * @param string $payment_ref Payment reference.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_payment_ref( $payment_ref ) {
		if ( empty( $payment_ref ) ) {
			return esc_html__( 'Payment reference is required.', 'ocpay-woocommerce' );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9\-_]{10,50}$/', $payment_ref ) ) {
			return esc_html__( 'Invalid payment reference format.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate payment status
	 *
	 * @param string $status Payment status.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_payment_status( $status ) {
		$valid_statuses = array( 'PENDING', 'CONFIRMED', 'FAILED' );

		if ( ! in_array( strtoupper( $status ), $valid_statuses, true ) ) {
			return esc_html__( 'Invalid payment status.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate redirect URL
	 *
	 * @param string $url URL to validate.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_redirect_url( $url ) {
		if ( empty( $url ) ) {
			return esc_html__( 'Redirect URL is required.', 'ocpay-woocommerce' );
		}

		$parsed = wp_parse_url( $url );

		if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
			return esc_html__( 'Invalid redirect URL format.', 'ocpay-woocommerce' );
		}

		// Only allow http and https
		if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return esc_html__( 'Redirect URL must use http or https protocol.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate fee mode
	 *
	 * @param string $fee_mode Fee mode.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_fee_mode( $fee_mode ) {
		$valid_modes = array( 'NO_FEE', 'SPLIT_FEE', 'CUSTOMER_FEE' );

		if ( ! in_array( strtoupper( $fee_mode ), $valid_modes, true ) ) {
			return esc_html__( 'Invalid fee mode.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Validate order
	 *
	 * @param WC_Order $order Order object.
	 * @return bool|string True if valid, error message if invalid.
	 */
	public static function validate_order( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return esc_html__( 'Invalid order.', 'ocpay-woocommerce' );
		}

		// Check if order total is valid
		$total = $order->get_total();
		if ( $total <= 0 ) {
			return esc_html__( 'Order total must be greater than 0.', 'ocpay-woocommerce' );
		}

		return true;
	}

	/**
	 * Sanitize payment description
	 *
	 * @param string $description Description text.
	 * @return string Sanitized description.
	 */
	public static function sanitize_description( $description ) {
		// Remove HTML tags
		$description = wp_strip_all_tags( $description );

		// Limit to 200 characters
		$description = substr( $description, 0, 200 );

		return sanitize_text_field( $description );
	}

	/**
	 * Sanitize API response data
	 *
	 * @param array $data API response data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_api_response( $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Sanitize based on key name
			if ( in_array( $key, array( 'paymentRef', 'orderId', 'transactionId' ), true ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( in_array( $key, array( 'amount' ), true ) ) {
				$sanitized[ $key ] = (float) $value;
			} elseif ( in_array( $key, array( 'status' ), true ) ) {
				$sanitized[ $key ] = strtoupper( sanitize_text_field( $value ) );
			} elseif ( 'paymentUrl' === $key ) {
				$sanitized[ $key ] = esc_url_raw( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}
}
