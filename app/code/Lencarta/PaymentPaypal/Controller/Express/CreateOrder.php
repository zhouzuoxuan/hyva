<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Controller\Express;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\PaymentPaypal\Model\Config;
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
        private readonly Config $config
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

            return $result->setData([
                'success' => true,
                'paypal_order_id' => $paypalResult['response']['id'],
                'request_id' => $paypalResult['request_id'],
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Unable to start PayPal checkout.'),
            ]);
        }
    }
}
