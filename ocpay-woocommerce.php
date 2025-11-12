<?php
/**
 * Plugin Name: OCPay for WooCommerce
 * Plugin URI: https://oneclickdz.com
 * Description: Accept secure payments via OCPay powered by SATIM bank-grade security.
 * Version: 1.0.1
 * Author: OneClick DZ
 * Author URI: https://oneclickdz.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ocpay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 10.3.4
 * Requires Plugins: woocommerce
 * 
 * @package OCPay_WooCommerce
 * @author OneClick DZ
 * @license GPL v3 or later
 */

defined( 'ABSPATH' ) || exit;

// Define constants early
if ( ! defined( 'OCPAY_WOOCOMMERCE_PATH' ) ) {
	define( 'OCPAY_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'OCPAY_WOOCOMMERCE_URL' ) ) {
	define( 'OCPAY_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'OCPAY_WOOCOMMERCE_VERSION' ) ) {
	define( 'OCPAY_WOOCOMMERCE_VERSION', '1.0.1' );
}
if ( ! defined( 'OCPAY_WOOCOMMERCE_BASENAME' ) ) {
	define( 'OCPAY_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );
}

// Declare HPOS compatibility early
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}, 0 );

// Register activation/deactivation hooks
register_activation_hook( __FILE__, function() {
	// Schedule status polling cron job
	if ( ! wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' ) ) {
		wp_schedule_event(
			time(),
			'ocpay_every_5_minutes',
			'wp_scheduled_event_ocpay_check_payment_status'
		);
	}
	update_option( 'ocpay_woocommerce_version', OCPAY_WOOCOMMERCE_VERSION );
	update_option( 'ocpay_woocommerce_activated', current_time( 'mysql' ) );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
	wp_clear_scheduled_hook( 'wp_scheduled_event_ocpay_check_payment_status' );
	flush_rewrite_rules();
} );

/**
 * OCPay WooCommerce Plugin Main Class
 */
class OCPay_WooCommerce {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public static $version = '1.0.1';

	/**
	 * Plugin instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get plugin instance (Singleton)
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
		$this->define_constants();
		// Don't include files here - they're loaded on plugins_loaded
	}

	/**
	 * Define plugin constants
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'OCPAY_WOOCOMMERCE_PATH' ) ) {
			define( 'OCPAY_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
		}
		if ( ! defined( 'OCPAY_WOOCOMMERCE_URL' ) ) {
			define( 'OCPAY_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );
		}
		if ( ! defined( 'OCPAY_WOOCOMMERCE_VERSION' ) ) {
			define( 'OCPAY_WOOCOMMERCE_VERSION', self::$version );
		}
		if ( ! defined( 'OCPAY_WOOCOMMERCE_BASENAME' ) ) {
			define( 'OCPAY_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );
		}
	}

	/**
	 * Include required files
	 *
	 * @return void
	 */
	private function includes() {
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-logger.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-error-handler.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-validator.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-api-client.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-gateway.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-settings.php';
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-order-handler.php';
	}

	/**
	 * Initialize plugin hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		// Check dependencies on admin_init
		add_action( 'admin_init', array( $this, 'check_dependencies' ) );

		// Initialize on woocommerce_loaded to ensure WooCommerce is fully loaded
		add_action( 'woocommerce_loaded', array( $this, 'init_payment_gateway' ) );

		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Declare HPOS (High-Performance Order Storage) compatibility
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Initialize payment gateway after WooCommerce is loaded
	 *
	 * @return void
	 */
	public function init_payment_gateway() {
		// Include WooCommerce dependent files
		$this->includes();

		// Initialize status polling
		add_action( 'wp_scheduled_event_ocpay_check_payment_status', array( $this, 'check_pending_payments' ) );

		// Register custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ocpay_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ocpay_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Check if dependencies are installed
	 *
	 * @return void
	 */
	public function check_dependencies() {
		$notices = array();

		// Check if WooCommerce is installed and activated
		if ( ! class_exists( 'WooCommerce' ) ) {
			$notices[] = esc_html__( 'OCPay for WooCommerce requires WooCommerce to be installed and activated.', 'ocpay-woocommerce' );
		}

		// Check WooCommerce version (require 4.0 or higher)
		if ( class_exists( 'WooCommerce' ) ) {
			global $woocommerce;
			if ( isset( $woocommerce->version ) && version_compare( $woocommerce->version, '4.0', '<' ) ) {
				$notices[] = sprintf(
					esc_html__( 'OCPay for WooCommerce requires WooCommerce 4.0 or higher. You have version %s.', 'ocpay-woocommerce' ),
					$woocommerce->version
				);
			}
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
			$notices[] = esc_html__( 'OCPay for WooCommerce requires PHP 7.2 or higher.', 'ocpay-woocommerce' );
		}

		// Display admin notices
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo $notice; ?></p>
				</div>
				<?php
			}
			// Deactivate plugin if dependencies are not met
			deactivate_plugins( OCPAY_WOOCOMMERCE_BASENAME );
		}
	}

	/**
	 * Register OCPay as a payment gateway
	 *
	 * @param array $gateways Array of payment gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'OCPay_Payment_Gateway';
		return $gateways;
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ocpay-woocommerce',
			false,
			dirname( OCPAY_WOOCOMMERCE_BASENAME ) . '/languages'
		);
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Array of cron schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['ocpay_every_5_minutes'] ) ) {
			$schedules['ocpay_every_5_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 5 Minutes', 'ocpay-woocommerce' ),
			);
		}
		return $schedules;
	}

	/**
	 * Check pending payments status (called by cron)
	 *
	 * @return void
	 */
	public function check_pending_payments() {
		// This will be implemented in Phase 4
		// For now, just a placeholder
	}

	/**
	 * AJAX handler for test connection
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ocpay_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'ocpay-woocommerce' ) );
		}

		// Get API client
		$api_client = new OCPay_API_Client(
			get_option( 'woocommerce_ocpay_api_key' ),
			get_option( 'woocommerce_ocpay_api_mode' )
		);

		$result = $api_client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for clearing logs
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'ocpay_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'ocpay-woocommerce' ) );
		}

		$logger = OCPay_Logger::get_instance();
		$logger->clear_logs();

		wp_send_json_success( array(
			'message' => esc_html__( 'Logs cleared successfully.', 'ocpay-woocommerce' ),
		) );
	}

	/**
	 * Plugin activation hook
	 *
	 * @return void
	 */
	public function activate() {
		// Schedule status polling cron job
		if ( ! wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' ) ) {
			wp_schedule_event(
				time(),
				'ocpay_every_5_minutes',
				'wp_scheduled_event_ocpay_check_payment_status'
			);
		}

		// Create plugin version option
		update_option( 'ocpay_woocommerce_version', self::$version );
		update_option( 'ocpay_woocommerce_activated', current_time( 'mysql' ) );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook
	 *
	 * @return void
	 */
	public function deactivate() {
		// Unschedule cron job
		wp_clear_scheduled_hook( 'wp_scheduled_event_ocpay_check_payment_status' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Initialize plugin
 */

// Include files on plugins_loaded
add_action( 'plugins_loaded', function() {
	// Include all required files first
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-logger.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-error-handler.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-validator.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-api-client.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-gateway.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-settings.php';
	require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-order-handler.php';

	// Initialize main class
	OCPay_WooCommerce::get_instance();

	// Load text domain
	load_plugin_textdomain( 'ocpay-woocommerce', false, dirname( OCPAY_WOOCOMMERCE_BASENAME ) . '/languages' );
}, 5 );

// Register gateway on woocommerce_loaded when WooCommerce is ready
add_action( 'woocommerce_loaded', function() {
	add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
		$gateways[] = 'OCPay_Payment_Gateway';
		return $gateways;
	} );
}, 5 );
