<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Adminhtml\Widget\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Textarea extends AbstractField
{
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $field = $this->createElement('textarea', $element, [
            'style' => 'min-height: 140px;',
            'class' => 'widget-option admin__control-textarea',
        ]);

        if ($element->getRequired()) {
            $field->addClass('required-entry');
        }

        $notice = '<p class="note"><span>'
            . $this->escapeHtml((string) __('Supports multiple lines. Line breaks are preserved on the storefront.'))
            . '</span></p>';

        $this->appendAfterHtml($element, $field->getElementHtml() . $notice);
        return $element;
    }
}
