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

    protected function decodeJsonArray(string $value): array
    {
        if ($value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($item): string => is_scalar($item) ? trim((string) $item) : '',
            $decoded
        ), static fn(string $item): bool => $item !== ''));
    }

    protected function getLines(string $key): array
    {
        $value = $this->getStringData($key);
        if ($value === '') {
            return [];
        }

        $jsonItems = $this->decodeJsonArray($value);
        if ($jsonItems) {
            return $jsonItems;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
    }
}
