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

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'quote_' . $quote->getId(),
                    'description' => 'Magento Quote #' . $quote->getId(),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $grandTotal,
                    ],
                    'custom_id' => (string) $quote->getReservedOrderId(),
                ],
            ],
            'application_context' => [
                'brand_name' => $this->config->getBrandName($storeId) ?: 'Lencarta',
                'landing_page' => 'LOGIN',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                'return_url' => $this->urlBuilder->getUrl('checkout/onepage/success'),
                'cancel_url' => $this->urlBuilder->getUrl('lencarta_checkout/index/index'),
            ],
        ];
    }
}
