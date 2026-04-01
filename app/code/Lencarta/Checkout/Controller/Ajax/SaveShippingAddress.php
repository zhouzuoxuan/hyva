<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\QuoteUpdater;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SaveShippingAddress implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly QuoteUpdater $quoteUpdater,
        private readonly TotalsProvider $totalsProvider
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->sessionQuoteProvider->getQuote();

            $street = array_filter([
                (string) ($_POST['street_1'] ?? ''),
                (string) ($_POST['street_2'] ?? ''),
            ]);

            $this->quoteUpdater->saveShippingAddress($quote, [
                'firstname'  => (string) ($_POST['firstname'] ?? ''),
                'lastname'   => (string) ($_POST['lastname'] ?? ''),
                'company'    => (string) ($_POST['company'] ?? ''),
                'telephone'  => (string) ($_POST['telephone'] ?? ''),
                'street'     => $street,
                'city'       => (string) ($_POST['city'] ?? ''),
                'postcode'   => (string) ($_POST['postcode'] ?? ''),
                'country_id' => (string) ($_POST['country_id'] ?? 'GB'),
                'region'     => (string) ($_POST['region'] ?? ''),
                'region_id'  => (string) ($_POST['region_id'] ?? ''),
            ]);

            return $result->setData([
                'success' => true,
                'shipping_methods' => $this->totalsProvider->getShippingMethods($quote),
                'totals' => $this->totalsProvider->getTotals($quote),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => __('Unable to save shipping address.')]);
        }
    }
}
