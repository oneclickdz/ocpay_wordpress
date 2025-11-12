<?php
/**
 * OCPay Logger
 *
 * @package OCPay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * OCPay_Logger class
 *
 * Handles logging for OCPay operations
 */
class OCPay_Logger {

	/**
	 * Logger instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Debug mode
	 *
	 * @var bool
	 */
	private $debug_mode;

	/**
	 * Get logger instance (Singleton)
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
		$this->log_file   = WP_CONTENT_DIR . '/ocpay-woocommerce-logs.txt';
		$this->debug_mode = get_option( 'ocpay_debug_mode', false );
	}

	/**
	 * Log message
	 *
	 * @param string $level Log level (info, warning, error, debug).
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 *
	 * @return void
	 */
	private function log( $level, $message, $context = array() ) {
		// Skip debug logs if debug mode is off
		if ( 'debug' === $level && ! $this->debug_mode ) {
			return;
		}

		// Prepare log entry
		$timestamp = current_time( 'mysql' );
		$log_entry = sprintf(
			"[%s] %s: %s",
			$timestamp,
			strtoupper( $level ),
			$message
		);

		// Add context if available
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		$log_entry .= "\n";

		// Write to log file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_operations_fopen
		$handle = @fopen( $this->log_file, 'a' );
		if ( $handle ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_operations_fwrite
			fwrite( $handle, $log_entry );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_operations_fclose
			fclose( $handle );
		}

		// Also log to WordPress error log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'OCPay: ' . $log_entry );
		}
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 *
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 *
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 *
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 *
	 * @return void
	 */
	public function debug( $message, $context = array() ) {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Get log file path
	 *
	 * @return string
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * Get log contents
	 *
	 * @return string
	 */
	public function get_logs() {
		if ( file_exists( $this->log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return file_get_contents( $this->log_file );
		}
		return '';
	}

	/**
	 * Clear logs
	 *
	 * @return bool
	 */
	public function clear_logs() {
		if ( file_exists( $this->log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_operations_unlink
			return @unlink( $this->log_file );
		}
		return true;
	}

	/**
	 * Enable debug mode
	 *
	 * @return void
	 */
	public function enable_debug() {
		$this->debug_mode = true;
		update_option( 'ocpay_debug_mode', true );
	}

	/**
	 * Disable debug mode
	 *
	 * @return void
	 */
	public function disable_debug() {
		$this->debug_mode = false;
		update_option( 'ocpay_debug_mode', false );
	}
}
