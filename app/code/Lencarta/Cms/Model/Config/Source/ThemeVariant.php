<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ThemeVariant implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'light', 'label' => __('Light')],
            ['value' => 'muted', 'label' => __('Muted')],
            ['value' => 'dark', 'label' => __('Dark')],
        ];
    }
}
