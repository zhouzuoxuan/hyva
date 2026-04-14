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
        emailAutosaveTimer: null,
        emailRequestInFlight: false,
        emailDirty: false,
        emailNeedsResave: false,
        lastSavedEmail: '',
        emailRequestCounter: 0,
        emailIdleSaveMs: 1200,
        emailQuickSaveMs: 250,

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

        shippingAutosaveTimer: null,
        shippingRequestInFlight: false,
        shippingNeedsResave: false,
        shippingRequestCounter: 0,
        shippingDirty: false,
        lastSavedShippingSignature: '',
        lastObservedShippingSignature: '',

        shippingIdleSaveMs: 1500,
        shippingQuickSaveMs: 250,
        shippingAfterRequestDelayMs: 500,

        shippingWatcherTimer: null,
        shippingWatcherIntervalMs: 700,

        init() {
            this.lastSavedEmail = this.getNormalizedEmail();
            this.lastSavedShippingSignature = this.getShippingSignature();
            this.lastObservedShippingSignature = this.lastSavedShippingSignature;

            if (typeof this.config.defaultTermsAccepted !== 'undefined') {
                this.termsAccepted = !!this.config.defaultTermsAccepted;
            }

            if (hasInitialState) {
                window.lencartaCheckoutState = this;
                window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                this.startShippingWatcher();
                return;
            }

            this.loadState()
                .then(() => {
                    this.lastSavedEmail = this.getNormalizedEmail();
                    this.lastSavedShippingSignature = this.getShippingSignature();
                    this.lastObservedShippingSignature = this.lastSavedShippingSignature;
                    window.lencartaCheckoutState = this;
                    window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                })
                .catch(() => {
                    this.message = this.translate(
                        'Unable to initialize checkout.',
                        'Unable to initialize checkout.'
                    );
                })
                .finally(() => {
                    this.isReady = true;
                    this.startShippingWatcher();
                });
        },

        getFormKey() {
            if (window.hyva && typeof window.hyva.getFormKey === 'function') {
                return window.hyva.getFormKey();
            }

            const input = document.querySelector('input[name="form_key"]');
            return input ? input.value : '';
        },

        translate(key, fallback = '') {
            const dict = this.config.i18n || {};
            return dict[key] || fallback || key;
        },

        getNormalizedEmail() {
            return (this.email || '').trim().toLowerCase();
        },

        isEmailValidForSave() {
            const email = this.getNormalizedEmail();

            if (!email) {
                return false;
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
        },

        scheduleEmailAutosave(delay) {
            if (this.emailAutosaveTimer) {
                clearTimeout(this.emailAutosaveTimer);
            }

            this.emailAutosaveTimer = setTimeout(() => {
                this.flushEmailAutosave();
            }, delay);
        },

        markEmailDirty() {
            this.emailDirty = true;
            this.emailSaveState = 'idle';
            this.scheduleEmailAutosave(this.emailIdleSaveMs);
        },

        queueEmailAutosave(source = 'field') {
            this.emailDirty = true;

            if (source === 'change' || source === 'blur') {
                this.scheduleEmailAutosave(this.emailQuickSaveMs);
                return;
            }

            this.scheduleEmailAutosave(this.emailIdleSaveMs);
        },

        flushEmailAutosave() {
            if (this.emailAutosaveTimer) {
                clearTimeout(this.emailAutosaveTimer);
                this.emailAutosaveTimer = null;
            }

            const normalizedEmail = this.getNormalizedEmail();

            if (!normalizedEmail) {
                this.emailSaveState = 'idle';
                return;
            }

            if (!this.isEmailValidForSave()) {
                this.emailSaveState = 'error';
                return;
            }

            if (
                normalizedEmail === this.lastSavedEmail &&
                !this.emailRequestInFlight
            ) {
                this.emailDirty = false;
                this.emailSaveState = 'saved';
                return;
            }

            if (this.emailRequestInFlight) {
                this.emailNeedsResave = true;
                return;
            }

            this.saveEmail(normalizedEmail);
        },

        async saveEmail(normalizedEmail = null) {
            const emailToSave = normalizedEmail || this.getNormalizedEmail();
            const requestId = ++this.emailRequestCounter;
            let requestSucceeded = false;

            this.emailRequestInFlight = true;
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

                if (requestId !== this.emailRequestCounter) {
                    return;
                }

                if (!data.success) {
                    this.emailSaveState = 'error';
                    this.message =
                        data.message ||
                        this.translate('Unable to save email.', 'Unable to save email.');
                    return;
                }

                requestSucceeded = true;
                this.emailSaveState = 'saved';
                this.message = '';
                this.lastSavedEmail = emailToSave;
                this.emailDirty = false;
            } catch (e) {
                if (requestId !== this.emailRequestCounter) {
                    return;
                }

                this.emailSaveState = 'error';
                this.message = this.translate('Unable to save email.', 'Unable to save email.');
            } finally {
                if (requestId !== this.emailRequestCounter) {
                    return;
                }

                this.emailRequestInFlight = false;

                if (!requestSucceeded) {
                    this.emailNeedsResave = false;
                    return;
                }

                const latestEmail = this.getNormalizedEmail();
                const changedDuringRequest = latestEmail !== this.lastSavedEmail;

                if (this.emailNeedsResave || changedDuringRequest || this.emailDirty) {
                    this.emailNeedsResave = false;
                    this.scheduleEmailAutosave(this.emailQuickSaveMs);
                }
            }
        },

        getAddressFieldConfig() {
            return this.config.addressFieldConfig || {};
        },

        getAddressFieldLabel(field) {
            const cfg = this.getAddressFieldConfig()[field] || {};
            return cfg.label || field;
        },

        isAddressFieldRequired(field) {
            const cfg = this.getAddressFieldConfig()[field] || {};
            const country = (this.shipping.country_id || 'GB').trim().toUpperCase();

            if (field === 'postcode') {
                const optionalCountries = Array.isArray(cfg.optional_countries) ? cfg.optional_countries : [];
                return !!cfg.required && !optionalCountries.includes(country);
            }

            if (field === 'region') {
                const requiredCountries = Array.isArray(cfg.required_countries) ? cfg.required_countries : [];
                return !!cfg.required || requiredCountries.includes(country);
            }

            return !!cfg.required;
        },

        isAddressFieldFilled(field) {
            const payload = this.getShippingPayload();
            return !!payload[field];
        },

        getRequiredAddressFieldsInOrder() {
            return [
                'firstname',
                'lastname',
                'street_1',
                'country_id',
                'region',
                'city',
                'postcode',
                'telephone'
            ];
        },

        getMissingRequiredAddressFields() {
            return this.getRequiredAddressFieldsInOrder()
                .filter((field) => this.isAddressFieldRequired(field) && !this.isAddressFieldFilled(field))
                .map((field) => this.getAddressFieldLabel(field));
        },

        hasAddressStarted() {
            const payload = this.getShippingPayload();

            return !!(
                payload.firstname ||
                payload.lastname ||
                payload.company ||
                payload.telephone ||
                payload.street_1 ||
                payload.street_2 ||
                payload.city ||
                payload.postcode ||
                payload.region
            );
        },

        showAddressIncompleteNotice() {
            return this.hasAddressStarted() && this.getMissingRequiredAddressFields().length > 0;
        },

        getAddressIncompleteMessage() {
            const missing = this.getMissingRequiredAddressFields();

            if (!missing.length) {
                return '';
            }

            return (
                this.translate('Please complete required fields:', 'Please complete required fields:') +
                ' ' +
                missing.join(', ') +
                '.'
            );
        },

        canSaveShippingAddress() {
            return this.getMissingRequiredAddressFields().length === 0;
        },

        // 兼容旧模板调用
        canLoadShippingMethods() {
            return this.canSaveShippingAddress();
        },

        getShippingPayload() {
            return {
                firstname: (this.shipping.firstname || '').trim(),
                lastname: (this.shipping.lastname || '').trim(),
                company: (this.shipping.company || '').trim(),
                telephone: (this.shipping.telephone || '').trim(),
                street_1: (this.shipping.street_1 || '').trim(),
                street_2: (this.shipping.street_2 || '').trim(),
                city: (this.shipping.city || '').trim(),
                postcode: (this.shipping.postcode || '').trim(),
                region: (this.shipping.region || '').trim(),
                country_id: (this.shipping.country_id || 'GB').trim().toUpperCase()
            };
        },

        getShippingSignature() {
            return JSON.stringify(this.getShippingPayload());
        },

        startShippingWatcher() {
            if (this.shippingWatcherTimer) {
                clearInterval(this.shippingWatcherTimer);
            }

            this.shippingWatcherTimer = setInterval(() => {
                if (document.hidden) {
                    return;
                }

                const currentSignature = this.getShippingSignature();

                if (currentSignature === this.lastObservedShippingSignature) {
                    return;
                }

                this.lastObservedShippingSignature = currentSignature;

                if (!this.canSaveShippingAddress()) {
                    this.shippingDirty = true;
                    this.shippingSaveState = 'idle';
                    return;
                }

                if (
                    currentSignature === this.lastSavedShippingSignature &&
                    !this.shippingRequestInFlight
                ) {
                    this.shippingDirty = false;
                    return;
                }

                this.shippingDirty = true;
                this.scheduleShippingAutosave(this.shippingQuickSaveMs);
            }, this.shippingWatcherIntervalMs);
        },

        scheduleShippingAutosave(delay) {
            if (this.shippingAutosaveTimer) {
                clearTimeout(this.shippingAutosaveTimer);
            }

            this.shippingAutosaveTimer = setTimeout(() => {
                this.flushShippingAutosave();
            }, delay);
        },

        markShippingDirty() {
            this.shippingDirty = true;
            this.lastObservedShippingSignature = this.getShippingSignature();

            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                return;
            }

            this.scheduleShippingAutosave(this.shippingIdleSaveMs);
        },

        queueShippingAutosave(source = 'field') {
            this.shippingDirty = true;
            this.lastObservedShippingSignature = this.getShippingSignature();

            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                return;
            }

            if (source === 'country') {
                this.scheduleShippingAutosave(300);
                return;
            }

            this.scheduleShippingAutosave(this.shippingQuickSaveMs);
        },

        flushShippingAutosave() {
            if (this.shippingAutosaveTimer) {
                clearTimeout(this.shippingAutosaveTimer);
                this.shippingAutosaveTimer = null;
            }

            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                return;
            }

            const currentSignature = this.getShippingSignature();

            if (
                currentSignature === this.lastSavedShippingSignature &&
                !this.shippingRequestInFlight
            ) {
                this.shippingDirty = false;
                this.shippingSaveState = 'saved';
                return;
            }

            if (this.shippingRequestInFlight) {
                this.shippingNeedsResave = true;
                return;
            }

            this.saveShippingAddress(currentSignature);
        },

        async saveShippingAddress(requestSignature = null) {
            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                return;
            }

            const payload = this.getShippingPayload();
            const signature = requestSignature || JSON.stringify(payload);
            const requestId = ++this.shippingRequestCounter;
            let requestSucceeded = false;

            this.shippingRequestInFlight = true;
            this.shippingSaveState = 'saving';
            this.shippingMethodsState = 'loading';

            const body = new URLSearchParams({
                form_key: this.getFormKey(),
                firstname: payload.firstname,
                lastname: payload.lastname,
                company: payload.company,
                telephone: payload.telephone,
                street_1: payload.street_1,
                street_2: payload.street_2,
                city: payload.city,
                postcode: payload.postcode,
                region: payload.region,
                country_id: payload.country_id
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

                if (requestId !== this.shippingRequestCounter) {
                    return;
                }

                if (!data.success) {
                    this.shippingSaveState = 'error';
                    this.shippingMethodsState = 'unavailable';
                    this.message =
                        data.message ||
                        this.translate(
                            'Unable to save shipping address.',
                            'Unable to save shipping address.'
                        );
                    return;
                }

                requestSucceeded = true;
                this.shippingSaveState = 'saved';
                this.totals = data.totals || {};
                this.shippingMethods = Array.isArray(data.shipping_methods) ? data.shipping_methods : [];
                this.shippingMethodsState = this.shippingMethods.length > 0 ? 'ready' : 'unavailable';
                this.message = '';
                this.lastSavedShippingSignature = signature;
                this.lastObservedShippingSignature = signature;
                this.shippingDirty = false;

                if (data.selected_shipping_method) {
                    this.selectedShippingMethod = data.selected_shipping_method;
                }
            } catch (e) {
                if (requestId !== this.shippingRequestCounter) {
                    return;
                }

                this.shippingSaveState = 'error';
                this.shippingMethodsState = 'unavailable';
                this.message = this.translate(
                    'Unable to save shipping address.',
                    'Unable to save shipping address.'
                );
            } finally {
                if (requestId !== this.shippingRequestCounter) {
                    return;
                }

                this.shippingRequestInFlight = false;

                if (!requestSucceeded) {
                    this.shippingNeedsResave = false;
                    return;
                }

                const latestSignature = this.getShippingSignature();
                const changedDuringRequest = latestSignature !== this.lastSavedShippingSignature;

                if (this.shippingNeedsResave || changedDuringRequest || this.shippingDirty) {
                    this.shippingNeedsResave = false;
                    this.scheduleShippingAutosave(this.shippingAfterRequestDelayMs);
                }
            }
        },

        visibleItems() {
            return this.itemsExpanded
                ? this.items
                : this.items.slice(0, this.maxVisibleItems);
        },

        hasHiddenItems() {
            return this.items.length > this.maxVisibleItems;
        },

        hiddenItemsCount() {
            return Math.max(0, this.items.length - this.maxVisibleItems);
        },

        itemsToggleLabel() {
            if (this.itemsExpanded) {
                return this.translate('Show fewer items', 'Show fewer items');
            }

            const count = this.hiddenItemsCount();
            const singular = this.translate('item', 'item');
            const plural = this.translate('items', 'items');

            return `${this.translate('View', 'View')} ${count} ${count > 1 ? plural : singular} ${this.translate('more', 'more')}`;
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
                this.message = data.message || this.translate('Unable to load checkout state.', 'Unable to load checkout state.');
                return;
            }

            this.hydrateFromState(data.data || {});
            this.isReady = true;
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
                    this.message =
                        data.message ||
                        this.translate('Unable to save shipping method.', 'Unable to save shipping method.');
                    return;
                }

                this.selectedShippingMethod = method.code;
                this.totals = data.totals || {};
                this.message = '';
            } catch (e) {
                this.message = this.translate('Unable to save shipping method.', 'Unable to save shipping method.');
            }
        },

        applyCoupon() {
            this.message = this.translate(
                'Coupon application will be connected in the next step.',
                'Coupon application will be connected in the next step.'
            );
        }
    };
}
