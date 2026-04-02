function initLencartaCheckout(config) {
    return {
        config,
        loading: false,
        message: '',
        termsAccepted: false,
        couponCode: '',
        couponOpen: false,
        email: '',
        emailSaveState: 'idle',

        shipping: {
            firstname: '',
            lastname: '',
            company: '',
            telephone: '',
            street_1: '',
            street_2: '',
            city: '',
            postcode: '',
            region: '',
            country_id: 'GB'
        },

        shippingSaveState: 'idle',
        shippingMethodsState: 'idle',

        items: [],
        itemsExpanded: false,
        maxVisibleItems: 2,

        totals: {},
        shippingMethods: [],
        selectedShippingMethod: '',

        init() {
            this.loadState()
                .then(() => {
                    window.lencartaCheckoutState = this;
                    window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                })
                .catch(() => {
                    this.message = 'Unable to initialize checkout.';
                });
        },

        getFormKey() {
            if (window.hyva && typeof window.hyva.getFormKey === 'function') {
                return window.hyva.getFormKey();
            }
            return '';
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
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.success) {
                this.message = 'Unable to load checkout state.';
                return;
            }

            this.email = data.data.email || '';
            this.items = data.data.items || [];
            this.totals = data.data.totals || {};
            this.shippingMethods = data.data.shipping_methods || [];
            this.selectedShippingMethod = data.data.selected_shipping_method || '';

            if (this.shippingMethods.length > 0) {
                this.shippingMethodsState = 'ready';
            }
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
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
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
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
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
                this.shippingMethods = data.shipping_methods || [];
                this.shippingMethodsState = this.shippingMethods.length > 0 ? 'ready' : 'unavailable';
                this.message = '';
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
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
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
