<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;

class CouponManager
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly CouponFactory $couponFactory
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

    public function getAppliedCouponLabel(Quote $quote): string
    {
        $couponCode = trim((string) ($quote->getCouponCode() ?: ''));

        if ($couponCode !== '') {
            $couponRuleName = $this->getCouponRuleNameByCode($couponCode);
            if ($couponRuleName !== '') {
                return $couponRuleName;
            }
        }

        $appliedRuleNames = $this->getAppliedRuleNames($quote);
        if ($appliedRuleNames !== []) {
            return (string) reset($appliedRuleNames);
        }

        return $couponCode;
    }

    public function getAppliedRuleNames(Quote $quote): array
    {
        $appliedRuleIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $quote->getAppliedRuleIds())
        )));

        if ($appliedRuleIds === []) {
            return [];
        }

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('rule_id', ['in' => $appliedRuleIds]);

        $namesById = [];
        foreach ($collection as $rule) {
            $name = trim((string) $rule->getName());
            if ($name !== '') {
                $namesById[(int) $rule->getRuleId()] = $name;
            }
        }

        $names = [];
        foreach ($appliedRuleIds as $ruleId) {
            $name = $namesById[$ruleId] ?? '';
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function getCouponRuleNameByCode(string $couponCode): string
    {
        if ($couponCode === '') {
            return '';
        }

        try {
            $coupon = $this->couponFactory->create()->loadByCode($couponCode);
            $ruleId = (int) $coupon->getRuleId();

            if ($ruleId <= 0) {
                return '';
            }

            $collection = $this->ruleCollectionFactory->create();
            $collection->addFieldToFilter('rule_id', $ruleId);
            $rule = $collection->getFirstItem();

            return trim((string) $rule->getName());
        } catch (\Throwable) {
            return '';
        }
    }
}
