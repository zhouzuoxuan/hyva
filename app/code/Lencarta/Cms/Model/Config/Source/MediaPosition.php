<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MediaPosition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'right', 'label' => __('Image Right')],
            ['value' => 'left', 'label' => __('Image Left')],
        ];
    }
}
