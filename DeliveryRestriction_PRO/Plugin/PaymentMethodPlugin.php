<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Plugin;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Payment\Model\MethodList;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Layer 3 — Payment Method Filter.
 *
 * Fires when Magento builds the available payment method list on checkout.
 * When COD restriction is enabled and the shipping zip is restricted for COD,
 * this plugin removes the configured COD payment method codes from the list
 * so the customer never sees COD as an option for that zip.
 *
 * Works for all storefront types including PWA / GraphQL because
 * MethodList::getAvailableMethods() is the single authoritative method
 * that every Magento checkout path calls to build the payment list.
 *
 * Admin enable/disable: Stores → Config → Delivery Restriction → COD Restriction
 *  → Enable COD Restriction = Yes
 */
class PaymentMethodPlugin
{
    public function __construct(
        private readonly Config                $config,
        private readonly ZipValidator          $zipValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger                $logger
    ) {}

    /**
     * After plugin on MethodList::getAvailableMethods().
     *
     * @param MethodList            $subject
     * @param MethodInterface[]     $result
     * @param CartInterface         $quote
     * @return MethodInterface[]
     */
    public function afterGetAvailableMethods(
        MethodList      $subject,
        array           $result,
        CartInterface   $quote
    ): array {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();

            // Only act when both features are enabled
            if (!$this->config->isEnabled($storeId) || !$this->config->isCodRestrictionEnabled($storeId)) {
                return $result;
            }

            // Only filter when virtual quotes have no shipping address
            if ($quote->isVirtual()) {
                return $result;
            }

            $shippingAddress = $quote->getShippingAddress();
            if ($shippingAddress === null) {
                return $result;
            }

            $zipCode = trim((string) $shippingAddress->getPostcode());
            if ($zipCode === '') {
                return $result;
            }

            // Check COD availability for this zip
            if ($this->zipValidator->isCodAvailable($zipCode, $storeId)) {
                return $result; // COD allowed — no change
            }

            // COD is restricted for this zip — remove configured COD payment codes
            $blockedCodes = $this->config->getCodPaymentCodes($storeId);

            $filtered = array_filter(
                $result,
                static fn(MethodInterface $method): bool =>
                    !in_array($method->getCode(), $blockedCodes, true)
            );

            if ($this->config->isLoggingEnabled($storeId)) {
                $this->logger->info('[DeliveryRestriction] COD removed from payment methods', [
                    'zip'           => $zipCode,
                    'blocked_codes' => $blockedCodes,
                ]);
            }

            return array_values($filtered);

        } catch (\Throwable $e) {
            // Never break the payment list — log and return original
            $this->logger->error(
                '[DeliveryRestriction] PaymentMethodPlugin error: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return $result;
        }
    }
}
