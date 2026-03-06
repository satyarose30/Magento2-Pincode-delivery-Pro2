<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Block\Checkout;

use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Renders the zip/pincode checker widget on the checkout shipping step.
 * Uses the same AJAX endpoint as the product-page widget.
 */
class ZipChecker extends Template
{
    protected $_template = 'Custom_DeliveryRestriction::checkout/zip_checker.phtml';

    public function __construct(
        Context              $context,
        private readonly Config  $config,
        private readonly FormKey $formKey,
        array                $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isCheckoutZipCheckerEnabled();
    }

    public function getWidgetTitle(): string
    {
        return $this->escapeHtml($this->config->getCheckoutCheckerTitle());
    }

    public function getInputPlaceholder(): string
    {
        return $this->escapeHtmlAttr($this->config->getInputPlaceholder());
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('deliveryrestriction/ajax/check');
    }

    /** @return string JSON-safe config for the JS component */
    public function getComponentConfigJson(): string
    {
        return (string) json_encode(
            [
                'ajaxUrl'   => $this->getAjaxUrl(),
                'formKey'   => $this->formKey->getFormKey(),
                'context'   => 'checkout',
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
