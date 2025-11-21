<?php
/**
 * Plugin Name: OCPay for WooCommerce
 * Plugin URI: https://oneclickdz.com
 * Description: Accept secure payments via OCPay powered by SATIM bank-grade security.
 * Version: 1.2.1
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
	define( 'OCPAY_WOOCOMMERCE_VERSION', '1.2.1' );
}
if ( ! defined( 'OCPAY_WOOCOMMERCE_BASENAME' ) ) {
	define( 'OCPAY_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );
}

// Register custom cron schedules EARLY so activation scheduling works
add_filter( 'cron_schedules', 'ocpay_register_cron_schedule', 5 );
function ocpay_register_cron_schedule( $schedules ) {
	// Fast check for recent orders (every 5 minutes)
	if ( ! isset( $schedules['ocpay_every_5_minutes'] ) ) {
		$schedules['ocpay_every_5_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Every 5 Minutes', 'ocpay-woocommerce' ),
		);
	}
	
	// Standard check for all pending orders (every 20 minutes)
	if ( ! isset( $schedules['ocpay_every_20_minutes'] ) ) {
		$schedules['ocpay_every_20_minutes'] = array(
			'interval' => 20 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Every 20 Minutes', 'ocpay-woocommerce' ),
		);
	}
	
	// Check for stuck orders (every 30 minutes)
	if ( ! isset( $schedules['ocpay_every_30_minutes'] ) ) {
		$schedules['ocpay_every_30_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Every 30 Minutes', 'ocpay-woocommerce' ),
		);
	}
	
	return $schedules;
}

// Ensure cron events exist (helps if schedule failed on activation previously)
add_action( 'plugins_loaded', 'ocpay_ensure_cron_events', 15 );
function ocpay_ensure_cron_events() {
	// Main check every 20 minutes
	if ( ! wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' ) ) {
		wp_schedule_event( time() + 60, 'ocpay_every_20_minutes', 'wp_scheduled_event_ocpay_check_payment_status' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OCPay: Scheduled main payment status cron event (20 min)' );
		}
	}
	
	// Recent orders check every 5 minutes
	if ( ! wp_next_scheduled( 'ocpay_check_recent_orders' ) ) {
		wp_schedule_event( time() + 120, 'ocpay_every_5_minutes', 'ocpay_check_recent_orders' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OCPay: Scheduled recent orders cron event (5 min)' );
		}
	}
	
	// Stuck orders check every 30 minutes
	if ( ! wp_next_scheduled( 'ocpay_check_stuck_orders' ) ) {
		wp_schedule_event( time() + 180, 'ocpay_every_30_minutes', 'ocpay_check_stuck_orders' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OCPay: Scheduled stuck orders cron event (30 min)' );
		}
	}
}

// Declare HPOS compatibility early
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}, 0 );

// Register activation/deactivation hooks
register_activation_hook( __FILE__, function() {
	// Force cron schedule registration before scheduling
	add_filter( 'cron_schedules', 'ocpay_register_cron_schedule', 5 );
	
	// Schedule all cron events
	if ( ! wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' ) ) {
		wp_schedule_event( time() + 60, 'ocpay_every_20_minutes', 'wp_scheduled_event_ocpay_check_payment_status' );
	}
	
	if ( ! wp_next_scheduled( 'ocpay_check_recent_orders' ) ) {
		wp_schedule_event( time() + 120, 'ocpay_every_5_minutes', 'ocpay_check_recent_orders' );
	}
	
	if ( ! wp_next_scheduled( 'ocpay_check_stuck_orders' ) ) {
		wp_schedule_event( time() + 180, 'ocpay_every_30_minutes', 'ocpay_check_stuck_orders' );
	}
	
	update_option( 'ocpay_woocommerce_version', OCPAY_WOOCOMMERCE_VERSION );
	update_option( 'ocpay_woocommerce_activated', current_time( 'mysql' ) );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
	// Clear all scheduled cron events
	wp_clear_scheduled_hook( 'wp_scheduled_event_ocpay_check_payment_status' );
	wp_clear_scheduled_hook( 'ocpay_check_recent_orders' );
	wp_clear_scheduled_hook( 'ocpay_check_stuck_orders' );
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
		// Initialize hooks early to register AJAX handlers
		$this->init_hooks();
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
		require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-status-checker.php';
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

		// Register AJAX handlers early on plugins_loaded (before woocommerce_loaded)
		add_action( 'plugins_loaded', array( $this, 'register_ajax_handlers' ), 5 );
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

		// Initialize status polling with 20-minute schedule
		add_action( 'wp_scheduled_event_ocpay_check_payment_status', array( $this, 'check_pending_payments' ) );

		// Register custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Register AJAX handlers
	 *
	 * @return void
	 */
	public function register_ajax_handlers() {
		// Include required files for AJAX handlers
		if ( ! class_exists( 'OCPay_Logger' ) ) {
			require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-logger.php';
		}
		if ( ! class_exists( 'OCPay_API_Client' ) ) {
			require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-validator.php';
			require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-api-client.php';
		}

		// AJAX handlers - register for both authenticated users (wp_ajax_) and non-authenticated (wp_ajax_nopriv_)
		// These handlers check permissions internally, so we register them for both
		add_action( 'wp_ajax_ocpay_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ocpay_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_ocpay_manual_check', array( $this, 'ajax_manual_check' ) );
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
		if ( ! isset( $schedules['ocpay_every_20_minutes'] ) ) {
			$schedules['ocpay_every_20_minutes'] = array(
				'interval' => 20 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 20 Minutes', 'ocpay-woocommerce' ),
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
		// Delegate to status checker
		if ( class_exists( 'OCPay_Status_Checker' ) ) {
			OCPay_Status_Checker::get_instance()->check_pending_payments();
		}
	}

	/**
	 * AJAX handler for test connection
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'ocpay-woocommerce' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'ocpay-woocommerce' ) );
		}

		// Get the gateway instance to access its settings
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( esc_html__( 'WooCommerce not loaded.', 'ocpay-woocommerce' ) );
		}

		$gateways = WC()->payment_gateways->payment_gateways();
		if ( ! isset( $gateways['ocpay'] ) ) {
			wp_send_json_error( esc_html__( 'OCPay gateway not found.', 'ocpay-woocommerce' ) );
		}

		$gateway = $gateways['ocpay'];
		$api_mode = $gateway->get_option( 'api_mode', 'sandbox' );
		$api_key = ( 'live' === $api_mode ) 
			? $gateway->get_option( 'api_key_live' )
			: $gateway->get_option( 'api_key_sandbox' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( esc_html__( 'API key not configured.', 'ocpay-woocommerce' ) );
		}

		// Get API client
		$api_client = new OCPay_API_Client( $api_key, $api_mode );

		$result = $api_client->test_connection();

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			// Provide more specific error messages for common issues
			if ( empty( $error_message ) || $error_message === 'Unknown error' ) {
				$error_message = esc_html__( 'API request failed. Please check your API key and try again.', 'ocpay-woocommerce' );
			}
			wp_send_json_error( $error_message );
		}

		// Success response
		$message = isset( $result['message'] ) ? $result['message'] : esc_html__( 'API connection successful!', 'ocpay-woocommerce' );
		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX handler for clearing logs
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'ocpay-woocommerce' ) );
		}

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
	 * AJAX handler for manual status check
	 *
	 * @return void
	 */
	public function ajax_manual_check() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ocpay_admin_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'ocpay-woocommerce' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'ocpay-woocommerce' ) );
		}

		// Load required classes if not already loaded
		if ( ! class_exists( 'OCPay_Status_Checker' ) ) {
			$status_checker_file = OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-status-checker.php';
			if ( file_exists( $status_checker_file ) ) {
				require_once $status_checker_file;
			}
		}

		// Trigger status check with robust error handling
		if ( class_exists( 'OCPay_Status_Checker' ) ) {
			try {
				$checker = OCPay_Status_Checker::get_instance();
				$checker->check_pending_payments();
				wp_send_json_success( array(
					'message' => esc_html__( 'Payment status check completed. Check logs for details.', 'ocpay-woocommerce' ),
				) );
			} catch ( \Throwable $e ) {
				// Log error if logger available
				if ( class_exists( 'OCPay_Logger' ) ) {
					OCPay_Logger::get_instance()->error( 'Manual status check fatal error', array( 'error' => $e->getMessage() ) );
				}
				wp_send_json_error( sprintf( '%s %s', esc_html__( 'Error during status check:', 'ocpay-woocommerce' ), esc_html( $e->getMessage() ) ) );
			}
		} else {
			wp_send_json_error( esc_html__( 'Status checker not available.', 'ocpay-woocommerce' ) );
		}
	}

	/**
	 * Plugin activation hook
	 *
	 * @return void
	 */
	public function activate() {
		// Schedule status polling cron job - every 20 minutes
		if ( ! wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' ) ) {
			wp_schedule_event(
				time(),
				'ocpay_every_20_minutes',
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

// Include base files that don't depend on WooCommerce
require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-logger.php';
require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-error-handler.php';
require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-validator.php';
require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-block-support.php';
require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-security.php';

// Include debug functions in development mode
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once OCPAY_WOOCOMMERCE_PATH . 'includes/debug-functions.php';
}

// Initialize the OCPay_WooCommerce class early on plugins_loaded to register AJAX handlers
add_action( 'plugins_loaded', function() {
	// Include the main class if not already included
	if ( ! class_exists( 'OCPay_WooCommerce' ) ) {
		// The class is defined inline in this file, so it's already available
	}
	
	// Instantiate the main plugin class
	if ( class_exists( 'OCPay_WooCommerce' ) ) {
		OCPay_WooCommerce::get_instance();
	}
}, 5 );

// Initialize the gateway after WooCommerce is fully loaded
add_action('woocommerce_loaded', 'init_ocpay_gateway');

// Register OCPay payment method with WooCommerce Blocks
add_action('woocommerce_blocks_payment_method_type_registration', function($payment_method_registry) {
    // Include the payment block class only when blocks are being loaded
    if (!class_exists('OCPay_Payment_Block')) {
        $payment_block_file = OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-block.php';
        if (file_exists($payment_block_file)) {
            require_once $payment_block_file;
        } else {
            error_log('OCPay: Payment block class file not found at ' . $payment_block_file);
            return;
        }
    }
    
    if (class_exists('OCPay_Payment_Block')) {
        try {
            $payment_method_registry->register(new OCPay_Payment_Block());
            error_log('OCPay: Successfully registered OCPay_Payment_Block with WooCommerce Blocks payment method registry');
        } catch (Exception $e) {
            error_log('OCPay: Error registering payment block - ' . $e->getMessage());
        }
    } else {
        error_log('OCPay: OCPay_Payment_Block class not found during registration');
    }
}, 10);

/**
 * Check and log OCPay gateway settings
 */
function ocpay_check_gateway_settings() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Get all gateways
    $gateways = WC()->payment_gateways->payment_gateways();
    
    // Check if our gateway exists
    if (isset($gateways['ocpay'])) {
        $gateway = $gateways['ocpay'];
        
        // Log settings
        error_log('OCPay Gateway Settings: ' . print_r([
            'enabled' => $gateway->enabled,
            'title' => $gateway->title,
            'description' => $gateway->description,
            'api_key_set' => !empty($gateway->get_option('api_key')),
            'api_mode' => $gateway->get_option('api_mode', 'not set'),
            'is_available' => $gateway->is_available() ? 'true' : 'false',
            'currency' => get_woocommerce_currency(),
            'is_checkout' => is_checkout() ? 'true' : 'false',
            'cart_initialized' => isset(WC()->cart) ? 'true' : 'false',
            'cart_total' => isset(WC()->cart) ? WC()->cart->get_total('edit') : 'Cart not initialized'
        ], true));
    } else {
        error_log('OCPay Gateway: Not found in registered gateways');
    }
}

// Add a debug action that can be triggered manually
add_action('admin_init', function() {
    if (current_user_can('manage_woocommerce') && isset($_GET['debug_ocpay'])) {
        ocpay_check_gateway_settings();
        wp_die('OCPay debug information has been logged. Check your debug.log file.');
    }
});

/**
 * Force OCPay gateway to be available for testing
 */
function ocpay_force_available_gateway($available_gateways) {
    // Only force in development/testing environment
    if (defined('WP_DEBUG') && WP_DEBUG && isset($available_gateways['ocpay'])) {
        // Force enable the gateway for testing
        $available_gateways['ocpay']->enabled = 'yes';
        // Log that we're forcing the gateway
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug('OCPay: Forcing gateway to be available for testing', array('source' => 'ocpay-woocommerce'));
        }
    }
    return $available_gateways;
}

/**
 * Ensure OCPay appears in available gateways even if not fully configured
 */
function ocpay_ensure_gateway_visibility($available_gateways) {
    // Get all registered gateways
    $all_gateways = WC()->payment_gateways->payment_gateways();
    
    // If OCPay is registered but not in available list, add it for testing
    if (isset($all_gateways['ocpay']) && !isset($available_gateways['ocpay'])) {
        $gateway = $all_gateways['ocpay'];
        
        // Override availability check for testing with block checkout
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $available_gateways['ocpay'] = $gateway;
            error_log('OCPay: Added gateway to available list for testing');
        }
    }
    
    return $available_gateways;
}

// Apply the filters
add_filter('woocommerce_available_payment_gateways', 'ocpay_ensure_gateway_visibility', 10, 1);

// Make sure the function is only declared once
if (!function_exists('ocpay_init_gateway')) {
function ocpay_init_gateway() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>OCPay for WooCommerce requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    
    // Include WooCommerce-dependent files
    require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-api-client.php';
    require_once OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-gateway.php';
    
    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'ocpay_add_gateway');
    
    // Add debug information
    add_action('woocommerce_init', function() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_logger')) {
            return;
        }
        
        $logger = wc_get_logger();
        $logger->debug('OCPay: WooCommerce init hook triggered', array('source' => 'ocpay-woocommerce'));
        
        // Log all registered gateways
        $gateways = WC()->payment_gateways()->payment_gateways();
        $logger->debug('OCPay: All registered gateways: ' . print_r(array_keys($gateways), true), array('source' => 'ocpay-woocommerce'));
        
        // Log available gateways
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $logger->debug('OCPay: Available gateways: ' . print_r(array_keys($available_gateways), true), array('source' => 'ocpay-woocommerce'));
        
        // Log if our gateway is in the list
        if (isset($available_gateways['ocpay'])) {
            $gateway = $available_gateways['ocpay'];
            $logger->debug(sprintf(
                'OCPay: Gateway is available. Enabled: %s, API Key: %s',
                $gateway->enabled === 'yes' ? 'Yes' : 'No',
                !empty($gateway->get_option('api_key')) ? 'Set' : 'Not Set'
            ), array('source' => 'ocpay-woocommerce'));
        } else {
            $logger->debug('OCPay: Gateway is NOT in available gateways', array('source' => 'ocpay-woocommerce'));
        }
    });
    
    // Add admin notice with gateway status
    add_action('admin_notices', function() {
        if (!current_user_can('manage_woocommerce') || !class_exists('WooCommerce')) {
            return;
        }
        
        $gateways = WC()->payment_gateways->payment_gateways();
        $class = 'notice notice-info';
        $message = 'OCPay Gateway Status: ';
        
        if (isset($gateways['ocpay'])) {
            $gateway = $gateways['ocpay'];
            $message .= sprintf(
                'Registered. Enabled: %s, API Key: %s',
                $gateway->enabled === 'yes' ? 'Yes' : 'No',
                !empty($gateway->get_option('api_key')) ? 'Set' : 'Not Set'
            );
            
            // Add link to gateway settings
            $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=ocpay');
            $message .= sprintf(' | <a href="%s">%s</a>', $settings_url, __('Configure', 'ocpay-woocommerce'));
        } else {
            $message .= 'Not registered in WooCommerce payment gateways.';
        }
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    });
}
}

/**
 * Add OCPay Gateway to WooCommerce
 *
 * @param array $gateways Array of payment gateways.
 * @return array Modified array of payment gateways.
 */
function ocpay_add_gateway($gateways) {
    // Debug information
    $debug_info = [
        'time' => current_time('mysql'),
        'current_filter' => current_filter(),
        'is_admin' => is_admin(),
        'gateways_before' => array_keys($gateways),
        'class_exists' => class_exists('OCPay_Payment_Gateway') ? 'yes' : 'no',
        'wc_loaded' => class_exists('WooCommerce') ? 'yes' : 'no',
        'wc_payment_gateways' => class_exists('WC_Payment_Gateways') ? 'yes' : 'no',
        'ocpay_in_gateways' => in_array('OCPay_Payment_Gateway', $gateways, true) ? 'yes' : 'no'
    ];
    
    // Log debug info
    error_log('OCPay: ocpay_add_gateway called. ' . print_r($debug_info, true));
    
    // Only add our gateway if it's not already present
    if (!in_array('OCPay_Payment_Gateway', $gateways, true)) {
        // Make sure the class is loaded
        if (!class_exists('OCPay_Payment_Gateway')) {
            $gateway_file = OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-gateway.php';
            error_log('OCPay: Attempting to load gateway class from: ' . $gateway_file);
            
            if (file_exists($gateway_file)) {
                require_once $gateway_file;
                error_log('OCPay: Successfully loaded payment gateway class in ocpay_add_gateway');
                
                // Double check if the class exists after requiring the file
                if (!class_exists('OCPay_Payment_Gateway')) {
                    error_log('OCPay ERROR: Class still does not exist after including the file');
                    return $gateways;
                }
            } else {
                error_log('OCPay ERROR: Gateway class file not found at ' . $gateway_file);
                return $gateways;
            }
        }
        
        // Add our gateway to the list
        $gateways[] = 'OCPay_Payment_Gateway';
        error_log('OCPay: Successfully added to payment gateways list. New gateways: ' . print_r($gateways, true));
    } else {
        error_log('OCPay: Gateway already exists in gateways list');
    }
    
    return $gateways;
}

/**
 * Initialize the OCPay gateway
 * 
 * This function is hooked into 'woocommerce_init' with a priority of 20 to ensure
 * WooCommerce is fully loaded before we try to register our gateway.
 */
function init_ocpay_gateway() {
    // Only proceed if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        error_log('OCPay: WooCommerce not active');
        add_action('admin_notices', 'ocpay_woocommerce_missing_notice');
        return;
    }
    
    // Include the gateway class file
    if (!class_exists('OCPay_Payment_Gateway')) {
        $gateway_file = OCPAY_WOOCOMMERCE_PATH . 'includes/class-ocpay-payment-gateway.php';
        if (file_exists($gateway_file)) {
            require_once $gateway_file;
            error_log('OCPay: Successfully loaded payment gateway class');
        } else {
            error_log('OCPay ERROR: Gateway class file not found at ' . $gateway_file);
            return;
        }
    }
    
    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'ocpay_add_gateway');
    error_log('OCPay: Added filter for woocommerce_payment_gateways');
    
    // Add debug info for admin users
    if (is_admin() || (defined('WP_DEBUG') && WP_DEBUG)) {
        add_action('woocommerce_after_checkout_form', function() {
            if (current_user_can('manage_woocommerce')) {
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                error_log('OCPay: Available gateways on checkout: ' . print_r(array_keys($available_gateways), true));
                
                $all_gateways = WC()->payment_gateways->payment_gateways();
                error_log('OCPay: All registered gateways: ' . print_r(array_keys($all_gateways), true));
                
                if (isset($all_gateways['ocpay'])) {
                    $ocpay = $all_gateways['ocpay'];
                    error_log('OCPay Gateway Status: ' . print_r([
                        'enabled' => $ocpay->enabled,
                        'title' => $ocpay->title,
                        'description' => $ocpay->description,
                        'api_key_set' => !empty($ocpay->get_option('api_key')),
                        'api_mode' => $ocpay->get_option('api_mode', 'not set')
                    ], true));
                }
            }
        });
    }
    
    // Include required files
    $files = array(
        'class-ocpay-logger.php',
        'class-ocpay-error-handler.php',
        'class-ocpay-validator.php',
        'class-ocpay-api-client.php',
        'class-ocpay-payment-gateway.php',
        'class-ocpay-settings.php',
        'class-ocpay-order-handler.php',
        'class-ocpay-status-checker.php'
    );
    
    foreach ($files as $file) {
        $path = OCPAY_WOOCOMMERCE_PATH . 'includes/' . $file;
        if (file_exists($path)) {
            require_once $path;
        } else {
            error_log('OCPay: Required file not found: ' . $path);
        }
    }
    
    // Initialize the main plugin class
    if (class_exists('OCPay_WooCommerce')) {
        OCPay_WooCommerce::get_instance();
        error_log('OCPay: Main plugin class initialized');
    } else {
        error_log('OCPay: Failed to initialize main plugin class');
    }
}

// Display admin notice if WooCommerce is not active
function ocpay_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('OCPay for WooCommerce requires WooCommerce to be installed and active.', 'ocpay-woocommerce'); ?></p>
    </div>
    <?php
}

// Register OCPay as a WooCommerce Blocks payment method
add_action('woocommerce_blocks_loaded', function() {
    error_log('OCPay: woocommerce_blocks_loaded action triggered');
    
    // Initialize blocks support via JavaScript registration and data localization
    if (class_exists('OCPay_Block_Support')) {
        OCPay_Block_Support::init();
        error_log('OCPay: WooCommerce Blocks support initialized (JavaScript + PHP integration)');
    }
    
    error_log('OCPay: WooCommerce Blocks environment ready');
}, 0);

// Add debug endpoint
add_action('init', function() {
    if (isset($_GET['debug_ocpay_status']) && current_user_can('manage_woocommerce')) {
        if (!class_exists('WooCommerce')) {
            wp_die('WooCommerce is not active');
        }
        
        $gateways = WC()->payment_gateways->payment_gateways();
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $ocpay_gateway = isset($gateways['ocpay']) ? $gateways['ocpay'] : null;
        
        if (!$ocpay_gateway) {
            wp_die('OCPay gateway not found in registered gateways');
        }
        
        $status = [
            'gateway_id' => $ocpay_gateway->id,
            'title' => $ocpay_gateway->title,
            'enabled' => $ocpay_gateway->enabled,
            'settings' => $ocpay_gateway->settings,
            'is_available' => $ocpay_gateway->is_available(),
            'available_gateways' => array_keys($available_gateways),
            'all_gateways' => array_keys($gateways),
            'blocks_supported' => class_exists('Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry'),
            'blocks_loaded' => did_action('woocommerce_blocks_loaded'),
            'current_currency' => get_woocommerce_currency(),
            'is_checkout' => is_checkout(),
            'is_admin' => is_admin(),
            'wc_loaded' => class_exists('WooCommerce'),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not defined',
            'php_version' => phpversion(),
            'wordpress_version' => get_bloginfo('version'),
            'is_ssl' => is_ssl(),
            'site_url' => get_site_url(),
            'home_url' => home_url(),
        ];
        
        wp_send_json($status);
    }
});

// Add REST API support for WooCommerce Blocks
add_action('rest_api_init', function() {
    // Allow unauthenticated access to payment gateways for blocks
    add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $object_type) {
        if ('read' === $context && ('payment_gateway' === $object_type || 'payment_gateways' === $object_type)) {
            return true;
        }
        return $permission;
    }, 10, 4);
});

// Add debug info to checkout page
add_action('woocommerce_after_checkout_form', function() {
    if (current_user_can('manage_woocommerce')) {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        echo '<!-- OCPay Debug: Available Gateways: ' . print_r(array_keys($available_gateways), true) . ' -->';
        
        if (isset($available_gateways['ocpay'])) {
            $gateway = $available_gateways['ocpay'];
            echo '<!-- OCPay Debug: ';
            echo 'Enabled: ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No') . ', ';
            echo 'API Key: ' . (!empty($gateway->get_option('api_key')) ? 'Set' : 'Not Set') . ', ';
            echo 'Available: ' . ($gateway->is_available() ? 'Yes' : 'No');
            echo ' -->';
        } else {
            echo '<!-- OCPay Debug: OCPay gateway not found in available gateways -->';
        }
    }
});
