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

        // Run health check
        $('#ocpay-run-health-check').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Checking...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_run_health_check',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        const { passed, total } = response.data;
                        const percentage = Math.round((passed / total) * 100);
                        
                        // Update health score display
                        $('.ocpay-health-score .score').text(percentage + '%');
                        
                        // Update health score gradient based on percentage
                        const $scoreDiv = $('.ocpay-health-summary');
                        $scoreDiv.removeClass('healthy warning critical');
                        if (percentage >= 80) {
                            $scoreDiv.addClass('healthy');
                        } else if (percentage >= 50) {
                            $scoreDiv.addClass('warning');
                        } else {
                            $scoreDiv.addClass('critical');
                        }
                        
                        $btn.text('✓ Updated').css('color', 'green');
                        showNotice('success', 'Health check completed: ' + passed + '/' + total + ' checks passed');
                    } else {
                        showNotice('error', 'Health check failed: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    showNotice('error', 'Health check error: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $btn.text(originalText).css('color', '');
                    }, 3000);
                },
            });
        });

        // Refresh stats
        $('#ocpay-refresh-stats').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Refreshing...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_get_stats',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        const stats = response.data;
                        
                        // Update stat values in the stat items
                        const $statItems = $('.stat-item');
                        $statItems.eq(0).find('.stat-value').text(stats.pending_count);
                        $statItems.eq(1).find('.stat-value').text(stats.recent_count);
                        $statItems.eq(2).find('.stat-value').text(stats.stuck_count);
                        $statItems.eq(3).find('.stat-value').text(stats.today_processed);
                        
                        $btn.text('✓ Updated').css('color', 'green');
                        showNotice('success', 'Statistics refreshed successfully');
                    } else {
                        showNotice('error', 'Stats refresh failed: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    showNotice('error', 'Stats refresh error: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $btn.text(originalText).css('color', '');
                    }, 3000);
                },
            });
        });

        // Test cron
        $('#ocpay-test-cron').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Testing...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_test_cron',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $btn.text('✓ Tested').css('color', 'green');
                        showNotice('success', 'Cron status retrieved successfully');
                        
                        // Optionally reload to show updated cron times
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice('error', 'Cron test failed: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    showNotice('error', 'Cron test error: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $btn.text(originalText).css('color', '');
                    }, 3000);
                },
            });
        });

        // Manual status check button
        $('#ocpay-manual-check').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const $result = $('#ocpay-check-result');
            const $spinner = $('#ocpay-check-spinner');
            const nonce = $btn.data('nonce');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Checking...');
            $spinner.css('visibility', 'visible');
            $result.html('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ocpay_manual_check',
                    nonce: nonce,
                },
                success: function (response) {
                    if (response.success) {
                        showResult($result, 'success', '✓ ' + response.data.message);
                        $btn.text('✓ Completed').css('color', 'green');
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        showResult($result, 'error', '✗ ' + response.data);
                    }
                },
                error: function (xhr, status, error) {
                    showResult($result, 'error', '✗ Error: ' + error);
                },
                complete: function () {
                    $spinner.css('visibility', 'hidden');
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
