<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Observer;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

/**
 * Hides (or reveals) the configured payment methods based on zip code.
 *
 * Fires on: payment_method_is_active
 *
 * Event data:
 *   method_instance — AbstractMethod
 *   result          — DataObject with 'is_available' flag
 *   quote           — Quote|null (may be null in some callers)
 *
 * Logic:
 *   1. Feature disabled → no-op.
 *   2. Method code not in restricted list → no-op (allow other methods).
 *   3. Resolve shipping zip from quote or checkout session.
 *   4. Empty zip → no-op (don't hide COD before address is entered).
 *   5. Call ZipValidator::isCodAvailable() — if false, mark method unavailable.
 *
 * Thread-safety note:
 *   This observer never throws. Any failure is logged and silently skipped
 *   to avoid breaking checkout for non-delivery-restriction reasons.
 */
class RestrictPaymentByZip implements ObserverInterface
{
    public function __construct(
        private readonly Config          $config,
        private readonly ZipValidator    $zipValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly Logger          $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $this->doExecute($observer);
        } catch (\Throwable $e) {
            $this->logger->error(
                '[DeliveryRestriction] RestrictPaymentByZip error: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    private function doExecute(Observer $observer): void
    {
        $quote   = $observer->getData('quote');
        $storeId = $quote instanceof Quote ? (int) $quote->getStoreId() : null;

        if (!$this->config->isCodRestrictionEnabled($storeId)) {
            return;
        }

        $methodInstance = $observer->getData('method_instance');
        if ($methodInstance === null) {
            return;
        }

        $methodCode      = (string) $methodInstance->getCode();
        $restrictedCodes = $this->config->getCodPaymentCodes($storeId);

        if (!in_array($methodCode, $restrictedCodes, true)) {
            return; // this payment method is not subject to zip restriction
        }

        $zip = $this->resolveShippingZip($observer);

        if ($zip === '') {
            return; // no zip entered yet — don't hide the method
        }

        if (!$this->zipValidator->isCodAvailable($zip, $storeId)) {
            /** @var \Magento\Framework\DataObject $result */
            $result = $observer->getData('result');
            if ($result !== null) {
                $result->setData('is_available', false);

                if ($this->config->isLoggingEnabled($storeId)) {
                    $this->logger->info('[DeliveryRestriction] COD hidden for zip', [
                        'zip'         => $zip,
                        'method_code' => $methodCode,
                    ]);
                }
            }
        }
    }

    /**
     * Resolves shipping zip from:
     *  1. quote data attached to the event (present in REST API / admin contexts)
     *  2. checkout session quote (standard frontend checkout)
     */
    private function resolveShippingZip(Observer $observer): string
    {
        // Try event-attached quote first
        $quote = $observer->getData('quote');
        if ($quote instanceof Quote) {
            $addr = $quote->getShippingAddress();
            if ($addr !== null) {
                $zip = trim((string) $addr->getPostcode());
                if ($zip !== '') {
                    return $zip;
                }
            }
        }

        // Fall back to checkout session
        try {
            $sessionQuote = $this->checkoutSession->getQuote();
            $addr         = $sessionQuote?->getShippingAddress();
            if ($addr !== null) {
                return trim((string) $addr->getPostcode());
            }
        } catch (\Throwable) {
            // session may not be available in all contexts (e.g. REST API)
        }

        return '';
    }
}
