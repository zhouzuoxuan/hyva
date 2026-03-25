<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Widget;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

abstract class AbstractWidget extends Template implements BlockInterface
{
    protected FilterProvider $filterProvider;

    public function __construct(
        Context $context,
        FilterProvider $filterProvider,
        array $data = []
    ) {
        $this->filterProvider = $filterProvider;
        parent::__construct($context, $data);
    }

    public function getField(string $key, string $default = ''): string
    {
        $value = $this->getData($key);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function getBoolField(string $key, bool $default = false): bool
    {
        $value = $this->getData($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array((string) $value, ['1', 'true', 'yes'], true);
    }

    public function getItemsField(string $key): array
    {
        $value = $this->getField($key);
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

    public function getHtmlField(string $key): string
    {
        $value = $this->getField($key);
        if ($value === '') {
            return '';
        }

        $value = $this->sanitizeHtml($value);

        try {
            return (string) $this->filterProvider->getBlockFilter()->filter($value);
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    public function getPictureFallback(string $primaryKey, string $fallbackKey = ''): string
    {
        $primary = $this->getField($primaryKey);
        if ($primary !== '') {
            return $primary;
        }

        return $fallbackKey !== '' ? $this->getField($fallbackKey) : '';
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

    protected function sanitizeHtml(string $html): string
    {
        $patterns = [
            '#<script\b[^>]*>.*?</script>#is',
            '#<style\b[^>]*>.*?</style>#is',
            '#<iframe\b[^>]*>.*?</iframe>#is',
            '#<object\b[^>]*>.*?</object>#is',
            '#<embed\b[^>]*>.*?</embed>#is',
            '#\son[a-z]+\s*=\s*("|\').*?\1#is',
            '#\s(href|src)\s*=\s*("|\')\s*javascript:.*?\2#is',
        ];

        return preg_replace($patterns, '', $html) ?? $html;
    }
}
