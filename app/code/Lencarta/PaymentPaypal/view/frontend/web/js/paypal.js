(function (window, document) {
    'use strict';

    var CONTAINER_ID = 'lencarta-paypal-button';
    var sdkPromise = null;
    var buttonsInstance = null;
    var renderTimer = null;
    var lastRenderSignature = '';
    var observer = null;

    function getPaypalConfig() {
        return window.lencartaPaypalConfig || {};
    }

    function getCheckoutState() {
        return window.lencartaCheckoutState || null;
    }

    function getPaypalState() {
        var checkout = getCheckoutState();

        if (!checkout || typeof checkout.getPaypalState !== 'function') {
            return {
                canRender: false,
                blockingMessage: 'Checkout state is not ready yet.'
            };
        }

        return checkout.getPaypalState();
    }

    function getContainer() {
        return document.getElementById(CONTAINER_ID);
    }

    function getFormKey() {
        var checkout = getCheckoutState();

        if (checkout && typeof checkout.getFormKey === 'function') {
            return checkout.getFormKey();
        }

        if (window.hyva && typeof window.hyva.getFormKey === 'function') {
            return window.hyva.getFormKey();
        }

        var input = document.querySelector('input[name="form_key"]');
        return input ? input.value : '';
    }

    function buildSdkUrl(config) {
        var params = new URLSearchParams();

        params.set('client-id', config.clientId || '');
        params.set('currency', config.currency || 'USD');
        params.set('components', 'buttons');
        params.set('intent', config.intent || 'capture');
        params.set('commit', 'true');

        if (config.locale) {
            params.set('locale', config.locale);
        }

        if (config.disableFunding) {
            params.set('disable-funding', config.disableFunding);
        }

        if (config.enableFunding) {
            params.set('enable-funding', config.enableFunding);
        }

        if (config.debug) {
            params.set('debug', 'true');
        }

        return 'https://www.paypal.com/sdk/js?' + params.toString();
    }

    function loadPaypalSdk() {
        var config = getPaypalConfig();

        if (window.paypal && typeof window.paypal.Buttons === 'function') {
            return Promise.resolve(window.paypal);
        }

        if (sdkPromise) {
            return sdkPromise;
        }

        sdkPromise = new Promise(function (resolve, reject) {
            if (!config.clientId) {
                reject(new Error('PayPal clientId is missing.'));
                return;
            }

            var existing = document.querySelector('script[data-lencarta-paypal-sdk="1"]');

            if (existing) {
                existing.addEventListener('load', function () {
                    resolve(window.paypal);
                }, { once: true });

                existing.addEventListener('error', function () {
                    reject(new Error('Failed to load PayPal SDK.'));
                }, { once: true });

                return;
            }

            var script = document.createElement('script');
            script.src = buildSdkUrl(config);
            script.async = true;
            script.defer = true;
            script.setAttribute('data-lencarta-paypal-sdk', '1');

            script.onload = function () {
                if (window.paypal && typeof window.paypal.Buttons === 'function') {
                    resolve(window.paypal);
                    return;
                }

                reject(new Error('PayPal SDK loaded but window.paypal is unavailable.'));
            };

            script.onerror = function () {
                reject(new Error('Failed to load PayPal SDK.'));
            };

            document.head.appendChild(script);
        });

        return sdkPromise;
    }

    function clearContainer() {
        var container = getContainer();
        if (container) {
            container.innerHTML = '';
        }
    }

    function destroyButtons() {
        if (buttonsInstance && typeof buttonsInstance.close === 'function') {
            try {
                buttonsInstance.close();
            } catch (e) {
                // ignore
            }
        }

        buttonsInstance = null;
        clearContainer();
    }

    function getRenderSignature() {
        var config = getPaypalConfig();
        var state = getPaypalState();

        return JSON.stringify({
            clientId: config.clientId || '',
            currency: config.currency || 'USD',
            buttonColor: config.buttonColor || 'gold',
            buttonShape: config.buttonShape || 'rect',
            buttonLabel: config.buttonLabel || 'paypal',
            canRender: !!state.canRender,
            signature: state.signature || '',
            termsAccepted: !!state.termsAccepted
        });
    }

    function notifyCheckoutMessage(message) {
        var checkout = getCheckoutState();

        if (checkout) {
            checkout.message = message || '';
        }
    }

    function scheduleRender(delay) {
        if (typeof delay === 'undefined') {
            delay = 50;
        }

        if (renderTimer) {
            clearTimeout(renderTimer);
        }

        renderTimer = setTimeout(function () {
            renderTimer = null;
            renderPaypalButton();
        }, delay);
    }

    function createOrderRequest() {
        var config = getPaypalConfig();
        var state = getPaypalState();

        if (!state.canRender) {
            throw new Error(state.blockingMessage || 'Unable to start PayPal checkout.');
        }

        var createUrl =
            config.createOrderUrl ||
            (config.urls && config.urls.createOrder) ||
            '';

        if (!createUrl) {
            throw new Error('PayPal create order URL is missing.');
        }

        var body = new URLSearchParams({
            form_key: getFormKey(),
            checkout_signature: state.signature || ''
        });

        return fetch(createUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success || !data.order_id) {
                    throw new Error(
                        (data && data.message) || 'Unable to start PayPal checkout.'
                    );
                }

                return data.order_id;
            });
    }

    function captureOrderRequest(paypalOrderId) {
        var config = getPaypalConfig();
        var captureUrl =
            config.captureOrderUrl ||
            (config.urls && config.urls.captureOrder) ||
            '';

        if (!captureUrl) {
            throw new Error('PayPal capture order URL is missing.');
        }

        var body = new URLSearchParams({
            form_key: getFormKey(),
            paypal_order_id: paypalOrderId || ''
        });

        return fetch(captureUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error(
                        (data && data.message) || 'Unable to finalize PayPal payment.'
                    );
                }

                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }

                if (config.successUrl) {
                    window.location.href = config.successUrl;
                    return;
                }

                window.location.reload();
            });
    }

    function buildButtons() {
        var config = getPaypalConfig();

        return window.paypal.Buttons({
            style: {
                layout: 'vertical',
                color: config.buttonColor || 'gold',
                shape: config.buttonShape || 'rect',
                label: config.buttonLabel || 'paypal',
                tagline: false
            },

            onClick: function (data, actions) {
                var state = getPaypalState();

                if (!state.canRender) {
                    notifyCheckoutMessage(
                        state.blockingMessage || 'Please complete checkout details first.'
                    );

                    if (actions && typeof actions.reject === 'function') {
                        return actions.reject();
                    }

                    return false;
                }

                notifyCheckoutMessage('');

                if (actions && typeof actions.resolve === 'function') {
                    return actions.resolve();
                }

                return true;
            },

            createOrder: function () {
                return createOrderRequest();
            },

            onApprove: function (data) {
                return captureOrderRequest(data.orderID);
            },

            onCancel: function () {
                notifyCheckoutMessage('');
            },

            onError: function (err) {
                console.error('PayPal error:', err);
                notifyCheckoutMessage(
                    (err && err.message) || 'Unable to start PayPal checkout.'
                );
            }
        });
    }

    function renderPaypalButton() {
        var container = getContainer();
        var state = getPaypalState();

        if (!container) {
            return;
        }

        if (!state.canRender) {
            destroyButtons();
            lastRenderSignature = '';
            return;
        }

        var signature = getRenderSignature();

        if (
            buttonsInstance &&
            lastRenderSignature === signature &&
            container.childNodes.length > 0
        ) {
            return;
        }

        loadPaypalSdk()
            .then(function () {
                var liveContainer = getContainer();
                var liveState = getPaypalState();

                if (!liveContainer || !liveState.canRender) {
                    destroyButtons();
                    lastRenderSignature = '';
                    return;
                }

                destroyButtons();

                var buttons = buildButtons();

                if (typeof buttons.isEligible === 'function' && !buttons.isEligible()) {
                    clearContainer();
                    lastRenderSignature = '';
                    return;
                }

                buttonsInstance = buttons;

                return buttons.render(liveContainer).then(function () {
                    lastRenderSignature = signature;
                    notifyCheckoutMessage('');
                });
            })
            .catch(function (error) {
                console.error('PayPal render failed:', error);
                notifyCheckoutMessage(
                    (error && error.message) || 'Unable to load PayPal checkout.'
                );
            });
    }

    function startObserver() {
        if (observer) {
            return;
        }

        observer = new MutationObserver(function () {
            if (getContainer()) {
                scheduleRender(30);
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }

    function boot() {
        startObserver();

        document.addEventListener('DOMContentLoaded', function () {
            scheduleRender(30);
            scheduleRender(300);
            scheduleRender(900);
        });

        window.addEventListener('lencarta-checkout-ready', function () {
            scheduleRender(30);
            scheduleRender(250);
        });

        window.addEventListener('lencarta-checkout-paypal-state-changed', function () {
            scheduleRender(20);
        });

        window.addEventListener('pageshow', function () {
            scheduleRender(50);
        });

        scheduleRender(50);
        scheduleRender(400);
        scheduleRender(1200);
    }

    window.LencartaPaypal = {
        forceRender: function () {
            scheduleRender(0);
        },
        destroy: function () {
            destroyButtons();
            lastRenderSignature = '';
        }
    };

    boot();
})(window, document);
