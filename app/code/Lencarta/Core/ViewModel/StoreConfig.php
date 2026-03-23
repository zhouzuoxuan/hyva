<?php
declare(strict_types=1);

namespace Lencarta\Core\ViewModel;

use Lencarta\Core\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class StoreConfig implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }

    public function isDebugLoggingEnabled(?int $storeId = null): bool
    {
        return $this->config->isDebugLoggingEnabled($storeId);
    }

    public function getBrandName(?int $storeId = null): string
    {
        return $this->config->getBrandName($storeId);
    }
}
