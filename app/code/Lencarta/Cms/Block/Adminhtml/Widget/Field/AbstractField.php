<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Adminhtml\Widget\Field;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Factory as ElementFactory;

abstract class AbstractField extends Template
{
    protected ElementFactory $elementFactory;

    public function __construct(
        Context $context,
        ElementFactory $elementFactory,
        array $data = []
    ) {
        $this->elementFactory = $elementFactory;
        parent::__construct($context, $data);
    }

    protected function createElement(string $type, AbstractElement $element, array $extraData = []): AbstractElement
    {
        $data = array_merge($element->getData(), $extraData);
        $field = $this->elementFactory->create($type, ['data' => $data]);
        $field->setId((string) ($extraData['id'] ?? $element->getId()));
        $field->setForm($element->getForm());
        $field->setName((string) ($extraData['name'] ?? $element->getName()));

        return $field;
    }

    protected function appendAfterHtml(AbstractElement $element, string $html): void
    {
        $existing = (string) $element->getData('after_element_html');
        $element->setData('after_element_html', $existing . $html);
    }
}
