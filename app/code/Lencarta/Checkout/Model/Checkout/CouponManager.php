<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class CouponManager
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function apply(Quote $quote, string $couponCode): void
    {
        $couponCode = trim($couponCode);

        if ($couponCode === '') {
            throw new LocalizedException(__('Please enter a coupon code.'));
        }

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        if (strcasecmp((string) $quote->getCouponCode(), $couponCode) !== 0) {
            throw new LocalizedException(__('The coupon code is not valid.'));
        }
    }

    public function remove(Quote $quote): void
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode('');
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
    }
}
