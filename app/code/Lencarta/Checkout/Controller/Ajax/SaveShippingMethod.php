<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\CheckoutStateProvider;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\ShippingManager;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class SaveShippingMethod extends AbstractJsonAction implements HttpPostActionInterface
{
    public function __construct(
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly ShippingManager $shippingManager,
        private readonly CheckoutStateProvider $checkoutStateProvider
    ) {
        parent::__construct($resultJsonFactory, $formKeyValidator);
    }

    public function execute()
    {
        try {
            $this->validateFormKey($this->request);

            $quote = $this->sessionQuoteProvider->getQuote();

            $this->shippingManager->saveMethod(
                $quote,
                trim((string) $this->request->getParam('carrier_code', '')),
                trim((string) $this->request->getParam('method_code', ''))
            );

            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => true,
                'state' => $state,
                'totals' => $state['totals'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        } catch (LocalizedException $e) {
            return $this->createResult(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            return $this->createResult(['success' => false, 'message' => __('Unable to save shipping method.')]);
        }
    }
}
