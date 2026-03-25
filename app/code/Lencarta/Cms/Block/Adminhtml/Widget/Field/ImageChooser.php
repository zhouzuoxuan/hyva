<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Adminhtml\Widget\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class ImageChooser extends AbstractField
{
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $config = (array) $this->getData('config');
        $buttonLabel = (string) ($config['button']['open'] ?? __('Choose Image...'));

        $input = $this->createElement('text', $element, [
            'class' => 'widget-option input-text admin__control-text',
        ]);
        $input->addCustomAttribute('data-force_static_path', '1');

        if ($element->getRequired()) {
            $input->addClass('required-entry');
        }

        $sourceUrl = $this->getUrl('cms/wysiwyg_images/index', [
            'target_element_id' => $element->getId(),
            'type' => 'file',
            'static_urls_allowed' => 1,
        ]);

        $escapedSourceUrl = $this->escapeJs($sourceUrl);
        $button = $this->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setType('button')
            ->setClass('btn-chooser')
            ->setLabel($buttonLabel)
            ->setOnClick(
                "require(['mage/adminhtml/browser'], function () {"
                . "if (typeof MediabrowserUtility !== 'undefined') {"
                . "MediabrowserUtility.openDialog('{$escapedSourceUrl}');"
                . "} else {"
                . "console.error('MediabrowserUtility is not available');"
                . "}"
                . "}); return false;"
            )
            ->setDisabled($element->getReadonly());

        $previewId = $element->getId() . '_preview';
        $inputId = $element->getId();

        $script = <<<HTML
<script>
require(['jquery'], function ($) {
    var input = $('#{$inputId}');
    var preview = $('#{$previewId}');

    function updatePreview() {
        var value = $.trim(input.val() || '');
        if (!value) {
            preview.hide().attr('src', '');
            return;
        }
        preview.attr('src', value).show();
    }

    input.on('change keyup', updatePreview);
    updatePreview();
});
</script>
HTML;

        $previewHtml = '<div style="margin-top:12px;">'
            . '<img id="' . $this->escapeHtmlAttr($previewId) . '"'
            . ' src="' . $this->escapeHtmlAttr((string) $element->getValue()) . '"'
            . ' alt="' . $this->escapeHtmlAttr((string) __('Selected image preview')) . '"'
            . ' style="display:none; max-width:220px; height:auto; border:1px solid #d6d6d6; padding:4px; background:#fff;" />'
            . '</div>';

        $notice = '<p class="note"><span>'
            . $this->escapeHtml((string) __('Choose an image from the media gallery. Static media paths are enforced for widget output.'))
            . '</span></p>';

        $html = $input->getElementHtml() . '&nbsp;' . $button->toHtml() . $previewHtml . $notice . $script;
        $this->appendAfterHtml($element, $html);

        return $element;
    }
}
