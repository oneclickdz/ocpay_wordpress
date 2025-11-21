<?php
/**
 * OCPay Order Handler
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Order_Handler class
 *
 * Handles order-related operations
 */
class OCPay_Order_Handler {

	/**
	 * Order handler instance
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
	 * Get order handler instance (Singleton)
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
		$this->logger     = OCPay_Logger::get_instance();
		$this->api_client = new OCPay_API_Client(
			$this->get_gateway_setting( 'api_key' ),
			$this->get_gateway_setting( 'api_mode' )
		);

		// Hooks
		add_action( 'woocommerce_api_ocpay_return', array( $this, 'handle_payment_return' ) );
	}

	/**
	 * Get gateway setting
	 *
	 * @param string $option Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_gateway_setting( $option, $default = '' ) {
		$settings = get_option( 'woocommerce_ocpay_settings', array() );
		return isset( $settings[ $option ] ) ? $settings[ $option ] : $default;
	}

	/**
	 * Check pending payments
	 *
	 * @return void
	 */
	public function check_pending_payments() {
		$this->logger->info( 'Checking pending OCPay payments' );

		// Get all pending orders with OCPay payment reference
		$args = array(
			'post_type'      => 'shop_order',
			'posts_per_page' => -1,
			'post_status'    => 'wc-pending',
			'meta_query'     => array(
				array(
					'key'     => '_payment_method',
					'value'   => 'ocpay',
					'compare' => '=',
				),
				array(
					'key'     => '_ocpay_payment_ref',
					'compare' => 'EXISTS',
				),
			),
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			$this->logger->info( 'No pending OCPay payments to check' );
			return;
		}

		$this->logger->info( 'Found pending orders', array( 'count' => $query->post_count ) );

		foreach ( $query->get_posts() as $post ) {
			$order      = wc_get_order( $post->ID );
			$payment_ref = $order->get_meta( '_ocpay_payment_ref' );

			if ( ! $payment_ref ) {
				continue;
			}

			$this->check_payment_status( $order, $payment_ref );
		}

		wp_reset_postdata();
	}

	/**
	 * Check payment status for a specific order
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $payment_ref Payment reference.
	 * @return void
	 */
	private function check_payment_status( $order, $payment_ref ) {
		$this->logger->info( 'Checking payment status', array(
			'order_id'    => $order->get_id(),
			'payment_ref' => $payment_ref,
		) );

		// Get payment status from API
		$response = $this->api_client->check_payment_status( $payment_ref );

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Failed to check payment status', array(
				'order_id' => $order->get_id(),
				'error'    => $response->get_error_message(),
			) );
			return;
		}

		$status = isset( $response['status'] ) ? $response['status'] : null;

		$this->logger->info( 'Payment status retrieved', array(
			'order_id'    => $order->get_id(),
			'status'      => $status,
		) );

		// Update order based on payment status
		switch ( $status ) {
			case 'CONFIRMED':
				$this->handle_payment_confirmed( $order );
				break;

			case 'FAILED':
				$this->handle_payment_failed( $order );
				break;

			case 'PENDING':
				// Still waiting for payment
				break;
		}
	}

	/**
	 * Handle payment confirmed
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function handle_payment_confirmed( $order ) {
		$this->logger->info( 'Payment confirmed', array( 'order_id' => $order->get_id() ) );

		// Check if order is already marked as processing or completed
		if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return;
		}

		// Mark order as processing
		$order->set_status( 'processing' );
		$order->add_order_note( esc_html__( 'OCPay payment confirmed. Order is processing.', 'ocpay-woocommerce' ) );
		$order->save();

		// Send customer notification
		do_action( 'woocommerce_payment_complete', $order->get_id() );
	}

	/**
	 * Handle payment failed
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function handle_payment_failed( $order ) {
		$this->logger->info( 'Payment failed', array( 'order_id' => $order->get_id() ) );

		// Check if order is already marked as failed or cancelled
		if ( in_array( $order->get_status(), array( 'failed', 'cancelled' ), true ) ) {
			return;
		}

		// Mark order as failed
		$order->set_status( 'failed' );
		$order->add_order_note( esc_html__( 'OCPay payment failed. Please try another payment method.', 'ocpay-woocommerce' ) );
		$order->save();

		// Send customer notification
		// Note: This could be enhanced to send email notification
	}

	/**
	 * Handle payment return from OCPay
	 *
	 * @return void
	 */
	public function handle_payment_return() {
		// Get payment reference from URL parameter
		$payment_ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : null;

		if ( ! $payment_ref ) {
			$this->logger->error( 'Payment return handler called without payment reference' );
			wp_die( esc_html__( 'Invalid payment reference.', 'ocpay-woocommerce' ), 'Bad Request', array( 'response' => 400 ) );
		}

		$this->logger->info( 'Handling payment return', array( 'payment_ref' => $payment_ref ) );

		// Get order by payment reference
		$order = $this->get_order_by_payment_ref( $payment_ref );

		if ( ! $order ) {
			$this->logger->error( 'Order not found for payment reference', array( 'payment_ref' => $payment_ref ) );
			wp_die( esc_html__( 'Order not found.', 'ocpay-woocommerce' ), 'Not Found', array( 'response' => 404 ) );
		}

		$this->logger->info( 'Order found for payment return', array( 
			'order_id'    => $order->get_id(),
			'payment_ref' => $payment_ref,
		) );

		// Check payment status via API
		$response = $this->api_client->check_payment_status( $payment_ref );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to check payment status during return', array(
				'order_id'    => $order->get_id(),
				'payment_ref' => $payment_ref,
				'error'       => $response->get_error_message(),
			) );
			
			// Even if API check fails, show a pending message to customer
			$this->display_payment_return( $order, 'pending' );
			return;
		}

		$status = isset( $response['status'] ) ? $response['status'] : 'PENDING';

		$this->logger->info( 'Payment status on return', array(
			'order_id'    => $order->get_id(),
			'status'      => $status,
		) );

		// Update order and display appropriate message
		$display_status = strtolower( $status );

		switch ( $status ) {
			case 'CONFIRMED':
				$this->handle_payment_confirmed( $order );
				$display_status = 'success';
				break;

			case 'FAILED':
				$this->handle_payment_failed( $order );
				$display_status = 'failed';
				break;

			case 'PENDING':
			default:
				$display_status = 'pending';
				break;
		}

		$this->display_payment_return( $order, $display_status );
	}

	/**
	 * Get order by payment reference
	 *
	 * @param string $payment_ref Payment reference.
	 * @return WC_Order|false
	 */
	public function get_order_by_payment_ref( $payment_ref ) {
		$args = array(
			'post_type'      => 'shop_order',
			'posts_per_page' => 1,
			'meta_key'       => '_ocpay_payment_ref',
			'meta_value'     => $payment_ref,
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return wc_get_order( $query->get_posts()[0]->ID );
		}

		return false;
	}

	/**
	 * Display payment return page
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $status Payment status (success, failed, pending).
	 * @return void
	 */
	private function display_payment_return( $order, $status ) {
		// Ensure we're in WordPress environment
		if ( ! function_exists( 'get_header' ) ) {
			wp_die( esc_html__( 'Cannot display page.', 'ocpay-woocommerce' ) );
		}

		// Set up page context
		global $wp_query;
		$wp_query->set_404();

		// Get appropriate template
		$template_file = OCPAY_WOOCOMMERCE_PATH . 'templates/payment-' . $status . '.php';

		// Fallback to pending template if specific status template doesn't exist
		if ( ! file_exists( $template_file ) ) {
			$template_file = OCPAY_WOOCOMMERCE_PATH . 'templates/payment-pending.php';
		}

		// Start output buffering
		ob_start();

		// Load template
		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo '<div class="ocpay-payment-container"><p>' . esc_html__( 'Payment page template not found.', 'ocpay-woocommerce' ) . '</p></div>';
		}

		$output = ob_get_clean();

		// Get WordPress header
		get_header();

		// Display the page content
		?>
		<div id="primary" class="content-area">
			<main id="main" class="site-main">
				<?php echo wp_kses_post( $output ); ?>
			</main>
		</div>
		<?php

		// Get WordPress footer
		get_footer();

		die();
	}
}

// Initialize order handler on plugins_loaded
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		OCPay_Order_Handler::get_instance();
	}
} );
