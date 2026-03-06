<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Block\Checkout;

use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Outputs window.customDrCheckoutConfig JSON for the checkout KO mixin.
 * Only rendered when delivery_restriction/checkout/show_zip_checker = 1.
 */
class ZipCheckerConfig extends Template
{
    protected $_template = 'Custom_DeliveryRestriction::checkout/zip_checker_config.phtml';

    public function __construct(
        Context          $context,
        private readonly Config  $config,
        private readonly FormKey $formKey,
        array            $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isCheckoutZipCheckerEnabled();
    }

    public function getConfigJson(): string
    {
        return (string) json_encode([
            'enabled'  => $this->isEnabled(),
            'ajaxUrl'  => $this->getUrl('deliveryrestriction/ajax/check'),
            'formKey'  => $this->formKey->getFormKey(),
            'title'    => $this->config->getCheckoutCheckerTitle(),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
