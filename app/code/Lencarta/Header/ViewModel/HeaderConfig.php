<?php
declare(strict_types=1);

namespace Lencarta\Header\ViewModel;

use Lencarta\Header\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class HeaderConfig implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function getPhone(): string
    {
        return $this->config->getPhone();
    }

    public function isPhoneVisible(): bool
    {
        return $this->config->isPhoneVisible();
    }

    public function getPhoneHref(): string
    {
        return $this->config->getPhoneHref();
    }

    public function getDesktopLinks(): array
    {
        return $this->config->getDesktopLinks();
    }

    public function isCompareEnabled(): bool
    {
        return $this->config->isCompareEnabled();
    }

    public function isWishlistEnabled(): bool
    {
        return $this->config->isWishlistEnabled();
    }

    public function getSocialLinks(): array
    {
        return $this->config->getSocialLinks();
    }

    public function isMobileCtaEnabled(): bool
    {
        return $this->config->isMobileCtaEnabled();
    }

    public function getMobileCtaLabel(): string
    {
        return $this->config->getMobileCtaLabel();
    }

    public function getMobileCtaUrl(): string
    {
        return $this->config->getMobileCtaUrl();
    }
}
