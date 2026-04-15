<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class GetTotals extends AbstractJsonAction implements HttpGetActionInterface
{
    public function __construct(
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly TotalsProvider $totalsProvider
    ) {
        parent::__construct($resultJsonFactory, $formKeyValidator);
    }

    public function execute()
    {
        $quote = $this->sessionQuoteProvider->getQuote();

        return $this->createResult([
            'success' => true,
            'totals' => $this->totalsProvider->getTotals($quote),
        ]);
    }
}
