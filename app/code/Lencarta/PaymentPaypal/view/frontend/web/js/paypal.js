(function () {
    function loadPaypalSdk(config) {
        return new Promise((resolve, reject) => {
            if (window.paypal) {
                resolve(window.paypal);
                return;
            }

            const script = document.createElement('script');
            script.src = `${config.sdkUrl}?client-id=${encodeURIComponent(config.clientId)}&currency=${encodeURIComponent(config.currency)}&components=buttons`;
            script.async = true;
            script.onload = () => resolve(window.paypal);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function renderPaypalButton() {
        const config = window.lencartaPaypalConfig || {};
        const checkout = window.lencartaCheckoutState;

        if (!config.enabled || !config.clientId || !checkout) {
            return;
        }

        const container = document.getElementById('lencarta-paypal-button');
        if (!container || container.dataset.rendered === '1') {
            return;
        }

        const paypal = await loadPaypalSdk(config);
        container.dataset.rendered = '1';

        paypal.Buttons({
            style: {
                color: config.button.color || 'gold',
                shape: config.button.shape || 'rect',
                label: config.button.label || 'paypal',
                layout: 'vertical'
            },
            createOrder: async function () {
                const response = await fetch(config.createOrderUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        form_key: config.formKey || ''
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || config.i18n.createOrderError || 'Unable to create PayPal order.');
                }

                return result.paypal_order_id;
            },
            onApprove: async function (data) {
                const response = await fetch(config.captureUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        form_key: config.formKey || '',
                        paypal_order_id: data.orderID || '',
                        payer_id: data.payerID || ''
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || config.i18n.finalizeError || 'Unable to finalize PayPal payment.');
                }

                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                }
            },
            onError: function (err) {
                console.error(err);
                const message = err && err.message ? err.message : (config.i18n.genericError || 'PayPal error');
                if (window.lencartaCheckoutState) {
                    window.lencartaCheckoutState.message = message;
                } else {
                    alert(message);
                }
            }
        }).render('#lencarta-paypal-button');
    }

    window.addEventListener('lencarta-checkout-ready', function () {
        renderPaypalButton().catch(console.error);
    });
})();
