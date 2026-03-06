<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model;

use Magento\Framework\Model\AbstractModel;
use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;

/**
 * @method int    getZipcodeId()
 * @method string getZipCode()
 * @method string getRestrictionType()
 * @method int    getStatus()
 * @method string getCustomerGroupIds()
 * @method string getCategoryIds()
 * @method string getStoreIds()
 * @method string getDescription()
 * @method int    getSortOrder()
 */
class ZipCode extends AbstractModel
{
    public const STATUS_ENABLED  = 1;
    public const STATUS_DISABLED = 0;

    protected function _construct(): void
    {
        $this->_init(ZipCodeResource::class);
    }

    /** @return int[] Empty = all groups */
    public function getCustomerGroupIdsArray(): array
    {
        return $this->parseCommaSeparatedInts((string) $this->getData('customer_group_ids'));
    }

    /** @return int[] Empty = all categories */
    public function getCategoryIdsArray(): array
    {
        return $this->parseCommaSeparatedInts((string) $this->getData('category_ids'));
    }

    /** @return int[] Empty = all stores */
    public function getStoreIdsArray(): array
    {
        return $this->parseCommaSeparatedInts((string) $this->getData('store_ids'));
    }

    /** Returns true if COD payment is restricted for this zip rule */
    public function isCodRestricted(): bool
    {
        return (bool) $this->getData('cod_restricted');
    }

    /** Returns true if partial/installment payment is eligible for this zip rule */
    public function isPartialPaymentEligible(): bool
    {
        return (bool) $this->getData('partial_payment_eligible');
    }

    /** @return int[] */
    private function parseCommaSeparatedInts(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }
}
