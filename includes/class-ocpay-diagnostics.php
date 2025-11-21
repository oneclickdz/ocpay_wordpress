<?php
/**
 * OCPay Diagnostics & Monitoring
 *
 * Provides health checks and monitoring for payment processing
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Diagnostics class
 *
 * Handles diagnostics and monitoring
 */
class OCPay_Diagnostics {

	/**
	 * Diagnostics instance
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
	 * Get diagnostics instance (Singleton)
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

		// Admin page hooks
		add_action( 'admin_menu', array( $this, 'add_diagnostics_page' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_ocpay_run_health_check', array( $this, 'ajax_run_health_check' ) );
		add_action( 'wp_ajax_ocpay_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_ocpay_test_cron', array( $this, 'ajax_test_cron' ) );
	}

	/**
	 * Add diagnostics page to admin menu
	 *
	 * @return void
	 */
	public function add_diagnostics_page() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'OCPay Diagnostics', 'ocpay-woocommerce' ),
			esc_html__( 'OCPay Diagnostics', 'ocpay-woocommerce' ),
			'manage_woocommerce',
			'ocpay-diagnostics',
			array( $this, 'render_diagnostics_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_ocpay-diagnostics' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ocpay-diagnostics',
			OCPAY_WOOCOMMERCE_URL . 'assets/css/admin.css',
			array(),
			OCPAY_WOOCOMMERCE_VERSION
		);

		wp_enqueue_script(
			'ocpay-diagnostics',
			OCPAY_WOOCOMMERCE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			OCPAY_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'ocpay-diagnostics',
			'ocpayDiagnostics',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ocpay_admin_nonce' ),
			)
		);
	}

	/**
	 * Render diagnostics page
	 *
	 * @return void
	 */
	public function render_diagnostics_page() {
		$health_checks = $this->run_health_checks();
		$stats         = $this->get_system_stats();
		$cron_status   = $this->get_cron_status();
		?>
		<div class="wrap ocpay-diagnostics">
			<h1><?php esc_html_e( 'OCPay Diagnostics & Monitoring', 'ocpay-woocommerce' ); ?></h1>

			<div class="ocpay-diagnostics-grid">
				<!-- Health Status Card -->
				<div class="ocpay-card ocpay-health-card">
					<h2><?php esc_html_e( 'System Health', 'ocpay-woocommerce' ); ?></h2>
					<div class="ocpay-health-summary">
						<?php
						$passed = array_filter( $health_checks, function( $check ) {
							return true === $check['status'];
						} );
						$total  = count( $health_checks );
						$score  = ( count( $passed ) / $total ) * 100;
						?>
						<div class="ocpay-health-score <?php echo $score >= 80 ? 'healthy' : ( $score >= 50 ? 'warning' : 'critical' ); ?>">
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

					<button type="button" class="button button-primary" id="ocpay-run-health-check">
						<?php esc_html_e( 'Run Health Check', 'ocpay-woocommerce' ); ?>
					</button>
				</div>

				<!-- Statistics Card -->
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
					<button type="button" class="button" id="ocpay-refresh-stats">
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
					<button type="button" class="button" id="ocpay-test-cron">
						<?php esc_html_e( 'Test Cron Jobs', 'ocpay-woocommerce' ); ?>
					</button>
				</div>

				<!-- System Information Card -->
				<div class="ocpay-card ocpay-info-card">
					<h2><?php esc_html_e( 'System Information', 'ocpay-woocommerce' ); ?></h2>
					<div class="system-info">
						<div class="info-item">
							<strong><?php esc_html_e( 'Plugin Version:', 'ocpay-woocommerce' ); ?></strong>
							<span><?php echo esc_html( OCPAY_WOOCOMMERCE_VERSION ); ?></span>
						</div>
						<div class="info-item">
							<strong><?php esc_html_e( 'WooCommerce Version:', 'ocpay-woocommerce' ); ?></strong>
							<span><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ); ?></span>
						</div>
						<div class="info-item">
							<strong><?php esc_html_e( 'WordPress Version:', 'ocpay-woocommerce' ); ?></strong>
							<span><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
						</div>
						<div class="info-item">
							<strong><?php esc_html_e( 'PHP Version:', 'ocpay-woocommerce' ); ?></strong>
							<span><?php echo esc_html( PHP_VERSION ); ?></span>
						</div>
						<div class="info-item">
							<strong><?php esc_html_e( 'Verification Method:', 'ocpay-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Multi-layered polling (JavaScript + Cron)', 'ocpay-woocommerce' ); ?></span>
						</div>
					</div>
					<p class="description">
						<?php esc_html_e( 'OCPay uses intelligent client-side polling and server-side cron jobs to verify payment status. This ensures reliable order processing even without webhook support.', 'ocpay-woocommerce' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
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
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway  = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
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

		// Check file permissions
		$log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
		$checks['log_permissions'] = array(
			'label'   => __( 'Log Directory Writable', 'ocpay-woocommerce' ),
			'status'  => is_writable( $log_dir ),
			'message' => ! is_writable( $log_dir ) ? __( 'Log directory not writable', 'ocpay-woocommerce' ) : '',
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
		// Pending orders
		$pending = $this->get_orders_by_criteria( array( 'status' => 'pending' ) );
		
		// Recent orders (< 30 min)
		$recent = $this->get_orders_by_criteria( array(
			'status'       => 'pending',
			'date_created' => '>=' . gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) ),
		) );
		
		// Stuck orders (> 1 hour)
		$stuck = $this->get_orders_by_criteria( array(
			'status'       => 'pending',
			'date_created' => '<' . gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
		) );
		
		// Today processed
		$processed = $this->get_orders_by_criteria( array(
			'status'       => array( 'processing', 'completed' ),
			'date_created' => '>=' . gmdate( 'Y-m-d 00:00:00' ),
		) );

		return array(
			'pending_count'    => count( $pending ),
			'recent_count'     => count( $recent ),
			'stuck_count'      => count( $stuck ),
			'today_processed'  => count( $processed ),
		);
	}

	/**
	 * Get orders by criteria
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_orders_by_criteria( $args ) {
		$default_args = array(
			'limit'          => -1,
			'payment_method' => 'ocpay',
			'meta_query'     => array(
				array(
					'key'     => '_ocpay_payment_ref',
					'compare' => 'EXISTS',
				),
			),
			'return'         => 'objects',
		);

		$args  = wp_parse_args( $args, $default_args );
		$query = new WC_Order_Query( $args );
		return $query->get_orders();
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
			
			$status[ $key ] = array(
				'name'     => $cron['name'],
				'interval' => $cron['interval'],
				'active'   => false !== $next_run,
				'next_run' => $next_run ? human_time_diff( $next_run, current_time( 'timestamp' ) ) : 'N/A',
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
	 * AJAX handler for health check
	 *
	 * @return void
	 */
	public function ajax_run_health_check() {
		check_ajax_referer( 'ocpay_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'ocpay-woocommerce' ) );
		}

		$health_checks = $this->run_health_checks();
		wp_send_json_success( $health_checks );
	}

	/**
	 * AJAX handler for getting stats
	 *
	 * @return void
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'ocpay_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'ocpay-woocommerce' ) );
		}

		$stats = $this->get_system_stats();
		wp_send_json_success( $stats );
	}

	/**
	 * AJAX handler for testing cron
	 *
	 * @return void
	 */
	public function ajax_test_cron() {
		check_ajax_referer( 'ocpay_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'ocpay-woocommerce' ) );
		}

		// Trigger cron jobs manually
		do_action( 'wp_scheduled_event_ocpay_check_payment_status' );
		do_action( 'ocpay_check_recent_orders' );
		do_action( 'ocpay_check_stuck_orders' );

		wp_send_json_success( array(
			'message' => __( 'Cron jobs triggered successfully', 'ocpay-woocommerce' ),
		) );
	}
}

// Initialize diagnostics
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) && is_admin() ) {
		OCPay_Diagnostics::get_instance();
	}
} );
