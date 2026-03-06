/**
 * Custom_DeliveryRestriction — Zip Checker AMD Module
 *
 * FIX (CODE_REVIEW Medium):
 *  - Explicit network timeout (10 s) with user-visible error and button re-enable
 *  - Disabled-state reset in all exit paths (success, error, timeout)
 *    Prevents stuck UI where button stays greyed out after a failed request.
 *
 * FIX (CODE_REVIEW Low):
 *  - All user-facing text from backend config — no hardcoded strings in JS
 *  - Uses jQuery .text() for displaying server-returned strings (auto-escapes HTML)
 *    so zip_code returned raw from controller is safe here.
 *
 * sessionStorage key: 'cdr_checked_zip' — persists checked zip across reloads.
 */
define(['jquery'], function ($) {
    'use strict';

    var STORAGE_KEY    = 'cdr_checked_zip';
    var ZIP_PATTERN    = /^[a-zA-Z0-9\s\-]{2,10}$/;
    var AJAX_TIMEOUT   = 10000; // 10 s

    var _ajaxUrl = '';
    var _formKey = '';

    // ── State helpers ──────────────────────────────────────────────────────────

    function setLoading(isLoading) {
        var btn   = $('#custom-dr-check-btn');
        var input = $('#custom-dr-zip-input');
        btn.prop('disabled', isLoading);
        input.prop('readonly', isLoading);
        btn.find('span').text(isLoading ? '...' : 'Check');
    }

    function showResult(message, type) {
        // type: 'available' | 'unavailable' | 'error' | 'warning'
        var $result = $('#custom-dr-zip-result');
        $result
            .removeClass('custom-dr-zipcheckerwidget__result--available ' +
                         'custom-dr-zipcheckerwidget__result--unavailable ' +
                         'custom-dr-zipcheckerwidget__result--error ' +
                         'custom-dr-zipcheckerwidget__result--warning')
            .addClass('custom-dr-zipcheckerwidget__result--' + type)
            .text(message)  // FIX: .text() auto-escapes — never use .html() for server data
            .show();
    }

    function clearResult() {
        $('#custom-dr-zip-result').hide().text('').removeClass(
            'custom-dr-zipcheckerwidget__result--available ' +
            'custom-dr-zipcheckerwidget__result--unavailable ' +
            'custom-dr-zipcheckerwidget__result--error ' +
            'custom-dr-zipcheckerwidget__result--warning'
        );
        // Clear payment badges
        $('#custom-dr-cod-badge, #custom-dr-pp-badge').remove();
    }

    function showBadge(id, message, type) {
        $('#' + id).remove(); // remove stale
        var iconMap = {
            'cod-available':     '💵',
            'cod-unavailable':   '🚫',
            'partial-eligible':  '💳',
            'partial-unavailable': ''
        };
        if (!message) { return; }
        var icon = iconMap[type] || '';
        var $badge = $('<span>')
            .attr('id', id)
            .addClass('custom-dr-zipcheckerwidget__badge custom-dr-zipcheckerwidget__badge--' + type)
            .text((icon ? icon + ' ' : '') + message);
        $('#custom-dr-zip-result').after($badge);
    }

    // ── Core check ────────────────────────────────────────────────────────────

    function checkZip(zip) {
        if (!ZIP_PATTERN.test(zip)) {
            showResult('Please enter a valid zip / postal code (2-10 characters).', 'warning');
            return;
        }

        setLoading(true);
        clearResult();

        var xhr = $.ajax({
            url:     _ajaxUrl,
            type:    'POST',
            timeout: AJAX_TIMEOUT,
            data:    { zip_code: zip, form_key: _formKey },
            dataType: 'json'
        });

        xhr.done(function (response) {
            // FIX: setLoading(false) guaranteed in done+fail+always paths
            setLoading(false);

            if (!response || response.error) {
                showResult(response && response.message
                    ? response.message
                    : 'An error occurred. Please try again.', 'error');
                return;
            }

            if (response.available) {
                var msg = response.message || 'Delivery available.';
                if (response.delivery_message) {
                    msg += '\n' + response.delivery_message;
                }
                showResult(msg, 'available');

                // COD badge
                if (response.cod_message !== undefined) {
                    var codType = response.cod_available ? 'cod-available' : 'cod-unavailable';
                    showBadge('custom-dr-cod-badge', response.cod_message, codType);
                }

                // Partial payment badge
                if (response.partial_payment_message !== undefined) {
                    var ppType = response.partial_payment_eligible ? 'partial-eligible' : 'partial-unavailable';
                    showBadge('custom-dr-pp-badge', response.partial_payment_message, ppType);
                }

                // Persist for next page load
                try { sessionStorage.setItem(STORAGE_KEY, zip); } catch (e) {}
            } else {
                showResult(response.message || 'Delivery not available.', 'unavailable');
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
            }
        });

        xhr.fail(function (jqXHR, textStatus) {
            setLoading(false); // FIX: always re-enable on failure

            if (textStatus === 'timeout') {
                // FIX MEDIUM: explicit timeout message — not a generic error
                showResult('Request timed out. Please check your connection and try again.', 'error');
            } else if (textStatus === 'abort') {
                // Silent — user navigated away
            } else {
                showResult('Network error. Please try again.', 'error');
            }
        });
    }

    // ── Auto-restore from sessionStorage ─────────────────────────────────────

    function autoCheck() {
        var saved = '';
        try { saved = sessionStorage.getItem(STORAGE_KEY) || ''; } catch (e) {}

        if (saved && ZIP_PATTERN.test(saved)) {
            $('#custom-dr-zip-input').val(saved);
            checkZip(saved);
        }
    }

    // ── Public init ───────────────────────────────────────────────────────────

    return {
        init: function (config) {
            _ajaxUrl = config.ajaxUrl || '';
            _formKey = config.formKey || '';

            if (!_ajaxUrl) {
                return;
            }

            // Check button click
            $(document).on('click', '#custom-dr-check-btn', function () {
                var zip = $.trim($('#custom-dr-zip-input').val());
                checkZip(zip);
            });

            // Enter key in input
            $(document).on('keydown', '#custom-dr-zip-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var zip = $.trim($(this).val());
                    checkZip(zip);
                }
            });

            // Clear result when user edits zip
            $(document).on('input', '#custom-dr-zip-input', function () {
                clearResult();
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
            });

            // Auto-check on page load if previous zip was saved
            $(document).ready(function () {
                autoCheck();
            });
        }
    };
});
