(function () {
    'use strict';

    const BUTTON_CONTAINER_ID = 'lencarta-paypal-button';

    let sdkPromise = null;
    let booted = false;
    let listenersBound = false;

    let renderInProgress = false;
    let rendered = false;
    let renderedContainer = null;
    let paypalActions = null;
    let renderToken = 0;

    function getCheckoutState() {
        return window.lencartaCheckoutState || null;
    }

    function getPaypalConfig() {
        const state = getCheckoutState();
        const stateConfig = state && state.config ? state.config : {};

        return Object.assign(
            {},
            window.lencartaPaypalConfig || {},
            stateConfig.paypal || {},
            {
                urls: Object.assign(
                    {},
                    (window.lencartaPaypalConfig && window.lencartaPaypalConfig.urls) || {},
                    (stateConfig.paypal && stateConfig.paypal.urls) || {},
                    stateConfig.urls || {}
                ),
                i18n: Object.assign(
                    {},
                    (window.lencartaPaypalConfig && window.lencartaPaypalConfig.i18n) || {},
                    (stateConfig.paypal && stateConfig.paypal.i18n) || {},
                    stateConfig.i18n || {}
                )
            }
        );
    }

    function getButtonContainer() {
        return document.getElementById(BUTTON_CONTAINER_ID);
    }

    function getFormKey() {
        if (window.hyva && typeof window.hyva.getFormKey === 'function') {
            return window.hyva.getFormKey();
        }

        const input = document.querySelector('input[name="form_key"]');
        return input ? input.value : '';
    }

    function translate(key, fallback) {
        const config = getPaypalConfig();
        const dict = config.i18n || {};
        return dict[key] || fallback || key;
    }

    function setCheckoutMessage(message) {
        const state = getCheckoutState();
        if (state) {
            state.message = message || '';
        }
    }

    function isTermsAccepted() {
        const state = getCheckoutState();
        return !!(state && state.termsAccepted);
    }

    function getUrl(candidates) {
        const config = getPaypalConfig();
        const urls = config.urls || {};

        for (let i = 0; i < candidates.length; i++) {
            const key = candidates[i];
            if (urls[key]) {
                return urls[key];
            }
        }

        return '';
    }

    function ignoreZoidError(error) {
        if (!error) {
            return false;
        }

        const message = String(error && error.message ? error.message : error);
        return message.indexOf('zoid destroyed all components') !== -1;
    }

    function destroyRenderedInstance(clearContainer = true) {
        const previousContainer = renderedContainer || getButtonContainer();

        renderToken += 1;
        renderInProgress = false;
        rendered = false;
        paypalActions = null;
        renderedContainer = null;

        if (clearContainer && previousContainer) {
            previousContainer.innerHTML = '';
        }
    }

    function syncButtonState() {
        if (!paypalActions) {
            return;
        }

        try {
            if (isTermsAccepted()) {
                paypalActions.enable();
            } else {
                paypalActions.disable();
            }
        } catch (e) {
            // ignore
        }
    }

    function canRenderButton() {
        const config = getPaypalConfig();
        const container = getButtonContainer();

        return !!(
            config &&
            config.clientId &&
            container &&
            isTermsAccepted()
        );
    }

    function loadPaypalSdk() {
        if (window.paypal && typeof window.paypal.Buttons === 'function') {
            return Promise.resolve(window.paypal);
        }

        if (sdkPromise) {
            return sdkPromise;
        }

        const config = getPaypalConfig();

        sdkPromise = new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-lencarta-paypal-sdk="1"]');

            if (existing) {
                existing.addEventListener('load', function () {
                    if (window.paypal && typeof window.paypal.Buttons === 'function') {
                        resolve(window.paypal);
                    } else {
                        reject(new Error('PayPal SDK failed to initialize.'));
                    }
                }, { once: true });

                existing.addEventListener('error', function () {
                    reject(new Error('Unable to load PayPal SDK.'));
                }, { once: true });

                return;
            }

            const params = new URLSearchParams({
                'client-id': config.clientId,
                currency: config.currency || 'USD',
                components: 'buttons',
                intent: config.intent || 'capture',
                commit: 'true'
            });

            if (config.locale) {
                params.set('locale', config.locale);
            }

            const script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js?' + params.toString();
            script.async = true;
            script.defer = true;
            script.setAttribute('data-lencarta-paypal-sdk', '1');

            script.onload = function () {
                if (window.paypal && typeof window.paypal.Buttons === 'function') {
                    resolve(window.paypal);
                } else {
                    reject(new Error('PayPal SDK failed to initialize.'));
                }
            };

            script.onerror = function () {
                reject(new Error('Unable to load PayPal SDK.'));
            };

            document.head.appendChild(script);
        });

        return sdkPromise;
    }

    function buildCreateOrderPayload() {
        const state = getCheckoutState();
        const shipping = state && state.shipping ? state.shipping : {};

        return {
            form_key: getFormKey(),
            email: state && state.email ? state.email : '',
            firstname: shipping.firstname || '',
            lastname: shipping.lastname || '',
            company: shipping.company || '',
            telephone: shipping.telephone || '',
            street_1: shipping.street_1 || '',
            street_2: shipping.street_2 || '',
            city: shipping.city || '',
            postcode: shipping.postcode || '',
            region: shipping.region || '',
            country_id: shipping.country_id || '',
            shipping_method: state && state.selectedShippingMethod ? state.selectedShippingMethod : '',
            terms_accepted: isTermsAccepted() ? '1' : '0'
        };
    }

    async function createOrderRequest() {
        const url = getUrl([
            'createPaypalOrder',
            'createPayPalOrder',
            'paypalCreateOrder',
            'createOrder'
        ]);

        if (!url) {
            throw new Error(translate('Unable to start PayPal checkout.', 'Unable to start PayPal checkout.'));
        }

        const payload = buildCreateOrderPayload();
        const body = new URLSearchParams(payload);

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        const data = await response.json();

        if (!data || !data.success || !data.order_id) {
            throw new Error(
                (data && data.message) ||
                translate('Unable to start PayPal checkout.', 'Unable to start PayPal checkout.')
            );
        }

        return data.order_id;
    }

    async function captureOrderRequest(orderData) {
        const url = getUrl([
            'capturePaypalOrder',
            'capturePayPalOrder',
            'paypalCaptureOrder',
            'captureOrder'
        ]);

        if (!url) {
            throw new Error(translate('Unable to capture PayPal order.', 'Unable to capture PayPal order.'));
        }

        const body = new URLSearchParams({
            form_key: getFormKey(),
            order_id: orderData.orderID || '',
            payer_id: orderData.payerID || ''
        });

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        const data = await response.json();

        if (!data || !data.success) {
            throw new Error(
                (data && data.message) ||
                translate('Unable to capture PayPal order.', 'Unable to capture PayPal order.')
            );
        }

        if (data.redirect_url) {
            window.location.href = data.redirect_url;
            return;
        }

        if (data.message) {
            setCheckoutMessage(data.message);
        }
    }

    async function cancelOrderRequest(orderData) {
        const url = getUrl([
            'cancelPaypalOrder',
            'cancelPayPalOrder',
            'paypalCancelOrder',
            'cancelOrder'
        ]);

        if (!url) {
            setCheckoutMessage(
                translate('PayPal checkout has been cancelled.', 'PayPal checkout has been cancelled.')
            );
            return;
        }

        const body = new URLSearchParams({
            form_key: getFormKey(),
            order_id: orderData && orderData.orderID ? orderData.orderID : ''
        });

        try {
            await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body
            });
        } catch (e) {
            // ignore
        }

        setCheckoutMessage(
            translate('PayPal checkout has been cancelled.', 'PayPal checkout has been cancelled.')
        );
    }

    function buildButtonStyle(config) {
        return {
            layout: config.buttonLayout || 'vertical',
            color: config.buttonColor || 'gold',
            shape: config.buttonShape || 'rect',
            label: config.buttonLabel || 'paypal',
            tagline: false
        };
    }

    function handleRenderError(error) {
        if (ignoreZoidError(error)) {
            return;
        }

        console.error(error);
        setCheckoutMessage(
            error && error.message
                ? error.message
                : translate('Unable to start PayPal checkout.', 'Unable to start PayPal checkout.')
        );
    }

    function renderPaypalButton() {
        if (!canRenderButton()) {
            destroyRenderedInstance(true);
            return Promise.resolve();
        }

        const container = getButtonContainer();

        if (!container) {
            return Promise.resolve();
        }

        if (renderInProgress) {
            return Promise.resolve();
        }

        if (rendered && renderedContainer === container && container.childElementCount > 0) {
            syncButtonState();
            return Promise.resolve();
        }

        renderInProgress = true;
        const currentToken = ++renderToken;

        container.innerHTML = '';

        return loadPaypalSdk()
            .then(function () {
                if (currentToken !== renderToken) {
                    return;
                }

                if (!canRenderButton()) {
                    destroyRenderedInstance(true);
                    return;
                }

                const config = getPaypalConfig();

                const buttons = window.paypal.Buttons({
                    style: buildButtonStyle(config),

                    onInit: function (_data, actions) {
                        if (currentToken !== renderToken) {
                            return;
                        }

                        paypalActions = actions;
                        syncButtonState();
                    },

                    createOrder: async function () {
                        try {
                            if (!isTermsAccepted()) {
                                throw new Error(
                                    translate(
                                        'Please accept the terms before continuing to payment.',
                                        'Please accept the terms before continuing to payment.'
                                    )
                                );
                            }

                            setCheckoutMessage('');
                            return await createOrderRequest();
                        } catch (error) {
                            setCheckoutMessage(
                                error && error.message
                                    ? error.message
                                    : translate('Unable to start PayPal checkout.', 'Unable to start PayPal checkout.')
                            );
                            throw error;
                        }
                    },

                    onApprove: async function (data) {
                        try {
                            setCheckoutMessage('');
                            await captureOrderRequest(data);
                        } catch (error) {
                            console.error(error);
                            setCheckoutMessage(
                                error && error.message
                                    ? error.message
                                    : translate('Unable to capture PayPal order.', 'Unable to capture PayPal order.')
                            );
                        }
                    },

                    onCancel: async function (data) {
                        await cancelOrderRequest(data);
                    },

                    onError: function (error) {
                        if (ignoreZoidError(error)) {
                            return;
                        }

                        console.error(error);
                        setCheckoutMessage(
                            error && error.message
                                ? error.message
                                : translate('Unable to start PayPal checkout.', 'Unable to start PayPal checkout.')
                        );
                    }
                });

                if (!buttons || typeof buttons.isEligible !== 'function' || !buttons.isEligible()) {
                    setCheckoutMessage(
                        translate('PayPal is currently unavailable.', 'PayPal is currently unavailable.')
                    );
                    return;
                }

                return buttons.render('#' + BUTTON_CONTAINER_ID)
                    .then(function () {
                        if (currentToken !== renderToken) {
                            return;
                        }

                        rendered = true;
                        renderedContainer = container;
                        syncButtonState();
                    })
                    .catch(function (error) {
                        if (currentToken !== renderToken) {
                            return;
                        }

                        handleRenderError(error);
                    });
            })
            .catch(function (error) {
                if (currentToken !== renderToken) {
                    return;
                }

                handleRenderError(error);
            })
            .finally(function () {
                if (currentToken === renderToken) {
                    renderInProgress = false;
                }
            });
    }

    function waitForContainerAndRender(attempt) {
        const tries = typeof attempt === 'number' ? attempt : 0;

        if (!isTermsAccepted()) {
            destroyRenderedInstance(true);
            return;
        }

        const container = getButtonContainer();

        if (!container) {
            if (tries < 20) {
                window.setTimeout(function () {
                    waitForContainerAndRender(tries + 1);
                }, 120);
            }
            return;
        }

        renderPaypalButton();
    }

    function bindListeners() {
        if (listenersBound) {
            return;
        }

        listenersBound = true;

        window.addEventListener('lencarta-checkout-ready', function () {
            if (isTermsAccepted()) {
                waitForContainerAndRender(0);
            }
        });

        window.addEventListener('lencarta:paypal:mount', function () {
            if (isTermsAccepted()) {
                waitForContainerAndRender(0);
            }
        });

        window.addEventListener('lencarta:paypal:terms-changed', function () {
            if (isTermsAccepted()) {
                setCheckoutMessage('');
                waitForContainerAndRender(0);
            } else {
                destroyRenderedInstance(true);
                setCheckoutMessage(
                    translate(
                        'Please accept the terms before continuing to payment.',
                        'Please accept the terms before continuing to payment.'
                    )
                );
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && isTermsAccepted()) {
                waitForContainerAndRender(0);
            }
        });
    }

    function bootPaypal() {
        bindListeners();

        if (booted) {
            if (isTermsAccepted()) {
                waitForContainerAndRender(0);
            }
            return;
        }

        booted = true;

        if (isTermsAccepted()) {
            waitForContainerAndRender(0);
        }
    }

    window.LencartaPaypal = {
        boot: bootPaypal,
        render: renderPaypalButton,
        destroy: destroyRenderedInstance
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootPaypal, { once: true });
    } else {
        bootPaypal();
    }
})();
