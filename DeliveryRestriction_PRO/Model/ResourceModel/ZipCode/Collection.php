<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel\ZipCode;

use Custom\DeliveryRestriction\Model\ZipCode;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'zipcode_id';

    protected function _construct(): void
    {
        $this->_init(ZipCode::class, ZipCodeResource::class);
    }

    public function addActiveFilter(): static
    {
        $this->addFieldToFilter('status', ZipCode::STATUS_ENABLED);
        return $this;
    }

    /**
     * Include records scoped to this store OR scoped to all stores (empty/null store_ids).
     * Uses FIND_IN_SET for comma-separated storage format.
     */
    public function addStoreFilter(int $storeId): static
    {
        $this->getSelect()->where(
            'store_ids IS NULL OR store_ids = "" OR FIND_IN_SET(?, store_ids)',
            (string) $storeId
        );
        return $this;
    }

    /**
     * Include records scoped to this customer group OR scoped to all groups (empty/null).
     * FIX (CODE_REVIEW High): always filter by resolved customer group ID so
     * per-record group restrictions are never silently ignored.
     */
    public function addCustomerGroupFilter(int $customerGroupId): static
    {
        $this->getSelect()->where(
            'customer_group_ids IS NULL OR customer_group_ids = "" OR FIND_IN_SET(?, customer_group_ids)',
            (string) $customerGroupId
        );
        return $this;
    }

    public function addSortOrderSort(): static
    {
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);
        return $this;
    }
}
