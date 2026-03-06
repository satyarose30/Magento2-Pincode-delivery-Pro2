<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Plugin;

use Custom\DeliveryRestriction\Helper\CategoryExtractor;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Checkout Shipping Step — Layer 1 of dual protection.
 *
 * Fires when the customer clicks "Next" at the shipping step.
 * Throws LocalizedException to keep the customer on the shipping step.
 *
 * ALL CODE_REVIEW fixes applied:
 *
 *  MEDIUM — Explicit customer group and category IDs:
 *   Reads customer group from the active quote (not session) and passes
 *   category IDs from cart items into ZipValidator::isAvailable(), making
 *   shipping-step validation consistent with observer-level validation.
 *
 *  MEDIUM — Centralized message:
 *   Uses Config::getCheckoutErrorMessageForZip() — same logic as observer,
 *   no drift risk.
 *
 *  LOW — No double-escaping:
 *   The zip code is NOT pre-escaped before being injected into the message.
 *   Magento escapes error messages at render-time; pre-escaping would show
 *   HTML entities (e.g. &#x27;) to the customer in some flows.
 */
class ShippingInformationPlugin
{
    public function __construct(
        private readonly Config                  $config,
        private readonly ZipValidator            $zipValidator,
        private readonly StoreManagerInterface   $storeManager,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CategoryExtractor       $categoryExtractor
    ) {}

    /**
     * @throws LocalizedException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        int                           $cartId,
        ShippingInformationInterface  $addressInformation
    ): array {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId) || !$this->config->isBlockOrderEnabled($storeId)) {
            return [$cartId, $addressInformation];
        }

        $shippingAddress = $addressInformation->getShippingAddress();
        if ($shippingAddress === null) {
            return [$cartId, $addressInformation];
        }

        $zipCode = trim((string) $shippingAddress->getPostcode());
        if ($zipCode === '') {
            return [$cartId, $addressInformation];
        }

        // FIX MEDIUM: resolve customer group and categories from the active quote
        $quote           = $this->cartRepository->getActive($cartId);
        $customerGroupId = (int) $quote->getCustomerGroupId();
        $categoryIds     = $this->categoryExtractor->extractFromQuote($quote);

        if (!$this->zipValidator->isAvailable($zipCode, $storeId, $customerGroupId, $categoryIds)) {
            // FIX MEDIUM: centralized message — no drift vs observer
            // FIX LOW: pass raw zip — no pre-escaping; Magento escapes at render-time
            $message = $this->config->getCheckoutErrorMessageForZip($zipCode, $storeId);
            throw new LocalizedException(__($message));
        }

        return [$cartId, $addressInformation];
    }
}
