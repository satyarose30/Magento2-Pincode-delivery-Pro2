<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Observer;

use Custom\DeliveryRestriction\Helper\CategoryExtractor;
use Custom\DeliveryRestriction\Helper\Email as EmailHelper;
use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Order Submission Guard — Layer 2 of dual protection.
 *
 * Fires on `sales_model_service_quote_submit_before` — the last possible
 * moment before a quote becomes an order.
 * Also catches headless, REST API, and admin-placed orders that bypass
 * the checkout shipping step.
 *
 * ALL CODE_REVIEW fixes applied:
 *
 *  MEDIUM — Centralized message creation:
 *   Uses Config::getCheckoutErrorMessageForZip() — same as plugin — no drift.
 *
 *  MEDIUM — Centralized CategoryExtractor:
 *   Removes the duplicate extractCategoryIds() method; uses the shared helper.
 *
 *  LOW — Category loading:
 *   CategoryExtractor::extractFromQuote() wraps each item in try/catch,
 *   safely handling partially-loaded product objects.
 */
class ValidateZipOnOrderPlace implements ObserverInterface
{
    public function __construct(
        private readonly Config            $config,
        private readonly ZipValidator      $zipValidator,
        private readonly EmailHelper       $emailHelper,
        private readonly Logger            $logger,
        private readonly CategoryExtractor $categoryExtractor
    ) {}

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');

        if (!($quote instanceof Quote)) {
            return;
        }

        $storeId = (int) $quote->getStoreId();

        if (!$this->config->isEnabled($storeId) || !$this->config->isBlockOrderEnabled($storeId)) {
            return;
        }

        if ($quote->isVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress === null) {
            return;
        }

        $zipCode = trim((string) $shippingAddress->getPostcode());
        if ($zipCode === '') {
            return;
        }

        $customerGroupId = (int) $quote->getCustomerGroupId();
        $categoryIds     = $this->categoryExtractor->extractFromQuote($quote);

        $isAvailable = $this->zipValidator->isAvailable($zipCode, $storeId, $customerGroupId, $categoryIds);

        if (!$isAvailable) {
            if ($this->config->isLoggingEnabled($storeId)) {
                $this->logger->info('[DeliveryRestriction] Order blocked — restricted zip', [
                    'zip'            => $zipCode,
                    'store_id'       => $storeId,
                    'customer_group' => $customerGroupId,
                    'customer_email' => (string) $quote->getCustomerEmail(),
                ]);
            }

            $firstName    = (string) $quote->getCustomerFirstname();
            $lastName     = (string) $quote->getCustomerLastname();
            $customerName = trim("$firstName $lastName") ?: 'Guest';

            $this->emailHelper->sendRestrictedZipAlert(
                $zipCode,
                $customerName,
                (string) $quote->getCustomerEmail(),
                $storeId
            );

            // FIX MEDIUM: centralized message — consistent with plugin
            // FIX LOW: raw zip — no pre-escaping
            $message = $this->config->getCheckoutErrorMessageForZip($zipCode, $storeId);
            throw new LocalizedException(__($message));
        }
    }
}
