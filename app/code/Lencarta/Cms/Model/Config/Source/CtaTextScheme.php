<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CtaTextScheme implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'inherit', 'label' => __('Inherit')],
            ['value' => 'light', 'label' => __('Light')],
            ['value' => 'dark', 'label' => __('Dark')],
            ['value' => 'brand', 'label' => __('Brand')],
        ];
    }
}
