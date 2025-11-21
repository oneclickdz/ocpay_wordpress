/**
 * OCPay Order Status Poller - Smart client-side status checking
 * 
 * Automatically polls order status with intelligent intervals
 * 
 * @package OCPay_WooCommerce
 */

(function ($) {
    'use strict';

    /**
     * Order Status Poller Class
     */
    class OCPayOrderStatusPoller {
        constructor(config) {
            this.orderId = config.orderId;
            this.nonce = config.nonce;
            this.ajaxUrl = config.ajaxUrl;
            this.redirectUrl = config.redirectUrl || null;
            this.maxAttempts = config.maxAttempts || 40; // 40 attempts max
            this.currentAttempt = 0;
            this.isPolling = false;
            this.intervalId = null;
            
            // Adaptive intervals: Start fast, slow down gradually
            this.intervals = [
                2000,  // 2s  - First check (immediate)
                3000,  // 3s  - Second check
                5000,  // 5s  - Third check
                5000,  // 5s  - Fourth check
                10000, // 10s - After 15s
                10000, // 10s
                15000, // 15s - After 45s
                15000, // 15s
                20000, // 20s - After 1m 15s
                30000  // 30s - After that, continue with 30s
            ];
            
            this.callbacks = {
                onStart: config.onStart || null,
                onCheck: config.onCheck || null,
                onSuccess: config.onSuccess || null,
                onFailed: config.onFailed || null,
                onTimeout: config.onTimeout || null,
                onError: config.onError || null
            };

            this.init();
        }

        init() {
            console.log('OCPay Order Status Poller initialized for order #' + this.orderId);
            
            // Update UI
            this.updateUI('checking', 'Checking payment status...');
            
            // Start polling immediately
            this.startPolling();
        }

        startPolling() {
            if (this.isPolling) {
                console.log('Polling already in progress');
                return;
            }

            this.isPolling = true;
            
            if (this.callbacks.onStart) {
                this.callbacks.onStart();
            }

            // First check immediately
            this.checkStatus();
        }

        stopPolling() {
            this.isPolling = false;
            if (this.intervalId) {
                clearTimeout(this.intervalId);
                this.intervalId = null;
            }
            console.log('Polling stopped');
        }

        getCurrentInterval() {
            // Use adaptive intervals, fallback to last interval (30s)
            const index = Math.min(this.currentAttempt, this.intervals.length - 1);
            return this.intervals[index];
        }

        scheduleNextCheck() {
            if (!this.isPolling) {
                return;
            }

            if (this.currentAttempt >= this.maxAttempts) {
                this.handleTimeout();
                return;
            }

            const interval = this.getCurrentInterval();
            console.log('Next check in ' + (interval / 1000) + 's (attempt ' + (this.currentAttempt + 1) + '/' + this.maxAttempts + ')');

            this.intervalId = setTimeout(() => {
                this.checkStatus();
            }, interval);
        }

        checkStatus() {
            if (!this.isPolling) {
                return;
            }

            this.currentAttempt++;
            
            console.log('Checking order status (attempt ' + this.currentAttempt + ')...');
            
            if (this.callbacks.onCheck) {
                this.callbacks.onCheck(this.currentAttempt, this.maxAttempts);
            }

            // Update UI with attempt info
            this.updateUI('checking', 
                'Checking payment status... (attempt ' + this.currentAttempt + '/' + this.maxAttempts + ')'
            );

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ocpay_check_payment_status',
                    order_id: this.orderId,
                    nonce: this.nonce
                },
                timeout: 10000, // 10s timeout per request
                success: (response) => {
                    this.handleResponse(response);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    this.handleError(textStatus, errorThrown);
                }
            });
        }

        handleResponse(response) {
            console.log('Status check response:', response);

            if (!response.success) {
                console.error('Status check failed:', response.data);
                // Don't stop on API errors, continue polling
                this.scheduleNextCheck();
                return;
            }

            const data = response.data;
            const status = data.status;

            // Check if order status changed to completed/processing
            if (status === 'completed' || status === 'processing') {
                this.handleSuccess(data);
            } 
            // Check if order failed
            else if (status === 'failed' || status === 'cancelled') {
                this.handleFailed(data);
            }
            // Still pending, continue polling
            else if (status === 'pending' || status === 'on-hold') {
                this.scheduleNextCheck();
            }
            // Unknown status
            else {
                console.warn('Unknown order status:', status);
                this.scheduleNextCheck();
            }
        }

        handleSuccess(data) {
            console.log('Payment confirmed!');
            this.stopPolling();
            
            this.updateUI('success', 'Payment confirmed! Redirecting...');

            if (this.callbacks.onSuccess) {
                this.callbacks.onSuccess(data);
            }

            // Redirect after 2 seconds
            setTimeout(() => {
                if (this.redirectUrl) {
                    window.location.href = this.redirectUrl;
                } else {
                    // Reload page to show updated status
                    window.location.reload();
                }
            }, 2000);
        }

        handleFailed(data) {
            console.log('Payment failed');
            this.stopPolling();
            
            this.updateUI('failed', 'Payment failed. Please try again or contact support.');

            if (this.callbacks.onFailed) {
                this.callbacks.onFailed(data);
            }
        }

        handleTimeout() {
            console.log('Polling timeout - max attempts reached');
            this.stopPolling();
            
            this.updateUI('timeout', 
                'Payment verification is taking longer than expected. We will notify you via email once confirmed. You can also check your order status in your account.'
            );

            if (this.callbacks.onTimeout) {
                this.callbacks.onTimeout();
            }
        }

        handleError(textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            
            if (this.callbacks.onError) {
                this.callbacks.onError(textStatus, errorThrown);
            }

            // Continue polling even on errors (might be temporary network issue)
            this.scheduleNextCheck();
        }

        updateUI(type, message) {
            const $statusBox = $('#ocpay-status-box');
            if ($statusBox.length === 0) {
                return;
            }

            // Update message
            $statusBox.find('.ocpay-status-message').text(message);

            // Update icon/spinner
            const $icon = $statusBox.find('.ocpay-status-icon');
            $icon.removeClass('checking success failed timeout');
            $icon.addClass(type);

            // Update progress if checking
            if (type === 'checking' && this.maxAttempts > 0) {
                const progress = Math.min(100, (this.currentAttempt / this.maxAttempts) * 100);
                $statusBox.find('.ocpay-progress-bar').css('width', progress + '%');
            }

            // Add attempt counter
            if (type === 'checking') {
                const attemptText = 'Attempt ' + this.currentAttempt + ' of ' + this.maxAttempts;
                $statusBox.find('.ocpay-attempt-counter').text(attemptText);
            }
        }

        // Public method to manually trigger a check
        manualCheck() {
            if (this.isPolling) {
                console.log('Manual check requested while polling');
                // Reset interval and check now
                if (this.intervalId) {
                    clearTimeout(this.intervalId);
                }
                this.checkStatus();
            } else {
                console.log('Manual check requested - restarting polling');
                this.startPolling();
            }
        }

        // Public method to get current state
        getState() {
            return {
                isPolling: this.isPolling,
                currentAttempt: this.currentAttempt,
                maxAttempts: this.maxAttempts,
                progress: (this.currentAttempt / this.maxAttempts) * 100
            };
        }
    }

    /**
     * jQuery plugin wrapper
     */
    $.fn.ocpayOrderStatusPoller = function(config) {
        return this.each(function() {
            const $element = $(this);
            
            // Check if already initialized
            if ($element.data('ocpayPoller')) {
                console.log('Poller already initialized');
                return;
            }

            // Create poller instance
            const poller = new OCPayOrderStatusPoller(config);
            
            // Store instance
            $element.data('ocpayPoller', poller);
            
            // Bind manual check button if exists
            $element.on('click', '.ocpay-check-status-btn', function(e) {
                e.preventDefault();
                poller.manualCheck();
            });
        });
    };

    /**
     * Auto-initialize on thank you page
     */
    $(document).ready(function() {
        // Check if we have order status poller config
        if (typeof ocpayPollerConfig !== 'undefined' && ocpayPollerConfig.orderId) {
            console.log('Auto-initializing order status poller');
            
            // Create status box if it doesn't exist
            if ($('#ocpay-status-box').length === 0) {
                const statusBox = `
                    <div id="ocpay-status-box" class="ocpay-status-box">
                        <div class="ocpay-status-icon checking">
                            <span class="spinner"></span>
                        </div>
                        <div class="ocpay-status-content">
                            <h3 class="ocpay-status-title">Verifying Payment</h3>
                            <p class="ocpay-status-message">Please wait while we verify your payment...</p>
                            <div class="ocpay-progress-wrapper">
                                <div class="ocpay-progress-bar"></div>
                            </div>
                            <p class="ocpay-attempt-counter"></p>
                            <button class="button ocpay-check-status-btn" style="display:none;">Check Status Now</button>
                        </div>
                    </div>
                `;
                $('.woocommerce-order').prepend(statusBox);
            }

            // Initialize poller
            $('#ocpay-status-box').ocpayOrderStatusPoller(ocpayPollerConfig);
        }
    });

    // Expose class globally for manual usage
    window.OCPayOrderStatusPoller = OCPayOrderStatusPoller;

})(jQuery);
