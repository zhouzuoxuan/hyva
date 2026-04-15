<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\DebugLogger;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;

class OrderPaymentRegistrar
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly TransactionBuilderInterface $transactionBuilder,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function registerCapturedPayment(Order $order, string $paypalOrderId, string $captureId, array $captureData): void
    {
        $payment = $order->getPayment();
        $payment->setMethod('lencarta_paypal');
        $payment->setLastTransId($captureId);
        $payment->setTransactionId($captureId);
        $payment->setIsTransactionClosed(false);
        $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
        $payment->setAdditionalInformation('paypal_capture_id', $captureId);
        $payment->setAdditionalInformation('paypal_capture_payload', $captureData);

        $transaction = $this->transactionBuilder
            ->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($captureId)
            ->setAdditionalInformation([
                'paypal_order_id' => $paypalOrderId,
                'paypal_capture_id' => $captureId,
            ])
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

        $payment->addTransactionCommentsToOrder($transaction, __('PayPal capture registered: %1', $captureId));

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice && (float) $invoice->getTotalQty() > 0) {
                $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
                $invoice->register();
                $invoice->getOrder()->setIsInProcess(true);

                $dbTransaction = $this->transactionFactory->create();
                $dbTransaction->addObject($invoice)->addObject($invoice->getOrder())->save();
            }
        }

        if ($order->getState() === Order::STATE_NEW) {
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
        }

        $this->orderRepository->save($order);

        $this->debugLogger->info('Magento order payment registered for PayPal capture', [
            'order_id' => (int) $order->getEntityId(),
            'increment_id' => $order->getIncrementId(),
            'paypal_order_id' => $paypalOrderId,
            'capture_id' => $captureId,
        ], (int) $order->getStoreId());
    }
}
