/**
 * OCPay WooCommerce Admin JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Clear logs button
        $('#ocpay-clear-logs').on('click', function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                return;
            }

            const $btn = $(this);
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_clear_logs',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $btn.text('✓ Cleared').css('color', 'green');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotice('error', 'Error clearing logs: ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    showNotice('error', 'Error clearing logs: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $btn.text(originalText).css('color', '');
                    }, 2000);
                },
            });
        });

        // Test API connection
        $('#ocpay-test-connection').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const $result = $('#ocpay-connection-result');
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Testing...');
            $result.html('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_test_connection',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        showResult($result, 'success', '✓ ' + response.data.message);
                        $btn.text('✓ Connected').css('color', 'green');
                    } else {
                        showResult($result, 'error', '✗ ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    showResult($result, 'error', '✗ Connection error: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $btn.text(originalText).css('color', '');
                    }, 3000);
                },
            });
        });

        // Helper function to display result messages
        function showResult($container, type, message) {
            const className = type === 'success' ? 'ocpay-status-success' : 'ocpay-status-error';
            $container.html('<p class="' + className + '">' + message + '</p>');
        }

        // Helper function to show admin notices
        function showNotice(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            $('h1').after($notice);
        }
    });
})(jQuery);
