<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Api\CreateOrder;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Storage\PaypalOrderStorage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class PaypalOrderSyncService
{
    public function __construct(
        private readonly CreateOrder $createOrderApi,
        private readonly PaypalOrderBuilder $paypalOrderBuilder,
        private readonly PaypalOrderStorage $paypalOrderStorage,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function sync(Quote $quote, string $checkoutSignature): array
    {
        $this->validateQuote($quote);

        if (!(string) $quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        $signature = trim($checkoutSignature) ?: $this->buildFallbackSignature($quote);
        $existing = $this->paypalOrderStorage->getReusableCreatedOrder((int) $quote->getId(), $signature);

        if ($existing) {
            $paypalOrderId = (string) $existing['paypal_order_id'];
            $this->attachPaypalOrderToQuote($quote, $paypalOrderId, $signature);

            $this->debugLogger->debug('PayPal order sync reused existing order', [
                'quote_id' => (int) $quote->getId(),
                'paypal_order_id' => $paypalOrderId,
                'checkout_signature' => $signature,
            ], (int) $quote->getStoreId());

            return [
                'paypal_order_id' => $paypalOrderId,
                'request_id' => (string) ($existing['paypal_request_id'] ?? ''),
                'checkout_signature' => $signature,
                'reused' => true,
            ];
        }

        $this->paypalOrderStorage->deactivateActiveQuoteOrders((int) $quote->getId(), 'superseded');

        $payload = $this->paypalOrderBuilder->build($quote);
        $result = $this->createOrderApi->execute($payload, (int) $quote->getStoreId());
        $paypalOrderId = (string) ($result['response']['id'] ?? '');

        if ($paypalOrderId === '') {
            throw new LocalizedException(__('Unable to create PayPal order.'));
        }

        $this->attachPaypalOrderToQuote($quote, $paypalOrderId, $signature);
        $this->paypalOrderStorage->saveCreated(
            $quote,
            $paypalOrderId,
            (string) ($result['request_id'] ?? ''),
            $signature
        );

        $this->debugLogger->info('PayPal order sync created new order', [
            'quote_id' => (int) $quote->getId(),
            'paypal_order_id' => $paypalOrderId,
            'checkout_signature' => $signature,
            'grand_total' => (float) $quote->getGrandTotal(),
            'currency' => (string) $quote->getQuoteCurrencyCode(),
        ], (int) $quote->getStoreId());

        return [
            'paypal_order_id' => $paypalOrderId,
            'request_id' => (string) ($result['request_id'] ?? ''),
            'checkout_signature' => $signature,
            'reused' => false,
        ];
    }

    public function invalidate(Quote $quote, string $reason = 'invalidated'): void
    {
        if (!$quote->getId()) {
            return;
        }

        $this->paypalOrderStorage->deactivateActiveQuoteOrders((int) $quote->getId(), $reason);

        $payment = $quote->getPayment();
        $payment->unsAdditionalInformation('paypal_order_id');
        $payment->unsAdditionalInformation('paypal_checkout_signature');
        $this->cartRepository->save($quote);

        $this->debugLogger->debug('PayPal order sync invalidated active quote orders', [
            'quote_id' => (int) $quote->getId(),
            'reason' => $reason,
        ], (int) $quote->getStoreId());
    }

    private function validateQuote(Quote $quote): void
    {
        if (!$quote->getId() || !$quote->getItemsCount()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        if (!$quote->getIsActive()) {
            throw new LocalizedException(__('This quote is no longer active.'));
        }

        if (!(string) $quote->getCustomerEmail()) {
            throw new LocalizedException(__('Please save your email first.'));
        }

        if (!$quote->isVirtual()) {
            $shipping = $quote->getShippingAddress();

            if (!(string) $shipping->getShippingMethod()) {
                throw new LocalizedException(__('Please select a shipping method.'));
            }

            if (!(string) $shipping->getCountryId()) {
                throw new LocalizedException(__('Please complete your shipping address first.'));
            }
        }
    }

    private function attachPaypalOrderToQuote(Quote $quote, string $paypalOrderId, string $signature): void
    {
        $payment = $quote->getPayment();
        $payment->setMethod('lencarta_paypal');
        $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
        $payment->setAdditionalInformation('paypal_checkout_signature', $signature);
        $this->cartRepository->save($quote);
    }

    private function buildFallbackSignature(Quote $quote): string
    {
        $shipping = $quote->getShippingAddress();

        return hash('sha256', implode('|', [
            (string) $quote->getId(),
            (string) $quote->getItemsCount(),
            number_format((float) $quote->getGrandTotal(), 2, '.', ''),
            (string) $quote->getQuoteCurrencyCode(),
            (string) $quote->getCustomerEmail(),
            (string) $shipping->getShippingMethod(),
            (string) $shipping->getCountryId(),
            (string) $shipping->getPostcode(),
            (string) $quote->getCouponCode(),
            (string) $quote->getUpdatedAt(),
        ]));
    }
}
