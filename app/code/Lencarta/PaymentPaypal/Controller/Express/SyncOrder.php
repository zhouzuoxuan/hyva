<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Controller\Express;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Service\PaypalOrderSyncService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SyncOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly RequestInterface $request,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly PaypalOrderSyncService $paypalOrderSyncService,
        private readonly Config $config,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->config->isActive()) {
                throw new LocalizedException(__('PayPal is currently unavailable.'));
            }

            $quote = $this->sessionQuoteProvider->getQuote();
            $checkoutSignature = trim((string) $this->request->getParam('checkout_signature', ''));
            $syncResult = $this->paypalOrderSyncService->sync($quote, $checkoutSignature);

            return $result->setData([
                'success' => true,
                'paypal_order_id' => $syncResult['paypal_order_id'],
                'order_id' => $syncResult['paypal_order_id'],
                'request_id' => $syncResult['request_id'],
                'checkout_signature' => $syncResult['checkout_signature'],
                'reused' => (bool) $syncResult['reused'],
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->debugLogger->error('PayPal sync order controller failure', [
                'exception' => $e,
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Unable to sync PayPal checkout.'),
            ]);
        }
    }
}
