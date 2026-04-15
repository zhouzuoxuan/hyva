<?php
declare(strict_types=1);

namespace Lencarta\Checkout\Router;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;

class CheckoutAliasRouter implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request)
    {
        $identifier = trim((string) $request->getPathInfo(), '/');

        if (!in_array($identifier, ['checkout', 'checkout/index', 'checkout/index/index'], true)) {
            return null;
        }

        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);
        $request->setModuleName('lencarta_checkout');
        $request->setControllerName('index');
        $request->setActionName('index');

        return $this->actionFactory->create(Forward::class);
    }
}
