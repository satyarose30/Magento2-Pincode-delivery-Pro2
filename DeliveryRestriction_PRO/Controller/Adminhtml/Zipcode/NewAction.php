<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Controller\Adminhtml\Zipcode;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class NewAction extends Action
{
    public const ADMIN_RESOURCE = 'Custom_DeliveryRestriction::manage_zipcodes';

    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    { parent::__construct($context); }

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('New Zip Code Rule'));
        return $page;
    }
}
