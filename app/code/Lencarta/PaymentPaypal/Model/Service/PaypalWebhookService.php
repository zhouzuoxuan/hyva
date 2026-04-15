<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Service;

use Lencarta\PaymentPaypal\Model\Api\VerifyWebhookSignature;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Storage\PaypalOrderStorage;
use Magento\Framework\Exception\LocalizedException;

class PaypalWebhookService
{
    public function __construct(
        private readonly VerifyWebhookSignature $verifyWebhookSignature,
        private readonly PaypalOrderStorage $paypalOrderStorage,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function process(array $headers, array $body, ?int $storeId = null): void
    {
        if (!$this->verifyWebhookSignature->execute($headers, $body, $storeId)) {
            throw new LocalizedException(__('Invalid PayPal webhook signature.'));
        }

        $eventType = (string) ($body['event_type'] ?? 'unknown');
        $resource = $body['resource'] ?? [];
        $paypalOrderId = $this->extractPaypalOrderId($body);
        $paypalCaptureId = (string) ($resource['id'] ?? '');

        $this->debugLogger->info('PayPal webhook received', [
            'event_type' => $eventType,
            'paypal_order_id' => $paypalOrderId,
            'paypal_capture_id' => $paypalCaptureId,
            'body' => $body,
        ], $storeId);

        if ($paypalOrderId !== '') {
            $status = match ($eventType) {
                'PAYMENT.CAPTURE.COMPLETED' => 'webhook_capture_completed',
                'PAYMENT.CAPTURE.DENIED' => 'webhook_capture_denied',
                'PAYMENT.CAPTURE.REFUNDED' => 'webhook_capture_refunded',
                'CHECKOUT.ORDER.APPROVED' => 'webhook_order_approved',
                default => 'webhook_' . strtolower(str_replace('.', '_', $eventType)),
            };

            $this->paypalOrderStorage->updateWebhookState($paypalOrderId, $eventType, $status);

            if ($paypalCaptureId !== '' && $eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                $this->paypalOrderStorage->markCaptured($paypalOrderId, $paypalCaptureId);
            }
        }
    }

    private function extractPaypalOrderId(array $body): string
    {
        $resource = $body['resource'] ?? [];

        if (!empty($resource['supplementary_data']['related_ids']['order_id'])) {
            return (string) $resource['supplementary_data']['related_ids']['order_id'];
        }

        if (!empty($resource['id']) && ($body['event_type'] ?? '') === 'CHECKOUT.ORDER.APPROVED') {
            return (string) $resource['id'];
        }

        return '';
    }
}
