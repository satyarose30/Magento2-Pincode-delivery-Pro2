<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ZipcodeActions extends Column
{
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item['zipcode_id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $item[$name] = [
                'edit' => [
                    'href'  => $this->urlBuilder->getUrl('custom_dr/zipcode/edit', ['zipcode_id' => $id]),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href'    => $this->urlBuilder->getUrl('custom_dr/zipcode/delete', ['zipcode_id' => $id]),
                    'label'   => __('Delete'),
                    'confirm' => [
                        'title'   => __('Delete Rule #%1', $id),
                        'message' => __('Are you sure you want to delete this zip code rule? This cannot be undone.'),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
