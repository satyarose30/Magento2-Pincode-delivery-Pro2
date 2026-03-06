<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Adminhtml\Zipcode;

use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Custom\DeliveryRestriction\Model\ZipCodeFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Custom_DeliveryRestriction::manage_zipcodes';

    public function __construct(
        Context $context,
        private readonly PageFactory     $pageFactory,
        private readonly ZipCodeFactory  $zipCodeFactory,
        private readonly ZipCodeResource $zipCodeResource
    ) { parent::__construct($context); }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $id      = (int) $this->getRequest()->getParam('zipcode_id');
        $zipCode = $this->zipCodeFactory->create();

        if ($id) {
            $this->zipCodeResource->load($zipCode, $id);
            if (!$zipCode->getId()) {
                $this->messageManager->addErrorMessage(__('This zip code rule no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(
            $id ? __('Edit Zip Code Rule #%1', $id) : __('New Zip Code Rule')
        );
        return $page;
    }
}
