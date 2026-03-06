/**
 * Custom_DeliveryRestriction — Checkout Shipping Step Zip Validator Mixin
 *
 * Adds live pincode availability feedback to the checkout shipping step.
 * Watches the shipping address postcode field; debounces AJAX checks so
 * we don't fire on every keystroke.
 *
 * Features:
 *  - Availability badge (green ✔ / red ✗)
 *  - Estimated delivery text (from admin-configurable template)
 *  - COD availability badge (if feature enabled)
 *  - Partial payment eligibility badge (if feature enabled)
 *  - Only shown when admin has enabled "Show Live Pincode Checker at Checkout"
 */
define([
    'jquery',
    'ko',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage'
], function ($, ko, urlBuilder, quote) {
    'use strict';

    var DEBOUNCE_MS  = 600;
    var ZIP_PATTERN  = /^[a-zA-Z0-9\s\-]{2,10}$/;
    var AJAX_TIMEOUT = 10000;

    // Config injected from layout XML data-mage-init or window global
    var _cfg = window.customDrCheckoutConfig || {};

    return function (Component) {
        return Component.extend({

            // ── Observables ─────────────────────────────────────────────────
            cdrZipStatus:         ko.observable(''),   // 'available'|'unavailable'|'error'|''
            cdrZipMessage:        ko.observable(''),
            cdrDeliveryMessage:   ko.observable(''),
            cdrCodMessage:        ko.observable(''),
            cdrCodStatus:         ko.observable(''),   // 'available'|'unavailable'|''
            cdrPartialMessage:    ko.observable(''),
            cdrPartialStatus:     ko.observable(''),   // 'eligible'|'not-eligible'|''
            cdrIsChecking:        ko.observable(false),
            cdrEnabled:           ko.observable(!!(_cfg.enabled)),
            cdrTitle:             ko.observable(_cfg.title || 'Check Delivery to Your Pincode'),

            // ── Lifecycle ────────────────────────────────────────────────────
            initialize: function () {
                this._super();

                if (!_cfg.enabled || !_cfg.ajaxUrl) {
                    return this;
                }

                var self      = this;
                var debouncer = null;

                // Subscribe to shipping address postcode changes
                quote.shippingAddress.subscribe(function (addr) {
                    if (!addr) { return; }
                    var zip = (addr.postcode || '').trim();
                    self._clearResult();

                    if (!ZIP_PATTERN.test(zip)) { return; }

                    clearTimeout(debouncer);
                    debouncer = setTimeout(function () {
                        self._checkZip(zip);
                    }, DEBOUNCE_MS);
                });

                return this;
            },

            // ── Core AJAX check ──────────────────────────────────────────────
            _checkZip: function (zip) {
                var self = this;

                self.cdrIsChecking(true);
                self._clearResult();

                $.ajax({
                    url:      _cfg.ajaxUrl,
                    type:     'POST',
                    timeout:  AJAX_TIMEOUT,
                    dataType: 'json',
                    data: {
                        zip_code: zip,
                        form_key: _cfg.formKey || ''
                    }
                })
                .done(function (res) {
                    self.cdrIsChecking(false);

                    if (!res || res.error) {
                        self.cdrZipStatus('error');
                        self.cdrZipMessage(res && res.message ? res.message : 'Error checking zip code.');
                        return;
                    }

                    if (res.available) {
                        self.cdrZipStatus('available');
                        self.cdrZipMessage(res.message || '');
                        self.cdrDeliveryMessage(res.delivery_message || '');

                        // COD
                        if (res.cod_message !== undefined) {
                            self.cdrCodMessage(res.cod_message);
                            self.cdrCodStatus(res.cod_available ? 'available' : 'unavailable');
                        }

                        // Partial payment
                        if (res.partial_payment_message !== undefined) {
                            self.cdrPartialMessage(res.partial_payment_message);
                            self.cdrPartialStatus(res.partial_payment_eligible ? 'eligible' : 'not-eligible');
                        }
                    } else {
                        self.cdrZipStatus('unavailable');
                        self.cdrZipMessage(res.message || '');
                    }
                })
                .fail(function (jqXHR, textStatus) {
                    self.cdrIsChecking(false);
                    self.cdrZipStatus('error');
                    self.cdrZipMessage(
                        textStatus === 'timeout'
                            ? 'Pincode check timed out. Please try again.'
                            : 'Network error checking pincode.'
                    );
                });
            },

            _clearResult: function () {
                this.cdrZipStatus('');
                this.cdrZipMessage('');
                this.cdrDeliveryMessage('');
                this.cdrCodMessage('');
                this.cdrCodStatus('');
                this.cdrPartialMessage('');
                this.cdrPartialStatus('');
            }
        });
    };
});
