<?php
/**
 * OCPay Payment Return Success Template
 *
 * @package OCPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="ocpay-payment-container">
	<div class="ocpay-payment-header">
		<h1><?php esc_html_e( 'Payment Successful', 'ocpay-woocommerce' ); ?></h1>
		<p><?php esc_html_e( 'Your order has been placed and payment is being processed.', 'ocpay-woocommerce' ); ?></p>
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

				<dt><?php esc_html_e( 'Order Date:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( $order->get_date_created()->format( get_option( 'date_format' ) ) ); ?></dd>
			</dl>
		</div>

		<div class="ocpay-status-message info">
			<p><?php esc_html_e( 'You will receive an email confirmation shortly. Your order status will be updated as soon as payment is confirmed.', 'ocpay-woocommerce' ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'View Order', 'ocpay-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button">
				<?php esc_html_e( 'Continue Shopping', 'ocpay-woocommerce' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
