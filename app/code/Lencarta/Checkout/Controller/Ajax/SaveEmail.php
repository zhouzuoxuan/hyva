<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\QuoteUpdater;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SaveEmail implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly QuoteUpdater $quoteUpdater
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $email = (string) ($_POST['email'] ?? '');
            $quote = $this->sessionQuoteProvider->getQuote();

            $this->quoteUpdater->saveEmail($quote, $email);

            return $result->setData(['success' => true]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => __('Unable to save email.')]);
        }
    }
}
