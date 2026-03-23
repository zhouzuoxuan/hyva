<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Adminhtml\Widget\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Repeater extends AbstractField
{
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $config = (array) $this->getData('config');
        $buttonLabel = (string) ($config['button_label'] ?? __('Add Row'));
        $emptyText = (string) ($config['empty_text'] ?? __('No items yet.'));
        $placeholder = (string) ($config['row_placeholder'] ?? __('Enter text'));

        $hidden = $this->createElement('hidden', $element, [
            'class' => 'widget-option',
        ]);

        $hiddenId = (string) $hidden->getId();
        $containerId = $hiddenId . '_rows';
        $emptyId = $hiddenId . '_empty';
        $buttonId = $hiddenId . '_add';
        $placeholderJs = json_encode($placeholder, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $emptyTextJs = json_encode($emptyText, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $removeLabelJs = json_encode((string) __('Remove'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $html = $hidden->getElementHtml();
        $html .= '<div class="admin__control-table-wrapper" style="margin-top:8px;">';
        $html .= '<table class="data-grid" style="width:100%;">';
        $html .= '<thead><tr><th>' . $this->escapeHtml(__('USP Text')) . '</th><th style="width:90px;">' . $this->escapeHtml(__('Action')) . '</th></tr></thead>';
        $html .= '<tbody id="' . $this->escapeHtmlAttr($containerId) . '"></tbody>';
        $html .= '</table>';
        $html .= '<div id="' . $this->escapeHtmlAttr($emptyId) . '" class="note" style="margin:8px 0 0;"><span>' . $this->escapeHtml($emptyText) . '</span></div>';
        $html .= '<div style="margin-top:12px;"><button id="' . $this->escapeHtmlAttr($buttonId) . '" type="button" class="action-default scalable"><span>' . $this->escapeHtml($buttonLabel) . '</span></button></div>';
        $html .= '</div>';

        $currentValueJs = json_encode((string) $element->getValue(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $script = <<<HTML
<script>
require(['jquery', 'underscore'], function ($, _) {
    var hidden = $('#{$hiddenId}');
    var rowsContainer = $('#{$containerId}');
    var emptyState = $('#{$emptyId}');
    var addButton = $('#{$buttonId}');
    var placeholder = {$placeholderJs};
    var emptyText = {$emptyTextJs};
    var removeLabel = {$removeLabelJs};
    function parseValue(value) {
        if (!value) {
            return [];
        }
        try {
            var parsed = JSON.parse(value);
            if ($.isArray(parsed)) {
                return $.map(parsed, function (item) {
                    item = $.trim(String(item || ''));
                    return item ? item : null;
                });
            }
        } catch (e) {}
        return $.map(String(value).split(/\r\n|\r|\n/), function (item) {
            item = $.trim(item || '');
            return item ? item : null;
        });
    }
    function sync() {
        var values = [];
        rowsContainer.find('input[data-role="repeater-item"]').each(function () {
            var value = $.trim($(this).val() || '');
            if (value) {
                values.push(value);
            }
        });
        hidden.val(JSON.stringify(values)).trigger('change');
        if (values.length) {
            emptyState.hide();
        } else {
            emptyState.show().find('span').text(emptyText);
        }
    }
    function rowHtml(value) {
        value = value || '';
        return '<tr>' +
            '<td><input type="text" data-role="repeater-item" class="admin__control-text input-text" style="width:100%;" placeholder="' + _.escape(placeholder) + '" value="' + _.escape(value) + '" /></td>' +
            '<td><button type="button" class="action-delete" data-role="remove-row"><span>' + _.escape(removeLabel) + '</span></button></td>' +
            '</tr>';
    }
    function addRow(value) {
        rowsContainer.append(rowHtml(value));
        sync();
    }
    addButton.on('click', function () {
        addRow('');
    });
    rowsContainer.on('click', '[data-role="remove-row"]', function () {
        $(this).closest('tr').remove();
        sync();
    });
    rowsContainer.on('keyup change', '[data-role="repeater-item"]', sync);
    $.each(parseValue({$currentValueJs}), function (_, value) {
        addRow(value);
    });
    if (!rowsContainer.children().length) {
        sync();
    }
});
</script>
HTML;

        $this->appendAfterHtml($element, $html . $script);
        return $element;
    }
}
