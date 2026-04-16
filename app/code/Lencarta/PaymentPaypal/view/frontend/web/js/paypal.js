(function (window, document) {
    'use strict';

    var CONTAINER_ID = 'lencarta-paypal-button';
    var sdkPromise = null;
    var buttonsInstance = null;
    var renderTimer = null;
    var lastRenderSignature = '';
    var observer = null;
    var activePaypalOrderId = '';
    var activeSyncSignature = '';
    var syncPromise = null;
    var invalidatePromise = null;

    function getPaypalConfig() {
        if (window.lencartaPaypalConfig && typeof window.lencartaPaypalConfig === 'object') {
            return window.lencartaPaypalConfig;
        }

        var node = document.getElementById('lencarta-paypal-config');
        if (!node) {
            return {};
        }

        var raw = node.getAttribute('data-config') || '';
        if (!raw) {
            return {};
        }

        try {
            window.lencartaPaypalConfig = JSON.parse(raw);
            return window.lencartaPaypalConfig || {};
        } catch (e) {
            console.error('Unable to parse PayPal config.', e);
            return {};
        }
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

    function isReadyForSync(state) {
        return !!(state && state.canRender && state.canStart);
    }

    function buildStateNotReadyError(state, fallback) {
        var error = new Error((state && state.blockingMessage) || fallback || 'PayPal checkout is not ready yet.');
        error.code = 'PAYPAL_STATE_NOT_READY';
        error.silent = true;

        return error;
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

    function getButtonStyleValue(config, key, fallback) {
        if (config[key]) {
            return config[key];
        }

        if (config.button && config.button[key]) {
            return config.button[key];
        }

        return fallback;
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

        return (config.sdkUrl || 'https://www.paypal.com/sdk/js') + '?' + params.toString();
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
            buttonColor: getButtonStyleValue(config, 'color', 'gold'),
            buttonShape: getButtonStyleValue(config, 'shape', 'rect'),
            buttonLabel: getButtonStyleValue(config, 'label', 'paypal'),
            canRender: !!state.canRender,
            signature: state.signature || '',
            activePaypalOrderId: activePaypalOrderId || ''
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

    function parseJsonResponse(response) {
        return response.text().then(function (text) {
            if (!text) {
                return {};
            }

            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('PayPal endpoint did not return valid JSON.');
            }
        });
    }

    function postForm(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return parseJsonResponse(response);
        });
    }

    function resetActiveOrder() {
        activePaypalOrderId = '';
        activeSyncSignature = '';
        syncPromise = null;
    }

    function syncOrderRequest(force) {
        var config = getPaypalConfig();
        var state = getPaypalState();
        var signature = state.signature || '';
        var syncUrl = config.syncOrderUrl || config.createOrderUrl || (config.urls && config.urls.syncOrder) || '';

        if (!state.canRender) {
            return Promise.reject(buildStateNotReadyError(state, 'PayPal checkout is not ready to render yet.'));
        }

        if (!isReadyForSync(state)) {
            return Promise.reject(buildStateNotReadyError(state, 'Please complete your contact, shipping address, and shipping method first.'));
        }

        if (!syncUrl) {
            return Promise.reject(new Error('PayPal sync order URL is missing.'));
        }

        if (!force && activePaypalOrderId && activeSyncSignature === signature) {
            return Promise.resolve(activePaypalOrderId);
        }

        if (!force && syncPromise) {
            return syncPromise;
        }

        var body = new URLSearchParams({
            form_key: getFormKey(),
            checkout_signature: signature
        });

        syncPromise = postForm(syncUrl, body)
            .then(function (data) {
                var orderId = data && (data.order_id || data.paypal_order_id || data.id) || '';

                if (!data || !data.success || !orderId) {
                    throw new Error((data && data.message) || (config.i18n && config.i18n.syncError) || 'Unable to sync PayPal order.');
                }

                activePaypalOrderId = orderId;
                activeSyncSignature = data.checkout_signature || signature;
                return orderId;
            })
            .finally(function () {
                syncPromise = null;
            });

        return syncPromise;
    }

    function invalidateOrderRequest(reason) {
        var config = getPaypalConfig();
        var invalidateUrl = config.invalidateOrderUrl || (config.urls && config.urls.invalidateOrder) || '';

        if (!activePaypalOrderId && !activeSyncSignature) {
            resetActiveOrder();
            return Promise.resolve();
        }

        if (!invalidateUrl) {
            resetActiveOrder();
            return Promise.resolve();
        }

        if (invalidatePromise) {
            return invalidatePromise;
        }

        var body = new URLSearchParams({
            form_key: getFormKey(),
            reason: reason || 'invalidated'
        });

        invalidatePromise = postForm(invalidateUrl, body)
            .catch(function () {
                return {};
            })
            .finally(function () {
                resetActiveOrder();
                invalidatePromise = null;
            });

        return invalidatePromise;
    }

    function createOrderRequest() {
        return syncOrderRequest(false);
    }

    function captureOrderRequest(paypalOrderId) {
        var config = getPaypalConfig();
        var captureUrl =
            config.captureOrderUrl ||
            config.captureUrl ||
            (config.urls && config.urls.captureOrder) ||
            '';

        if (!captureUrl) {
            throw new Error('PayPal capture order URL is missing.');
        }

        var body = new URLSearchParams({
            form_key: getFormKey(),
            paypal_order_id: paypalOrderId || ''
        });

        return postForm(captureUrl, body)
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Unable to finalize PayPal payment.');
                }

                resetActiveOrder();

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
                color: getButtonStyleValue(config, 'color', 'gold'),
                shape: getButtonStyleValue(config, 'shape', 'rect'),
                label: getButtonStyleValue(config, 'label', 'paypal'),
                tagline: false
            },

            onClick: function (data, actions) {
                var state = getPaypalState();

                if (!state.canStart) {
                    notifyCheckoutMessage(state.blockingMessage || 'Please complete checkout details first.');

                    if (actions && typeof actions.reject === 'function') {
                        return actions.reject();
                    }

                    return false;
                }

                notifyCheckoutMessage('');

                return syncOrderRequest(false)
                    .then(function () {
                        if (actions && typeof actions.resolve === 'function') {
                            return actions.resolve();
                        }

                        return true;
                    })
                    .catch(function (error) {
                        notifyCheckoutMessage((error && error.message) || 'Unable to sync PayPal checkout.');

                        if (actions && typeof actions.reject === 'function') {
                            return actions.reject();
                        }

                        return false;
                    });
            },

            createOrder: function () {
                return createOrderRequest();
            },

            onApprove: function (data) {
                return captureOrderRequest(data.orderID);
            },

            onCancel: function () {
                notifyCheckoutMessage('');
                return invalidateOrderRequest('cancelled').finally(function () {
                    scheduleRender(50);
                });
            },

            onError: function (err) {
                console.error('PayPal error:', err);
                notifyCheckoutMessage((err && err.message) || 'Unable to start PayPal checkout.');
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
            if (activePaypalOrderId || activeSyncSignature) {
                invalidateOrderRequest('state_not_renderable');
            }

            destroyButtons();
            lastRenderSignature = '';
            notifyCheckoutMessage('');
            return;
        }

        if (!isReadyForSync(state)) {
            if (activePaypalOrderId || activeSyncSignature) {
                invalidateOrderRequest('state_not_ready');
            }

            destroyButtons();
            lastRenderSignature = '';
            notifyCheckoutMessage('');
            return;
        }

        syncOrderRequest(false)
            .then(function () {
                var signature = getRenderSignature();

                if (buttonsInstance && lastRenderSignature === signature && container.childNodes.length > 0) {
                    return;
                }

                return loadPaypalSdk()
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
                    });
            })
            .catch(function (error) {
                destroyButtons();
                lastRenderSignature = '';

                if (error && error.silent) {
                    notifyCheckoutMessage('');
                    return;
                }

                console.error('PayPal render failed:', error);
                notifyCheckoutMessage((error && error.message) || 'Unable to load PayPal checkout.');
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
            var state = getPaypalState();
            var currentSignature = state.signature || '';

            if (!state.canRender || !isReadyForSync(state)) {
                if (activePaypalOrderId || activeSyncSignature) {
                    invalidateOrderRequest('checkout_state_not_ready').finally(function () {
                        scheduleRender(20);
                    });
                    return;
                }

                scheduleRender(20);
                return;
            }

            if (activeSyncSignature && activeSyncSignature !== currentSignature) {
                invalidateOrderRequest('checkout_state_changed').finally(function () {
                    scheduleRender(20);
                });
                return;
            }

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
            invalidateOrderRequest('manual_destroy');
            destroyButtons();
            lastRenderSignature = '';
        },
        invalidate: function (reason) {
            return invalidateOrderRequest(reason || 'manual_invalidate');
        },
        sync: function () {
            return syncOrderRequest(true);
        }
    };

    boot();
})(window, document);
