<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ContentPosition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'left-top', 'label' => __('Left / Top')],
            ['value' => 'left-center', 'label' => __('Left / Centre')],
            ['value' => 'left-bottom', 'label' => __('Left / Bottom')],
            ['value' => 'center-center', 'label' => __('Centre / Centre')],
            ['value' => 'right-top', 'label' => __('Right / Top')],
            ['value' => 'right-center', 'label' => __('Right / Centre')],
            ['value' => 'right-bottom', 'label' => __('Right / Bottom')],
        ];
    }
}
