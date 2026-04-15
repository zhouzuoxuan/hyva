<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Controller\Express;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Lencarta\PaymentPaypal\Model\Service\PaypalFinalizeService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;

class Capture implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly PaypalFinalizeService $paypalFinalizeService,
        private readonly UrlInterface $urlBuilder,
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

            $paypalOrderId = trim((string) ($_POST['paypal_order_id'] ?? ''));
            if ($paypalOrderId === '') {
                throw new LocalizedException(__('Missing PayPal order ID.'));
            }

            $quote = $this->sessionQuoteProvider->getQuote();
            $finalizeResult = $this->paypalFinalizeService->finalize($quote, $paypalOrderId);
            $redirectUrl = $this->urlBuilder->getUrl('checkout/onepage/success');

            return $result->setData([
                'success' => true,
                'redirect_url' => $redirectUrl,
                'order_id' => $finalizeResult['order_id'],
                'increment_id' => $finalizeResult['increment_id'],
                'already_processed' => (bool) ($finalizeResult['already_processed'] ?? false),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->debugLogger->error('PayPal capture controller failure', [
                'exception' => $e,
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Unable to finalize PayPal payment.'),
            ]);
        }
    }
}
