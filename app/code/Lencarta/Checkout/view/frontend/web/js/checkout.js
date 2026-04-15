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
            country_id: initialShipping.country_id || config.defaultCountryId || 'GB'
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
            this.restoreTermsAccepted();
            this.ensureShippingCountry();

            this.lastSavedEmail = this.getNormalizedEmail();
            this.lastSavedShippingSignature = this.getShippingSignature();
            this.lastObservedShippingSignature = this.lastSavedShippingSignature;

            if (hasInitialState) {
                window.lencartaCheckoutState = this;
                window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                this.emitPaypalStateChanged();
                this.startShippingWatcher();
                return;
            }

            this.loadState()
                .then(() => {
                    this.ensureShippingCountry();
                    this.lastSavedEmail = this.getNormalizedEmail();
                    this.lastSavedShippingSignature = this.getShippingSignature();
                    this.lastObservedShippingSignature = this.lastSavedShippingSignature;
                    window.lencartaCheckoutState = this;
                    window.dispatchEvent(new CustomEvent('lencarta-checkout-ready'));
                    this.emitPaypalStateChanged();
                })
                .catch(() => {
                    this.message = this.translate(
                        'Unable to initialize checkout.',
                        'Unable to initialize checkout.'
                    );
                    this.emitPaypalStateChanged();
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

        getCountryOptions() {
            return Array.isArray(this.config.countryOptions) ? this.config.countryOptions : [];
        },

        getSelectableCountryOptions() {
            return this.getCountryOptions().filter((option) => {
                return option && typeof option.value !== 'undefined' && String(option.value).trim() !== '';
            });
        },

        countryExists(countryId) {
            const normalized = String(countryId || '').trim().toUpperCase();
            if (!normalized) {
                return false;
            }

            return this.getSelectableCountryOptions().some((option) => {
                return String(option.value || '').trim().toUpperCase() === normalized;
            });
        },

        getDefaultCountryId() {
            const configured = String(this.config.defaultCountryId || '').trim().toUpperCase();
            if (configured && this.countryExists(configured)) {
                return configured;
            }

            const firstSelectable = this.getSelectableCountryOptions()[0];
            if (firstSelectable && firstSelectable.value) {
                return String(firstSelectable.value).trim().toUpperCase();
            }

            return 'GB';
        },

        normalizeCountryId(countryId) {
            const normalized = String(countryId || '').trim().toUpperCase();
            if (normalized && this.countryExists(normalized)) {
                return normalized;
            }

            return this.getDefaultCountryId();
        },

        ensureShippingCountry() {
            const normalized = this.normalizeCountryId(this.shipping.country_id);
            if (this.shipping.country_id !== normalized) {
                this.shipping.country_id = normalized;
            }

            return this.shipping.country_id;
        },

        getTermsStorageKey() {
            const websiteCode = String(this.config.websiteCode || '').trim();
            const storeCode = String(this.config.storeCode || this.config.storeViewCode || '').trim();

            return ['lencarta_checkout_terms_accepted', websiteCode, storeCode]
                .filter(Boolean)
                .join(':');
        },

        restoreTermsAccepted() {
            let restored = false;

            try {
                const stored = localStorage.getItem(this.getTermsStorageKey());
                if (stored !== null) {
                    this.termsAccepted = stored === '1';
                    restored = true;
                }
            } catch (e) {}

            if (!restored && typeof this.config.defaultTermsAccepted !== 'undefined') {
                this.termsAccepted = !!this.config.defaultTermsAccepted;
            }
        },

        persistTermsAccepted() {
            try {
                localStorage.setItem(
                    this.getTermsStorageKey(),
                    this.termsAccepted ? '1' : '0'
                );
            } catch (e) {}
        },

        handleTermsAcceptedChange(event) {
            this.termsAccepted = !!(event && event.target ? event.target.checked : this.termsAccepted);
            this.persistTermsAccepted();
            this.emitPaypalStateChanged();
        },

        emitPaypalStateChanged() {
            window.dispatchEvent(new CustomEvent('lencarta-checkout-paypal-state-changed', {
                detail: this.getPaypalState()
            }));
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

        getCheckoutBlockingMessage() {
            if (!this.isEmailValidForSave()) {
                return this.translate(
                    'Please enter a valid email address.',
                    'Please enter a valid email address.'
                );
            }

            const missing = this.getMissingRequiredAddressFields();
            if (missing.length > 0) {
                return this.getAddressIncompleteMessage();
            }

            if (!this.selectedShippingMethod) {
                return this.translate(
                    'Please select a delivery option.',
                    'Please select a delivery option.'
                );
            }

            return '';
        },

        canMountPaypalButton() {
            return this.getCheckoutBlockingMessage() === '';
        },

        canStartPaypalCheckout() {
            return this.canMountPaypalButton() && !!this.termsAccepted;
        },

        getPaypalState() {
            const blockingMessage = this.getCheckoutBlockingMessage();

            return {
                canRender: this.canMountPaypalButton(),
                canStart: this.canStartPaypalCheckout(),
                termsAccepted: !!this.termsAccepted,
                emailValid: this.isEmailValidForSave(),
                addressComplete: this.getMissingRequiredAddressFields().length === 0,
                shippingMethodSelected: !!this.selectedShippingMethod,
                countryId: this.ensureShippingCountry(),
                blockingMessage: !blockingMessage && !this.termsAccepted
                    ? this.translate(
                        'Please accept the terms before continuing to payment.',
                        'Please accept the terms before continuing to payment.'
                    )
                    : blockingMessage,
                signature: [
                    this.getNormalizedEmail(),
                    this.getShippingSignature(),
                    this.selectedShippingMethod || ''
                ].join('|')
            };
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
            this.emitPaypalStateChanged();
        },

        queueEmailAutosave(source = 'field') {
            this.emailDirty = true;

            if (source === 'change' || source === 'blur') {
                this.scheduleEmailAutosave(this.emailQuickSaveMs);
            } else {
                this.scheduleEmailAutosave(this.emailIdleSaveMs);
            }

            this.emitPaypalStateChanged();
        },

        flushEmailAutosave() {
            if (this.emailAutosaveTimer) {
                clearTimeout(this.emailAutosaveTimer);
                this.emailAutosaveTimer = null;
            }

            const normalizedEmail = this.getNormalizedEmail();

            if (!normalizedEmail) {
                this.emailSaveState = 'idle';
                this.emitPaypalStateChanged();
                return;
            }

            if (!this.isEmailValidForSave()) {
                this.emailSaveState = 'error';
                this.emitPaypalStateChanged();
                return;
            }

            if (
                normalizedEmail === this.lastSavedEmail &&
                !this.emailRequestInFlight
            ) {
                this.emailDirty = false;
                this.emailSaveState = 'saved';
                this.emitPaypalStateChanged();
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
            this.emitPaypalStateChanged();

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
                    this.emitPaypalStateChanged();
                    return;
                }

                requestSucceeded = true;
                this.emailSaveState = 'saved';
                this.message = '';
                this.lastSavedEmail = emailToSave;
                this.emailDirty = false;
                this.emitPaypalStateChanged();
            } catch (e) {
                if (requestId !== this.emailRequestCounter) {
                    return;
                }

                this.emailSaveState = 'error';
                this.message = this.translate('Unable to save email.', 'Unable to save email.');
                this.emitPaypalStateChanged();
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
            const country = this.ensureShippingCountry();

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
                country_id: this.ensureShippingCountry()
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
                    this.shippingMethodsState = 'idle';
                    this.emitPaypalStateChanged();
                    return;
                }

                if (
                    currentSignature === this.lastSavedShippingSignature &&
                    !this.shippingRequestInFlight
                ) {
                    this.shippingDirty = false;
                    this.emitPaypalStateChanged();
                    return;
                }

                this.shippingDirty = true;
                this.scheduleShippingAutosave(this.shippingQuickSaveMs);
                this.emitPaypalStateChanged();
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
                this.shippingMethodsState = 'idle';
                this.emitPaypalStateChanged();
                return;
            }

            this.scheduleShippingAutosave(this.shippingIdleSaveMs);
            this.emitPaypalStateChanged();
        },

        queueShippingAutosave(source = 'field') {
            if (source === 'country') {
                this.ensureShippingCountry();
            }

            this.shippingDirty = true;
            this.lastObservedShippingSignature = this.getShippingSignature();

            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                this.shippingMethodsState = 'idle';
                this.emitPaypalStateChanged();
                return;
            }

            if (source === 'country') {
                this.scheduleShippingAutosave(300);
            } else {
                this.scheduleShippingAutosave(this.shippingQuickSaveMs);
            }

            this.emitPaypalStateChanged();
        },

        flushShippingAutosave() {
            if (this.shippingAutosaveTimer) {
                clearTimeout(this.shippingAutosaveTimer);
                this.shippingAutosaveTimer = null;
            }

            if (!this.canSaveShippingAddress()) {
                this.shippingSaveState = 'idle';
                this.shippingMethodsState = 'idle';
                this.emitPaypalStateChanged();
                return;
            }

            const currentSignature = this.getShippingSignature();

            if (
                currentSignature === this.lastSavedShippingSignature &&
                !this.shippingRequestInFlight
            ) {
                this.shippingDirty = false;
                this.shippingSaveState = 'saved';
                this.emitPaypalStateChanged();
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
                this.shippingMethodsState = 'idle';
                this.emitPaypalStateChanged();
                return;
            }

            const payload = this.getShippingPayload();
            const signature = requestSignature || JSON.stringify(payload);
            const requestId = ++this.shippingRequestCounter;
            let requestSucceeded = false;

            this.shippingRequestInFlight = true;
            this.shippingSaveState = 'saving';
            this.shippingMethodsState = 'loading';
            this.emitPaypalStateChanged();

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
                    this.emitPaypalStateChanged();
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

                this.emitPaypalStateChanged();
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
                this.emitPaypalStateChanged();
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

        hydrateFromState(state) {
            const shipping = state.shipping || {};

            this.email = state.email || '';
            this.couponCode = state.coupon_code || '';
            this.items = Array.isArray(state.items) ? state.items : [];
            this.totals = state.totals || {};
            this.shippingMethods = Array.isArray(state.shipping_methods) ? state.shipping_methods : [];
            this.selectedShippingMethod = state.selected_shipping_method || '';
            this.shippingMethodsState = this.shippingMethods.length > 0 ? 'ready' : 'idle';

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
                country_id: this.normalizeCountryId(shipping.country_id || this.shipping.country_id)
            };

            this.ensureShippingCountry();
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
                this.emitPaypalStateChanged();
                return;
            }

            this.hydrateFromState(data.data || {});
            this.isReady = true;
            this.emitPaypalStateChanged();
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
                    this.emitPaypalStateChanged();
                    return;
                }

                this.selectedShippingMethod = method.code;
                this.totals = data.totals || {};
                this.message = '';
                this.emitPaypalStateChanged();
            } catch (e) {
                this.message = this.translate('Unable to save shipping method.', 'Unable to save shipping method.');
                this.emitPaypalStateChanged();
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

window.initLencartaCheckout = initLencartaCheckout;
