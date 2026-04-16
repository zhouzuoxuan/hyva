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

class InvalidateOrder implements HttpPostActionInterface
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
                return $result->setData(['success' => true]);
            }

            $quote = $this->sessionQuoteProvider->getQuote();
            $reason = trim((string) $this->request->getParam('reason', 'invalidated')) ?: 'invalidated';
            $this->paypalOrderSyncService->invalidate($quote, $reason);

            return $result->setData([
                'success' => true,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->debugLogger->error('PayPal invalidate order controller failure', [
                'exception' => $e,
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Unable to invalidate PayPal checkout.'),
            ]);
        }
    }
}
