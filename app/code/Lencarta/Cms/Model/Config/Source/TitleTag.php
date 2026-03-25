<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TitleTag implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'h1', 'label' => __('H1')],
            ['value' => 'h2', 'label' => __('H2')],
            ['value' => 'h3', 'label' => __('H3')],
            ['value' => 'div', 'label' => __('Div')],
        ];
    }
}
