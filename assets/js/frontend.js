/**
 * OCPay WooCommerce Frontend JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Handle OCPay payment method selection
        $('form.checkout').on('change', 'input[name="payment_method"]', function () {
            if ('ocpay' === $(this).val()) {
                // Show OCPay specific content if needed
                $(document).trigger('payment_method_selected', ['ocpay']);
            }
        });

        // Handle checkout form submission
        $('form.checkout').on('checkout_place_order_ocpay', function () {
            // Validation can be added here if needed
            return true;
        });

        // Show/hide payment box
        $(document).on('payment_method_selected', function (e, method) {
            if ('ocpay' === method) {
                $('[class*="payment_box payment_method_ocpay"]').slideDown();
            } else {
                $('[class*="payment_box payment_method_ocpay"]').slideUp();
            }
        });

        // Initialize on page load
        if ($('input[name="payment_method"]:checked').val() === 'ocpay') {
            $(document).trigger('payment_method_selected', ['ocpay']);
        }

        // Handle payment return messages
        if (typeof ocpayPaymentReturn !== 'undefined') {
            showPaymentMessage(
                ocpayPaymentReturn.status,
                ocpayPaymentReturn.message
            );
        }
    });

    /**
     * Show payment status message
     *
     * @param {string} status Status type (success, error, warning, info)
     * @param {string} message Message text
     */
    function showPaymentMessage(status, message) {
        const validStatuses = ['success', 'error', 'warning', 'info'];
        const messageStatus = validStatuses.includes(status) ? status : 'info';
        
        const $message = $('<div class="ocpay-status-message ' + messageStatus + '">' +
            '<p>' + message + '</p>' +
            '</div>');

        // Insert at the top of the checkout area
        const $checkoutForm = $('form.checkout');
        if ($checkoutForm.length) {
            $checkoutForm.before($message);
        } else {
            $('body').prepend($message);
        }

        // Auto-hide info messages after 5 seconds
        if ('info' === messageStatus) {
            setTimeout(function () {
                $message.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    /**
     * Format currency value
     *
     * @param {number} value Value to format
     * @param {string} currency Currency code
     * @returns {string}
     */
    function formatCurrency(value, currency) {
        const formatter = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
        });
        return formatter.format(value);
    }

    // Expose functions globally if needed
    window.ocpayWooCommerce = {
        showPaymentMessage: showPaymentMessage,
        formatCurrency: formatCurrency,
    };

})(jQuery);
