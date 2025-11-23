<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OCPay Gateway Class.
 */
class WC_Gateway_OCPay extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'ocpay';
		$this->icon               = plugins_url( 'assets/img/cibeddahabia.png', __FILE__ );
		$this->has_fields         = false;
		$this->method_title       = __( 'CIB / EDAHABIA', 'ocpay-gateway' );
		$this->method_description = __( 'Pay securely using OCPay', 'ocpay-gateway' );

		// Declare support for WooCommerce features
		$this->supports = array(
			'products',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->sandbox_key   = $this->get_option( 'sandbox_key' );
		$this->prod_key      = $this->get_option( 'production_key' );
		$this->environment   = $this->get_option( 'environment' );
		$this->fee_mode      = $this->get_option( 'fee_mode' );
		$this->order_status  = $this->get_option( 'order_status' );
		$this->enable_logging = $this->get_option( 'enable_logging', 'yes' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_ocpay', array( $this, 'webhook' ) );
		
		// Runs on: Thank you page after order placement
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		
		// Runs on: Customer My Account > View single order page (BEFORE rendering)
		add_action( 'woocommerce_before_account_orders', array( $this, 'check_pending_orders_list' ), 1 );
		add_action( 'woocommerce_before_account_downloads', array( $this, 'check_pending_orders_list' ), 1 );
		add_action( 'woocommerce_before_account_navigation', array( $this, 'check_pending_orders_list' ), 1 );
		add_action( 'woocommerce_view_order', array( $this, 'check_order_status_frontend' ), 1 );
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'check_order_status_frontend' ), 1 );
		
		// Runs on: Admin viewing single order edit page
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'check_order_status_admin' ) );
		
		// Runs on: Admin orders list page load (both old and HPOS)
		add_action( 'load-edit.php', array( $this, 'check_pending_orders_list_admin' ), 1 );
		add_action( 'load-woocommerce_page_wc-orders', array( $this, 'check_pending_orders_list_admin' ), 1 );
	}



	/**
	 * Check if this gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = parent::is_available();
		// Only allow DZD currency
		if ( 'DZD' !== get_woocommerce_currency() ) {
			return false;
		}
		return $is_available;
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'ocpay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable OCPay Payment', 'ocpay-gateway' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'ocpay-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'ocpay-gateway' ),
				'default'     => __( 'OCPay', 'ocpay-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'ocpay-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'ocpay-gateway' ),
				'default'     => __( 'Pay securely with OCPay.', 'ocpay-gateway' ),
				'desc_tip'    => true,
			),
			'environment' => array(
				'title'       => __( 'Environment', 'ocpay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select the environment to use.', 'ocpay-gateway' ),
				'default'     => 'sandbox',
				'options'     => array(
					'sandbox'    => __( 'Sandbox', 'ocpay-gateway' ),
					'production' => __( 'Production', 'ocpay-gateway' ),
				),
			),
			'sandbox_key' => array(
				'title'       => __( 'Sandbox API Key', 'ocpay-gateway' ),
				'type'        => 'password',
			),
			'production_key' => array(
				'title'       => __( 'Production API Key', 'ocpay-gateway' ),
				'type'        => 'password',
			),
			'fee_mode' => array(
				'title'       => __( 'Fee Mode', 'ocpay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Who pays the fees?', 'ocpay-gateway' ),
				'default'     => 'NO_FEE',
				'options'     => array(
					'NO_FEE'       => __( 'Merchant pays (No Fee)', 'ocpay-gateway' ),
					'SPLIT_FEE'    => __( 'Split Fee', 'ocpay-gateway' ),
					'CUSTOMER_FEE' => __( 'Customer pays', 'ocpay-gateway' ),
				),
			),
			'order_status' => array(
				'title'       => __( 'Final Order Status', 'ocpay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Status to set after successful payment.', 'ocpay-gateway' ),
				'default'     => 'processing',
				'options'     => array(
					'processing' => __( 'Processing', 'ocpay-gateway' ),
					'completed'  => __( 'Completed', 'ocpay-gateway' ),
					'on-hold'    => __( 'On Hold', 'ocpay-gateway' ),
				),
			),
			'enable_logging' => array(
				'title'       => __( 'Enable Logging', 'ocpay-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable request and error logging', 'ocpay-gateway' ),
				'default'     => 'yes',
			),
			'logs_section' => array(
				'title'       => __( 'Recent Logs', 'ocpay-gateway' ),
				'type'        => 'title',
				'description' => $this->get_recent_logs_html(),
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Don't create new payment link for already processed orders
		if ( $order->has_status( array( 'processing', 'completed', 'on-hold', 'cancelled' ) ) ) {
			$this->log( 'Order #' . $order->get_order_number() . ' already processed with status: ' . $order->get_status() );
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		// Get API Key based on environment
		$api_key = ( 'production' === $this->environment ) ? $this->prod_key : $this->sandbox_key;
		
		if ( empty( $api_key ) ) {
			wc_add_notice( __( 'Payment error: Missing API Key.', 'ocpay-gateway' ), 'error' );
			return;
		}

		// Check if order is pending with existing payment link - reuse it
		$existing_payment_url = $order->get_meta( '_ocpay_payment_url' );
		if ( $order->has_status( array( 'pending' ) ) && ! empty( $existing_payment_url ) ) {
			$this->log( 'Reusing existing payment link for pending Order #' . $order->get_order_number() );
			return array(
				'result'   => 'success',
				'redirect' => $existing_payment_url,
			);
		}

		// For failed orders or orders without payment link, create new one
		// Prepare API Request
		$api_url = 'https://api.oneclickdz.com/v3/ocpay/createLink';

		$body = array(
			'productInfo' => array(
				'title'       => get_bloginfo( 'name' ) . ' - Order #' . $order->get_order_number(),
				'description' => 'Payment for Order #' . $order->get_order_number(),
				'amount'      => intval( $order->get_total() ), // Must be integer (whole numbers, no decimals)
			),
			'feeMode'        => $this->fee_mode,
			'successMessage' => __( '', 'ocpay-gateway' ),
			'redirectUrl'    => $this->get_return_url( $order ),
		);

		// Send Request
		$this->log( 'Creating payment link for Order #' . $order->get_order_number() . ' - Amount: ' . $order->get_total() );
		$this->log( 'Request body: ' . json_encode( $body ) );
		
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'X-Access-Token' => $api_key,
			),
			'body'    => json_encode( $body ),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			$error_message = 'Connection error: ' . $response->get_error_message();
			$this->log( $error_message, 'error' );
			wc_add_notice( __( 'Connection error.', 'ocpay-gateway' ), 'error' );
			return;
		}

		$body_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body_response['success'] ) || ! $body_response['success'] ) {
			$error_msg = __( 'Payment creation failed.', 'ocpay-gateway' );
			
			if ( isset( $body_response['error'] ) ) {
				$error_msg = is_array( $body_response['error'] ) ? json_encode( $body_response['error'] ) : $body_response['error'];
			}
			
			$this->log( 'Payment creation failed for Order #' . $order->get_order_number() . ': ' . $error_msg, 'error' );
			$this->log( 'API Response: ' . wp_remote_retrieve_body( $response ), 'error' );
			wc_add_notice( $error_msg, 'error' );
			return;
		}

		$payment_url = isset( $body_response['data']['paymentUrl'] ) ? $body_response['data']['paymentUrl'] : '';
		$payment_ref = isset( $body_response['data']['paymentRef'] ) ? $body_response['data']['paymentRef'] : '';

		if ( $payment_url ) {
			// Store payment ref and URL in order meta for later verification
			$order->update_meta_data( '_ocpay_payment_ref', $payment_ref );
			$order->update_meta_data( '_ocpay_payment_url', $payment_url );
			
			// Set order to pending payment status
			if ( ! $order->has_status( 'pending' ) ) {
				$order->update_status( 'pending', __( 'Awaiting payment via OCPay.', 'ocpay-gateway' ) );
			}
			
			$order->save();

			$this->log( 'Payment link created successfully for Order #' . $order->get_order_number() . ' - Ref: ' . $payment_ref );

			return array(
				'result'   => 'success',
				'redirect' => $payment_url,
			);
		} else {
			$this->log( 'Invalid response from payment gateway for Order #' . $order->get_order_number(), 'error' );
			wc_add_notice( __( 'Invalid response from payment gateway.', 'ocpay-gateway' ), 'error' );
			return;
		}
	}

	/**
	 * Webhook/Return Handler.
	 * Although OCPay redirects back, we might want to double check status here or via a specific endpoint.
	 * For now, we rely on the return page check or a separate webhook if configured.
	 * Since the user asked for "hooks that would validate the payment on return page", 
	 * we will implement that logic in `thankyou_page` or a specific action.
	 */
	public function webhook() {
		// Placeholder for webhook handling if OCPay sends server-to-server notifications
	}

	/**
	 * Verify payment on Thank You page.
	 */
	public function thankyou_page( $order_id ) {
		$this->log( '[thankyou_page] Checking order #' . $order_id );
		$this->check_payment_status( $order_id );
	}

	/**
	 * Check single order status (Frontend - Customer viewing order).
	 * 
	 * @param int $order_id Order ID.
	 */
	public function check_order_status_frontend( $order_id ) {
		$this->log( '[check_order_status_frontend] Checking order #' . $order_id );
		$order = wc_get_order( $order_id );
		
		if ( $order && $order->get_payment_method() === $this->id && $order->has_status( array( 'pending' ) ) ) {
			$this->check_payment_status( $order_id );
		}
	}

	/**
	 * Check single order status (Admin viewing order).
	 *
	 * @param int|WC_Order $order_id Order ID or Object.
	 */
	public function check_order_status_admin( $order_id ) {
		if ( is_object( $order_id ) ) {
			$order = $order_id;
		} else {
			$order = wc_get_order( $order_id );
		}

		if ( $order && $order->get_payment_method() === $this->id && $order->has_status( array( 'pending' ) ) ) {
			$this->log( '[check_order_status_admin] Checking order #' . $order->get_order_number() );
			$this->check_payment_status( $order->get_id() );
		}
	}

	/**
	 * Check pending orders list (Frontend - Customer orders page).
	 */
	public function check_pending_orders_list() {
		$this->log( '[check_pending_orders_list] Checking customer pending orders' );
		
		$customer_orders = wc_get_orders( array(
			'customer_id' => get_current_user_id(),
			'payment_method' => $this->id,
			'status' => array( 'pending' ),
			'limit' => 10,
		) );

		foreach ( $customer_orders as $order ) {
			$this->check_payment_status( $order->get_id() );
		}
	}

	/**
	 * Check pending orders list (Admin orders page).
	 * Works with both traditional and HPOS order screens.
	 */
	public function check_pending_orders_list_admin() {
		$this->log( '[check_pending_orders_list_admin] Checking admin pending orders' );

		$pending_orders = wc_get_orders( array(
			'payment_method' => $this->id,
			'status' => array( 'pending' ),
			'limit' => 20,
			'orderby' => 'date',
			'order' => 'DESC',
		) );

		foreach ( $pending_orders as $order ) {
			$this->check_payment_status( $order->get_id() );
		}
	}

	/**
	 * Check Payment Status via API.
	 * Core function that makes the actual API call.
	 *
	 * @param int $order_id Order ID.
	 */
	private function check_payment_status( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Don't check status for already processed orders
		if ( $order->has_status( array( 'processing', 'completed', 'on-hold', 'cancelled' ) ) ) {
			return;
		}

		$payment_ref = $order->get_meta( '_ocpay_payment_ref' );
		if ( ! $payment_ref ) {
			return;
		}

		$api_key = ( 'production' === $this->environment ) ? $this->prod_key : $this->sandbox_key;
		$api_url = 'https://api.oneclickdz.com/v3/ocpay/checkPayment/' . $payment_ref;

		$response = wp_remote_get( $api_url, array(
			'headers' => array(
				'X-Access-Token' => $api_key,
			),
			'timeout' => 45,
		) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API Error checking payment for Order #' . $order->get_order_number() . ': ' . $response->get_error_message(), 'error' );
			return;
		}

		$body_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body_response['success'] ) && $body_response['success'] ) {
			$status = isset( $body_response['data']['status'] ) ? $body_response['data']['status'] : '';
			
			// Store last known status
			$order->update_meta_data( '_ocpay_last_status', $status );
			$order->save_meta_data();
			
			if ( 'CONFIRMED' === $status ) {
				$this->log( 'Payment confirmed for Order #' . $order->get_order_number() . ' - Ref: ' . $payment_ref );
				$order->payment_complete();
				$order->update_status( $this->order_status, __( 'Payment confirmed via OCPay API.', 'ocpay-gateway' ) );
			} elseif ( 'FAILED' === $status ) {
				$this->log( 'Payment failed for Order #' . $order->get_order_number() . ' - Ref: ' . $payment_ref, 'error' );
				$order->update_status( 'failed', __( 'Payment failed via OCPay API.', 'ocpay-gateway' ) );
			}
		}
	}

	/**
	 * Log a message.
	 *
	 * @param string $message Log message.
	 * @param string $type Log type (info, error).
	 */
	private function log( $message, $type = 'info' ) {
		if ( 'yes' !== $this->enable_logging ) {
			return;
		}

		$logs = get_option( 'ocpay_logs', array() );
		
		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'type'      => $type,
			'message'   => $message,
		);

		// Keep only last 200 logs
		if ( count( $logs ) > 200 ) {
			$logs = array_slice( $logs, -200 );
		}

		update_option( 'ocpay_logs', $logs );
	}

	/**
	 * Get recent logs HTML for settings page.
	 *
	 * @return string
	 */
	private function get_recent_logs_html() {
		$logs = get_option( 'ocpay_logs', array() );
		
		if ( empty( $logs ) ) {
			return '<p>' . __( 'No logs available yet.', 'ocpay-gateway' ) . '</p>';
		}

		$html = '<div style="height: 400px; overflow: auto; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
		$html .= '<table style="width: 100%; font-size: 12px; border-collapse: collapse;">';
		$html .= '<thead><tr><th style="text-align: left; padding: 8px; background: #f0f0f0;">Time</th><th style="text-align: left; padding: 8px; background: #f0f0f0;">Type</th><th style="text-align: left; padding: 8px; background: #f0f0f0;">Message</th></tr></thead>';
		$html .= '<tbody>';
		
		// Show most recent first
		$logs = array_reverse( $logs );
		
		foreach ( $logs as $log ) {
			$type_color = $log['type'] === 'error' ? '#d63638' : '#2271b1';
			$html .= '<tr>';
			$html .= '<td style="padding: 8px; white-space: nowrap;">' . esc_html( $log['timestamp'] ) . '</td>';
			$html .= '<td style="padding: 8px; color: ' . $type_color . '; font-weight: bold; white-space: nowrap;">' . esc_html( strtoupper( $log['type'] ) ) . '</td>';
			$html .= '<td style="padding: 8px; word-wrap: break-word;">' . esc_html( $log['message'] ) . '</td>';
			$html .= '</tr>';
		}
		
		$html .= '</tbody></table></div>';
		$clear_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ocpay&clear_logs=1' );
		$html .= '<p style="margin-top: 10px;"><a href="' . esc_url( $clear_url ) . '" class="button" onclick="return confirm(&quot;Clear all logs?&quot;);">Clear Logs</a></p>';

		// Handle clear logs
		if ( isset( $_GET['clear_logs'] ) && $_GET['clear_logs'] == '1' ) {
			delete_option( 'ocpay_logs' );
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ocpay' ) );
			exit;
		}

		return $html;
	}
}
