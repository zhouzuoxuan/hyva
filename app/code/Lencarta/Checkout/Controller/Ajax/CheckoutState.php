<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\CheckoutStateProvider;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class CheckoutState extends AbstractJsonAction implements HttpGetActionInterface
{
    public function __construct(
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly CheckoutStateProvider $checkoutStateProvider
    ) {
        parent::__construct($resultJsonFactory, $formKeyValidator);
    }

    public function execute()
    {
        $quote = $this->sessionQuoteProvider->getQuote();

        return $this->createResult([
            'success' => true,
            'data' => $this->checkoutStateProvider->getState($quote),
        ]);
    }
}
