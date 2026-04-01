<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class ShippingManager
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function saveMethod(Quote $quote, string $carrierCode, string $methodCode): void
    {
        if ($carrierCode === '' || $methodCode === '') {
            throw new LocalizedException(__('Please select a shipping method.'));
        }

        $address = $quote->getShippingAddress();
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        $shippingMethod = $carrierCode . '_' . $methodCode;
        $address->setShippingMethod($shippingMethod);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->cartRepository->save($quote);
    }
}
