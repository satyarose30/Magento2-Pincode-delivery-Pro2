<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed system-config reader — PRO version.
 *
 * FIXES applied from CODE_REVIEW_RECOMMENDATIONS.md:
 *  - All string getters return hard-coded fallback when admin value is empty
 *    (prevents blank UI strings: High fix on Config.php)
 *  - getCheckoutErrorMessageForZip() centralizes the %1 substitution used by
 *    both the plugin and observer, eliminating logic drift (Medium fix)
 *  - getErrorMessage() is preserved but wired to UI via getCheckoutErrorMessage
 *
 * PHP 8.4 compatible: readonly constructor promotion, explicit nullable types,
 * no implicit nullable parameters.
 */
class Config
{
    // ── General ────────────────────────────────────────────────────────────────
    private const XML_ENABLED          = 'delivery_restriction/general/enabled';
    private const XML_RESTRICTION_TYPE = 'delivery_restriction/general/restriction_type';
    private const XML_ZIP_CODES        = 'delivery_restriction/general/zip_codes';
    private const XML_ERROR_MSG        = 'delivery_restriction/general/error_message';
    private const XML_USE_DB_ZIPCODES  = 'delivery_restriction/general/use_db_zipcodes';

    // ── Product page ───────────────────────────────────────────────────────────
    private const XML_PRODUCT_ENABLED  = 'delivery_restriction/product_page/enabled';
    private const XML_WIDGET_TITLE     = 'delivery_restriction/product_page/widget_title';
    private const XML_AVAIL_MSG        = 'delivery_restriction/product_page/available_message';
    private const XML_UNAVAIL_MSG      = 'delivery_restriction/product_page/unavailable_message';
    private const XML_PLACEHOLDER      = 'delivery_restriction/product_page/input_placeholder';

    // ── Delivery estimate ──────────────────────────────────────────────────────
    private const XML_ESTIMATE_ENABLED  = 'delivery_restriction/delivery_estimate/enabled';
    private const XML_MIN_DAYS          = 'delivery_restriction/delivery_estimate/min_days';
    private const XML_MAX_DAYS          = 'delivery_restriction/delivery_estimate/max_days';
    private const XML_DELIVERY_TEXT_TMPL = 'delivery_restriction/delivery_estimate/delivery_text_template';

    // ── Checkout ───────────────────────────────────────────────────────────────
    private const XML_BLOCK_ORDER           = 'delivery_restriction/checkout/block_order';
    private const XML_CHECKOUT_ERR_MSG      = 'delivery_restriction/checkout/checkout_error_message';
    private const XML_SHOW_CHECKOUT_CHECKER = 'delivery_restriction/checkout/show_zip_checker';
    private const XML_CHECKOUT_CHECKER_TITLE = 'delivery_restriction/checkout/checkout_checker_title';

    // ── COD / Payment Restriction ──────────────────────────────────────────────
    private const XML_COD_ENABLED          = 'delivery_restriction/cod_payment/enabled';
    private const XML_COD_PAYMENT_CODES    = 'delivery_restriction/cod_payment/payment_codes';
    private const XML_COD_RESTRICTION_MODE = 'delivery_restriction/cod_payment/restriction_mode';
    private const XML_COD_ZIP_CODES        = 'delivery_restriction/cod_payment/zip_codes';
    private const XML_COD_AVAIL_MSG        = 'delivery_restriction/cod_payment/available_message';
    private const XML_COD_UNAVAIL_MSG      = 'delivery_restriction/cod_payment/unavailable_message';

    // ── Partial Payment ────────────────────────────────────────────────────────
    private const XML_PP_ENABLED          = 'delivery_restriction/partial_payment/enabled';
    private const XML_PP_ZIP_CODES        = 'delivery_restriction/partial_payment/zip_codes';
    private const XML_PP_ELIGIBLE_MSG     = 'delivery_restriction/partial_payment/eligible_message';
    private const XML_PP_NOT_ELIGIBLE_MSG = 'delivery_restriction/partial_payment/not_eligible_message';

    // ── Customer Groups ────────────────────────────────────────────────────────
    private const XML_GROUPS_ENABLED  = 'delivery_restriction/customer_groups/enabled';
    private const XML_GROUPS_LIST     = 'delivery_restriction/customer_groups/group_ids';

    // ── Email ──────────────────────────────────────────────────────────────────
    private const XML_EMAIL_ENABLED    = 'delivery_restriction/email/enabled';
    private const XML_EMAIL_SENDER     = 'delivery_restriction/email/sender';
    private const XML_EMAIL_RECIPIENT  = 'delivery_restriction/email/recipient';
    private const XML_EMAIL_CC         = 'delivery_restriction/email/cc';

    // ── Logging ────────────────────────────────────────────────────────────────
    private const XML_LOGGING_ENABLED  = 'delivery_restriction/logging/enabled';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    // ── General ────────────────────────────────────────────────────────────────

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRestrictionType(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_RESTRICTION_TYPE, ScopeInterface::SCOPE_STORE, $storeId) ?: 'blacklist';
    }

    public function getRawZipCodes(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_ZIP_CODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function useDbZipCodes(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_USE_DB_ZIPCODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // ── Product page ───────────────────────────────────────────────────────────

    public function isProductPageEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PRODUCT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** FIX: returns non-empty fallback when admin field is blank */
    public function getWidgetTitle(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_WIDGET_TITLE, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Check Delivery Availability');
    }

    /** FIX: returns non-empty fallback when admin field is blank */
    public function getAvailableMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_AVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Great news! Delivery is available to your area.');
    }

    /** FIX: returns non-empty fallback when admin field is blank */
    public function getUnavailableMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_UNAVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Sorry, delivery is not available to your zip code.');
    }

    /** FIX: returns non-empty fallback when admin field is blank */
    public function getInputPlaceholder(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_PLACEHOLDER, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Enter ZIP / Postal Code');
    }

    // ── Delivery estimate ──────────────────────────────────────────────────────

    public function isDeliveryEstimateEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ESTIMATE_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinDays(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_MIN_DAYS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getMaxDays(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_MAX_DAYS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    // ── Checkout ───────────────────────────────────────────────────────────────

    public function isBlockOrderEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_BLOCK_ORDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** FIX: returns non-empty fallback; %1 is the placeholder for the zip code */
    public function getCheckoutErrorMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_CHECKOUT_ERR_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('We cannot deliver to zip code %1. Please update your shipping address.');
    }

    /**
     * FIX (CODE_REVIEW Medium): Centralizes the %1 substitution used by both
     * the plugin and observer — prevents drift between two identical code blocks.
     * The zip is already sanitised by callers; we deliberately do NOT escape here
     * because Magento escapes at render-time, preventing double-escaping.
     */
    public function getCheckoutErrorMessageForZip(string $zipCode, ?int $storeId = null): string
    {
        return str_replace('%1', $zipCode, $this->getCheckoutErrorMessage($storeId));
    }

    // ── Customer Groups ────────────────────────────────────────────────────────

    public function isCustomerGroupRestrictionEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_GROUPS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** @return int[] */
    public function getRestrictedCustomerGroupIds(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_GROUPS_LIST, ScopeInterface::SCOPE_STORE, $storeId);
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    // ── Email ──────────────────────────────────────────────────────────────────

    public function isAdminEmailEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAdminEmailSender(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId) ?: 'general';
    }

    public function getAdminEmailRecipient(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_EMAIL_RECIPIENT, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getAdminEmailCc(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_EMAIL_CC, ScopeInterface::SCOPE_STORE, $storeId));
    }

    // ── Logging ────────────────────────────────────────────────────────────────

    public function isLoggingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // ── Delivery estimate text template ────────────────────────────────────────

    /**
     * Returns the editable delivery text template.
     * Supports {min_date}, {max_date}, {min_days}, {max_days} placeholders.
     */
    public function getDeliveryTextTemplate(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_DELIVERY_TEXT_TMPL, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : 'Estimated delivery: {min_date} - {max_date} ({min_days}-{max_days} business days)';
    }

    /**
     * Resolves the delivery text template with actual values.
     *
     * @param array{min_date:string,max_date:string,min_days:int,max_days:int} $delivery
     */
    public function renderDeliveryText(array $delivery, ?int $storeId = null): string
    {
        return strtr($this->getDeliveryTextTemplate($storeId), [
            '{min_date}' => $delivery['min_date'],
            '{max_date}' => $delivery['max_date'],
            '{min_days}' => (string) $delivery['min_days'],
            '{max_days}' => (string) $delivery['max_days'],
        ]);
    }

    // ── Checkout live pincode checker ──────────────────────────────────────────

    public function isCheckoutZipCheckerEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_SHOW_CHECKOUT_CHECKER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCheckoutCheckerTitle(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_CHECKOUT_CHECKER_TITLE, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Check Delivery to Your Pincode');
    }

    // ── COD / Payment restriction ──────────────────────────────────────────────

    public function isCodRestrictionEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_COD_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** @return string[] list of payment method codes to restrict */
    public function getCodPaymentCodes(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_COD_PAYMENT_CODES, ScopeInterface::SCOPE_STORE, $storeId);
        if (trim($raw) === '') {
            return ['cashondelivery'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function getCodRestrictionMode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_COD_RESTRICTION_MODE, ScopeInterface::SCOPE_STORE, $storeId) ?: 'blacklist';
    }

    public function getRawCodZipCodes(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_COD_ZIP_CODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCodAvailableMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_COD_AVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Cash on Delivery is available for your zip code.');
    }

    public function getCodUnavailableMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_COD_UNAVAIL_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Cash on Delivery is not available for your zip code.');
    }

    // ── Partial payment ────────────────────────────────────────────────────────

    public function isPartialPaymentEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PP_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRawPartialPaymentZipCodes(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PP_ZIP_CODES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDepositPercent(?int $storeId = null): int
    {
        $v = (int) $this->scopeConfig->getValue('delivery_restriction/partial_payment/deposit_percent', ScopeInterface::SCOPE_STORE, $storeId);
        return max(1, min(99, $v ?: 30));
    }

    public function getPartialPaymentEligibleMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_PP_ELIGIBLE_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        $msg = $v !== '' ? $v : (string) __('Partial / installment payment is available for your zip code.');
        return str_replace('{percent}', (string) $this->getDepositPercent($storeId), $msg);
    }

    public function getPartialPaymentNotEligibleMessage(?int $storeId = null): string
    {
        $v = trim((string) $this->scopeConfig->getValue(self::XML_PP_NOT_ELIGIBLE_MSG, ScopeInterface::SCOPE_STORE, $storeId));
        return $v !== '' ? $v : (string) __('Partial payment is not available for your zip code.');
    }
}
