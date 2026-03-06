<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Adminhtml\Zipcode;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Custom\DeliveryRestriction\Model\ZipCodeFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Custom_DeliveryRestriction::manage_zipcodes';

    public function __construct(
        Context $context,
        private readonly ZipCodeFactory  $zipCodeFactory,
        private readonly ZipCodeResource $zipCodeResource,
        private readonly Logger          $logger
    ) { parent::__construct($context); }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $id       = (int) $this->getRequest()->getParam('zipcode_id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('No rule ID provided.'));
            return $redirect;
        }

        $zipCode = $this->zipCodeFactory->create();
        $this->zipCodeResource->load($zipCode, $id);

        if (!$zipCode->getId()) {
            $this->messageManager->addErrorMessage(__('Rule not found.'));
            return $redirect;
        }

        try {
            $this->zipCodeResource->delete($zipCode);
            $this->messageManager->addSuccessMessage(__('Zip code rule deleted.'));
        } catch (\Throwable $e) {
            $this->logger->error('[DeliveryRestriction] Delete error: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Could not delete the rule.'));
        }

        return $redirect;
    }
}
