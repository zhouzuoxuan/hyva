<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class GetTotals implements HttpGetActionInterface
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
            'totals' => $this->totalsProvider->getTotals($quote),
        ]);
    }
}
