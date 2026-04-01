<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Config;
use Magento\Quote\Model\Quote;

class PaypalOrderBuilder
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function build(Quote $quote): array
    {
        $currency = (string) $quote->getQuoteCurrencyCode();
        $grandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'quote_' . $quote->getId(),
                    'description'  => 'Magento Quote #' . $quote->getId(),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $grandTotal,
                    ],
                ],
            ],
            'application_context' => [
                'brand_name'          => $this->config->getBrandName((int) $quote->getStoreId()) ?: 'Lencarta',
                'landing_page'        => 'LOGIN',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            ],
        ];
    }
}
