<?php
/**
 * OCPay Payment Return Failed Template
 *
 * @package OCPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="ocpay-payment-container">
	<div class="ocpay-payment-header">
		<h1><?php esc_html_e( 'Payment Failed', 'ocpay-woocommerce' ); ?></h1>
		<p><?php esc_html_e( 'Unfortunately, your payment could not be processed.', 'ocpay-woocommerce' ); ?></p>
	</div>

	<?php if ( $order ) : ?>
		<div class="ocpay-order-details">
			<dl>
				<dt><?php esc_html_e( 'Order Number:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( '#' . $order->get_order_number() ); ?></dd>

				<dt><?php esc_html_e( 'Order Total:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>

				<dt><?php esc_html_e( 'Order Status:', 'ocpay-woocommerce' ); ?></dt>
				<dd><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></dd>
			</dl>
		</div>

		<div class="ocpay-status-message error">
			<p><?php esc_html_e( 'Please try again or choose a different payment method.', 'ocpay-woocommerce' ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'Try Again', 'ocpay-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button">
				<?php esc_html_e( 'Back to Checkout', 'ocpay-woocommerce' ); ?>
			</a>
		</p>
	<?php else : ?>
		<div class="ocpay-status-message error">
			<p><?php esc_html_e( 'We could not find your order. Please contact support.', 'ocpay-woocommerce' ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'Back to Checkout', 'ocpay-woocommerce' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
