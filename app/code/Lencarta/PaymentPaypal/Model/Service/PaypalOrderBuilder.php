<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Config;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;

class PaypalOrderBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function build(Quote $quote): array
    {
        $storeId = (int) $quote->getStoreId();
        $currency = (string) $quote->getQuoteCurrencyCode();
        $grandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');
        $shippingAddress = $quote->getShippingAddress();
        $shippingMethod = (string) $shippingAddress->getShippingMethod();

        $purchaseUnit = [
            'reference_id' => 'quote_' . $quote->getId(),
            'description' => sprintf(
                'Magento Quote #%s | %s',
                (string) $quote->getId(),
                $shippingMethod !== '' ? $shippingMethod : 'no-shipping-method'
            ),
            'amount' => [
                'currency_code' => $currency,
                'value' => $grandTotal,
            ],
            'custom_id' => (string) $quote->getReservedOrderId(),
        ];

        if (!$quote->isVirtual()) {
            $purchaseUnit['shipping'] = [
                'name' => [
                    'full_name' => $this->buildFullName($shippingAddress),
                ],
                'address' => array_filter([
                    'address_line_1' => (string) ($shippingAddress->getStreetLine(1) ?: ''),
                    'address_line_2' => (string) ($shippingAddress->getStreetLine(2) ?: ''),
                    'admin_area_2' => (string) $shippingAddress->getCity(),
                    'admin_area_1' => (string) $shippingAddress->getRegionCode(),
                    'postal_code' => (string) $shippingAddress->getPostcode(),
                    'country_code' => (string) $shippingAddress->getCountryId(),
                ], static fn ($value): bool => $value !== ''),
            ];
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'application_context' => [
                'brand_name' => $this->config->getBrandName($storeId) ?: 'Lencarta',
                'landing_page' => 'LOGIN',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => $quote->isVirtual() ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
                'return_url' => $this->urlBuilder->getUrl('checkout/onepage/success'),
                'cancel_url' => $this->urlBuilder->getUrl('lencarta_checkout/index/index'),
            ],
        ];
    }

    private function buildFullName($address): string
    {
        $fullName = trim(implode(' ', array_filter([
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
        ])));

        return $fullName !== '' ? $fullName : 'Customer';
    }
}
