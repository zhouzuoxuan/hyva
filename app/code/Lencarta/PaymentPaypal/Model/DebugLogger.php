<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model;

use Lencarta\PaymentPaypal\Logger\Logger;

class DebugLogger
{
    public function __construct(
        private readonly Logger $logger,
        private readonly Config $config
    ) {
    }

    public function debug(string $message, array $context = [], ?int $storeId = null): void
    {
        if (!$this->config->isDebug($storeId)) {
            return;
        }

        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = [], ?int $storeId = null): void
    {
        $this->logger->info($message, $context);
    }

    public function error(string $message, array $context = [], ?int $storeId = null): void
    {
        $this->logger->error($message, $context);
    }
}
