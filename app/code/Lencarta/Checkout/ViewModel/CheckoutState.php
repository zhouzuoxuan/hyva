<?php
declare(strict_types=1);

namespace Lencarta\Checkout\ViewModel;

use Lencarta\Checkout\Model\Checkout\TotalsProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Address;

class CheckoutState implements ArgumentInterface
{
    private ?array $addressMetadataMap = null;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly TotalsProvider $totalsProvider,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly AllowedCountries $allowedCountries,
        private readonly DirectoryHelper $directoryHelper,
        private readonly AddressMetadataInterface $addressMetadata,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getInitialState(): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            return $this->getEmptyState();
        }

        $shippingAddress = $quote->getShippingAddress();

        return [
            'email' => (string) ($quote->getCustomerEmail() ?: ''),
            'items' => $this->totalsProvider->getItems($quote),
            'totals' => $this->totalsProvider->getTotals($quote),
            'shipping_methods' => $this->totalsProvider->getShippingMethods($quote),
            'selected_shipping_method' => (string) ($shippingAddress?->getShippingMethod() ?: ''),
            'coupon_code' => (string) ($quote->getCouponCode() ?: ''),
            'shipping' => $this->getShippingData($shippingAddress),
        ];
    }


    public function isTermsCheckedByDefault(): bool
    {
        return $this->scopeConfig->isSetFlag('lencarta_checkout/general/terms_checked_by_default');
    }

    public function getCountryOptions(): array
    {
        $allowedCountryIds = $this->allowedCountries->getAllowedCountries();

        $collection = $this->countryCollectionFactory->create();
        $collection->loadByStore();

        if (!empty($allowedCountryIds)) {
            $collection->addFieldToFilter('country_id', ['in' => $allowedCountryIds]);
        }

        $options = [];
        foreach ($collection as $country) {
            $options[] = [
                'value' => (string) $country->getCountryId(),
                'label' => (string) $country->getName(),
            ];
        }

        usort($options, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    public function getAddressFieldConfig(): array
    {
        return [
            'firstname' => [
                'label' => (string) __('First name'),
                'required' => $this->isAttributeRequired('firstname', true),
            ],
            'lastname' => [
                'label' => (string) __('Last name'),
                'required' => $this->isAttributeRequired('lastname', true),
            ],
            'company' => [
                'label' => (string) __('Company'),
                'required' => $this->isAttributeRequired('company', false),
            ],
            'street_1' => [
                'label' => (string) __('Street address'),
                'required' => $this->isAttributeRequired('street', true),
            ],
            'street_2' => [
                'label' => (string) __('Address line 2'),
                'required' => false,
            ],
            'country_id' => [
                'label' => (string) __('Country'),
                'required' => $this->isAttributeRequired('country_id', true),
            ],
            'region' => [
                'label' => (string) __('County / Region'),
                'required' => $this->isAttributeRequired('region', false),
                'required_countries' => array_values($this->directoryHelper->getCountriesWithStatesRequired()),
            ],
            'city' => [
                'label' => (string) __('City'),
                'required' => $this->isAttributeRequired('city', true),
            ],
            'postcode' => [
                'label' => (string) __('Postcode'),
                'required' => $this->isAttributeRequired('postcode', true),
                'optional_countries' => array_values($this->directoryHelper->getCountriesWithOptionalZip()),
            ],
            'telephone' => [
                'label' => (string) __('Phone number'),
                'required' => $this->isAttributeRequired('telephone', true),
            ],
        ];
    }

    private function getShippingData(?Address $address): array
    {
        if (!$address) {
            return $this->getEmptyShipping();
        }

        $street = $address->getStreet() ?: [];

        return [
            'firstname' => (string) ($address->getFirstname() ?: ''),
            'lastname' => (string) ($address->getLastname() ?: ''),
            'company' => (string) ($address->getCompany() ?: ''),
            'telephone' => (string) ($address->getTelephone() ?: ''),
            'street_1' => (string) ($street[0] ?? ''),
            'street_2' => (string) ($street[1] ?? ''),
            'city' => (string) ($address->getCity() ?: ''),
            'postcode' => (string) ($address->getPostcode() ?: ''),
            'region' => (string) ($address->getRegion() ?: ''),
            'country_id' => (string) ($address->getCountryId() ?: 'GB'),
        ];
    }

    private function getEmptyState(): array
    {
        return [
            'email' => '',
            'items' => [],
            'totals' => [],
            'shipping_methods' => [],
            'selected_shipping_method' => '',
            'coupon_code' => '',
            'shipping' => $this->getEmptyShipping(),
        ];
    }

    private function getEmptyShipping(): array
    {
        return [
            'firstname' => '',
            'lastname' => '',
            'company' => '',
            'telephone' => '',
            'street_1' => '',
            'street_2' => '',
            'city' => '',
            'postcode' => '',
            'region' => '',
            'country_id' => 'GB',
        ];
    }

    private function isAttributeRequired(string $attributeCode, bool $default = false): bool
    {
        $map = $this->getAddressMetadataMap();

        if (!isset($map[$attributeCode])) {
            return $default;
        }

        return (bool) $map[$attributeCode]['required'];
    }

    private function getAddressMetadataMap(): array
    {
        if ($this->addressMetadataMap !== null) {
            return $this->addressMetadataMap;
        }

        $this->addressMetadataMap = [];

        try {
            $attributes = $this->addressMetadata->getAllAttributesMetadata();
        } catch (LocalizedException) {
            return $this->addressMetadataMap;
        }

        foreach ($attributes as $attribute) {
            $this->addressMetadataMap[$attribute->getAttributeCode()] = [
                'required' => (bool) $attribute->isRequired(),
            ];
        }

        return $this->addressMetadataMap;
    }
}
