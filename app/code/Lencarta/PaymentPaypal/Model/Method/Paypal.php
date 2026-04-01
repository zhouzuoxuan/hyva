<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Method;

use Magento\Payment\Model\Method\AbstractMethod;

class Paypal extends AbstractMethod
{
    protected $_code = 'lencarta_paypal';
    protected $_isOffline = false;
}
