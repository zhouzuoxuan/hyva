<?php
declare(strict_types=1);

namespace Lencarta\Header\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MessageDisplayMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'blank', 'label' => __('Blank')],
            ['value' => 'tag', 'label' => __('Tag')],
        ];
    }
}
