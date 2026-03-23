<?php
declare(strict_types=1);

namespace Lencarta\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'lencarta_core/general/enabled';
    public const XML_PATH_DEBUG_LOGGING = 'lencarta_core/general/debug_logging';
    public const XML_PATH_BRAND_NAME = 'lencarta_core/general/brand_name';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugLoggingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_LOGGING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBrandName(?int $storeId = null): string
    {
        $brandName = (string) $this->scopeConfig->getValue(
            self::XML_PATH_BRAND_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $brandName !== '' ? $brandName : 'Lencarta';
    }
}
