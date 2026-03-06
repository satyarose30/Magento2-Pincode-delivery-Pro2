/**
 * Custom_DeliveryRestriction — Checkout Pincode Checker
 *
 * Lightweight AMD module for the checkout page zip checker widget.
 * Uses the same AJAX endpoint as the product-page widget.
 * Shows: delivery available/unavailable, COD status, partial payment status.
 * Timeout + disabled-state reset on all exit paths.
 */
define(['jquery'], function ($) {
    'use strict';

    var STORAGE_KEY  = 'cdr_checked_zip';
    var ZIP_PATTERN  = /^[a-zA-Z0-9\s\-]{2,10}$/;
    var AJAX_TIMEOUT = 10000;

    var _ajaxUrl = '';
    var _formKey = '';

    // ── Helpers ────────────────────────────────────────────────────────────────

    function setLoading(on) {
        $('#custom-dr-checkout-check-btn').prop('disabled', on).find('span').text(on ? '...' : 'Verify');
        $('#custom-dr-checkout-zip-input').prop('readonly', on);
    }

    function clearResult() {
        $('#custom-dr-checkout-zip-result').empty().hide();
    }

    function showBadge(container, message, type) {
        var badge = $('<div>')
            .addClass('custom-dr-checkout-checker__badge custom-dr-checkout-checker__badge--' + type)
            .text(message);
        container.append(badge);
    }

    function renderResult(response) {
        var $result = $('#custom-dr-checkout-zip-result');
        $result.empty();

        if (!response || response.error) {
            var errMsg = (response && response.message) ? response.message : 'An error occurred.';
            showBadge($result, errMsg, 'error');
            $result.show();
            return;
        }

        var type = response.available ? 'available' : 'unavailable';
        showBadge($result, response.message, type);

        if (response.available && response.delivery_message) {
            showBadge($result, response.delivery_message, 'estimate');
        }

        if (typeof response.cod_available !== 'undefined') {
            var codType = response.cod_available ? 'cod-available' : 'cod-unavailable';
            showBadge($result, response.cod_message, codType);
        }

        if (typeof response.partial_payment_eligible !== 'undefined') {
            var ppType = response.partial_payment_eligible ? 'pp-available' : 'pp-unavailable';
            showBadge($result, response.partial_payment_message, ppType);
        }

        $result.show();

        try {
            if (response.available) {
                sessionStorage.setItem(STORAGE_KEY, $('#custom-dr-checkout-zip-input').val().trim());
            } else {
                sessionStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {}
    }

    // ── Core ───────────────────────────────────────────────────────────────────

    function checkZip(zip) {
        if (!ZIP_PATTERN.test(zip)) {
            var $r = $('#custom-dr-checkout-zip-result').empty();
            showBadge($r, 'Please enter a valid zip / postal code (2-10 characters).', 'warning');
            $r.show();
            return;
        }

        setLoading(true);
        clearResult();

        $.ajax({
            url:      _ajaxUrl,
            type:     'POST',
            timeout:  AJAX_TIMEOUT,
            data:     { zip_code: zip, form_key: _formKey },
            dataType: 'json'
        }).done(function (response) {
            setLoading(false);
            renderResult(response);
        }).fail(function (jqXHR, textStatus) {
            setLoading(false);
            var msg = textStatus === 'timeout'
                ? 'Request timed out. Please try again.'
                : 'Network error. Please try again.';
            var $r = $('#custom-dr-checkout-zip-result').empty();
            showBadge($r, msg, 'error');
            $r.show();
        });
    }

    function autoRestore() {
        var saved = '';
        try { saved = sessionStorage.getItem(STORAGE_KEY) || ''; } catch (e) {}
        if (saved && ZIP_PATTERN.test(saved)) {
            $('#custom-dr-checkout-zip-input').val(saved);
            checkZip(saved);
        }
    }

    // ── Public ─────────────────────────────────────────────────────────────────

    return {
        init: function (config) {
            _ajaxUrl = config.ajaxUrl || '';
            _formKey = config.formKey || '';

            if (!_ajaxUrl) { return; }

            $(document).on('click', '#custom-dr-checkout-check-btn', function () {
                checkZip($.trim($('#custom-dr-checkout-zip-input').val()));
            });

            $(document).on('keydown', '#custom-dr-checkout-zip-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    checkZip($.trim($(this).val()));
                }
            });

            $(document).on('input', '#custom-dr-checkout-zip-input', function () {
                clearResult();
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
            });

            $(document).ready(function () { autoRestore(); });
        }
    };
});
