<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RestrictionType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'blacklist', 'label' => __('Blacklist — Block listed zips (all others can order)')],
            ['value' => 'whitelist', 'label' => __('Whitelist — Allow ONLY listed zips (all others blocked)')],
        ];
    }
}
