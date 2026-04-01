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
        if (!container) {
            return;
        }

        const paypal = await loadPaypalSdk(config);

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
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        form_key: window.lencartaCheckoutConfig.formKey
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Unable to create PayPal order.');
                }

                return result.paypal_order_id;
            },
            onApprove: async function (data) {
                console.log('PayPal approved order:', data.orderID);
                alert('Approve 已拿到，下一步接 finalize。当前阶段先到这里。');
            },
            onError: function (err) {
                console.error(err);
                alert('PayPal error');
            }
        }).render('#lencarta-paypal-button');
    }

    window.addEventListener('lencarta-checkout-ready', function () {
        renderPaypalButton().catch(console.error);
    });
})();
