<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\ViewModel;

use Lencarta\PaymentPaypal\Model\Config;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class PaypalConfig implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly FormKey $formKey
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isActive();
    }

    public function getClientId(): string
    {
        return $this->config->getClientId();
    }

    public function getSdkBaseUrl(): string
    {
        return $this->config->getSdkBaseUrl();
    }

    public function getCurrency(): string
    {
        return (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getCreateOrderUrl(): string
    {
        return $this->urlBuilder->getUrl('lencarta_paypal/express/createOrder');
    }

    public function getCaptureUrl(): string
    {
        return $this->urlBuilder->getUrl('lencarta_paypal/express/capture');
    }

    public function getWebhookUrl(): string
    {
        return $this->urlBuilder->getUrl('lencarta_paypal/webhook/index');
    }

    public function getButtonColor(): string
    {
        return $this->config->getButtonColor() ?: 'gold';
    }

    public function getButtonShape(): string
    {
        return $this->config->getButtonShape() ?: 'rect';
    }

    public function getButtonLabel(): string
    {
        return $this->config->getButtonLabel() ?: 'paypal';
    }
}
