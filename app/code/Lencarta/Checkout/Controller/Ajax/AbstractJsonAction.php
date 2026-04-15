<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Controller\Ajax;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractJsonAction
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator
    ) {
    }

    protected function createResult(array $data): Json
    {
        return $this->resultJsonFactory->create()->setData($data);
    }

    protected function validateFormKey(RequestInterface $request): void
    {
        if (!$this->formKeyValidator->validate($request)) {
            throw new LocalizedException(__('Your session has expired. Please refresh the page and try again.'));
        }
    }
}
