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

class ApplyCoupon extends AbstractJsonAction implements HttpPostActionInterface
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
            $couponCode = trim((string) $this->request->getParam('coupon_code', ''));

            $this->couponManager->apply($quote, $couponCode);
            $state = $this->checkoutStateProvider->getState($quote);

            return $this->createResult([
                'success' => true,
                'message' => __('Coupon code "%1" was applied.', $state['coupon_code'] ?: $couponCode),
                'coupon_code' => $state['coupon_code'] ?? $couponCode,
                'state' => $state,
                'totals' => $state['totals'] ?? [],
                'shipping_methods' => $state['shipping_methods'] ?? [],
                'selected_shipping_method' => $state['selected_shipping_method'] ?? '',
            ]);
        } catch (LocalizedException $e) {
            return $this->createResult(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            return $this->createResult(['success' => false, 'message' => __('Unable to apply coupon.')]);
        }
    }
}
