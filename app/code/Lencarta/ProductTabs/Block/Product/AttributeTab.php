<?php
declare(strict_types=1);

namespace Lencarta\ProductTabs\Block\Product;

use Magento\Catalog\Helper\Output as CatalogOutput;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class AttributeTab extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly EavConfig $eavConfig,
        private readonly CatalogOutput $catalogOutput,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    public function getAttributeCode(): string
    {
        return (string) $this->getData('attribute_code');
    }

    public function getTitle(): string
    {
        $code = $this->getAttributeCode();
        if ($code === '') {
            return '';
        }

        $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
        if (!$attribute || !$attribute->getId()) {
            return '';
        }

        $storeId = $this->_storeManager->getStore()->getId();

        $label = $attribute->getStoreLabel($storeId);
        return $label !== '' ? $label : (string) $attribute->getDefaultFrontendLabel();
    }

    public function canShow(): bool
    {
        $product = $this->getProduct();
        $code = $this->getAttributeCode();

        if (!$product || $code === '') {
            return false;
        }

        $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
        if (!$attribute || !$attribute->getId()) {
            return false;
        }

        $raw = $product->getData($code);
        if ($raw === null || $raw === '' || $raw === false || (is_array($raw) && empty($raw))) {
            return false;
        }

        $formatted = trim((string) $attribute->getFrontend()->getValue($product));
        if ($formatted === '' || $formatted === (string) __('No')) {
            return false;
        }

        return true;
    }

    public function getValueHtml(): string
    {
        $product = $this->getProduct();
        $code = $this->getAttributeCode();

        if (!$product || $code === '') {
            return '';
        }

        $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
        if (!$attribute || !$attribute->getId()) {
            return '';
        }

        $value = (string) $attribute->getFrontend()->getValue($product);
        return $this->catalogOutput->productAttribute($product, $value, $code);
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData('sort_order');
    }
}
