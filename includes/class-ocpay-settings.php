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
	}

	/**
	 * Render dashboard
	 *
	 * @return void
	 */
	private function render_dashboard() {
		$logger = OCPay_Logger::get_instance();
		$api_key = get_option( 'woocommerce_ocpay_api_key' );
		$api_configured = ! empty( $api_key );
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
				<h2><?php esc_html_e( 'Activity Logs', 'ocpay-woocommerce' ); ?></h2>
				<p>
					<button type="button" class="button button-secondary" id="ocpay-clear-logs" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ocpay_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Clear Logs', 'ocpay-woocommerce' ); ?>
					</button>
				</p>
				<textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background-color: #f5f5f5;">
<?php echo esc_textarea( $logger->get_logs() ); ?>
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

		wp_enqueue_style(
			'ocpay-admin',
			OCPAY_WOOCOMMERCE_URL . 'assets/css/admin.css',
			array(),
			OCPAY_WOOCOMMERCE_VERSION
		);

		wp_enqueue_script(
			'ocpay-admin',
			OCPAY_WOOCOMMERCE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			OCPAY_WOOCOMMERCE_VERSION,
			true
		);
	}
}

// Initialize settings on plugins_loaded
add_action( 'plugins_loaded', function() {
	OCPay_Settings::get_instance();
} );
