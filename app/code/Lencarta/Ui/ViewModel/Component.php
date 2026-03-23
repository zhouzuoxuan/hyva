<?php
declare(strict_types=1);

namespace Lencarta\Ui\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class Component implements ArgumentInterface
{
    /**
     * @param array<int, string> $classes
     */
    public function classes(array $classes): string
    {
        $classes = array_filter(array_map('trim', $classes));

        return implode(' ', array_unique($classes));
    }

    public function alertClasses(string $variant = 'info'): string
    {
        $map = [
            'success' => 'border-green-200 bg-green-50 text-green-800',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
            'error'   => 'border-red-200 bg-red-50 text-red-800',
            'info'    => 'border-sky-200 bg-sky-50 text-sky-800',
        ];

        return $map[$variant] ?? $map['info'];
    }

    public function badgeClasses(string $variant = 'default'): string
    {
        $map = [
            'default' => 'bg-gray-100 text-gray-800',
            'primary' => 'bg-black text-white',
            'success' => 'bg-green-100 text-green-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'error'   => 'bg-red-100 text-red-800',
        ];

        return $map[$variant] ?? $map['default'];
    }

    public function buttonLinkClasses(string $variant = 'primary'): string
    {
        $map = [
            'primary'   => 'inline-flex items-center justify-center rounded-lg bg-black px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90',
            'secondary' => 'inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-900 transition hover:bg-gray-50',
            'text'      => 'inline-flex items-center text-sm font-semibold underline underline-offset-4',
        ];

        return $map[$variant] ?? $map['primary'];
    }
}
