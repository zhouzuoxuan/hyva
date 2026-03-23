<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MediaPosition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'left', 'label' => __('Image Left')],
            ['value' => 'right', 'label' => __('Image Right')],
        ];
    }
}
