<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class CheckoutState implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly TotalsProvider $totalsProvider
    ) {
    }

    public function execute()
    {
        $quote = $this->sessionQuoteProvider->getQuote();

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'data' => [
                'email' => (string) $quote->getCustomerEmail(),
                'items' => $this->totalsProvider->getItems($quote),
                'totals' => $this->totalsProvider->getTotals($quote),
                'shipping_methods' => $this->totalsProvider->getShippingMethods($quote),
                'selected_shipping_method' => (string) $quote->getShippingAddress()->getShippingMethod(),
            ],
        ]);
    }
}
