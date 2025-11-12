<?php
/**
 * OCPay Payment Return Pending Template
 *
 * @package OCPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="ocpay-payment-container">
	<div class="ocpay-payment-header">
		<h1><?php esc_html_e( 'Payment Processing', 'ocpay-woocommerce' ); ?></h1>
		<p><?php esc_html_e( 'Your payment is being processed. Please wait...', 'ocpay-woocommerce' ); ?></p>
	</div>

	<?php if ( $order ) : ?>
		<div class="ocpay-order-details">
			<dl>
				<dt><?php esc_html_e( 'Order Number:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( '#' . $order->get_order_number() ); ?></dd>

				<dt><?php esc_html_e( 'Order Total:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>

				<dt><?php esc_html_e( 'Payment Method:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php esc_html_e( 'OCPay', 'ocpay-woocommerce' ); ?></dd>
			</dl>
		</div>

		<div class="ocpay-status-message info">
			<p><?php esc_html_e( 'Your payment is being confirmed. This may take a few moments. You will receive an email notification once payment is confirmed.', 'ocpay-woocommerce' ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'View Order', 'ocpay-woocommerce' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
