<?php
declare(strict_types=1);

namespace Lencarta\Cms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Columns implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('1 Column')],
            ['value' => '2', 'label' => __('2 Columns')],
            ['value' => '3', 'label' => __('3 Columns')],
        ];
    }
}
