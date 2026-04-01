<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\ShippingManager;
use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SaveShippingMethod implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly ShippingManager $shippingManager,
        private readonly TotalsProvider $totalsProvider
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->sessionQuoteProvider->getQuote();

            $this->shippingManager->saveMethod(
                $quote,
                (string) ($_POST['carrier_code'] ?? ''),
                (string) ($_POST['method_code'] ?? '')
            );

            return $result->setData([
                'success' => true,
                'totals' => $this->totalsProvider->getTotals($quote),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => __('Unable to save shipping method.')]);
        }
    }
}
