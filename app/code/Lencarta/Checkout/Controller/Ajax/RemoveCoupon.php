<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Lencarta\Checkout\Model\Checkout\CheckoutStateProvider;
use Lencarta\Checkout\Model\Checkout\CouponManager;
use Lencarta\Checkout\Model\Checkout\SessionQuoteProvider;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class RemoveCoupon extends AbstractJsonAction implements HttpPostActionInterface
{
    public function __construct(
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly SessionQuoteProvider $sessionQuoteProvider,
        private readonly CouponManager $couponManager,
        private readonly CheckoutStateProvider $checkoutStateProvider
    ) {
        parent::__construct($resultJsonFactory, $formKeyValidator);
    }

    public function execute()
    {
        try {
            $this->validateFormKey($this->request);

            $quote = $this->sessionQuoteProvider->getQuote();
            $couponName = $this->couponManager->getAppliedCouponLabel($quote);
            $this->couponManager->remove($quote);
            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => true,
                'message' => $couponName !== ''
                    ? __('Discount "%1" was removed.', $couponName)
                    : __('Coupon code was removed.'),
                'coupon_code' => '',
                'coupon_name' => '',
                'state' => $state,
                'totals' => $state['totals'] ?? [],
                'shipping_methods' => $state['shipping_methods'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        } catch (LocalizedException $e) {
            $quote = $this->sessionQuoteProvider->getQuote();
            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => false,
                'message' => $e->getMessage(),
                'coupon_code' => $state['coupon_code'] ?? '',
                'coupon_name' => $state['coupon_name'] ?? '',
                'state' => $state,
                'totals' => $state['totals'] ?? [],
                'shipping_methods' => $state['shipping_methods'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        } catch (\Throwable) {
            $quote = $this->sessionQuoteProvider->getQuote();
            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => false,
                'message' => __('Unable to remove coupon.'),
                'coupon_code' => $state['coupon_code'] ?? '',
                'coupon_name' => $state['coupon_name'] ?? '',
                'state' => $state,
                'totals' => $state['totals'] ?? [],
                'shipping_methods' => $state['shipping_methods'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        }
    }
}
