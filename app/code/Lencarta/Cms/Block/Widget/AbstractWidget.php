<?php

declare(strict_types=1);

namespace Lencarta\Cms\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

abstract class AbstractWidget extends Template implements BlockInterface
{
    protected function getStringData(string $key, string $default = ''): string
    {
        $value = $this->getData($key);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    protected function getLines(string $key): array
    {
        $value = $this->getStringData($key);
        if ($value === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
    }
}
