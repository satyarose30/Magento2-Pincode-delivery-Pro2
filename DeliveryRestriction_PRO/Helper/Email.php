<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Helper;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Admin email notification helper — PRO version.
 *
 * ALL CODE_REVIEW fixes applied:
 *
 *  HIGH — CC implementation:
 *   getAdminEmailCc() is now read and each address is passed to addCc().
 *   Previously the config CC field was exposed but never consumed.
 *
 *  MEDIUM — finally guard:
 *   inlineTranslation->resume() is placed in a finally block guarded by a
 *   $suspended flag — resume() is only called when suspend() actually ran,
 *   preventing state corruption from pre-suspend exceptions.
 *
 *  MEDIUM — email validation:
 *   Both recipient and CC addresses are validated with filter_var(FILTER_VALIDATE_EMAIL)
 *   before being passed to TransportBuilder. Invalid addresses are skipped
 *   with a warning log entry rather than causing a transport exception.
 */
class Email
{
    private const TEMPLATE_RESTRICTED_ZIP = 'custom_dr_restricted_zip_alert';

    public function __construct(
        private readonly Config                $config,
        private readonly TransportBuilder      $transportBuilder,
        private readonly StateInterface        $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger                $logger
    ) {}

    public function sendRestrictedZipAlert(
        string $zipCode,
        string $customerName,
        string $customerEmail,
        ?int   $storeId = null
    ): void {
        if (!$this->config->isAdminEmailEnabled($storeId)) {
            return;
        }

        $recipient = $this->config->getAdminEmailRecipient($storeId);
        if (!$this->isValidEmail($recipient)) {
            $this->logger->warning('[DeliveryRestriction] Admin alert skipped — recipient email invalid or empty', [
                'recipient' => $recipient,
            ]);
            return;
        }

        // FIX MEDIUM: track suspend state for finally guard
        $suspended = false;

        try {
            $store = $this->storeManager->getStore($storeId);

            $this->inlineTranslation->suspend();
            $suspended = true;

            $builder = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_RESTRICTED_ZIP)
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => (int) $store->getId(),
                ])
                ->setTemplateVars([
                    'zip_code'       => htmlspecialchars($zipCode,        ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'customer_name'  => htmlspecialchars($customerName,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'customer_email' => htmlspecialchars($customerEmail,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'store_name'     => (string) $store->getName(),
                ])
                ->setFromByScope($this->config->getAdminEmailSender($storeId), (int) $store->getId())
                ->addTo($recipient);

            // FIX HIGH: actually consume the CC config field and add each valid address
            foreach ($this->getValidCcAddresses($storeId) as $ccEmail) {
                $builder->addCc($ccEmail);
            }

            $builder->getTransport()->sendMessage();

            $this->logger->info('[DeliveryRestriction] Admin alert sent', [
                'zip'       => $zipCode,
                'recipient' => $recipient,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error(
                '[DeliveryRestriction] Failed to send admin alert: ' . $e->getMessage(),
                ['exception' => $e]
            );
        } finally {
            // FIX MEDIUM: only resume if we actually suspended
            if ($suspended) {
                $this->inlineTranslation->resume();
            }
        }
    }

    /**
     * Parse, validate and deduplicate the CC config field.
     * FIX MEDIUM: defensively validate each address; skip invalids with a log entry.
     *
     * @return string[]
     */
    private function getValidCcAddresses(?int $storeId = null): array
    {
        $rawCc = $this->config->getAdminEmailCc($storeId);
        if ($rawCc === '') {
            return [];
        }

        $valid = [];
        foreach (array_map('trim', explode(',', $rawCc)) as $email) {
            if ($email === '') {
                continue;
            }
            if ($this->isValidEmail($email)) {
                $valid[] = $email;
            } else {
                $this->logger->warning('[DeliveryRestriction] Skipping invalid CC address', ['email' => $email]);
            }
        }

        return array_values(array_unique($valid));
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
