<?php

declare(strict_types=1);

namespace Lencarta\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_GENERAL_ENABLED = 'lencarta_core/general/enabled';
    public const XML_PATH_GENERAL_BRAND_NAME = 'lencarta_core/general/brand_name';
    public const XML_PATH_GENERAL_DEBUG_LOGGING = 'lencarta_core/general/debug_logging';

    public const XML_PATH_FEATURE_UI_COMPONENTS = 'lencarta_core/features/ui_components';
    public const XML_PATH_FEATURE_CMS_WIDGETS = 'lencarta_core/features/cms_widgets';
    public const XML_PATH_FEATURE_EXPERIMENTAL = 'lencarta_core/features/experimental';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getBrandName(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_GENERAL_BRAND_NAME, ScopeInterface::SCOPE_STORE, $storeId);
        return $value !== '' ? $value : 'Lencarta';
    }

    public function isDebugLoggingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_DEBUG_LOGGING, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isUiComponentsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FEATURE_UI_COMPONENTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCmsWidgetsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FEATURE_CMS_WIDGETS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isExperimentalEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FEATURE_EXPERIMENTAL, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
