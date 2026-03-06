<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\ResourceModel\ZipCode\Grid;

use Custom\DeliveryRestriction\Model\ResourceModel\ZipCode as ZipCodeResource;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * Grid-specific collection that extends SearchResult.
 * This is the correct approach for Magento 2.3+ UI component data providers —
 * it exposes full filtering, sorting, and pagination via the standard
 * UiComponent DataProvider mechanism.
 *
 * CODE_REVIEW Low note: virtual types are an alternative but CollectionFactory
 * mapping with a concrete SearchResult subclass is more explicit, easier to
 * debug, and the officially recommended approach in Magento DevDocs.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface        $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface       $eventManager,
        string                 $mainTable      = 'custom_dr_zipcode',
        string                 $resourceModel  = ZipCodeResource::class,
        string                 $identifierName = 'zipcode_id',
        string                 $connectionName = 'default',
        ?AdapterInterface      $connection     = null,
        ?AbstractDb            $resource       = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName,
            $connection,
            $resource
        );
    }

    protected function _initSelect(): static
    {
        parent::_initSelect();
        // Qualify zipcode_id to prevent ambiguous column errors on joins
        $this->addFilterToMap('zipcode_id', 'main_table.zipcode_id');
        return $this;
    }
}
