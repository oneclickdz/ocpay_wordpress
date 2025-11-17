<?php
/**
 * OCPay Status Checker - Payment Status Polling Service
 *
 * Handles automatic and manual payment status checking
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Status_Checker class
 *
 * Implements status polling for pending payments
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
	 * Maximum checks per cron run
	 *
	 * @var int
	 */
	private $max_checks_per_run = 100;

	/**
	 * Status check timeout (seconds)
	 *
	 * @var int
	 */
	private $check_timeout = 300; // 5 minutes

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

		// Hooks
		add_action( 'wp_scheduled_event_ocpay_check_payment_status', array( $this, 'check_pending_payments' ) );
		add_action( 'wp_ajax_ocpay_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
		add_action( 'woocommerce_view_order', array( $this, 'check_status_on_order_view' ), 10, 1 );
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
	 * Check pending payments (cron job)
	 *
	 * Called every 20 minutes to check status of pending orders
	 *
	 * @return void
	 */
	public function check_pending_payments() {
		// Reinitialize API client on each run to ensure fresh credentials
		$this->init_api_client();

		if ( ! $this->api_client ) {
			$this->logger->error( 'Cannot check pending payments: API client not initialized' );
			return;
		}

		// Check if already running to prevent overlaps
		$lock_key = 'ocpay_payment_check_lock';
		$lock_value = get_transient( $lock_key );

		if ( $lock_value ) {
			$this->logger->warning( 'Payment status check already in progress, skipping this run' );
			return;
		}

		// Set lock for 5 minutes
		set_transient( $lock_key, true, $this->check_timeout );

		try {
			$this->logger->info( 'Starting scheduled payment status check' );

			// Get pending orders with OCPay payment reference
			$pending_orders = $this->get_pending_orders();

			if ( empty( $pending_orders ) ) {
				$this->logger->info( 'No pending OCPay orders to check' );
				delete_transient( $lock_key );
				return;
			}

			$this->logger->info( 'Found pending orders to check', array( 'count' => count( $pending_orders ) ) );

			// Check each order
			$checked = 0;
			$updated = 0;

			foreach ( $pending_orders as $order_id ) {
				if ( $checked >= $this->max_checks_per_run ) {
					$this->logger->info( 'Reached maximum checks per run', array( 'checked' => $checked ) );
					break;
				}

				$result = $this->check_order_payment_status( $order_id );
				$checked++;

				if ( $result ) {
					$updated++;
				}
			}

			$this->logger->info( 'Scheduled payment status check completed', array(
				'checked' => $checked,
				'updated' => $updated,
			) );

		} catch ( Exception $e ) {
			$this->logger->error( 'Error during scheduled payment check', array(
				'error' => $e->getMessage(),
			) );
		} finally {
			// Release lock
			delete_transient( $lock_key );
		}
	}

	/**
	 * Get pending OCPay orders
	 *
	 * Retrieves orders that:
	 * - Have pending status
	 * - Use OCPay payment method
	 * - Have a payment reference
	 * - Were created within the last 24 hours
	 *
	 * @return array Array of order IDs
	 */
	private function get_pending_orders() {
		$args = array(
			'limit'          => $this->max_checks_per_run,
			'status'         => array( 'pending' ),
			'payment_method' => 'ocpay',
			'meta_query'     => array(
				array(
					'key'     => '_ocpay_payment_ref',
					'compare' => 'EXISTS',
				),
			),
			'orderby'        => 'date',
			'order'          => 'ASC',
			'return'         => 'objects',
		);

		$query  = new WC_Order_Query( $args );
		$orders = $query->get_orders();
		$order_ids = array();
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( $order instanceof WC_Order ) {
					$order_ids[] = $order->get_id();
				}
			}
		}
		return $order_ids;
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

		// Check order age - skip if older than 24 hours
		$order_date = $order->get_date_created();
		$age = time() - $order_date->getTimestamp();
		if ( $age > 86400 ) { // 24 hours
			$this->logger->info( 'Skipping old pending order', array(
				'order_id' => $order_id,
				'age_hours' => round( $age / 3600 ),
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
	 * Check status when customer views order page
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function check_status_on_order_view( $order_id ) {
		// Only check if pending
		$order = wc_get_order( $order_id );

		if ( ! $order || 'pending' !== $order->get_status() || 'ocpay' !== $order->get_payment_method() ) {
			return;
		}

		// Check status silently
		$this->check_order_payment_status( $order_id );
	}

	/**
	 * Get order status counts for admin dashboard
	 *
	 * @return array Status counts
	 */
	public function get_status_counts() {
		$args = array(
			'limit'          => -1,
			'status'         => array( 'pending' ),
			'payment_method' => 'ocpay',
			'meta_query'     => array(
				array(
					'key'     => '_ocpay_payment_ref',
					'compare' => 'EXISTS',
				),
			),
			'return'         => 'objects',
		);
		$query  = new WC_Order_Query( $args );
		$orders = $query->get_orders();
		$pending_count = is_array( $orders ) ? count( $orders ) : 0;

		return array(
			'pending' => $pending_count,
		);
	}
}

// Initialize status checker on plugins_loaded
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		OCPay_Status_Checker::get_instance();
	}
} );
