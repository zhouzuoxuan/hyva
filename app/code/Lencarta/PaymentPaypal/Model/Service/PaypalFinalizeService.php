<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Api\CaptureOrder;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Storage\PaypalOrderStorage;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;

class PaypalFinalizeService
{
    public function __construct(
        private readonly CaptureOrder $captureOrderApi,
        private readonly PaypalOrderStorage $paypalOrderStorage,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPaymentRegistrar $orderPaymentRegistrar,
        private readonly CheckoutSession $checkoutSession,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function finalize(Quote $quote, string $paypalOrderId): array
    {
        $this->validateQuote($quote, $paypalOrderId);

        $existing = $this->paypalOrderStorage->getByPaypalOrderId($paypalOrderId);
        if ($existing && !empty($existing['order_id'])) {
            $order = $this->orderRepository->get((int) $existing['order_id']);
            return [
                'order_id' => (int) $order->getEntityId(),
                'increment_id' => (string) $order->getIncrementId(),
                'redirect_url' => '/checkout/onepage/success',
                'already_processed' => true,
            ];
        }

        $captureResult = $this->captureOrderApi->execute($paypalOrderId, (int) $quote->getStoreId());
        $captureResponse = $captureResult['response'];
        $captureData = $this->extractCompletedCapture($captureResponse);

        $this->assertCaptureMatchesQuote($quote, $captureResponse, $captureData);

        $quotePayment = $quote->getPayment();
        $quotePayment->setMethod('lencarta_paypal');
        $quotePayment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
        $quotePayment->setAdditionalInformation('paypal_capture_id', $captureData['id']);
        $quotePayment->setAdditionalInformation('paypal_capture_payload', $captureData);
        $quote->setInventoryProcessed(false);
        $this->cartRepository->save($quote);

        $orderId = (int) $this->cartManagement->placeOrder((int) $quote->getId());
        $order = $this->orderRepository->get($orderId);

        $this->paypalOrderStorage->markCaptured($paypalOrderId, (string) $captureData['id']);
        $this->paypalOrderStorage->attachMagentoOrder($paypalOrderId, $orderId);

        $this->orderPaymentRegistrar->registerCapturedPayment($order, $paypalOrderId, (string) $captureData['id'], $captureData);
        $this->populateCheckoutSession($quote, $order);

        $this->debugLogger->info('PayPal finalize completed', [
            'quote_id' => (int) $quote->getId(),
            'order_id' => $orderId,
            'increment_id' => $order->getIncrementId(),
            'paypal_order_id' => $paypalOrderId,
            'paypal_capture_id' => $captureData['id'],
        ], (int) $quote->getStoreId());

        return [
            'order_id' => $orderId,
            'increment_id' => (string) $order->getIncrementId(),
            'redirect_url' => '/checkout/onepage/success',
            'capture_id' => (string) $captureData['id'],
            'already_processed' => false,
        ];
    }

    private function validateQuote(Quote $quote, string $paypalOrderId): void
    {
        if (!$quote->getId() || !$quote->getItemsCount()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        if (!$quote->getIsActive()) {
            throw new LocalizedException(__('This quote is no longer active.'));
        }

        $savedPaypalOrderId = (string) $quote->getPayment()->getAdditionalInformation('paypal_order_id');
        if ($savedPaypalOrderId === '' || $savedPaypalOrderId !== $paypalOrderId) {
            throw new LocalizedException(__('PayPal order mismatch. Please restart checkout.'));
        }

        if (!(string) $quote->getCustomerEmail()) {
            throw new LocalizedException(__('Please save your email first.'));
        }

        $shipping = $quote->getShippingAddress();
        if (!(string) $shipping->getShippingMethod()) {
            throw new LocalizedException(__('Please select a shipping method.'));
        }
    }

    private function extractCompletedCapture(array $captureResponse): array
    {
        $purchaseUnit = $captureResponse['purchase_units'][0] ?? [];
        $captures = $purchaseUnit['payments']['captures'] ?? [];
        $capture = $captures[0] ?? [];

        if (($captureResponse['status'] ?? '') !== 'COMPLETED' || ($capture['status'] ?? '') !== 'COMPLETED' || empty($capture['id'])) {
            throw new LocalizedException(__('PayPal capture did not complete successfully.'));
        }

        return $capture;
    }

    private function assertCaptureMatchesQuote(Quote $quote, array $captureResponse, array $captureData): void
    {
        $amount = (string) ($captureData['amount']['value'] ?? '');
        $currency = (string) ($captureData['amount']['currency_code'] ?? '');
        $expectedAmount = number_format((float) $quote->getGrandTotal(), 2, '.', '');
        $expectedCurrency = (string) $quote->getQuoteCurrencyCode();
        $referenceId = (string) ($captureResponse['purchase_units'][0]['reference_id'] ?? '');

        if ($amount !== $expectedAmount) {
            throw new LocalizedException(__('PayPal captured amount does not match quote total.'));
        }

        if ($currency !== $expectedCurrency) {
            throw new LocalizedException(__('PayPal captured currency does not match quote currency.'));
        }

        if ($referenceId !== 'quote_' . $quote->getId()) {
            throw new LocalizedException(__('PayPal reference ID does not match quote.'));
        }
    }

    private function populateCheckoutSession(Quote $quote, \Magento\Sales\Api\Data\OrderInterface $order): void
    {
        $this->checkoutSession->setLastQuoteId((int) $quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId((int) $quote->getId());
        $this->checkoutSession->setLastOrderId((int) $order->getEntityId());
        $this->checkoutSession->setLastRealOrderId((string) $order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus((string) $order->getStatus());
    }
}
