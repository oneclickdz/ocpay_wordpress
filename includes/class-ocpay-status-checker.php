<?php
/**
 * OCPay Status Checker - On-Demand Payment Status Checking
 *
 * Handles payment status checking when WooCommerce pages are loaded
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Status_Checker class
 *
 * Checks payment status on-demand when customers view order pages
 */
class OCPay_Status_Checker {

	/**
	 * Status checker instance
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
	 * API Client instance
	 *
	 * @var OCPay_API_Client
	 */
	private $api_client;



	/**
	 * Get status checker instance (Singleton)
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

		// Initialize API client
		$this->init_api_client();

		// Hooks - check status when pages load
		add_action( 'wp_ajax_ocpay_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'check_status_on_thankyou' ), 10, 1 );
		add_action( 'woocommerce_view_order', array( $this, 'check_status_on_order_view' ), 10, 1 );
		add_action( 'woocommerce_before_account_orders', array( $this, 'check_status_before_account_orders' ), 10 );
	}

	/**
	 * Initialize API client
	 *
	 * @return void
	 */
	private function init_api_client() {
		// Get the OCPay gateway instance to access its settings
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Retrieve gateway settings from WooCommerce
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
		
		if ( ! $gateway ) {
			$this->logger->warning( 'OCPay gateway not found for status checker initialization' );
			return;
		}

		// Get API mode and key from gateway
		$api_mode = $gateway->get_option( 'api_mode', 'sandbox' );
		$api_key = ( 'live' === $api_mode ) 
			? $gateway->get_option( 'api_key_live' )
			: $gateway->get_option( 'api_key_sandbox' );

		if ( ! empty( $api_key ) ) {
			$this->api_client = new OCPay_API_Client( $api_key, $api_mode );
		} else {
			$this->logger->warning( 'OCPay API key not configured for status checker' );
		}
	}

	/**
	 * Ensure API client is initialized
	 * 
	 * @return bool True if API client is initialized, false otherwise
	 */
	private function ensure_api_client() {
		// Only reinitialize if not already initialized
		if ( ! $this->api_client ) {
			$this->init_api_client();
		}

		if ( ! $this->api_client ) {
			$this->logger->error( 'Cannot check payment status: API client not initialized' );
			return false;
		}

		return true;
	}

	/**
	 * Check payment status on thank you page
	 *
	 * @param int $order_id Order ID
	 */
	public function check_status_on_thankyou( $order_id ) {
		if ( ! $this->ensure_api_client() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( $order && 'ocpay' === $order->get_payment_method() && 'pending' === $order->get_status() ) {
			$this->check_order_payment_status( $order_id );
		}
	}

	/**
	 * Check all pending payments
	 */
	public function check_pending_payments() {
		if ( ! $this->ensure_api_client() ) {
			return;
		}

		$query = new WC_Order_Query( array(
			'limit'          => apply_filters( 'ocpay_manual_check_limit', 50 ),
			'status'         => 'pending',
			'payment_method' => 'ocpay',
			'meta_query'     => array( array( 'key' => '_ocpay_payment_ref', 'compare' => 'EXISTS' ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'return'         => 'ids',
		) );

		foreach ( $query->get_orders() as $order_id ) {
			$this->check_order_payment_status( $order_id );
		}
	}



	/**
	 * Check payment status for a specific order
	 *
	 * @param int $order_id Order ID
	 * @return bool True if order was updated
	 */
	public function check_order_payment_status( $order_id ) {
		// Verify this is an OCPay order
		$order = wc_get_order( $order_id );
		
		if ( ! $order || 'ocpay' !== $order->get_payment_method() ) {
			return false;
		}

		// Skip if already completed or failed
		if ( in_array( $order->get_status(), array( 'completed', 'processing', 'failed', 'cancelled' ), true ) ) {
			return false;
		}

		// Get payment reference
		$payment_ref = $order->get_meta( '_ocpay_payment_ref' );
		
		if ( ! $payment_ref ) {
			$this->logger->warning( 'No payment reference found for order', array( 'order_id' => $order_id ) );
			return false;
		}

		// Check order age - skip if older than 30 days to prevent unnecessary API calls
		$order_date = $order->get_date_created();
		$age_days = ( time() - $order_date->getTimestamp() ) / DAY_IN_SECONDS;
		if ( $age_days > 30 ) {
			$this->logger->debug( 'Skipping old pending order', array(
				'order_id'  => $order_id,
				'age_days' => round( $age_days, 1 ),
			) );
			return false;
		}

		$this->logger->debug( 'Checking payment status for order', array(
			'order_id'    => $order_id,
			'payment_ref' => $payment_ref,
		) );

		// Initialize API client if not already done
		if ( ! $this->api_client ) {
			$this->init_api_client();
			if ( ! $this->api_client ) {
				$this->logger->error( 'API client not initialized', array( 'order_id' => $order_id ) );
				return false;
			}
		}

		// Check payment status via API
		$response = $this->api_client->check_payment_status( $payment_ref );

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Failed to check payment status', array(
				'order_id'    => $order_id,
				'payment_ref' => $payment_ref,
				'error'       => $response->get_error_message(),
			) );
			return false;
		}

		$status = isset( $response['status'] ) ? strtoupper( $response['status'] ) : null;

		$this->logger->debug( 'Payment status received', array(
			'order_id'    => $order_id,
			'payment_ref' => $payment_ref,
			'status'      => $status,
		) );

		// Update order based on payment status
		switch ( $status ) {
			case 'CONFIRMED':
				return $this->update_order_to_completed( $order, $response );

			case 'FAILED':
				return $this->update_order_to_failed( $order );

			case 'PENDING':
			default:
				// Still pending - no update needed
				return false;
		}
	}

	/**
	 * Update order to completed/processing based on settings
	 *
	 * @param WC_Order $order Order object
	 * @param array    $payment_data Payment data from API
	 * @return bool True if order was updated
	 */
	private function update_order_to_completed( $order, $payment_data ) {
		$order_id = $order->get_id();

		// Check if already processed
		if ( in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
			return false;
		}

		// Get configured success status from gateway settings
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
		
		if ( ! $gateway ) {
			$this->logger->warning( 'OCPay gateway not found when updating order status', array( 'order_id' => $order_id ) );
			$success_status = 'processing';
		} else {
			$success_status = $gateway->get_option( 'order_status_after_payment', 'processing' );
		}

		// Ensure it's one of the valid options
		if ( ! in_array( $success_status, array( 'completed', 'processing' ), true ) ) {
			$success_status = 'processing';
		}

		$this->logger->info( 'Updating order to successful status', array(
			'order_id'      => $order_id,
			'status'        => $success_status,
			'payment_ref'   => $order->get_meta( '_ocpay_payment_ref' ),
		) );

		// Update order
		$order->set_status( $success_status );
		$order->update_meta_data( '_ocpay_payment_confirmed_at', current_time( 'mysql' ) );
		$order->add_order_note( sprintf(
			/* translators: %s: payment reference */
			esc_html__( 'OCPay payment confirmed via status polling. Payment Reference: %s', 'ocpay-woocommerce' ),
			$order->get_meta( '_ocpay_payment_ref' )
		) );

		// Mark order as paid
		if ( 'completed' === $success_status ) {
			$order->set_date_completed( current_time( 'mysql' ) );
		}

		$order->save();

		// Trigger payment complete action
		do_action( 'woocommerce_payment_complete', $order_id );

		// Send customer notification
		do_action( 'woocommerce_order_status_' . $success_status, $order_id, $order );

		return true;
	}

	/**
	 * Update order to failed status
	 *
	 * @param WC_Order $order Order object
	 * @return bool True if order was updated
	 */
	private function update_order_to_failed( $order ) {
		$order_id = $order->get_id();

		// Check if already failed/cancelled
		if ( in_array( $order->get_status(), array( 'failed', 'cancelled' ), true ) ) {
			return false;
		}

		$this->logger->info( 'Updating order to failed status', array(
			'order_id'    => $order_id,
			'payment_ref' => $order->get_meta( '_ocpay_payment_ref' ),
		) );

		// Update order to hold status (not completed nor failed - for manual review)
		$order->set_status( 'on-hold' );
		$order->update_meta_data( '_ocpay_payment_failed_at', current_time( 'mysql' ) );
		$order->add_order_note( sprintf(
			/* translators: %s: payment reference */
			esc_html__( 'OCPay payment failed via status polling. Payment Reference: %s. Please contact customer for alternative payment method.', 'ocpay-woocommerce' ),
			$order->get_meta( '_ocpay_payment_ref' )
		) );

		$order->save();

		// Send admin notification
		do_action( 'woocommerce_order_status_on-hold', $order_id, $order );

		return true;
	}

	/**
	 * Check status when customer views order page (AJAX)
	 *
	 * @return void
	 */
	public function ajax_check_payment_status() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_frontend_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'ocpay-woocommerce' ) ) );
		}

		// Get order ID
		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;

		if ( $order_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid order ID.', 'ocpay-woocommerce' ) ) );
		}

		// Verify user can view this order
		$order = wc_get_order( $order_id );
		
		if ( ! $order || 'ocpay' !== $order->get_payment_method() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid order.', 'ocpay-woocommerce' ) ) );
		}

		// Check if user is customer or admin
		$customer_id = get_current_user_id();
		if ( $customer_id !== (int) $order->get_customer_id() && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to check this order.', 'ocpay-woocommerce' ) ) );
		}

		// Check status
		$result = $this->check_order_payment_status( $order_id );

		// Reload order to get updated status
		$order = wc_get_order( $order_id );

		wp_send_json_success( array(
			'updated' => $result,
			'status'  => $order->get_status(),
			'message' => esc_html__( 'Payment status updated.', 'ocpay-woocommerce' ),
		) );
	}

	/**
	 * Check status when viewing order page
	 *
	 * @param int $order_id Order ID
	 */
	public function check_status_on_order_view( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && 'pending' === $order->get_status() && 'ocpay' === $order->get_payment_method() ) {
			$this->check_order_payment_status( $order_id );
		}
	}

	/**
	 * Check status before displaying orders list
	 */
	public function check_status_before_account_orders() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$query = new WC_Order_Query( array(
			'limit'          => 5,
			'status'         => 'pending',
			'payment_method' => 'ocpay',
			'customer_id'    => get_current_user_id(),
			'date_created'   => '>=' . strtotime( '-30 days' ),
			'meta_query'     => array( array( 'key' => '_ocpay_payment_ref', 'compare' => 'EXISTS' ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'return'         => 'ids',
		) );

		foreach ( $query->get_orders() as $order_id ) {
			$this->check_order_payment_status( $order_id );
		}
	}


}

// Initialize status checker on plugins_loaded
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		OCPay_Status_Checker::get_instance();
	}
} );
