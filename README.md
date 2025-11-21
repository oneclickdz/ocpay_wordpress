# OCPay for WooCommerce - Complete Plugin Documentation

## Table of Contents

1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [Features](#features)
4. [Configuration](#configuration)
5. [Payment Flow](#payment-flow)
6. [Status Polling](#status-polling)
7. [Security Features](#security-features)
8. [API Reference](#api-reference)
9. [Troubleshooting](#troubleshooting)
10. [Development](#development)
11. [Changelog](#changelog)

---

## Overview

**OCPay for WooCommerce** is a comprehensive payment gateway plugin that integrates SATIM-powered OCPay payment processing with WooCommerce. It provides a secure, reliable way for Algerian businesses to accept online payments in Algerian Dinars (DZD).

### Key Information

- **Version**: 1.0.1
- **Author**: OneClick DZ
- **License**: GPL v3 or later
- **Minimum WordPress**: 5.0
- **Minimum PHP**: 7.2
- **Minimum WooCommerce**: 4.0
- **Currency Supported**: DZD (Algerian Dinar)

---

## Installation & Setup

### Prerequisites

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- SSL/HTTPS enabled (required for payment processing)
- OCPay merchant account (Sandbox and/or Production)

### Installation Steps

1. **Upload Plugin**
   - Download the plugin ZIP file
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin" and select the ZIP file
   - Click "Install Now"

2. **Activate Plugin**
   - Click "Activate Plugin" after installation
   - Plugin will verify dependencies

3. **Obtain API Keys**
   - Log in to your OCPay merchant dashboard at https://dashboard.oneclickdz.com
   - Navigate to API Settings
   - Copy your Sandbox API Key (for testing)
   - Copy your Production API Key (for live payments)

4. **Configure Plugin**
   - Go to WooCommerce → Settings → Payments
   - Click on "OCPay"
   - Enable the payment method
   - Enter your API keys (separate Sandbox and Production keys)
   - Select API Mode (Sandbox for testing, Production for live)
   - Configure order status after successful payment
   - Save changes

### Initial Configuration Checklist

- [ ] Plugin installed and activated
- [ ] OCPay appears in available payment methods
- [ ] API keys configured (both Sandbox and Production)
- [ ] API mode set to "Sandbox" for testing
- [ ] Order status preference selected
- [ ] SSL/HTTPS verified
- [ ] Test connection successful

---

## Features

### Core Features

1. **Secure Payment Processing**
   - Bank-grade SATIM security
   - PCI-DSS compliant
   - Encrypted payment data transmission

2. **Flexible Order Status Management**
   - Set order status after successful payment (Completed or Processing)
   - Failed payments marked as "On Hold" for manual review
   - Automatic order status updates via payment confirmation

3. **Simple On-Demand Status Checking**
   - Checks payment status when customer returns to site
   - Status refreshed when viewing order page
   - Manual check available for admins (up to 50 orders)
   - No background cron jobs - simpler and more efficient

4. **WooCommerce Blocks Support**
   - Compatible with latest WooCommerce block checkout
   - Works with classic checkout forms
   - Seamless integration with checkout blocks

5. **Multiple Fee Modes**
   - NO_FEE: You pay all transaction fees
   - SPLIT_FEE: 50/50 fee split between merchant and customer
   - CUSTOMER_FEE: Customer pays all transaction fees

6. **Comprehensive Logging**
   - Detailed activity logs for debugging
   - Request/response logging
   - Error tracking and reporting

7. **HPOS Compatibility**
   - Full support for WooCommerce High-Performance Order Storage
   - Compatible with custom order tables

### Security Features

- CSRF protection with nonces
- SQL injection prevention
- API key masking in logs
- Rate limiting on status checks
- Input validation and sanitization
- Output escaping for all user-facing content
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)

---

## Configuration

### Payment Gateway Settings

#### General Settings

**Enable/Disable**
- Enables or disables the OCPay payment method on checkout

**Title**
- Display name for the payment method on checkout
- Default: "OCPay - OneClick Payment"

**Description**
- Description shown to customers during checkout
- Default: "Pay securely using OCPay - powered by SATIM bank-grade security."

#### API Settings

**Sandbox API Key**
- Used for testing payments
- Obtain from OCPay merchant dashboard (Sandbox environment)
- Required for testing

**Production API Key**
- Used for live payments
- Obtain from OCPay merchant dashboard (Production environment)
- Required for accepting real payments

**API Mode**
- **Sandbox (Testing)**: Uses Sandbox API Key for testing
- **Production (Live)**: Uses Production API Key for real transactions

**Fee Mode**
- **NO_FEE**: You absorb all transaction fees
- **SPLIT_FEE**: Customer pays 50% of fees
- **CUSTOMER_FEE**: Customer pays 100% of fees

**Redirect URL**
- Custom URL to redirect customers after payment
- Leave empty to use default WooCommerce order-received page

#### Order Status Settings

**Order Status After Successful Payment**
- **Completed**: Order is immediately marked as complete
  - Use this if products are digital or should be auto-delivered
  - Triggers order completion hooks and actions
  
- **Processing**: Order is marked as processing
  - Use this if products require manual fulfillment or verification
  - Recommended for physical goods

#### Debug Settings

**Debug Mode**
- Enable for detailed logging
- Use only during testing/troubleshooting
- Disable in production to avoid performance impact
- Logs are visible in WooCommerce → OCPay admin page

### Environment Configuration

#### Testing (Sandbox)

```
API Mode: Sandbox (Testing)
API Key: Use your Sandbox API Key
Store Setting: Keep WooCommerce in test mode
```

#### Production (Live)

```
API Mode: Production (Live)
API Key: Use your Production API Key
Store Setting: Switch to live mode
SSL: Must be enabled
```

---

## Payment Flow

### Complete Payment Process

1. **Customer Initiates Checkout**
   - Customer selects OCPay payment method
   - Fills in required information
   - Clicks "Place Order"

2. **Order Creation**
   - WooCommerce creates order with "Pending" status
   - Order saved with OCPay payment method

3. **Payment Link Generation**
   - Plugin calls OCPay API to create payment link
   - Payment reference stored in order metadata
   - Customer redirected to OCPay payment page

4. **Payment Processing at OCPay**
   - Customer completes payment at OCPay
   - SATIM processes the transaction
   - Payment status determined (CONFIRMED or FAILED)

5. **Automatic Status Polling** (Every 20 minutes)
   - Cron job checks pending orders
   - Calls OCPay API for latest payment status
   - Updates order based on response:
     - **CONFIRMED**: Order set to configured success status
     - **FAILED**: Order marked as "On Hold"
     - **PENDING**: No change

6. **Customer Return**
   - Customer returns to store
   - Manual status check (optional)
   - Order page displays current status

7. **Order Completion**
   - Triggers WooCommerce payment complete hooks
   - Sends customer notifications
   - Updates inventory for physical products

### Status Flow Diagram

```
Customer Checkout
    ↓
Order Created (Pending)
    ↓
Payment Link Generated
    ↓
Customer Pays at OCPay
    ↓
Status Polling (20-minute intervals)
    ↓
CONFIRMED → Order set to Processing/Completed
FAILED → Order marked as On-Hold
PENDING → Continue polling
```

---

## Status Checking

### Overview

OCPay payments require asynchronous confirmation through the SATIM banking system. The plugin implements simple on-demand status checking to verify payment confirmation when customers interact with their orders.

### Checking Methods

#### 1. Thank You Page Check (Automatic)

**Trigger**: When customer returns from OCPay payment page

**What Checks**:
- The specific order that was just paid

**Process**:
1. Customer completes payment at OCPay
2. Customer is redirected to thank you page
3. Plugin automatically checks payment status via API
4. Order is updated if payment confirmed
5. Customer sees immediate result

**Benefits**:
- Immediate feedback to customer
- No waiting required
- Happens automatically on return

**Hook**: `woocommerce_thankyou`

#### 2. Order View Page Check (Automatic)

**Trigger**: When customer or admin views an order page

**Process**:
1. Customer/admin views order page
2. Plugin checks if order is still pending
3. If pending, checks payment status via API
4. Order is updated if payment confirmed
5. Page shows updated status

**Benefits**:
- Status refreshed every time order is viewed
- No manual intervention needed
- Works for both customers and admins

**Hook**: `woocommerce_view_order`

#### 3. Manual Admin Check

**Trigger**: Admin clicks "Manual Check" button in settings

**Process**:
1. Admin triggers manual check
2. Plugin retrieves up to 50 most recent pending orders
3. Checks payment status for each via API
4. Orders are updated based on responses
5. Results logged and displayed

**Benefits**:
- Useful for bulk checking
- Limited to 50 orders to prevent timeout
- Can be triggered anytime by admin

**Note**: This replaces the old automatic cron-based polling that ran every 20 minutes

### Payment Status Values

The OCPay API returns one of three statuses:

| Status | Meaning | Order Action | Display |
|--------|---------|--------------|---------|
| **CONFIRMED** | Payment successfully completed | Set to Processing/Completed | "Payment confirmed" |
| **FAILED** | Payment was declined or cancelled | Set to On-Hold | "Payment failed" |
| **PENDING** | Payment still processing | No change | "Processing..." |

### When Status is Checked

| Trigger | When It Happens | What Gets Checked |
|---------|----------------|-------------------|
| Thank You Page | Customer returns from OCPay | That specific order only |
| Order View Page | Customer/admin views order | That specific order if pending |
| Manual Admin Check | Admin clicks check button | Up to 50 most recent pending orders |

**Note**: This approach is simpler and more efficient than the old cron-based polling that ran every 20 minutes regardless of activity.

### Monitoring Status Checks

1. **Check Logs**
   - WooCommerce → OCPay → Activity Logs
   - View status check entries with timestamps

2. **Log Entries Include**:
   - When status was checked
   - Which order was checked
   - API response received
   - Whether order was updated

3. **Debug Information**
   - Enable Debug Mode in settings
   - Logs more detailed information
   - Check `/wp-content/debug.log` if WP_DEBUG enabled

### Troubleshooting Status Checks

**Orders Not Updating**:
1. Check API credentials are correct
2. Verify payment references saved in orders
3. Check debug logs for API errors
4. Try manual admin check to test immediately

**Customer Not Seeing Updated Status**:
1. Make sure customer visited thank you page after payment
2. Ask customer to refresh their order page
3. Run manual admin check to force update

---

## Security Features

### 1. Data Protection

#### API Key Protection
- Sandbox and Production keys stored separately
- API keys never logged in plain text
- Keys masked as `***HIDDEN***` in logs
- Keys transmitted only over HTTPS

#### Payment Reference Protection
- Payment references validated before processing
- Format validation prevents injection attacks
- References masked in logs

#### Order Data Security
- Customer data encrypted in transit
- Order metadata secured with WordPress permissions
- No sensitive data stored in cookies

### 2. Input Validation

#### All Inputs Validated

```php
// Payment amounts
- Must be numeric
- Must be >= 500 DZD
- Must be <= 100,000,000 DZD

// Currency
- Must be DZD (only supported currency)
- Converted to uppercase for comparison

// Payment reference
- Format: alphanumeric, dash, underscore only
- Length: 10-50 characters
- Regex: /^[a-zA-Z0-9\-_]{10,50}$/

// Redirect URLs
- Must be valid URL format
- Must use HTTP or HTTPS
- Must be absolute URL (not relative)
```

### 3. Output Escaping

All user-facing content properly escaped:

```php
// HTML context: esc_html()
// Attribute context: esc_attr()
// URL context: esc_url()
// JavaScript context: esc_js()
```

### 4. CSRF Protection

#### Nonces for All Forms

```php
// Admin pages
wp_create_nonce('ocpay_admin_nonce')

// Frontend AJAX
wp_create_nonce('ocpay_frontend_nonce')
```

#### Verification
- All nonces verified on receipt
- Invalid nonces rejected with error
- Logged for security monitoring

### 5. Rate Limiting

#### Status Check Rate Limiting

- **Limit**: 10 checks per minute per user/IP
- **Tracking**: Via transient cache
- **Response**: "Too many requests" error if exceeded
- **Purpose**: Prevents API abuse

#### Implementation

```php
// Check rate limit before processing
$count = get_transient('ocpay_status_check_' . $user_id);
if ($count >= 10) {
    wp_send_json_error(['message' => 'Too many requests']);
}
```

### 6. SQL Injection Prevention

#### Prepared Statements
- All database queries use WooCommerce query builders
- No direct SQL execution
- Parameters properly escaped

#### Order Queries
```php
// Secure: Uses WC_Order_Query
$query = new WC_Order_Query(['payment_method' => 'ocpay']);

// NEVER: Direct SQL
// SELECT * FROM wp_posts WHERE meta_value = $_GET['ref']
```

### 7. Security Headers

#### Applied to OCPay Pages

```
X-Content-Type-Options: nosniff
  - Prevents MIME sniffing

X-Frame-Options: SAMEORIGIN
  - Prevents clickjacking

X-XSS-Protection: 1; mode=block
  - Enables browser XSS filtering

Referrer-Policy: strict-origin-when-cross-origin
  - Controls referrer information leakage
```

### 8. HPOS (High-Performance Order Storage) Support

- Compatible with custom order tables
- Uses WC_Order_Query for HPOS compatibility
- No direct posts table queries
- Future-proof for WooCommerce 8.0+

### 9. Logging Security

#### What's Logged
- Payment creation attempts
- Status polling activities
- API responses (with sensitive data masked)
- Errors and exceptions

#### What's NOT Logged
- API keys (masked as `***HIDDEN***`)
- Full payment references (masked)
- Customer payment methods
- Card details (never handled by plugin)

#### Log Retention
- Logs stored in database
- Can be cleared via admin interface
- Should be cleared regularly for privacy

### 10. Best Practices for Merchants

1. **API Keys**
   - Keep keys secure and private
   - Rotate keys periodically
   - Use different keys for Sandbox and Production
   - Never share keys via email or chat

2. **SSL Certificate**
   - Ensure SSL/HTTPS enabled
   - Use valid certificate (not self-signed)
   - Test with SSL checker tools

3. **Passwords**
   - Use strong WordPress admin passwords
   - Enable two-factor authentication if available
   - Restrict admin access by IP if possible

4. **Regular Updates**
   - Update WordPress regularly
   - Update WooCommerce regularly
   - Update OCPay plugin when updates available
   - Update PHP version to latest supported

5. **Monitoring**
   - Review logs regularly
   - Monitor for suspicious activity
   - Set up alerts for failed payments
   - Keep backups of critical data

---

## API Reference

### Public API

#### OCPay_API_Client

**Main Class for API Communication**

```php
$api_client = new OCPay_API_Client( $api_key, $api_mode );
```

##### Methods

**create_payment_link( $args )**
- Creates a payment link for the customer
- Parameters:
  - `title` (string): Order/product description
  - `amount` (float): Payment amount in DZD
  - `currency` (string): Currency (always 'DZD')
  - `redirectUrl` (string): URL to redirect after payment
  - `feeMode` (string): NO_FEE | SPLIT_FEE | CUSTOMER_FEE
- Returns: Array with `paymentUrl` and `paymentRef`, or WP_Error

```php
$response = $api_client->create_payment_link([
    'title' => 'Order #123',
    'amount' => 5000,
    'currency' => 'DZD',
    'redirectUrl' => 'https://yoursite.com/checkout/order-received/',
    'feeMode' => 'NO_FEE'
]);

if (is_wp_error($response)) {
    echo "Error: " . $response->get_error_message();
} else {
    echo "Payment URL: " . $response['paymentUrl'];
    echo "Payment Ref: " . $response['paymentRef'];
}
```

**check_payment_status( $payment_ref )**
- Checks the current status of a payment
- Parameters:
  - `payment_ref` (string): Payment reference from create_payment_link
- Returns: Array with `status`, or WP_Error
- Status values: PENDING | CONFIRMED | FAILED

```php
$status = $api_client->check_payment_status( $payment_ref );

if (!is_wp_error($status)) {
    $payment_status = $status['status']; // CONFIRMED, FAILED, or PENDING
}
```

**test_connection()**
- Tests if API connection is working
- Returns: Array with success/failure info

```php
$result = $api_client->test_connection();
if ($result['success']) {
    echo "API connection successful!";
}
```

#### OCPay_Status_Checker

**Handles Status Polling**

```php
$checker = OCPay_Status_Checker::get_instance();
```

##### Methods

**check_pending_payments()**
- Checks all pending OCPay orders (cron job)
- Called automatically every 20 minutes
- Returns: void

```php
// Manually trigger (for testing)
OCPay_Status_Checker::get_instance()->check_pending_payments();
```

**check_order_payment_status( $order_id )**
- Checks payment status for a specific order
- Parameters: `order_id` (int)
- Returns: bool (true if order was updated)

```php
$updated = OCPay_Status_Checker::get_instance()
    ->check_order_payment_status( 123 );

if ($updated) {
    echo "Order status updated!";
}
```

**get_status_counts()**
- Returns count of pending orders
- Returns: Array with status counts

```php
$counts = OCPay_Status_Checker::get_instance()
    ->get_status_counts();
echo "Pending orders: " . $counts['pending'];
```

#### OCPay_Security

**Security and Validation**

```php
$security = OCPay_Security::get_instance();
```

##### Static Methods

**validate_payment_data( $data )**
- Validates payment data before API call
- Parameters: `data` (array)
- Returns: true or error message

```php
$valid = OCPay_Security::validate_payment_data([
    'amount' => 5000,
    'redirectUrl' => 'https://yoursite.com/checkout/'
]);

if ($valid !== true) {
    echo "Validation error: " . $valid;
}
```

**safe_output( $text, $context )**
- Safely escapes output based on context
- Contexts: html | attr | js | url
- Returns: Escaped string

```php
// HTML context
echo OCPay_Security::safe_output($title, 'html');

// Attribute context
echo OCPay_Security::safe_output($class, 'attr');

// URL context
echo OCPay_Security::safe_output($url, 'url');

// JavaScript context
echo OCPay_Security::safe_output($var, 'js');
```

#### OCPay_Logger

**Logging System**

```php
$logger = OCPay_Logger::get_instance();
```

##### Methods

**info( $message, $context )**
- Logs info level message
- Parameters:
  - `message` (string): Message to log
  - `context` (array): Additional context

```php
$logger->info('Payment created', [
    'order_id' => 123,
    'amount' => 5000
]);
```

**error( $message, $context )**
- Logs error level message

```php
$logger->error('API request failed', [
    'error' => 'Connection timeout',
    'url' => 'https://api.oneclickdz.com/...'
]);
```

**debug( $message, $context )**
- Logs debug level message (only if debug mode enabled)

```php
$logger->debug('Checking payment status', [
    'payment_ref' => 'ABC123...'
]);
```

**get_logs()**
- Retrieves all logged entries
- Returns: String of formatted log entries

```php
$logs = $logger->get_logs();
echo $logs; // Display in admin page
```

**clear_logs()**
- Clears all log entries
- Returns: void

```php
$logger->clear_logs();
```

#### OCPay_Validator

**Input Validation**

**All Static Methods**

```php
// Validate amount
$valid = OCPay_Validator::validate_amount( 5000 );
if ($valid !== true) echo "Error: " . $valid;

// Validate currency
$valid = OCPay_Validator::validate_currency( 'DZD' );

// Validate payment reference
$valid = OCPay_Validator::validate_payment_ref( 'ABC123XYZ' );

// Validate payment status
$valid = OCPay_Validator::validate_payment_status( 'CONFIRMED' );

// Validate redirect URL
$valid = OCPay_Validator::validate_redirect_url( 'https://...' );

// Validate fee mode
$valid = OCPay_Validator::validate_fee_mode( 'NO_FEE' );

// Sanitize description
$safe = OCPay_Validator::sanitize_description( $user_input );

// Sanitize API response
$safe = OCPay_Validator::sanitize_api_response( $api_response );
```

### WordPress Hooks

#### Actions

**woocommerce_payment_complete**
- Fired after order is marked as paid
- Hook name: `woocommerce_payment_complete`

```php
add_action('woocommerce_payment_complete', function($order_id) {
    echo "Order " . $order_id . " payment confirmed!";
});
```

**woocommerce_order_status_[status]**
- Fired when order status changes
- Hook names:
  - `woocommerce_order_status_processing`
  - `woocommerce_order_status_completed`
  - `woocommerce_order_status_on-hold`

```php
add_action('woocommerce_order_status_processing', function($order_id) {
    // Send notification email
});
```

**woocommerce_thankyou**
- Fired when customer returns to thank you page
- OCPay checks payment status at this time

**woocommerce_view_order**
- Fired when customer views their order
- OCPay checks payment status if order is still pending

#### Filters

**woocommerce_payment_gateways**
- Adds OCPay to available payment gateways

```php
add_filter('woocommerce_payment_gateways', function($gateways) {
    // OCPay_Payment_Gateway added automatically
    return $gateways;
});
```

### Database Schema

#### Order Metadata

Stored via `WC_Order::update_meta_data()` and `WC_Order::get_meta()`

| Meta Key | Type | Description |
|----------|------|-------------|
| `_payment_method` | string | Always 'ocpay' |
| `_ocpay_payment_ref` | string | Payment reference from API |
| `_ocpay_payment_url` | string | Payment URL sent to customer |
| `_ocpay_payment_confirmed_at` | string | Date payment confirmed |
| `_ocpay_payment_failed_at` | string | Date payment failed |

```php
// Getting payment reference
$payment_ref = $order->get_meta('_ocpay_payment_ref');

// Setting order metadata
$order->update_meta_data('_ocpay_payment_confirmed_at', 
    current_time('mysql'));
$order->save();
```

### Status Checking Hooks

The plugin uses WordPress and WooCommerce hooks to check payment status on-demand:

```php
// Check status on thank you page
add_action('woocommerce_thankyou', function($order_id) {
    OCPay_Status_Checker::get_instance()->check_status_on_thankyou($order_id);
});

// Check status when viewing order
add_action('woocommerce_view_order', function($order_id) {
    OCPay_Status_Checker::get_instance()->check_status_on_order_view($order_id);
});
```

**Note**: No cron jobs or background polling are used. Status is checked only when needed.

---

## Troubleshooting

### Common Issues

#### 1. "OCPay payment gateway not properly configured"

**Cause**: API key not set or invalid

**Solution**:
1. Go to WooCommerce → Settings → Payments → OCPay
2. Verify API key is entered for current mode (Sandbox or Production)
3. Copy API key again from OCPay dashboard
4. Test connection: Click "Test Connection" button

#### 2. Payment link not created

**Error**: "Payment gateway error. Please try again."

**Solutions**:
1. Check API key is valid
2. Verify mode matches your API key environment
3. Check order total is >= 500 DZD
4. Verify SSL/HTTPS is enabled
5. Check debug logs for detailed error

**To check logs**:
- WooCommerce → OCPay → Activity Logs
- Look for "Failed to create payment link" entries

#### 3. Orders not updating to Processing/Completed

**Cause**: Customer hasn't returned to store or API issues

**Solutions**:
1. Ask customer to visit their order page (this triggers status check)
2. Use manual admin check button to force update
3. Verify payment reference stored in order
4. Check payment status in OCPay dashboard
5. Enable debug mode for detailed logging

**Quick Fix**:
- Go to WooCommerce → Settings → Payments → OCPay
- Click "Manual Check" button
- This will check up to 50 most recent pending orders

#### 4. "Invalid payment reference" error

**Cause**: Corrupted payment reference or URL tampering

**Solutions**:
1. Don't modify URLs manually
2. Use official return links from OCPay
3. Report to OCPay support if persists
4. Check logs for details

#### 5. AJAX status check not working

**Cause**: Nonce invalid or JavaScript error

**Solutions**:
1. Clear browser cache
2. Hard refresh page (Ctrl+Shift+R or Cmd+Shift+R)
3. Check browser console for JavaScript errors
4. Verify nonce is generated in page
5. Enable debug mode for detailed logs

**To check**:
- Open browser DevTools (F12)
- Go to Console tab
- Check for JavaScript errors
- Check Network tab for AJAX requests

#### 6. "Too many requests" rate limit error

**Cause**: More than 10 status checks per minute

**Solution**:
- Wait 60 seconds before trying again
- Rate limiting is in place to protect API

#### 7. Plugin not showing on Payments page

**Causes & Solutions**:
1. WooCommerce not installed
   - Install and activate WooCommerce 4.0+
2. PHP version too old
   - Upgrade to PHP 7.2+
3. Plugin not activated
   - Go to Plugins, find OCPay, click Activate

#### 8. High CPU usage / Performance issues

**Note**: With the simplified on-demand checking, performance issues should be rare.

**If you still experience issues**:
1. Check for slow API responses in logs
2. Limit manual admin checks (currently 50 orders max)
3. Archive old orders to reduce query time
4. Contact hosting provider for optimization
5. Contact OCPay support if API is slow

#### 9. Customer not receiving notifications

**Cause**: Notification emails disabled or misconfigured

**Solution**:
1. Check WooCommerce email settings
2. Test email sending: WooCommerce → Settings → Emails
3. Check spam folder for test email
4. Contact hosting provider if emails not sending
5. Consider using email service plugin

#### 10. SSL/HTTPS issues

**Error**: "SSL certificate problem"

**Solutions**:
1. Generate valid SSL certificate
   - Go to your hosting provider
   - Request free SSL (Let's Encrypt usually available)
2. Verify certificate is installed
   - Use SSL checker: https://www.ssllabs.com/ssltest/
3. Update WordPress and WooCommerce
4. Clear cache and restart services

### Debug Mode

**Enable Debug Mode**:
1. WooCommerce → Settings → Payments → OCPay
2. Check "Enable Debug Logging"
3. Save changes

**View Debug Logs**:
1. WooCommerce → OCPay
2. Scroll to "Activity Logs" section
3. Review detailed log entries

**Log Entry Format**:
```
[2024-01-15 10:30:45] INFO: Checking payment status for order
Order ID: 123
Payment Ref: ABC123XYZ789
Status: CONFIRMED
```

### Getting Help

1. **Check Logs First**
   - Enable debug mode
   - Look for specific error messages
   - Search for error code online

2. **Contact Support**
   - OCPay Support: support@oneclickdz.com
   - Include: Error message, Order ID, Debug logs

3. **Community**
   - WordPress Plugin Forum
   - WooCommerce Documentation

---

## Development

### Code Structure

```
includes/
├── class-ocpay-api-client.php       # API communication
├── class-ocpay-payment-gateway.php  # WooCommerce integration
├── class-ocpay-status-checker.php   # Status polling service
├── class-ocpay-security.php         # Security & validation
├── class-ocpay-logger.php           # Logging system
├── class-ocpay-error-handler.php    # Error handling
├── class-ocpay-validator.php        # Input validation
├── class-ocpay-settings.php         # Admin settings page
├── class-ocpay-order-handler.php    # Order operations
├── class-ocpay-payment-block.php    # WooCommerce Blocks
└── class-ocpay-block-support.php    # Blocks support

assets/
├── css/
│   ├── admin.css                    # Admin styles
│   └── frontend.css                 # Frontend styles
├── js/
│   ├── admin.js                     # Admin scripts
│   ├── frontend.js                  # Frontend scripts
│   └── blocks-payment-method.js     # Blocks integration
└── images/
    └── ocpay-logo.png              # Payment method logo

templates/
├── payment-success.php              # Success page
├── payment-failed.php               # Failed page
└── payment-pending.php              # Pending page

ocpay-woocommerce.php               # Main plugin file
composer.json                        # Dependencies
```

### Key Classes

1. **OCPay_API_Client**: Handles all API communication
2. **OCPay_Payment_Gateway**: WooCommerce payment gateway
3. **OCPay_Status_Checker**: On-demand status checking
4. **OCPay_Security**: Security features and validation
5. **OCPay_Logger**: Logging system
6. **OCPay_Validator**: Input validation

### Extending the Plugin

#### Adding Custom Order Status

```php
// Hook into order confirmation
add_action('woocommerce_payment_complete', function($order_id) {
    $order = wc_get_order($order_id);
    if ('ocpay' === $order->get_payment_method()) {
        // Add custom logic
    }
});
```

#### Custom Notifications

```php
// Hook into order status change
add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);
    if ('ocpay' === $order->get_payment_method()) {
        // Send custom notification
    }
});
```

#### Modify Fee Mode Logic

```php
// Filter fee mode selection
add_filter('ocpay_fee_mode', function($fee_mode, $order) {
    // Custom logic to determine fee mode
    return $fee_mode;
}, 10, 2);
```

### Testing

#### Manual Testing Steps

1. **Sandbox Configuration**
   - Set API Mode to "Sandbox"
   - Use Sandbox API Key
   - Set order status to "Processing"

2. **Create Test Order**
   - Go to store checkout
   - Select OCPay payment
   - Enter test product details
   - Place order

3. **Check Payment Link**
   - Verify redirect to OCPay payment page
   - Check URL contains valid payment reference

4. **Complete Payment**
   - Use test payment method at OCPay
   - Complete or decline the payment

5. **Verify Status Update**
   - Check if order status updated
   - Verify in WooCommerce order details
   - Check activity logs for confirmation

#### WP-CLI Commands

```bash
# Check scheduled events
wp cron test

# List all scheduled events
wp cron event list

# Manually trigger status check
wp eval 'do_action("wp_scheduled_event_ocpay_check_payment_status");'

# Check plugin version
wp plugin list --field=name --field=version | grep ocpay
```

### Release Workflow

The plugin includes an automated GitHub Actions workflow for version management and releases.

#### How to Create a Release

1. **Navigate to GitHub Actions**:
   - Go to the repository on GitHub
   - Click "Actions" tab
   - Select "Release Plugin" workflow

2. **Trigger the Workflow**:
   - Click "Run workflow"
   - Select version bump type:
     - **patch**: Bug fixes (1.0.1 → 1.0.2)
     - **minor**: New features (1.0.1 → 1.1.0)
     - **major**: Breaking changes (1.0.1 → 2.0.0)

3. **Workflow Process**:
   - Automatically increments version in all files
   - Commits version changes
   - Creates git tag (e.g., v1.0.2)
   - Builds plugin ZIP file (excluding dev files)
   - Creates GitHub release with download link

4. **Release Package**:
   - Clean ZIP file with only production files
   - Excludes: .git, .github, node_modules, tests, etc.
   - Ready for WordPress plugin installation

For detailed documentation, see [.github/WORKFLOW_DOCS.md](.github/WORKFLOW_DOCS.md).

---

## Changelog

### Version 1.2.1 (Current)

**Major Simplification**:
- Removed complex cron-based polling system
- Replaced with simple on-demand status checking
- Status checked on thank you page (when customer returns)
- Status checked on order view page (when customer views order)
- Manual admin check for up to 50 most recent pending orders
- Much simpler architecture and easier to understand

**Benefits of Simplification**:
- No background cron jobs consuming server resources
- Status updates happen when they matter (when customer interacts)
- Easier to debug and maintain
- Follows best practices from similar payment gateways
- Reduced complexity without losing functionality

**What Still Works**:
- All payment processing functionality
- Admin settings and configuration
- Logging and debugging features
- Manual admin checks
- Security features
- API integration

**Previous Features (Still Available)**:
- Configurable order status after payment (Completed/Processing)
- On-Hold status for failed payments
- Comprehensive security enhancements
- Rate limiting for API requests
- Input validation and sanitization
- CSRF protection with nonces
- SQL injection prevention
- API key masking in logs
- HPOS compatibility

### Version 1.0.0

**Initial Release**:
- Basic payment gateway functionality
- API integration
- Order creation and processing
- WooCommerce Blocks support
- Basic logging system

---

## Additional Resources

- **OCPay Documentation**: https://docs.oneclickdz.com
- **OCPay Dashboard**: https://dashboard.oneclickdz.com
- **WooCommerce Documentation**: https://docs.woocommerce.com
- **WordPress Plugin Development**: https://developer.wordpress.org/plugins

---

## Support

For issues, questions, or feature requests:

1. **Check this documentation** - most questions answered here
2. **View Activity Logs** - WooCommerce → OCPay → Activity Logs
3. **Contact OCPay Support** - support@oneclickdz.com
4. **WordPress Plugin Forum** - wordpress.org plugin support

---

## License

OCPay for WooCommerce is released under the GPL v3 or later license.

```
OCPay for WooCommerce - Accept secure payments via OCPay
Copyright (C) 2024, OneClick DZ

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

**Last Updated**: January 2024
**Author**: OneClick DZ
**Website**: https://oneclickdz.com
