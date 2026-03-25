<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OverlayOpacity implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '20', 'label' => __('20')],
            ['value' => '35', 'label' => __('35')],
            ['value' => '50', 'label' => __('50')],
            ['value' => '65', 'label' => __('65')],
        ];
    }
}
