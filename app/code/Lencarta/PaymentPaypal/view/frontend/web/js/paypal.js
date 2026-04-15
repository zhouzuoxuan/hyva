(function (window, document) {
    'use strict';

    var CONTAINER_ID = 'lencarta-paypal-button';

    var sdkPromise = null;
    var buttonsInstance = null;
    var renderTimer = null;
    var containerObserver = null;

    var isRendering = false;
    var isRendered = false;
    var lastButtonsSignature = '';
    var hasBooted = false;

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
                canStart: false,
                blockingMessage: 'Checkout state is not ready yet.',
                signature: ''
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

    function waitForPaypalGlobal(timeoutMs) {
        timeoutMs = typeof timeoutMs === 'number' ? timeoutMs : 10000;

        return new Promise(function (resolve, reject) {
            var startedAt = Date.now();

            (function poll() {
                if (window.paypal && typeof window.paypal.Buttons === 'function') {
                    resolve(window.paypal);
                    return;
                }

                if (Date.now() - startedAt >= timeoutMs) {
                    reject(new Error('PayPal SDK loaded timeout.'));
                    return;
                }

                window.setTimeout(poll, 50);
            })();
        });
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
                waitForPaypalGlobal(10000).then(resolve).catch(reject);
                return;
            }

            var script = document.createElement('script');
            script.src = buildSdkUrl(config);
            script.async = true;
            script.defer = true;
            script.setAttribute('data-lencarta-paypal-sdk', '1');

            script.onload = function () {
                waitForPaypalGlobal(3000).then(resolve).catch(reject);
            };

            script.onerror = function () {
                reject(new Error('Failed to load PayPal SDK.'));
            };

            document.head.appendChild(script);
        });

        return sdkPromise;
    }

    function containerHasContent(container) {
        return !!(container && container.childNodes && container.childNodes.length > 0);
    }

    function setContainerState(state) {
        var container = getContainer();

        if (!container) {
            return;
        }

        container.setAttribute('data-paypal-state', state || 'idle');
    }

    function clearContainer() {
        var container = getContainer();

        if (container) {
            container.innerHTML = '';
            container.removeAttribute('data-paypal-rendered');
        }
    }

    function destroyButtons() {
        if (buttonsInstance && typeof buttonsInstance.close === 'function') {
            try {
                buttonsInstance.close();
            } catch (e) {
                // ignore paypal internal close issues
            }
        }

        buttonsInstance = null;
        isRendered = false;
        isRendering = false;
        lastButtonsSignature = '';
        clearContainer();
        setContainerState('idle');
    }

    function getButtonsSignature() {
        var config = getPaypalConfig();

        return JSON.stringify({
            clientId: config.clientId || '',
            currency: config.currency || 'USD',
            intent: config.intent || 'capture',
            locale: config.locale || '',
            disableFunding: config.disableFunding || '',
            enableFunding: config.enableFunding || '',
            buttonColor: config.buttonColor || 'gold',
            buttonShape: config.buttonShape || 'rect',
            buttonLabel: config.buttonLabel || 'paypal'
        });
    }

    function notifyCheckoutMessage(message) {
        var checkout = getCheckoutState();

        if (checkout) {
            checkout.message = message || '';
        }
    }

    function canAttemptInitialRender() {
        var config = getPaypalConfig();
        var checkout = getCheckoutState();
        var container = getContainer();

        if (!container) {
            return false;
        }

        if (!config.clientId) {
            return false;
        }

        if (!checkout) {
            return false;
        }

        return true;
    }

    function scheduleRender(delay) {
        if (typeof delay === 'undefined') {
            delay = 60;
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

        if (!state.canStart) {
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

                if (!state.canStart) {
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

    function renderPaypalButton(force) {
        var container = getContainer();
        var signature = getButtonsSignature();

        if (!container) {
            startContainerObserver();
            return;
        }

        if (!canAttemptInitialRender()) {
            startContainerObserver();
            return;
        }

        if (!force && isRendering) {
            return;
        }

        if (!force && isRendered && lastButtonsSignature === signature && containerHasContent(container)) {
            setContainerState('ready');
            return;
        }

        if (isRendered && lastButtonsSignature === signature && !containerHasContent(container)) {
            buttonsInstance = null;
            isRendered = false;
        }

        if (isRendered && lastButtonsSignature !== signature) {
            destroyButtons();
        }

        isRendering = true;
        setContainerState('loading');

        loadPaypalSdk()
            .then(function () {
                var liveContainer = getContainer();

                if (!liveContainer) {
                    isRendering = false;
                    setContainerState('idle');
                    startContainerObserver();
                    return;
                }

                if (isRendered && lastButtonsSignature === signature && containerHasContent(liveContainer)) {
                    isRendering = false;
                    setContainerState('ready');
                    return;
                }

                var buttons = buildButtons();

                if (typeof buttons.isEligible === 'function' && !buttons.isEligible()) {
                    clearContainer();
                    isRendering = false;
                    isRendered = false;
                    buttonsInstance = null;
                    setContainerState('ineligible');
                    return;
                }

                clearContainer();
                buttonsInstance = buttons;

                return buttons.render(liveContainer).then(function () {
                    isRendering = false;
                    isRendered = true;
                    lastButtonsSignature = signature;
                    liveContainer.setAttribute('data-paypal-rendered', '1');
                    setContainerState('ready');
                    notifyCheckoutMessage('');
                    stopContainerObserver();
                });
            })
            .catch(function (error) {
                isRendering = false;
                isRendered = false;
                buttonsInstance = null;
                setContainerState('error');

                console.error('PayPal render failed:', error);
                notifyCheckoutMessage(
                    (error && error.message) || 'Unable to load PayPal checkout.'
                );
            });
    }

    function startContainerObserver() {
        if (containerObserver) {
            return;
        }

        containerObserver = new MutationObserver(function () {
            if (getContainer()) {
                scheduleRender(30);
            }
        });

        containerObserver.observe(document.body || document.documentElement, {
            childList: true,
            subtree: true
        });
    }

    function stopContainerObserver() {
        if (!containerObserver) {
            return;
        }

        containerObserver.disconnect();
        containerObserver = null;
    }

    function bindEvents() {
        document.addEventListener('DOMContentLoaded', function () {
            scheduleRender(80);
            scheduleRender(500);
        });

        window.addEventListener('lencarta-checkout-ready', function () {
            scheduleRender(50);
            scheduleRender(250);
        });

        window.addEventListener('lencarta-checkout-paypal-state-changed', function () {
            var container = getContainer();

            if (!container) {
                scheduleRender(50);
                return;
            }

            if (!isRendered || !containerHasContent(container)) {
                scheduleRender(30);
            }
        });

        window.addEventListener('pageshow', function () {
            scheduleRender(80);
        });
    }

    function boot() {
        if (hasBooted) {
            return;
        }

        hasBooted = true;
        bindEvents();
        startContainerObserver();
        scheduleRender(120);
    }

    window.LencartaPaypal = {
        forceRender: function () {
            renderPaypalButton(true);
        },
        destroy: function () {
            destroyButtons();
        },
        getStatus: function () {
            return {
                sdkLoaded: !!(window.paypal && typeof window.paypal.Buttons === 'function'),
                isRendering: isRendering,
                isRendered: isRendered,
                hasContainer: !!getContainer(),
                hasContent: containerHasContent(getContainer()),
                signature: lastButtonsSignature
            };
        }
    };

    boot();
})(window, document);
