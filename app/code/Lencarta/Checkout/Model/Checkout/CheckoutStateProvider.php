<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutStateProvider
{
    public function __construct(
        private readonly TotalsProvider $totalsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getState(Quote $quote): array
    {
        if (!$quote->getId()) {
            return $this->getEmptyState();
        }

        $shippingAddress = $quote->getShippingAddress();
        $street = $shippingAddress ? ($shippingAddress->getStreet() ?: []) : [];

        return [
            'email' => (string) ($quote->getCustomerEmail() ?: ''),
            'items' => $this->totalsProvider->getItems($quote),
            'totals' => $this->totalsProvider->getTotals($quote),
            'shipping_methods' => $this->totalsProvider->getShippingMethods($quote),
            'selected_shipping_method' => (string) ($shippingAddress?->getShippingMethod() ?: ''),
            'coupon_code' => (string) ($quote->getCouponCode() ?: ''),
            'shipping' => [
                'firstname' => (string) ($shippingAddress?->getFirstname() ?: ''),
                'lastname' => (string) ($shippingAddress?->getLastname() ?: ''),
                'company' => (string) ($shippingAddress?->getCompany() ?: ''),
                'telephone' => (string) ($shippingAddress?->getTelephone() ?: ''),
                'street_1' => (string) ($street[0] ?? ''),
                'street_2' => (string) ($street[1] ?? ''),
                'city' => (string) ($shippingAddress?->getCity() ?: ''),
                'postcode' => (string) ($shippingAddress?->getPostcode() ?: ''),
                'region' => (string) ($shippingAddress?->getRegion() ?: ''),
                'country_id' => (string) ($shippingAddress?->getCountryId() ?: $this->getDefaultCountryId()),
            ],
        ];
    }

    public function getDefaultCountryId(): string
    {
        $store = $this->storeManager->getStore();
        $default = (string) $this->scopeConfig->getValue(
            'general/country/default',
            'store',
            $store->getId()
        );

        return $default !== '' ? strtoupper($default) : 'GB';
    }

    public function getWebsiteCode(): string
    {
        return (string) $this->storeManager->getWebsite()->getCode();
    }

    public function getStoreCode(): string
    {
        return (string) $this->storeManager->getStore()->getCode();
    }

    public function getEmptyState(): array
    {
        return [
            'email' => '',
            'items' => [],
            'totals' => [],
            'shipping_methods' => [],
            'selected_shipping_method' => '',
            'coupon_code' => '',
            'shipping' => [
                'firstname' => '',
                'lastname' => '',
                'company' => '',
                'telephone' => '',
                'street_1' => '',
                'street_2' => '',
                'city' => '',
                'postcode' => '',
                'region' => '',
                'country_id' => $this->getDefaultCountryId(),
            ],
        ];
    }
}
