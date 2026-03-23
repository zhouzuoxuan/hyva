<?php

declare(strict_types=1);

namespace Lencarta\Core\ViewModel;

use Lencarta\Core\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class StoreConfig implements ArgumentInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function getBrandName(?int $storeId = null): string
    {
        return $this->config->getBrandName($storeId);
    }

    public function isModuleEnabled(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }

    public function isUiComponentsEnabled(?int $storeId = null): bool
    {
        return $this->config->isUiComponentsEnabled($storeId);
    }

    public function isCmsWidgetsEnabled(?int $storeId = null): bool
    {
        return $this->config->isCmsWidgetsEnabled($storeId);
    }
}
