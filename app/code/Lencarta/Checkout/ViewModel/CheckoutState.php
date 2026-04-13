<?php

declare(strict_types=1);

namespace Lencarta\Checkout\ViewModel;

use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

class CheckoutState implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly TotalsProvider  $totalsProvider
    )
    {
    }

    public function getInitialState(): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            return $this->getEmptyState();
        }

        $shippingAddress = $quote->getShippingAddress();

        return [
            'email' => (string)($quote->getCustomerEmail() ?: ''),
            'items' => $this->totalsProvider->getItems($quote),
            'totals' => $this->totalsProvider->getTotals($quote),
            'shipping_methods' => $this->totalsProvider->getShippingMethods($quote),
            'selected_shipping_method' => (string)($shippingAddress?->getShippingMethod() ?: ''),
            'coupon_code' => (string)($quote->getCouponCode() ?: ''),
            'shipping' => $this->getShippingData($shippingAddress),
        ];
    }

    private function getShippingData(?Address $address): array
    {
        if (!$address) {
            return $this->getEmptyShipping();
        }

        $street = $address->getStreet() ?: [];

        return [
            'firstname' => (string)($address->getFirstname() ?: ''),
            'lastname' => (string)($address->getLastname() ?: ''),
            'company' => (string)($address->getCompany() ?: ''),
            'telephone' => (string)($address->getTelephone() ?: ''),
            'street_1' => (string)($street[0] ?? ''),
            'street_2' => (string)($street[1] ?? ''),
            'city' => (string)($address->getCity() ?: ''),
            'postcode' => (string)($address->getPostcode() ?: ''),
            'region' => (string)($address->getRegion() ?: ''),
            'country_id' => (string)($address->getCountryId() ?: 'GB'),
        ];
    }

    private function getEmptyState(): array
    {
        return [
            'email' => '',
            'items' => [],
            'totals' => [],
            'shipping_methods' => [],
            'selected_shipping_method' => '',
            'coupon_code' => '',
            'shipping' => $this->getEmptyShipping(),
        ];
    }

    private function getEmptyShipping(): array
    {
        return [
            'firstname' => '',
            'lastname' => '',
            'company' => '',
            'telephone' => '',
            'street_1' => '',
            'street_2' => '',
            'city' => '',
            'postcode' => '',
            'region' => '',
            'country_id' => 'GB',
        ];
    }
}
