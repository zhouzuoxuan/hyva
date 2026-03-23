<?php

declare(strict_types=1);

namespace Lencarta\Core\Model;

use Lencarta\Core\Helper\Data;

class Config
{
    public function __construct(private readonly Data $helper)
    {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->helper->isEnabled($storeId);
    }

    public function getBrandName(?int $storeId = null): string
    {
        return $this->helper->getBrandName($storeId);
    }

    public function isDebugLoggingEnabled(?int $storeId = null): bool
    {
        return $this->helper->isDebugLoggingEnabled($storeId);
    }

    public function isUiComponentsEnabled(?int $storeId = null): bool
    {
        return $this->helper->isUiComponentsEnabled($storeId);
    }

    public function isCmsWidgetsEnabled(?int $storeId = null): bool
    {
        return $this->helper->isCmsWidgetsEnabled($storeId);
    }

    public function isExperimentalEnabled(?int $storeId = null): bool
    {
        return $this->helper->isExperimentalEnabled($storeId);
    }
}
