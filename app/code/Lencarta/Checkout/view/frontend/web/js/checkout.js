function initLencartaCheckout(config) {
    const initialState = config.initialState || {};
    const hasInitialState =
        initialState &&
        typeof initialState === 'object' &&
        Array.isArray(initialState.items) &&
        typeof initialState.totals === 'object';

    const initialShipping = initialState.shipping || {};

    return {
        config,
        loading: false,
        isReady: hasInitialState,
        message: '',
        termsAccepted: false,
        couponCode: initialState.coupon_code || '',
        couponOpen: false,

        email: initialState.email || '',
        emailSaveState: 'idle',

        shipping: {
            firstname: initialShipping.firstname || '',
            lastname: initialShipping.lastname || '',
            company: initialShipping.company || '',
            telephone: initialShipping.telephone || '',
            street_1: initialShipping.street_1 || '',
            street_2: initialShipping.street_2 || '',
            city: initialShipping.city || '',
            postcode: initialShipping.postcode || '',
            region: initialShipping.region || '',
            country_id: initialShipping.country_id || 'GB'
        },

        shippingSaveState: 'idle',
        shippingMethodsState:
            Array.isArray(initialState.shipping_methods) && initialState.shipping_methods.length > 0
                ? 'ready'
                : 'idle',

        items: Array.isArray(initialState.items) ? initialState.items : [],
        itemsExpanded: false,
        maxVisibleItems: 2,

        totals: initialState.totals || {},
        shippingMethods: Array.isArray(initialState.shipping_methods) ? initialState.shipping_methods : [],
        selectedShippingMethod: initialState.selected_shipping_method || '',

        init() {
            if (hasInitialState) {
                window.lencartaCheckoutState = this;
                window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                return;
            }

            this.loadState()
                .then(() => {
                    window.lencartaCheckoutState = this;
                    window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                })
                .catch(() => {
                    this.message = 'Unable to initialize checkout.';
                })
                .finally(() => {
                    this.isReady = true;
                });
        },

        getFormKey() {
            if (window.hyva && typeof window.hyva.getFormKey === 'function') {
                return window.hyva.getFormKey();
            }

            const input = document.querySelector('input[name="form_key"]');
            return input ? input.value : '';
        },

        visibleItems() {
            if (this.itemsExpanded) {
                return this.items;
            }

            return this.items.slice(0, this.maxVisibleItems);
        },

        hasHiddenItems() {
            return this.items.length > this.maxVisibleItems;
        },

        hiddenItemsCount() {
            return Math.max(0, this.items.length - this.maxVisibleItems);
        },

        itemsToggleLabel() {
            if (this.itemsExpanded) {
                return 'Show fewer items';
            }

            const count = this.hiddenItemsCount();
            return `View ${count} more item${count > 1 ? 's' : ''}`;
        },

        toggleItemsExpanded() {
            this.itemsExpanded = !this.itemsExpanded;
        },

        async loadState() {
            const res = await fetch(this.config.urls.state, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await res.json();

            if (!data.success) {
                this.message = data.message || 'Unable to load checkout state.';
                return;
            }

            this.hydrateFromState(data.data || {});
            this.isReady = true;
        },

        hydrateFromState(state) {
            const shipping = state.shipping || {};

            this.email = state.email || '';
            this.items = Array.isArray(state.items) ? state.items : [];
            this.totals = state.totals || {};
            this.shippingMethods = Array.isArray(state.shipping_methods) ? state.shipping_methods : [];
            this.selectedShippingMethod = state.selected_shipping_method || '';
            this.couponCode = state.coupon_code || this.couponCode || '';

            this.shipping = {
                firstname: shipping.firstname || '',
                lastname: shipping.lastname || '',
                company: shipping.company || '',
                telephone: shipping.telephone || '',
                street_1: shipping.street_1 || '',
                street_2: shipping.street_2 || '',
                city: shipping.city || '',
                postcode: shipping.postcode || '',
                region: shipping.region || '',
                country_id: shipping.country_id || 'GB'
            };

            this.shippingMethodsState = this.shippingMethods.length > 0 ? 'ready' : 'idle';
        },

        canLoadShippingMethods() {
            return !!(
                this.shipping.firstname &&
                this.shipping.lastname &&
                this.shipping.street_1 &&
                this.shipping.city &&
                this.shipping.postcode &&
                this.shipping.country_id
            );
        },

        async autoSaveEmail() {
            if (!this.email) {
                this.emailSaveState = 'idle';
                return;
            }

            this.emailSaveState = 'saving';

            const body = new URLSearchParams({
                form_key: this.getFormKey(),
                email: this.email
            });

            try {
                const res = await fetch(this.config.urls.saveEmail, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body
                });

                const data = await res.json();

                if (!data.success) {
                    this.emailSaveState = 'error';
                    this.message = data.message || 'Unable to save email.';
                    return;
                }

                this.emailSaveState = 'saved';
                this.message = '';
            } catch (e) {
                this.emailSaveState = 'error';
                this.message = 'Unable to save email.';
            }
        },

        async queueShippingAutosave() {
            if (!this.canLoadShippingMethods()) {
                this.shippingSaveState = 'idle';
                this.shippingMethodsState = 'idle';
                this.shippingMethods = [];
                this.selectedShippingMethod = '';
                return;
            }

            await this.saveShippingAddress();
        },

        async saveShippingAddress() {
            this.shippingSaveState = 'saving';
            this.shippingMethodsState = 'loading';

            const body = new URLSearchParams({
                form_key: this.getFormKey(),
                firstname: this.shipping.firstname,
                lastname: this.shipping.lastname,
                company: this.shipping.company,
                telephone: this.shipping.telephone,
                street_1: this.shipping.street_1,
                street_2: this.shipping.street_2,
                city: this.shipping.city,
                postcode: this.shipping.postcode,
                region: this.shipping.region,
                country_id: this.shipping.country_id
            });

            try {
                const res = await fetch(this.config.urls.saveShippingAddress, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body
                });

                const data = await res.json();

                if (!data.success) {
                    this.shippingSaveState = 'error';
                    this.shippingMethodsState = 'unavailable';
                    this.message = data.message || 'Unable to save shipping address.';
                    return;
                }

                this.shippingSaveState = 'saved';
                this.totals = data.totals || {};
                this.shippingMethods = Array.isArray(data.shipping_methods) ? data.shipping_methods : [];
                this.shippingMethodsState = this.shippingMethods.length > 0 ? 'ready' : 'unavailable';
                this.message = '';

                if (data.selected_shipping_method) {
                    this.selectedShippingMethod = data.selected_shipping_method;
                }
            } catch (e) {
                this.shippingSaveState = 'error';
                this.shippingMethodsState = 'unavailable';
                this.message = 'Unable to save shipping address.';
            }
        },

        async selectShippingMethod(method) {
            const body = new URLSearchParams({
                form_key: this.getFormKey(),
                carrier_code: method.carrier_code,
                method_code: method.method_code
            });

            try {
                const res = await fetch(this.config.urls.saveShippingMethod, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body
                });

                const data = await res.json();

                if (!data.success) {
                    this.message = data.message || 'Unable to save shipping method.';
                    return;
                }

                this.selectedShippingMethod = method.code;
                this.totals = data.totals || {};
                this.message = '';
            } catch (e) {
                this.message = 'Unable to save shipping method.';
            }
        },

        applyCoupon() {
            this.message = 'Coupon application will be connected in the next step.';
        }
    };
}
