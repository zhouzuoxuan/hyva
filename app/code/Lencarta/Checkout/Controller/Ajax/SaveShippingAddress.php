<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\CheckoutStateProvider;
use Lencarta\Checkout\Model\Checkout\QuoteUpdater;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class SaveShippingAddress extends AbstractJsonAction implements HttpPostActionInterface
{
    public function __construct(
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly QuoteUpdater $quoteUpdater,
        private readonly CheckoutStateProvider $checkoutStateProvider
    ) {
        parent::__construct($resultJsonFactory, $formKeyValidator);
    }

    public function execute()
    {
        try {
            $this->validateFormKey($this->request);

            $quote = $this->sessionQuoteProvider->getQuote();

            $street = array_values(array_filter([
                trim((string) $this->request->getParam('street_1', '')),
                trim((string) $this->request->getParam('street_2', '')),
            ], static fn(string $value): bool => $value !== ''));

            $this->quoteUpdater->saveShippingAddress($quote, [
                'firstname' => trim((string) $this->request->getParam('firstname', '')),
                'lastname' => trim((string) $this->request->getParam('lastname', '')),
                'company' => trim((string) $this->request->getParam('company', '')),
                'telephone' => trim((string) $this->request->getParam('telephone', '')),
                'street' => $street,
                'city' => trim((string) $this->request->getParam('city', '')),
                'postcode' => trim((string) $this->request->getParam('postcode', '')),
                'country_id' => strtoupper(trim((string) $this->request->getParam('country_id', ''))),
                'region' => trim((string) $this->request->getParam('region', '')),
                'region_id' => trim((string) $this->request->getParam('region_id', '')),
            ]);

            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => true,
                'state' => $state,
                'shipping_methods' => $state['shipping_methods'] ?? [],
                'totals' => $state['totals'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        } catch (LocalizedException $e) {
            return $this->createResult(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            return $this->createResult(['success' => false, 'message' => __('Unable to save shipping address.')]);
        }
    }
}
