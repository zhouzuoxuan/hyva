<?php
declare(strict_types=1);

namespace Lencarta\Header\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    private const XML_PATH_PHONE = 'general/store_information/phone';

    private const XML_PATH_DESKTOP_SHOW_PHONE = 'lencarta_header/desktop/show_phone';
    private const XML_PATH_DESKTOP_SHOW_COMPARE = 'lencarta_header/desktop/show_compare';
    private const XML_PATH_DESKTOP_SHOW_WISHLIST = 'lencarta_header/desktop/show_wishlist';
    private const XML_PATH_DESKTOP_TOP_LINK_1_LABEL = 'lencarta_header/desktop/top_link_1_label';
    private const XML_PATH_DESKTOP_TOP_LINK_1_URL = 'lencarta_header/desktop/top_link_1_url';
    private const XML_PATH_DESKTOP_TOP_LINK_2_LABEL = 'lencarta_header/desktop/top_link_2_label';
    private const XML_PATH_DESKTOP_TOP_LINK_2_URL = 'lencarta_header/desktop/top_link_2_url';
    private const XML_PATH_DESKTOP_TOP_LINK_3_LABEL = 'lencarta_header/desktop/top_link_3_label';
    private const XML_PATH_DESKTOP_TOP_LINK_3_URL = 'lencarta_header/desktop/top_link_3_url';

    private const XML_PATH_MOBILE_MAX_LEVEL = 'lencarta_header/mobile_menu/max_level';
    private const XML_PATH_MOBILE_SHOW_SEARCH = 'lencarta_header/mobile_menu/show_search';
    private const XML_PATH_MOBILE_SHOW_SOCIALS = 'lencarta_header/mobile_menu/show_socials';
    private const XML_PATH_MOBILE_SHOW_SETTINGS_NAV = 'lencarta_header/mobile_menu/show_settings_nav';
    private const XML_PATH_MOBILE_HOME_LABEL = 'lencarta_header/mobile_menu/home_label';
    private const XML_PATH_MOBILE_HOME_URL = 'lencarta_header/mobile_menu/home_url';
    private const XML_PATH_MOBILE_EXTRA_1_LABEL = 'lencarta_header/mobile_menu/extra_link_1_label';
    private const XML_PATH_MOBILE_EXTRA_1_URL = 'lencarta_header/mobile_menu/extra_link_1_url';
    private const XML_PATH_MOBILE_EXTRA_2_LABEL = 'lencarta_header/mobile_menu/extra_link_2_label';
    private const XML_PATH_MOBILE_EXTRA_2_URL = 'lencarta_header/mobile_menu/extra_link_2_url';

    private const XML_PATH_MOBILE_CTA_ENABLED = 'lencarta_header/mobile_cta/enabled';
    private const XML_PATH_MOBILE_CTA_LABEL = 'lencarta_header/mobile_cta/label';
    private const XML_PATH_MOBILE_CTA_ICON_ID = 'lencarta_header/mobile_cta/icon_id';
    private const XML_PATH_MOBILE_CTA_URL = 'lencarta_header/mobile_cta/url';

    private const XML_PATH_SOCIAL_FACEBOOK = 'lencarta_header/social/facebook_url';
    private const XML_PATH_SOCIAL_INSTAGRAM = 'lencarta_header/social/instagram_url';
    private const XML_PATH_SOCIAL_PINTEREST = 'lencarta_header/social/pinterest_url';
    private const XML_PATH_SOCIAL_TWITTER = 'lencarta_header/social/twitter_url';

    private const XML_PATH_MESSAGES_DISPLAY_MODE = 'lencarta_header/messages/display_mode';
    private const XML_PATH_MESSAGES_SHOW_ICON = 'lencarta_header/messages/show_icon';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getPhone(): string
    {
        return trim((string) $this->getValue(self::XML_PATH_PHONE));
    }

    public function isPhoneVisible(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DESKTOP_SHOW_PHONE) && $this->getPhone() !== '';
    }

    public function getPhoneHref(): string
    {
        return 'tel:' . preg_replace('/[^+0-9]/', '', $this->getPhone());
    }

    public function isCompareEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DESKTOP_SHOW_COMPARE);
    }

    public function isWishlistEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DESKTOP_SHOW_WISHLIST);
    }

    public function getDesktopLinks(): array
    {
        $links = [
            [
                'label' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_1_LABEL),
                'url' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_1_URL),
            ],
            [
                'label' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_2_LABEL),
                'url' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_2_URL),
            ],
            [
                'label' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_3_LABEL),
                'url' => $this->getValue(self::XML_PATH_DESKTOP_TOP_LINK_3_URL),
            ],
        ];

        return array_values(array_filter($links, static function (array $link): bool {
            return trim((string) ($link['label'] ?? '')) !== '' && trim((string) ($link['url'] ?? '')) !== '';
        }));
    }

    public function getMobileMaxLevel(): int
    {
        $value = (int) $this->getValue(self::XML_PATH_MOBILE_MAX_LEVEL);
        return $value > 0 ? $value : 4;
    }

    public function isMobileSearchEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_MOBILE_SHOW_SEARCH);
    }

    public function isMobileSocialsEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_MOBILE_SHOW_SOCIALS);
    }

    public function isMobileSettingsNavEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_MOBILE_SHOW_SETTINGS_NAV);
    }

    public function getAdditionalMenuItemsBefore(): array
    {
        $label = trim((string) $this->getValue(self::XML_PATH_MOBILE_HOME_LABEL));
        $url = trim((string) $this->getValue(self::XML_PATH_MOBILE_HOME_URL));

        if ($label === '' || $url === '') {
            return [];
        }

        return [
            'home' => [
                'url' => $url,
                'name' => $label,
            ],
        ];
    }

    public function getAdditionalMenuItems(): array
    {
        $items = [];

        $map = [
            'extra_1' => [
                'label' => $this->getValue(self::XML_PATH_MOBILE_EXTRA_1_LABEL),
                'url' => $this->getValue(self::XML_PATH_MOBILE_EXTRA_1_URL),
            ],
            'extra_2' => [
                'label' => $this->getValue(self::XML_PATH_MOBILE_EXTRA_2_LABEL),
                'url' => $this->getValue(self::XML_PATH_MOBILE_EXTRA_2_URL),
            ],
        ];

        foreach ($map as $key => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $items[$key] = [
                'url' => $url,
                'name' => $label,
            ];
        }

        return $items;
    }

    public function isMobileCtaEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_MOBILE_CTA_ENABLED)
            && trim((string) $this->getValue(self::XML_PATH_MOBILE_CTA_LABEL)) !== ''
            && trim((string) $this->getValue(self::XML_PATH_MOBILE_CTA_URL)) !== '';
    }

    public function getMobileCtaLabel(): string
    {
        return trim((string) $this->getValue(self::XML_PATH_MOBILE_CTA_LABEL));
    }

    public function getMobileCtaIconId(): string
    {
        return trim((string) $this->getValue(self::XML_PATH_MOBILE_CTA_LABEL));
    }

    public function getMobileCtaUrl(): string
    {
        return trim((string) $this->getValue(self::XML_PATH_MOBILE_CTA_URL));
    }

    public function getSocialLinks(): array
    {
        $links = [
            [
                'url' => $this->getValue(self::XML_PATH_SOCIAL_FACEBOOK),
                'label' => 'Facebook',
                'icon' => 'social-facebook',
            ],
            [
                'url' => $this->getValue(self::XML_PATH_SOCIAL_INSTAGRAM),
                'label' => 'Instagram',
                'icon' => 'social-instagram',
            ],
            [
                'url' => $this->getValue(self::XML_PATH_SOCIAL_PINTEREST),
                'label' => 'Pinterest',
                'icon' => 'social-pinterest',
            ],
            [
                'url' => $this->getValue(self::XML_PATH_SOCIAL_TWITTER),
                'label' => 'Twitter',
                'icon' => 'social-twitter',
            ],
        ];

        return array_values(array_filter($links, static function (array $item): bool {
            return trim((string) ($item['url'] ?? '')) !== '';
        }));
    }

    public function getMessagesDisplayMode(): string
    {
        $mode = trim((string) $this->getValue(self::XML_PATH_MESSAGES_DISPLAY_MODE));
        return in_array($mode, ['default', 'blank', 'tag'], true) ? $mode : 'tag';
    }

    public function isMessagesIconEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_MESSAGES_SHOW_ICON);
    }

    private function getValue(string $path): string
    {
        return (string) $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    private function isSetFlag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    private function getStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
