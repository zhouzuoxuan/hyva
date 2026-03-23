<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class UspList extends Template implements BlockInterface
{
    protected $_template = 'Lencarta_Cms::widget/usp-list.phtml';

    /**
     * @return array<int, string>
     */
    public function getItemsList(): array
    {
        $items = preg_split('/\r\n|\r|\n/', (string) $this->getData('items')) ?: [];

        $items = array_map('trim', $items);
        $items = array_filter($items, static fn(string $item): bool => $item !== '');

        return array_values($items);
    }

    public function getGridColumnsClass(): string
    {
        $columns = (string) $this->getData('columns');

        return match ($columns) {
            '2' => 'md:grid-cols-2',
            '4' => 'md:grid-cols-2 xl:grid-cols-4',
            default => 'md:grid-cols-3',
        };
    }
}
