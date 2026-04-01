<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Model\Checkout;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Quote\Model\Quote;

class TotalsProvider
{
    public function __construct(
        private readonly PriceHelper $priceHelper,
        private readonly ImageHelper $imageHelper
    ) {
    }

    public function getTotals(Quote $quote): array
    {
        $quote->collectTotals();

        return [
            'currency'    => $quote->getQuoteCurrencyCode(),
            'subtotal'    => (float) $quote->getSubtotal(),
            'shipping'    => (float) $quote->getShippingAddress()->getShippingAmount(),
            'tax'         => (float) $quote->getShippingAddress()->getTaxAmount(),
            'discount'    => (float) abs((float) $quote->getShippingAddress()->getDiscountAmount()),
            'grand_total' => (float) $quote->getGrandTotal(),
            'formatted'   => [
                'subtotal'    => $this->priceHelper->currency((float) $quote->getSubtotal(), true, false),
                'shipping'    => $this->priceHelper->currency((float) $quote->getShippingAddress()->getShippingAmount(), true, false),
                'tax'         => $this->priceHelper->currency((float) $quote->getShippingAddress()->getTaxAmount(), true, false),
                'discount'    => $this->priceHelper->currency(abs((float) $quote->getShippingAddress()->getDiscountAmount()), true, false),
                'grand_total' => $this->priceHelper->currency((float) $quote->getGrandTotal(), true, false),
            ],
        ];
    }

    public function getItems(Quote $quote): array
    {
        $items = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            $optionsSummary = [];
            $productOptions = $item->getProductOptions() ?: [];

            if (!empty($productOptions['attributes_info'])) {
                foreach ($productOptions['attributes_info'] as $option) {
                    if (!empty($option['label']) && !empty($option['value'])) {
                        $optionsSummary[] = $option['label'] . ': ' . $option['value'];
                    }
                }
            }

            if (!empty($productOptions['options'])) {
                foreach ($productOptions['options'] as $option) {
                    if (!empty($option['label']) && !empty($option['value'])) {
                        $optionsSummary[] = $option['label'] . ': ' . strip_tags((string) $option['value']);
                    }
                }
            }

            $items[] = [
                'item_id' => (int) $item->getItemId(),
                'name' => (string) $item->getName(),
                'sku' => (string) $item->getSku(),
                'qty' => (float) $item->getQty(),
                'row_total' => $this->priceHelper->currency((float) $item->getRowTotalInclTax(), true, false),
                'image_url' => $product && $product->getId()
                    ? $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl()
                    : '',
                'options_summary' => implode(' | ', $optionsSummary),
            ];
        }

        return $items;
    }

    public function getShippingMethods(Quote $quote): array
    {
        $methods = [];
        $address = $quote->getShippingAddress();

        $address->setCollectShippingRates(true);
        $address->collectShippingRates();

        foreach ((array) $address->getGroupedAllShippingRates() as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methods[] = [
                    'carrier_code'  => (string) $rate->getCarrier(),
                    'method_code'   => (string) $rate->getMethod(),
                    'code'          => (string) $rate->getCode(),
                    'carrier_title' => (string) $rate->getCarrierTitle(),
                    'method_title'  => (string) $rate->getMethodTitle(),
                    'price'         => $this->priceHelper->currency((float) $rate->getPrice(), true, false),
                ];
            }
        }

        return $methods;
    }
}
