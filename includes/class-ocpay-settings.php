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
		
		// AJAX handlers
		add_action( 'wp_ajax_ocpay_run_health_check', array( $this, 'ajax_run_health_check' ) );
		add_action( 'wp_ajax_ocpay_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_ocpay_test_cron', array( $this, 'ajax_test_cron' ) );
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
		
		// Get health checks and stats
		$health_checks = $this->run_health_checks();
		$stats = $this->get_system_stats();
		$cron_status = $this->get_cron_status();
		?>
		<div class="ocpay-grid">
			<!-- System Health Card -->
			<div class="ocpay-card ocpay-health-card">
				<h2><?php esc_html_e( 'System Health', 'ocpay-woocommerce' ); ?></h2>
				<div class="ocpay-health-summary">
					<?php
					$passed = array_filter( $health_checks, function( $check ) {
						return true === $check['status'];
					} );
					$total  = count( $health_checks );
					$score  = $total > 0 ? ( count( $passed ) / $total ) * 100 : 0;
					$score_class = $score >= 80 ? 'healthy' : ( $score >= 50 ? 'warning' : 'critical' );
					?>
					<div class="ocpay-health-score <?php echo esc_attr( $score_class ); ?>">
						<span class="score"><?php echo round( $score ); ?>%</span>
						<span class="label"><?php esc_html_e( 'Health Score', 'ocpay-woocommerce' ); ?></span>
					</div>
				</div>

				<div class="ocpay-health-checks">
					<?php foreach ( $health_checks as $key => $check ) : ?>
						<div class="health-check <?php echo $check['status'] ? 'passed' : 'failed'; ?>">
							<span class="status-icon"><?php echo $check['status'] ? '✓' : '✕'; ?></span>
							<span class="check-label"><?php echo esc_html( $check['label'] ); ?></span>
							<?php if ( ! empty( $check['message'] ) ) : ?>
								<span class="check-message"><?php echo esc_html( $check['message'] ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" class="button button-primary" id="ocpay-run-health-check" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
					<?php esc_html_e( 'Refresh Health Check', 'ocpay-woocommerce' ); ?>
				</button>
			</div>

			<!-- Payment Statistics Card -->
			<div class="ocpay-card ocpay-stats-card">
				<h2><?php esc_html_e( 'Payment Statistics', 'ocpay-woocommerce' ); ?></h2>
				<div class="ocpay-stats-grid">
					<div class="stat-item">
						<span class="stat-value"><?php echo esc_html( $stats['pending_count'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Pending Orders', 'ocpay-woocommerce' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-value"><?php echo esc_html( $stats['recent_count'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Recent (< 30 min)', 'ocpay-woocommerce' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-value"><?php echo esc_html( $stats['stuck_count'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Stuck (> 1 hour)', 'ocpay-woocommerce' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-value"><?php echo esc_html( $stats['today_processed'] ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Processed Today', 'ocpay-woocommerce' ); ?></span>
					</div>
				</div>
				<button type="button" class="button" id="ocpay-refresh-stats" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
					<?php esc_html_e( 'Refresh Stats', 'ocpay-woocommerce' ); ?>
				</button>
			</div>

			<!-- Cron Jobs Status Card -->
			<div class="ocpay-card ocpay-cron-card">
				<h2><?php esc_html_e( 'Cron Jobs Status', 'ocpay-woocommerce' ); ?></h2>
				<div class="ocpay-cron-list">
					<?php foreach ( $cron_status as $key => $cron ) : ?>
						<div class="cron-item <?php echo $cron['active'] ? 'active' : 'inactive'; ?>">
							<div class="cron-header">
								<span class="cron-name"><?php echo esc_html( $cron['name'] ); ?></span>
								<span class="cron-status"><?php echo $cron['active'] ? '●' : '○'; ?></span>
							</div>
							<div class="cron-details">
								<span><?php esc_html_e( 'Interval:', 'ocpay-woocommerce' ); ?> <?php echo esc_html( $cron['interval'] ); ?></span>
								<?php if ( $cron['active'] ) : ?>
									<span><?php esc_html_e( 'Next run:', 'ocpay-woocommerce' ); ?> <?php echo esc_html( $cron['next_run'] ); ?></span>
									<span><?php esc_html_e( 'Last run:', 'ocpay-woocommerce' ); ?> <?php echo esc_html( $cron['last_run'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-primary" id="ocpay-test-cron" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
					<?php esc_html_e( 'Test Cron Jobs Now', 'ocpay-woocommerce' ); ?>
				</button>
			</div>

			<!-- Quick Actions Card -->
			<div class="ocpay-card">
				<h2><?php esc_html_e( 'Quick Actions', 'ocpay-woocommerce' ); ?></h2>
				<p>
					<button type="button" class="button button-primary" id="ocpay-manual-check" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Check Pending Orders Now', 'ocpay-woocommerce' ); ?>
					</button>
					<span class="spinner" id="ocpay-check-spinner" style="float: none; margin: 0 10px; visibility: hidden;"></span>
				</p>
				<div id="ocpay-check-result" style="margin-top: 15px;"></div>
				
				<hr style="margin: 20px 0;">
				
				<p>
					<button type="button" class="button button-secondary" id="ocpay-clear-logs" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Clear Activity Logs', 'ocpay-woocommerce' ); ?>
					</button>
				</p>
			</div>

			<!-- Activity Logs Card -->
			<div class="ocpay-card ocpay-full-width">
				<h2><?php esc_html_e( 'Activity Logs', 'ocpay-woocommerce' ); ?></h2>
				<textarea id="ocpay-logs-textarea" readonly style="width: 100%; height: 300px; font-family: 'Courier New', monospace; font-size: 12px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background-color: #f5f5f5; color: #333;">
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
		$js_url = OCPAY_WOOCOMMERCE_URL . 'assets/js/admin.js';
		
		// Check if files exist before enqueuing
		if ( file_exists( OCPAY_WOOCOMMERCE_PATH . 'assets/css/admin.css' ) ) {
			wp_enqueue_style(
				'ocpay-admin',
				$css_url,
				array(),
				OCPAY_WOOCOMMERCE_VERSION
			);
		}

		if ( file_exists( OCPAY_WOOCOMMERCE_PATH . 'assets/js/admin.js' ) ) {
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
	 * Run health checks
	 *
	 * @return array
	 */
	private function run_health_checks() {
		$checks = array();

		// Check if WooCommerce is active
		$checks['woocommerce'] = array(
			'label'   => __( 'WooCommerce Active', 'ocpay-woocommerce' ),
			'status'  => class_exists( 'WooCommerce' ),
			'message' => class_exists( 'WooCommerce' ) ? '' : __( 'WooCommerce not found', 'ocpay-woocommerce' ),
		);

		// Check if gateway is enabled
		$gateway = null;
		if ( class_exists( 'WooCommerce' ) ) {
			$gateways = WC()->payment_gateways->payment_gateways();
			$gateway  = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
		}
		
		$checks['gateway_enabled'] = array(
			'label'   => __( 'Gateway Enabled', 'ocpay-woocommerce' ),
			'status'  => $gateway && 'yes' === $gateway->enabled,
			'message' => ( ! $gateway || 'yes' !== $gateway->enabled ) ? __( 'OCPay gateway is disabled', 'ocpay-woocommerce' ) : '',
		);

		// Check API key configuration
		$api_mode = $gateway ? $gateway->get_option( 'api_mode', 'sandbox' ) : 'sandbox';
		$api_key  = $gateway ? ( 'live' === $api_mode ? $gateway->get_option( 'api_key_live' ) : $gateway->get_option( 'api_key_sandbox' ) ) : '';
		
		$checks['api_key'] = array(
			'label'   => __( 'API Key Configured', 'ocpay-woocommerce' ),
			'status'  => ! empty( $api_key ),
			'message' => empty( $api_key ) ? __( 'API key not configured', 'ocpay-woocommerce' ) : '',
		);

		// Check cron functionality
		$cron_working = $this->test_cron_functionality();
		$checks['cron'] = array(
			'label'   => __( 'WordPress Cron Working', 'ocpay-woocommerce' ),
			'status'  => $cron_working,
			'message' => ! $cron_working ? __( 'WordPress cron may not be working properly', 'ocpay-woocommerce' ) : '',
		);

		// Check if main cron is scheduled
		$main_cron_scheduled = wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' );
		$checks['main_cron_scheduled'] = array(
			'label'   => __( 'Main Cron Scheduled', 'ocpay-woocommerce' ),
			'status'  => false !== $main_cron_scheduled,
			'message' => false === $main_cron_scheduled ? __( 'Main cron job not scheduled', 'ocpay-woocommerce' ) : '',
		);

		// Check if recent orders cron is scheduled
		$recent_cron_scheduled = wp_next_scheduled( 'ocpay_check_recent_orders' );
		$checks['recent_cron_scheduled'] = array(
			'label'   => __( 'Recent Orders Cron Scheduled', 'ocpay-woocommerce' ),
			'status'  => false !== $recent_cron_scheduled,
			'message' => false === $recent_cron_scheduled ? __( 'Recent orders cron not scheduled', 'ocpay-woocommerce' ) : '',
		);

		// Check SSL
		$checks['ssl'] = array(
			'label'   => __( 'SSL Enabled', 'ocpay-woocommerce' ),
			'status'  => is_ssl(),
			'message' => ! is_ssl() ? __( 'SSL not enabled - recommended for live mode', 'ocpay-woocommerce' ) : '',
		);

		return $checks;
	}

	/**
	 * Get system statistics
	 *
	 * @return array
	 */
	private function get_system_stats() {
		$stats = array(
			'pending_count'   => 0,
			'recent_count'    => 0,
			'stuck_count'     => 0,
			'today_processed' => 0,
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return $stats;
		}

		try {
			// Pending orders
			$pending = $this->get_orders_by_criteria( array( 'status' => 'pending' ) );
			$stats['pending_count'] = count( $pending );
			
			// Recent orders (< 30 min)
			$recent = $this->get_orders_by_criteria( array(
				'status'       => 'pending',
				'date_created' => '>=' . gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) ),
			) );
			$stats['recent_count'] = count( $recent );
			
			// Stuck orders (> 1 hour)
			$stuck = $this->get_orders_by_criteria( array(
				'status'       => 'pending',
				'date_created' => '<' . gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
			) );
			$stats['stuck_count'] = count( $stuck );
			
			// Today processed
			$processed = $this->get_orders_by_criteria( array(
				'status'       => array( 'processing', 'completed' ),
				'date_created' => '>=' . gmdate( 'Y-m-d 00:00:00' ),
			) );
			$stats['today_processed'] = count( $processed );
		} catch ( Exception $e ) {
			// Return zeros on error
		}

		return $stats;
	}

	/**
	 * Get orders by criteria
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_orders_by_criteria( $args ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$default_args = array(
			'limit'          => -1,
			'payment_method' => 'ocpay',
			'meta_query'     => array(
				array(
					'key'     => '_ocpay_payment_ref',
					'compare' => 'EXISTS',
				),
			),
			'return'         => 'ids',
		);

		$args = wp_parse_args( $args, $default_args );

		try {
			if ( function_exists( 'wc_get_orders' ) ) {
				return wc_get_orders( $args );
			} elseif ( class_exists( 'WC_Order_Query' ) ) {
				$query = new WC_Order_Query( $args );
				return $query->get_orders();
			}
		} catch ( Exception $e ) {
			return array();
		}

		return array();
	}

	/**
	 * Get cron jobs status
	 *
	 * @return array
	 */
	private function get_cron_status() {
		$crons = array(
			'main' => array(
				'name'     => __( 'Main Status Check', 'ocpay-woocommerce' ),
				'hook'     => 'wp_scheduled_event_ocpay_check_payment_status',
				'interval' => __( 'Every 20 minutes', 'ocpay-woocommerce' ),
			),
			'recent' => array(
				'name'     => __( 'Recent Orders Check', 'ocpay-woocommerce' ),
				'hook'     => 'ocpay_check_recent_orders',
				'interval' => __( 'Every 5 minutes', 'ocpay-woocommerce' ),
			),
			'stuck' => array(
				'name'     => __( 'Stuck Orders Check', 'ocpay-woocommerce' ),
				'hook'     => 'ocpay_check_stuck_orders',
				'interval' => __( 'Every 30 minutes', 'ocpay-woocommerce' ),
			),
		);

		$status = array();

		foreach ( $crons as $key => $cron ) {
			$next_run = wp_next_scheduled( $cron['hook'] );
			$current_time = current_time( 'timestamp' );
			
			$status[ $key ] = array(
				'name'     => $cron['name'],
				'interval' => $cron['interval'],
				'active'   => false !== $next_run,
				'next_run' => $next_run ? ( $next_run > $current_time ? __( 'in', 'ocpay-woocommerce' ) . ' ' . human_time_diff( $current_time, $next_run ) : human_time_diff( $next_run, $current_time ) . ' ' . __( 'ago', 'ocpay-woocommerce' ) ) : __( 'N/A', 'ocpay-woocommerce' ),
				'last_run' => $this->get_last_cron_run( $cron['hook'] ),
			);
		}

		return $status;
	}

	/**
	 * Get last cron run time
	 *
	 * @param string $hook Cron hook.
	 * @return string
	 */
	private function get_last_cron_run( $hook ) {
		$last_run = get_option( 'ocpay_last_cron_run_' . $hook );
		
		if ( ! $last_run ) {
			return __( 'Never', 'ocpay-woocommerce' );
		}

		return human_time_diff( strtotime( $last_run ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ocpay-woocommerce' );
	}

	/**
	 * Test cron functionality
	 *
	 * @return bool
	 */
	private function test_cron_functionality() {
		// Check if DISABLE_WP_CRON is defined
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			// Cron is disabled but might be handled externally
			return true;
		}

		// Check if any cron jobs are scheduled
		$crons = _get_cron_array();
		return ! empty( $crons );
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

	/**
	 * Run health check via AJAX
	 */
	public function ajax_run_health_check() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'ocpay-woocommerce' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'ocpay-woocommerce' ) ) );
		}

		$checks = $this->run_health_checks();
		$passed = 0;
		$total  = count( $checks );

		foreach ( $checks as $check ) {
			if ( $check['status'] ) {
				$passed++;
			}
		}

		wp_send_json_success( array(
			'checks' => $checks,
			'passed' => $passed,
			'total'  => $total,
		) );
	}

	/**
	 * Get stats via AJAX
	 */
	public function ajax_get_stats() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'ocpay-woocommerce' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'ocpay-woocommerce' ) ) );
		}

		$stats = $this->get_system_stats();
		wp_send_json_success( $stats );
	}

	/**
	 * Test cron via AJAX
	 */
	public function ajax_test_cron() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'ocpay-woocommerce' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'ocpay-woocommerce' ) ) );
		}

		$cron_status = $this->get_cron_status();
		wp_send_json_success( $cron_status );
	}
}

// Initialize settings on plugins_loaded
add_action( 'plugins_loaded', function() {
	OCPay_Settings::get_instance();
} );
