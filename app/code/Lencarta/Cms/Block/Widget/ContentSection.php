<?php
declare(strict_types=1);

namespace Lencarta\Cms\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class ContentSection extends Template implements BlockInterface
{
    protected $_template = 'Lencarta_Cms::widget/content-section.phtml';

    public function hasImage(): bool
    {
        return trim((string) $this->getData('image_url')) !== '';
    }

    public function isMediaRight(): bool
    {
        return (string) $this->getData('media_position') === 'right';
    }

    public function getThemeVariant(): string
    {
        $variant = trim((string) $this->getData('theme_variant'));

        return $variant !== '' ? $variant : 'light';
    }
}
