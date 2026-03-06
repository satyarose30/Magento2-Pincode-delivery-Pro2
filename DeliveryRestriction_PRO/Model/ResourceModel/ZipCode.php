<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ZipCode extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('custom_dr_zipcode', 'zipcode_id');
    }
}
