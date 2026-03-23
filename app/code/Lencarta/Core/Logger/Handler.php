<?php
declare(strict_types=1);

namespace Lencarta\Core\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    protected $loggerType = MonologLogger::DEBUG;

    protected $fileName = '/var/log/lencarta_core.log';
}
