<?php
/**
 * OCPay Settings Handler
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Settings class
 *
 * Handles plugin settings and configuration
 */
class OCPay_Settings {

	/**
	 * Settings instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get settings instance (Singleton)
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'OCPay Settings', 'ocpay-woocommerce' ),
			esc_html__( 'OCPay', 'ocpay-woocommerce' ),
			'manage_woocommerce',
			'ocpay-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Initialize settings
	 *
	 * @return void
	 */
	public function init_settings() {
		// Settings are managed via WooCommerce gateway settings
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ocpay-woocommerce' ) );
		}

		try {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'OCPay Settings', 'ocpay-woocommerce' ); ?></h1>
				
				<div class="notice notice-info">
					<p><?php esc_html_e( 'OCPay settings are configured in WooCommerce > Settings > Payments > OCPay', 'ocpay-woocommerce' ); ?></p>
				</div>

				<div id="ocpay-dashboard" class="ocpay-dashboard">
					<?php $this->render_dashboard(); ?>
				</div>
			</div>
			<?php
		} catch ( Exception $e ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'OCPay Settings', 'ocpay-woocommerce' ); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'An error occurred while loading the settings page. Please check your WordPress error log for details.', 'ocpay-woocommerce' ); ?></p>
					<p><?php echo esc_html( $e->getMessage() ); ?></p>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render dashboard
	 *
	 * @return void
	 */
	private function render_dashboard() {
		try {
			$logger = OCPay_Logger::get_instance();
		} catch ( Exception $e ) {
			$logger = null;
		}
		
		// Get the gateway instance to access its settings
		$api_configured = false;
		try {
			if ( class_exists( 'WooCommerce' ) ) {
				$gateways = WC()->payment_gateways->payment_gateways();
				if ( isset( $gateways['ocpay'] ) ) {
					$gateway = $gateways['ocpay'];
					$api_key_sandbox = $gateway->get_option( 'api_key_sandbox' );
					$api_key_live = $gateway->get_option( 'api_key_live' );
					$api_configured = ! empty( $api_key_sandbox ) || ! empty( $api_key_live );
				}
			}
		} catch ( Exception $e ) {
			$api_configured = false;
		}
		?>
		<div class="ocpay-grid">
			<div class="ocpay-card">
				<h2><?php esc_html_e( 'System Status', 'ocpay-woocommerce' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Plugin Version', 'ocpay-woocommerce' ); ?></th>
						<td><?php echo esc_html( OCPAY_WOOCOMMERCE_VERSION ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WooCommerce', 'ocpay-woocommerce' ); ?></th>
						<td>
							<?php 
								if ( class_exists( 'WooCommerce' ) ) {
									echo '<span class="ocpay-status-success">✓ ' . esc_html__( 'Active', 'ocpay-woocommerce' ) . '</span>';
								} else {
									echo '<span class="ocpay-status-error">✗ ' . esc_html__( 'Not Active', 'ocpay-woocommerce' ) . '</span>';
								}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'PHP Version', 'ocpay-woocommerce' ); ?></th>
						<td><?php echo esc_html( phpversion() ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'API Configured', 'ocpay-woocommerce' ); ?></th>
						<td>
							<?php 
								if ( $api_configured ) {
									echo '<span class="ocpay-status-success">✓ ' . esc_html__( 'Yes', 'ocpay-woocommerce' ) . '</span>';
								} else {
									echo '<span class="ocpay-status-error">✗ ' . esc_html__( 'No', 'ocpay-woocommerce' ) . '</span>';
								}
							?>
						</td>
					</tr>
				</table>
			</div>

			<div class="ocpay-card">
				<h2><?php esc_html_e( 'API Connection', 'ocpay-woocommerce' ); ?></h2>
				<?php if ( $api_configured ) : ?>
					<p><?php esc_html_e( 'Click the button below to test your API connection:', 'ocpay-woocommerce' ); ?></p>
					<button type="button" class="button button-primary" id="ocpay-test-connection" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Test Connection', 'ocpay-woocommerce' ); ?>
					</button>
					<div id="ocpay-connection-result" style="margin-top: 15px;"></div>
				<?php else : ?>
					<p class="ocpay-status-error">
						<?php esc_html_e( 'Please configure your API key in WooCommerce Settings > Payments > OCPay to test the connection.', 'ocpay-woocommerce' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="ocpay-card">
				<h2><?php esc_html_e( 'Order Status Polling', 'ocpay-woocommerce' ); ?></h2>
				<?php
				// Get cron status
				$next_event = wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' );

				// Safe pending orders lookup
				$pending_status = $this->get_pending_orders_status();
				$pending_count  = isset( $pending_status['count'] ) ? (int) $pending_status['count'] : 0;
				$pending_error  = isset( $pending_status['error'] ) ? $pending_status['error'] : '';
				?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Cron Status', 'ocpay-woocommerce' ); ?></th>
						<td>
							<?php 
								if ( $next_event ) {
									$time_until = $next_event - time();
									$minutes_until = round( $time_until / 60 );
									echo '<span class="ocpay-status-success">✓ ' . esc_html__( 'Scheduled', 'ocpay-woocommerce' ) . '</span>';
									echo '<br><small>' . sprintf( esc_html__( 'Next run in %d minutes', 'ocpay-woocommerce' ), abs( $minutes_until ) ) . '</small>';
								} else {
									echo '<span class="ocpay-status-error">✗ ' . esc_html__( 'Not Scheduled', 'ocpay-woocommerce' ) . '</span>';
									echo '<br><small>' . esc_html__( 'Deactivate and reactivate the plugin to reschedule', 'ocpay-woocommerce' ) . '</small>';
								}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Pending Orders', 'ocpay-woocommerce' ); ?></th>
						<td>
							<?php echo esc_html( $pending_count ); ?>
							<?php if ( $pending_error ) : ?>
								<br><small class="ocpay-status-error"><?php echo esc_html( $pending_error ); ?></small>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" class="button button-primary" id="ocpay-manual-check" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Check Pending Orders Now', 'ocpay-woocommerce' ); ?>
					</button>
					<span class="spinner" id="ocpay-check-spinner" style="float: none; margin: 0 10px; visibility: hidden;"></span>
				</p>
				<div id="ocpay-check-result" style="margin-top: 15px;"></div>
			</div>

			<div class="ocpay-card">
				<h2><?php esc_html_e( 'Activity Logs', 'ocpay-woocommerce' ); ?></h2>
				<p>
					<button type="button" class="button button-secondary" id="ocpay-clear-logs" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Clear Logs', 'ocpay-woocommerce' ); ?>
					</button>
				</p>
				<textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background-color: #f5f5f5;">
<?php 
	if ( $logger ) {
		echo esc_textarea( $logger->get_logs() );
	} else {
		esc_html_e( 'Logs are currently unavailable.', 'ocpay-woocommerce' );
	}
?>
				</textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_ocpay-settings' !== $screen->id ) {
			return;
		}

		$css_url = OCPAY_WOOCOMMERCE_URL . 'assets/css/admin.css';
		$js_url = OCPAY_WOOCOMMERCE_URL . 'assets/js/ocpay.js';
		
		// Enqueue admin CSS
		if ( file_exists( OCPAY_WOOCOMMERCE_PATH . 'assets/css/admin.css' ) ) {
			wp_enqueue_style(
				'ocpay-admin',
				$css_url,
				array(),
				OCPAY_WOOCOMMERCE_VERSION
			);
		}

		// Enqueue consolidated JS
		if ( file_exists( OCPAY_WOOCOMMERCE_PATH . 'assets/js/ocpay.js' ) ) {
			wp_enqueue_script(
				'ocpay-admin',
				$js_url,
				array( 'jquery' ),
				OCPAY_WOOCOMMERCE_VERSION,
				true
			);
		}
	}

	/**
	 * Safely get pending OCPay orders count with fallbacks and error reporting.
	 *
	 * @return array { 'count' => int, 'error' => string }
	 */
	private function get_pending_orders_status() {
		$logger = null;
		try {
			$logger = OCPay_Logger::get_instance();
		} catch ( \Throwable $e ) {
			// Ignore logger failure
		}

		$result = array( 'count' => 0 );

		// Bail early if WooCommerce not loaded
		if ( ! class_exists( 'WooCommerce' ) ) {
			$result['error'] = __( 'WooCommerce not loaded.', 'ocpay-woocommerce' );
			return $result;
		}

		try {
			// Preferred: wc_get_orders (HPOS compatible)
			if ( function_exists( 'wc_get_orders' ) ) {
				$args  = array(
					'limit'      => -1,
					'status'     => array( 'pending' ),
					'return'     => 'ids',
					'meta_query' => array(
						array(
							'key'     => '_ocpay_payment_ref',
							'compare' => 'EXISTS',
						),
					),
					'orderby'    => 'date',
					'order'      => 'DESC',
				);
				$orders = wc_get_orders( $args );
				$result['count'] = is_array( $orders ) ? count( $orders ) : 0;
			} elseif ( class_exists( 'WC_Order_Query' ) ) {
				// Fallback to WC_Order_Query
				$query_args = array(
					'limit'      => -1,
					'status'     => 'pending',
					'return'     => 'ids',
					'meta_query' => array(
						array(
							'key'     => '_ocpay_payment_ref',
							'compare' => 'EXISTS',
						),
					),
				);
				$query          = new WC_Order_Query( $query_args );
				$pending_orders = $query->get_posts();
				$result['count'] = is_array( $pending_orders ) ? count( $pending_orders ) : 0;
			} else {
				$result['error'] = __( 'Order query class unavailable.', 'ocpay-woocommerce' );
			}
		} catch ( \Throwable $e ) {
			$result['error'] = __( 'Could not load pending orders.', 'ocpay-woocommerce' );
			if ( $logger ) {
				$logger->error( 'Pending orders lookup failed', array( 'message' => $e->getMessage() ) );
			}
		}

		return $result;
	}
}

// Initialize settings on plugins_loaded
add_action( 'plugins_loaded', function() {
	OCPay_Settings::get_instance();
} );
