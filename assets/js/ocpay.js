/**
 * OCPay WooCommerce - Consolidated JavaScript
 * Handles both admin and frontend functionality
 */

(function ($) {
    'use strict';

    // Admin functionality
    if (typeof ajaxurl !== 'undefined') {
        $(document).ready(function () {
            // Generic AJAX handler for admin buttons
            function handleAdminAction(btnSelector, action, confirmMsg, successCallback) {
                $(btnSelector).on('click', function (e) {
                    e.preventDefault();

                    if (confirmMsg && !confirm(confirmMsg)) {
                        return;
                    }

                    const $btn = $(this);
                    const nonce = $btn.data('nonce');
                    const originalText = $btn.text();
                    const $result = $('#' + action.replace('ocpay_', 'ocpay-') + '-result');

                    $btn.prop('disabled', true).text('Processing...');
                    $result.html('');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: { action: action, nonce: nonce },
                        success: function (response) {
                            if (response.success) {
                                $btn.text('✓ Done').css('color', 'green');
                                $result.html('<p style="color:green">✓ ' + (response.data.message || 'Success') + '</p>');
                                if (successCallback) successCallback();
                            } else {
                                $result.html('<p style="color:red">✗ ' + (response.data || 'Error') + '</p>');
                            }
                        },
                        error: function (xhr, status, error) {
                            $result.html('<p style="color:red">✗ Error: ' + error + '</p>');
                        },
                        complete: function () {
                            $btn.prop('disabled', false);
                            setTimeout(function () {
                                $btn.text(originalText).css('color', '');
                            }, 2000);
                        }
                    });
                });
            }

            // Setup admin actions
            handleAdminAction('#ocpay-clear-logs', 'ocpay_clear_logs', 
                'Are you sure you want to clear all logs?', 
                function() { setTimeout(function() { location.reload(); }, 1000); });
            
            handleAdminAction('#ocpay-test-connection', 'ocpay_test_connection');
            handleAdminAction('#ocpay-manual-check', 'ocpay_manual_check', 
                null,
                function() { setTimeout(function() { location.reload(); }, 2000); });
        });
    }

    // Frontend functionality
    $(document).ready(function () {
        // Payment method selection
        $('form.checkout').on('change', 'input[name="payment_method"]', function () {
            const isOCPay = 'ocpay' === $(this).val();
            $('.payment_box.payment_method_ocpay')[isOCPay ? 'slideDown' : 'slideUp']();
        });

        // Initialize on load
        if ($('input[name="payment_method"]:checked').val() === 'ocpay') {
            $('.payment_box.payment_method_ocpay').show();
        }

        // Payment return messages
        if (typeof ocpayPaymentReturn !== 'undefined') {
            const msg = ocpayPaymentReturn;
            const color = msg.status === 'success' ? 'green' : msg.status === 'error' ? 'red' : 'orange';
            $('form.checkout, .woocommerce-order').prepend(
                '<div style="padding:15px;margin:10px 0;background:#f0f0f0;border-left:4px solid ' + color + '">' +
                '<p><strong>' + msg.message + '</strong></p></div>'
            );
        }
    });

})(jQuery);
