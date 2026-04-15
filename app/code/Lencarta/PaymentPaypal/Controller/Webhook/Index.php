<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Controller\Webhook;

use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Service\PaypalWebhookService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly PaypalWebhookService $paypalWebhookService,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $headers = [
                'PAYPAL-AUTH-ALGO' => (string) $this->request->getHeader('PAYPAL-AUTH-ALGO'),
                'PAYPAL-CERT-URL' => (string) $this->request->getHeader('PAYPAL-CERT-URL'),
                'PAYPAL-TRANSMISSION-ID' => (string) $this->request->getHeader('PAYPAL-TRANSMISSION-ID'),
                'PAYPAL-TRANSMISSION-SIG' => (string) $this->request->getHeader('PAYPAL-TRANSMISSION-SIG'),
                'PAYPAL-TRANSMISSION-TIME' => (string) $this->request->getHeader('PAYPAL-TRANSMISSION-TIME'),
            ];

            $body = json_decode((string) $this->request->getContent(), true);
            if (!is_array($body)) {
                throw new LocalizedException(__('Invalid webhook payload.'));
            }

            $this->paypalWebhookService->process($headers, $body);

            return $result->setHttpResponseCode(200)->setData(['success' => true]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->debugLogger->error('PayPal webhook processing failure', [
                'exception' => $e,
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('Webhook processing failed.'),
            ]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
