<?php
declare(strict_types=1);

namespace Lencarta\Checkout\ViewModel;

use Lencarta\Checkout\Model\Checkout\CheckoutStateProvider;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutState implements ArgumentInterface
{
    private ?array $addressMetadataMap = null;

    public function __construct(
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly CheckoutStateProvider $checkoutStateProvider,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly AllowedCountries $allowedCountries,
        private readonly DirectoryHelper $directoryHelper,
        private readonly AddressMetadataInterface $addressMetadata,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getInitialState(): array
    {
        return $this->checkoutStateProvider->getState($this->sessionQuoteProvider->getQuote());
    }

    public function getDefaultCountryId(): string
    {
        return $this->checkoutStateProvider->getDefaultCountryId();
    }

    public function getWebsiteCode(): string
    {
        return $this->checkoutStateProvider->getWebsiteCode();
    }

    public function getStoreCode(): string
    {
        return $this->checkoutStateProvider->getStoreCode();
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

        $options = [[
            'value' => '',
            'label' => (string) __('Please select'),
        ]];

        foreach ($collection as $country) {
            $options[] = [
                'value' => (string) $country->getCountryId(),
                'label' => (string) $country->getName(),
            ];
        }

        $placeholder = array_shift($options);
        usort($options, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));
        array_unshift($options, $placeholder);

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
