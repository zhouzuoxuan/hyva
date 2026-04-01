<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Api\CreateOrder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class PaypalCheckoutService
{
    public function __construct(
        private readonly PaypalOrderBuilder $paypalOrderBuilder,
        private readonly CreateOrder $createOrderApi,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function createPaypalOrderForQuote(Quote $quote): array
    {
        $this->validateQuote($quote);

        $payload = $this->paypalOrderBuilder->build($quote);
        $result = $this->createOrderApi->execute($payload, (int) $quote->getStoreId());

        $payment = $quote->getPayment();
        $payment->setMethod('lencarta_paypal');
        $payment->setAdditionalInformation('paypal_order_id', (string) $result['response']['id']);
        $payment->setAdditionalInformation('paypal_request_id', (string) $result['request_id']);

        $quote->setTotalsCollectedFlag(false);
        $this->cartRepository->save($quote);

        return $result;
    }

    private function validateQuote(Quote $quote): void
    {
        if (!$quote->getItemsCount()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        if (!(string) $quote->getCustomerEmail()) {
            throw new LocalizedException(__('Please save your email first.'));
        }

        $shipping = $quote->getShippingAddress();

        if (!(string) $shipping->getFirstname() || !(string) $shipping->getLastname()) {
            throw new LocalizedException(__('Please complete the shipping address.'));
        }

        if (!(string) $shipping->getShippingMethod()) {
            throw new LocalizedException(__('Please select a shipping method.'));
        }

        if ((float) $quote->getGrandTotal() <= 0.0001) {
            throw new LocalizedException(__('Invalid quote total.'));
        }
    }
}
