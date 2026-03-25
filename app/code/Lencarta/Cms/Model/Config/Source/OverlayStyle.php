<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OverlayStyle implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'dark', 'label' => __('Dark')],
            ['value' => 'light', 'label' => __('Light')],
            ['value' => 'brand', 'label' => __('Brand')],
            ['value' => 'none', 'label' => __('None')],
        ];
    }
}
