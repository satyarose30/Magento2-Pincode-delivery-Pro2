<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Ajax;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Custom\DeliveryRestriction\Model\ZipValidator;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;

/**
 * POST /deliveryrestriction/ajax/check
 *
 * Accepts: { zip_code: string, form_key: string }
 * Returns: JSON payload — see execute() docblock.
 *
 * ALL CODE_REVIEW fixes applied:
 *
 *  MEDIUM — Module logger:
 *   Uses Custom\DeliveryRestriction\Logger\Logger instead of generic PSR
 *   logger, so all controller errors appear in delivery_restriction.log.
 *
 *  LOW — Raw zip in payload:
 *   zip_code in the success payload is NOT re-escaped — the frontend
 *   uses escapeHtml() via jQuery's .text() setter, so pre-escaping here
 *   would produce double-escaped HTML entities in the UI.
 *
 *  No category IDs passed on product-page AJAX:
 *   Per the FIX HIGH in ZipValidator, category-scoped rules are correctly
 *   skipped when $categoryIds = [], which is the product-page context.
 *   This prevents false blocks on the product page checker.
 */
class Check implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const ZIP_PATTERN = '/^[a-zA-Z0-9\s\-]{2,10}$/';

    public function __construct(
        private readonly RequestInterface      $request,
        private readonly JsonFactory           $jsonFactory,
        private readonly ZipValidator          $zipValidator,
        private readonly Config                $config,
        private readonly FormKeyValidator      $formKeyValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger                $logger         // FIX MEDIUM: module logger
    ) {}

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true; // CSRF handled via form_key below
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Response contract:
     *  Error   → { error: true,  message: string }
     *  Success → { error: false, available: bool, zip_code: string,
     *               message: string [, delivery_message: string, delivery: {…}] }
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->formKeyValidator->validate($this->request)) {
                return $result->setData($this->err((string) __('Invalid security token. Please refresh the page.')));
            }

            $rawZip = trim((string) $this->request->getParam('zip_code', ''));

            if ($rawZip === '') {
                return $result->setData($this->err((string) __('Please enter a zip code.')));
            }

            if (!preg_match(self::ZIP_PATTERN, $rawZip)) {
                return $result->setData($this->err(
                    (string) __('Please enter a valid zip / postal code (letters, digits, hyphens; 2-10 characters).')
                ));
            }

            $storeId = (int) $this->storeManager->getStore()->getId();

            // No category IDs on product-page — category-scoped rules skipped (correct per FIX HIGH)
            $isAvailable = $this->zipValidator->isAvailable($rawZip, $storeId);

            $payload = [
                'error'     => false,
                'available' => $isAvailable,
                'zip_code'  => $rawZip, // FIX LOW: raw — frontend escapes via .text()
            ];

            if ($isAvailable) {
                $payload['message'] = $this->config->getAvailableMessage($storeId);

                $delivery = $this->zipValidator->getEstimatedDelivery($storeId);
                if ($delivery !== null) {
                    $payload['delivery']         = $delivery;
                    // FIX: delivery text now uses the editable admin template (was hardcoded)
                    $payload['delivery_message'] = $this->config->renderDeliveryText($delivery, $storeId);
                }

                // COD availability for this zip
                if ($this->config->isCodRestrictionEnabled($storeId)) {
                    $codOk = $this->zipValidator->isCodAvailable($rawZip, $storeId);
                    $payload['cod_available'] = $codOk;
                    $payload['cod_message']   = $codOk
                        ? $this->config->getCodAvailableMessage($storeId)
                        : $this->config->getCodUnavailableMessage($storeId);
                }

                // Partial payment eligibility
                if ($this->config->isPartialPaymentEnabled($storeId)) {
                    $ppEligible = $this->zipValidator->isPartialPaymentEligible($rawZip, $storeId);
                    $payload['partial_payment_eligible'] = $ppEligible;
                    $ppMsg = $ppEligible
                        ? $this->config->getPartialPaymentEligibleMessage($storeId)
                        : $this->config->getPartialPaymentNotEligibleMessage($storeId);
                    if ($ppMsg !== '') {
                        $payload['partial_payment_message'] = $ppMsg;
                    }
                }
            } else {
                $payload['message'] = $this->config->getUnavailableMessage($storeId);
            }

            return $result->setData($payload);

        } catch (\Throwable $e) {
            $this->logger->error(
                '[DeliveryRestriction] AJAX check failed: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return $result->setData($this->err((string) __('An unexpected error occurred. Please try again.')));
        }
    }

    /** @return array{error:true, message:string} */
    private function err(string $message): array
    {
        return ['error' => true, 'message' => $message];
    }
}
