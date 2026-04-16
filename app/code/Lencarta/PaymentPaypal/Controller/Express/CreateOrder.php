<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Controller\Express;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Service\PaypalCheckoutService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class CreateOrder implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly PaypalCheckoutService $paypalCheckoutService,
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
            $paypalResult = $this->paypalCheckoutService->createPaypalOrderForQuote($quote);
            $paypalOrderId = (string) ($paypalResult['response']['id'] ?? '');

            return $result->setData([
                'success' => true,
                'order_id' => $paypalOrderId,
                'paypal_order_id' => $paypalOrderId,
                'request_id' => $paypalResult['request_id'] ?? '',
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->debugLogger->error('PayPal create order controller failure', [
                'exception' => $e,
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Unable to start PayPal checkout.'),
            ]);
        }
    }
}
