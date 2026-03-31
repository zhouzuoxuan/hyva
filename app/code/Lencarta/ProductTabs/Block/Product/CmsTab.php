<?php
declare(strict_types=1);

namespace Lencarta\ProductTabs\Block\Product;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class CmsTab extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly FilterProvider $filterProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCmsIdentifier(): string
    {
        return (string) $this->getData('cms_identifier');
    }

    private function getCmsBlockModel(): ?DataObject
    {
        $identifier = $this->getCmsIdentifier();
        if ($identifier === '') {
            return null;
        }

        $storeId = $this->_storeManager->getStore()->getId();

        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        $block = $collection->getFirstItem();
        return $block && $block->getId() ? $block : null;
    }

    public function getTitle(): string
    {
        // layout 里显式传了 title 就优先用
        $forced = (string) $this->getData('title');
        if ($forced !== '') {
            return $forced;
        }

        $block = $this->getCmsBlockModel();
        return (string)$block?->getTitle();
    }

    public function canShow(): bool
    {
        $block = $this->getCmsBlockModel();
        if (!$block) {
            return false;
        }

        $content = trim((string) $block->getContent());
        return $content !== '';
    }

    public function getContentHtml(): string
    {
        $block = $this->getCmsBlockModel();
        if (!$block) {
            return '';
        }

        $storeId = $this->_storeManager->getStore()->getId();

        // 让 CMS 内容里的 {{widget}} / {{block}} / 变量等都能正常解析
        return $this->filterProvider->getBlockFilter()
            ->setStoreId($storeId)
            ->filter((string) $block->getContent());
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData('sort_order');
    }
}
