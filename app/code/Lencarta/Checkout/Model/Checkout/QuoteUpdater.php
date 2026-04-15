<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class QuoteUpdater
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function saveEmail(Quote $quote, string $email): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Invalid email address.'));
        }

        $quote->setCustomerEmail($email);

        if (!$this->customerSession->isLoggedIn()) {
            $quote->setCheckoutMethod('guest');
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(0);
        }

        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();

        $shippingAddress->setEmail($email);
        $billingAddress->setEmail($email);

        $this->cartRepository->save($quote);
    }

    public function saveShippingAddress(Quote $quote, array $data): void
    {
        $required = ['firstname', 'lastname', 'telephone', 'street', 'city', 'postcode', 'country_id'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new LocalizedException(__('Missing required field: %1', $field));
            }
        }

        $address = $quote->getShippingAddress();
        $address->addData([
            'firstname' => (string) $data['firstname'],
            'lastname' => (string) $data['lastname'],
            'company' => (string) ($data['company'] ?? ''),
            'telephone' => (string) $data['telephone'],
            'street' => is_array($data['street']) ? $data['street'] : [(string) $data['street']],
            'city' => (string) $data['city'],
            'postcode' => (string) $data['postcode'],
            'country_id' => strtoupper((string) $data['country_id']),
            'region' => (string) ($data['region'] ?? ''),
            'region_id' => isset($data['region_id']) && $data['region_id'] !== '' ? (int) $data['region_id'] : null,
            'collect_shipping_rates' => 1,
        ]);

        if ($quote->getCustomerEmail()) {
            $address->setEmail((string) $quote->getCustomerEmail());
            $quote->getBillingAddress()->setEmail((string) $quote->getCustomerEmail());
        }

        $address->collectShippingRates();
        $this->clearInvalidShippingMethod($address);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->cartRepository->save($quote);
    }

    public function saveBillingAddress(Quote $quote, array $data): void
    {
        $address = $quote->getBillingAddress();
        $address->addData([
            'firstname' => (string) ($data['firstname'] ?? ''),
            'lastname' => (string) ($data['lastname'] ?? ''),
            'company' => (string) ($data['company'] ?? ''),
            'telephone' => (string) ($data['telephone'] ?? ''),
            'street' => is_array($data['street'] ?? null) ? $data['street'] : [(string) ($data['street'] ?? '')],
            'city' => (string) ($data['city'] ?? ''),
            'postcode' => (string) ($data['postcode'] ?? ''),
            'country_id' => (string) ($data['country_id'] ?? ''),
            'region' => (string) ($data['region'] ?? ''),
            'region_id' => ($data['region_id'] ?? '') !== '' ? (int) $data['region_id'] : null,
        ]);

        if ($quote->getCustomerEmail()) {
            $address->setEmail((string) $quote->getCustomerEmail());
        }

        $this->cartRepository->save($quote);
    }

    private function clearInvalidShippingMethod(\Magento\Quote\Model\Quote\Address $address): void
    {
        $currentMethod = (string) $address->getShippingMethod();

        if ($currentMethod === '') {
            return;
        }

        $availableCodes = [];
        foreach ((array) $address->getGroupedAllShippingRates() as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $availableCodes[] = (string) $rate->getCode();
            }
        }

        if (!in_array($currentMethod, $availableCodes, true)) {
            $address->setShippingMethod('');
        }
    }
}
