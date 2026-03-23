<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Widget;

class UspList extends AbstractWidget
{
    protected $_template = 'Lencarta_Cms::widget/usp-list.phtml';

    public function getItems(): array
    {
        return $this->getLines('items');
    }
}
