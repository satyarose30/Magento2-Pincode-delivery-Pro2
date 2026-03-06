<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Adminhtml\Zipcode;

use Custom\DeliveryRestriction\Logger\Logger;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Custom\DeliveryRestriction\Model\ZipCodeFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Custom_DeliveryRestriction::manage_zipcodes';

    public function __construct(
        Context $context,
        private readonly ZipCodeFactory        $zipCodeFactory,
        private readonly ZipCodeResource       $zipCodeResource,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Logger                $logger
    ) { parent::__construct($context); }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $data     = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        $id      = (int) ($data['zipcode_id'] ?? 0);
        $zipCode = $this->zipCodeFactory->create();

        if ($id) {
            $this->zipCodeResource->load($zipCode, $id);
            if (!$zipCode->getId()) {
                $this->messageManager->addErrorMessage(__('This zip code rule no longer exists.'));
                return $redirect->setPath('*/*/index');
            }
        }

        $rawZip = strtoupper(trim((string) ($data['zip_code'] ?? '')));
        if ($rawZip === '') {
            $this->messageManager->addErrorMessage(__('Zip code cannot be empty.'));
            $this->dataPersistor->set('custom_dr_zipcode', $data);
            return $redirect->setPath('*/*/edit', ['zipcode_id' => $id ?: null]);
        }

        $data['zip_code']           = $rawZip;
        $data['customer_group_ids'] = $this->normaliseMultiValue($data['customer_group_ids'] ?? '');
        $data['category_ids']       = $this->normaliseMultiValue($data['category_ids']       ?? '');
        $data['store_ids']          = $this->normaliseMultiValue($data['store_ids']           ?? '');
        $data['sort_order']         = max(0, (int) ($data['sort_order'] ?? 0));

        $zipCode->setData($data);

        try {
            $this->zipCodeResource->save($zipCode);
            $this->messageManager->addSuccessMessage(__('Zip code rule saved.'));
            $this->dataPersistor->clear('custom_dr_zipcode');

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['zipcode_id' => $zipCode->getId()]);
            }
            return $redirect->setPath('*/*/index');

        } catch (\Throwable $e) {
            $this->logger->error('[DeliveryRestriction] Save error: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Could not save. See delivery_restriction.log for details.'));
            $this->dataPersistor->set('custom_dr_zipcode', $data);
            return $redirect->setPath('*/*/edit', ['zipcode_id' => $id ?: null]);
        }
    }

    private function normaliseMultiValue(mixed $v): string
    {
        if (is_array($v)) {
            return implode(',', array_filter(array_map('trim', $v)));
        }
        return trim((string) $v);
    }
}
