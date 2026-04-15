<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Index;

use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly SessionQuoteProvider $sessionQuoteProvider
    ) {
    }

    public function execute(): Page|Redirect
    {
        $quote = $this->sessionQuoteProvider->getQuote();

        if (!$quote->getItemsCount()) {
            $redirect = $this->resultRedirectFactory->create();
            $redirect->setPath('checkout/cart');

            return $redirect;
        }

        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->set(__('Checkout'));

        return $page;
    }
}
