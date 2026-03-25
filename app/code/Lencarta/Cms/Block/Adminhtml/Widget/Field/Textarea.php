<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Adminhtml\Widget\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Textarea extends AbstractField
{
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $config = (array) $this->getData('config');
        $rows = (string) ($config['rows'] ?? '6');

        $field = $this->createElement('textarea', $element, [
            'rows' => $rows,
            'style' => 'min-height: 160px;',
            'class' => 'widget-option admin__control-textarea',
        ]);

        if ($element->getRequired()) {
            $field->addClass('required-entry');
        }

        $notice = '<p class="note"><span>'
            . $this->escapeHtml((string) __('Supports multiple lines and basic HTML. Avoid scripts and inline event handlers.'))
            . '</span></p>';

        $this->appendAfterHtml($element, $field->getElementHtml() . $notice);
        return $element;
    }
}
