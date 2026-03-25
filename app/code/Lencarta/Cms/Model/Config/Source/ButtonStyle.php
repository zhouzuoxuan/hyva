<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonStyle implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'primary', 'label' => __('Primary')],
            ['value' => 'secondary-light', 'label' => __('Secondary Light')],
            ['value' => 'secondary-dark', 'label' => __('Secondary Dark')],
            ['value' => 'outline-light', 'label' => __('Outline Light')],
            ['value' => 'outline-dark', 'label' => __('Outline Dark')],
        ];
    }
}
