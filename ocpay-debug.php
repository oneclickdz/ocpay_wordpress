<?php
/**
 * OCPay Status Polling Debug Helper
 *
 * Place this file in WordPress root to test status polling
 * Access it via: http://yoursite.com/ocpay-debug.php?action=test_polling
 *
 * @package OCPay_WooCommerce
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// Verify user is admin
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( 'Unauthorized' );
}

$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

// Enable error reporting for debugging
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

echo '<h1>OCPay Status Polling Debug</h1>';

// Test 1: Check if status checker is loaded
echo '<h2>1. Status Checker Class Check</h2>';
if ( class_exists( 'OCPay_Status_Checker' ) ) {
    echo '<p style="color: green;">✓ OCPay_Status_Checker class found</p>';
} else {
    echo '<p style="color: red;">✗ OCPay_Status_Checker class NOT found</p>';
}

// Test 2: Check API client is loaded
echo '<h2>2. API Client Class Check</h2>';
if ( class_exists( 'OCPay_API_Client' ) ) {
    echo '<p style="color: green;">✓ OCPay_API_Client class found</p>';
} else {
    echo '<p style="color: red;">✗ OCPay_API_Client class NOT found</p>';
}

// Test 3: Check gateway settings
echo '<h2>3. Gateway Settings Check</h2>';
if ( ! class_exists( 'WooCommerce' ) ) {
    echo '<p style="color: red;">✗ WooCommerce not loaded</p>';
} else {
    echo '<p style="color: green;">✓ WooCommerce loaded</p>';
    
    $gateways = WC()->payment_gateways->payment_gateways();
    $gateway = isset( $gateways['ocpay'] ) ? $gateways['ocpay'] : null;
    
    if ( ! $gateway ) {
        echo '<p style="color: red;">✗ OCPay gateway not found</p>';
    } else {
        echo '<p style="color: green;">✓ OCPay gateway found</p>';
        
        $api_mode = $gateway->get_option( 'api_mode', 'sandbox' );
        $api_key_sandbox = $gateway->get_option( 'api_key_sandbox' );
        $api_key_live = $gateway->get_option( 'api_key_live' );
        $order_status = $gateway->get_option( 'order_status_after_payment', 'processing' );
        
        echo '<pre>';
        echo "API Mode: " . esc_html( $api_mode ) . "\n";
        echo "API Key (Sandbox): " . ( ! empty( $api_key_sandbox ) ? '***SET***' : 'NOT SET' ) . "\n";
        echo "API Key (Live): " . ( ! empty( $api_key_live ) ? '***SET***' : 'NOT SET' ) . "\n";
        echo "Order Status After Payment: " . esc_html( $order_status ) . "\n";
        echo '</pre>';
    }
}

// Test 4: Check pending orders
echo '<h2>4. Pending OCPay Orders</h2>';
if ( class_exists( 'WC_Order_Query' ) ) {
    $args = array(
        'limit'              => 20,
        'status'             => 'pending',
        'payment_method'     => 'ocpay',
        'meta_query'         => array(
            array(
                'key'     => '_ocpay_payment_ref',
                'compare' => 'EXISTS',
            ),
        ),
        'return'             => 'ids',
    );
    
    $query = new WC_Order_Query( $args );
    $pending_orders = $query->get_posts();
    
    if ( empty( $pending_orders ) ) {
        echo '<p style="color: orange;">ℹ No pending OCPay orders found</p>';
    } else {
        echo '<p style="color: green;">✓ Found ' . count( $pending_orders ) . ' pending OCPay orders</p>';
        echo '<ul>';
        foreach ( $pending_orders as $order_id ) {
            $order = wc_get_order( $order_id );
            $payment_ref = $order->get_meta( '_ocpay_payment_ref' );
            echo '<li>';
            echo 'Order #' . esc_html( $order->get_order_number() ) . ' ';
            echo '(ID: ' . esc_html( $order_id ) . ') ';
            echo '- Ref: ' . esc_html( substr( $payment_ref, 0, 10 ) ) . '...';
            echo '</li>';
        }
        echo '</ul>';
    }
} else {
    echo '<p style="color: red;">✗ WC_Order_Query not available</p>';
}

// Test 5: Manual polling trigger
if ( 'test_polling' === $action ) {
    echo '<h2>5. Manual Status Polling Test</h2>';
    
    if ( ! class_exists( 'OCPay_Status_Checker' ) ) {
        echo '<p style="color: red;">✗ Cannot run: OCPay_Status_Checker not loaded</p>';
    } else {
        echo '<p>Running status check...</p>';
        
        $checker = OCPay_Status_Checker::get_instance();
        $checker->check_pending_payments();
        
        echo '<p style="color: green;">✓ Status check completed</p>';
        echo '<p>Check WooCommerce logs for detailed results (WooCommerce → OCPay)</p>';
    }
}

// Test 6: Status checking approach
echo '<h2>6. Status Checking Method</h2>';
echo '<p style="color: blue;">ℹ Using simplified on-demand status checking</p>';
echo '<p>Status is checked when:</p>';
echo '<ul>';
echo '<li>Customer returns to thank you page</li>';
echo '<li>Customer views order page</li>';
echo '<li>Admin manually triggers check</li>';
echo '</ul>';
echo '<p>No background cron polling is used - this simplifies the plugin and reduces server load.</p>';

$next_event = wp_next_scheduled( 'wp_scheduled_event_ocpay_check_payment_status' );
if ( $next_event ) {
    echo '<p style="color: orange;">⚠ Old cron event still exists (will be cleaned up)</p>';
    echo '<p>Next execution: ' . wp_date( 'Y-m-d H:i:s', $next_event ) . '</p>';
}

// Test 7: Logger check
echo '<h2>7. Logger Check</h2>';
if ( class_exists( 'OCPay_Logger' ) ) {
    echo '<p style="color: green;">✓ OCPay_Logger found</p>';
    $logger = OCPay_Logger::get_instance();
    $logs = $logger->get_logs();
    $log_lines = count( array_filter( explode( "\n", $logs ) ) );
    echo '<p>Total log entries: ' . esc_html( $log_lines ) . '</p>';
} else {
    echo '<p style="color: red;">✗ OCPay_Logger NOT found</p>';
}

// Debug form
echo '<hr>';
echo '<h2>Quick Actions</h2>';
echo '<form method="GET">';
echo '<button type="submit" name="action" value="test_polling">Run Manual Status Check</button>';
echo '</form>';

echo '<hr>';
echo '<p><a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ocpay' ) . '">Go to OCPay Settings</a></p>';
echo '<p><a href="' . admin_url( 'admin.php?page=ocpay-settings' ) . '">View OCPay Dashboard</a></p>';
?>
