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



    public function getHeroTextSchemeClasses(string $scheme): array
    {
        $map = [
            'light' => 'text-white',
            'dark' => 'text-slate-950',
            'brand-light' => 'text-brand',
        ];

        $textClass = $map[$scheme] ?? $map['light'];

        return [
            'eyebrow' => $textClass,
            'title' => $textClass,
            'description' => $textClass,
        ];
    }

    public function getCtaTextSchemeClasses(string $scheme, string $fallbackVariant = 'light'): array
    {
        if ($scheme === 'inherit') {
            $scheme = $fallbackVariant === 'dark' ? 'light' : 'dark';
        }

        $map = [
            'light' => 'text-white',
            'dark' => 'text-slate-950',
            'brand' => 'text-brand',
        ];

        $textClass = $map[$scheme] ?? $map['dark'];

        return [
            'eyebrow' => $textClass,
            'title' => $textClass,
            'description' => $textClass,
        ];
    }

    public function getButtonStyleClasses(string $style, string $context = 'hero', string $textScheme = 'light'): string
    {
        $contextualPrimary = in_array($textScheme, ['light', 'brand-light'], true)
            ? 'bg-white text-slate-950 hover:bg-slate-100'
            : 'bg-slate-950 text-white hover:bg-slate-800';

        $map = [
            'primary' => $contextualPrimary,
            'secondary-light' => 'border border-white/40 bg-white/10 text-white hover:bg-white/15',
            'secondary-dark' => 'border border-slate-950/15 bg-slate-950/5 text-slate-950 hover:bg-slate-950/10',
            'outline-light' => 'border border-white/55 bg-transparent text-white hover:bg-white/10',
            'outline-dark' => 'border border-slate-950/30 bg-transparent text-slate-950 hover:bg-slate-950/5',
        ];

        return $map[$style] ?? $contextualPrimary;
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
