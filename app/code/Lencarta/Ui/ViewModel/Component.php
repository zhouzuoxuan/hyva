<?php

declare(strict_types=1);

namespace Lencarta\Ui\ViewModel;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Component implements ArgumentInterface
{
    public function __construct(private readonly Escaper $escaper)
    {
    }

    public function variantClass(string $variant, array $map, string $default = ''): string
    {
        return $map[$variant] ?? $default;
    }

    public function boolAttr(bool $condition, string $attribute): string
    {
        return $condition ? $attribute : '';
    }

    public function escape(string $value): string
    {
        return $this->escaper->escapeHtml($value);
    }

    public function splitLines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
    }
}
