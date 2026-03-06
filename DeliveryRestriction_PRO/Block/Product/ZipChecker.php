<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Block\Product;

use Custom\DeliveryRestriction\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ZipChecker extends Template
{
    protected $_template = 'Custom_DeliveryRestriction::product/zip_checker.phtml';

    public function __construct(
        Context         $context,
        private readonly Config  $config,
        private readonly FormKey $formKey,
        array           $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        // FIX MEDIUM: pass current store ID for multi-store correctness
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->config->isEnabled($storeId) && $this->config->isProductPageEnabled($storeId);
    }

    public function getWidgetTitle(): string
    {
        return $this->escapeHtml($this->config->getWidgetTitle());
    }

    public function getInputPlaceholder(): string
    {
        return $this->escapeHtmlAttr($this->config->getInputPlaceholder());
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('deliveryrestriction/ajax/check');
    }

    /**
     * JSON config blob for the RequireJS component.
     * All values safe for embedding inside a JS object literal in a <script> tag.
     */
    public function getComponentConfigJson(): string
    {
        return (string) json_encode(
            [
                'ajaxUrl' => $this->getAjaxUrl(),
                'formKey' => $this->formKey->getFormKey(),
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
