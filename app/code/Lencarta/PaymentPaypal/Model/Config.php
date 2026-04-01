<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ACTIVE        = 'payment/lencarta_paypal/active';
    private const XML_PATH_SANDBOX       = 'payment/lencarta_paypal/sandbox_mode';
    private const XML_PATH_CLIENT_ID     = 'payment/lencarta_paypal/client_id';
    private const XML_PATH_CLIENT_SECRET = 'payment/lencarta_paypal/client_secret';
    private const XML_PATH_WEBHOOK_ID    = 'payment/lencarta_paypal/webhook_id';
    private const XML_PATH_BRAND_NAME    = 'payment/lencarta_paypal/brand_name';
    private const XML_PATH_BUTTON_COLOR  = 'payment/lencarta_paypal/button_color';
    private const XML_PATH_BUTTON_SHAPE  = 'payment/lencarta_paypal/button_shape';
    private const XML_PATH_BUTTON_LABEL  = 'payment/lencarta_paypal/button_label';
    private const XML_PATH_DEBUG         = 'payment/lencarta_paypal/debug';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isSandboxMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SANDBOX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getClientId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getClientSecret(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CLIENT_SECRET, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getWebhookId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getBrandName(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BRAND_NAME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getButtonColor(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BUTTON_COLOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getButtonShape(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BUTTON_SHAPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getButtonLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BUTTON_LABEL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiBaseUrl(?int $storeId = null): string
    {
        return $this->isSandboxMode($storeId)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function getSdkBaseUrl(): string
    {
        return 'https://www.paypal.com/sdk/js';
    }
}
