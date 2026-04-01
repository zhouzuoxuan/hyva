<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;

class SessionQuoteProvider
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }
}
