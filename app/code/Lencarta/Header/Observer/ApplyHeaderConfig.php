<?php
declare(strict_types=1);

namespace Lencarta\Header\Observer;

use Lencarta\Header\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ApplyHeaderConfig implements ObserverInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $layout = $observer->getEvent()->getLayout();
        if ($layout === null) {
            return;
        }

        $topmenuMobile = $layout->getBlock('topmenu_mobile');
        if ($topmenuMobile) {
            $topmenuMobile->setData('max_level', $this->config->getMobileMaxLevel());
            $topmenuMobile->setData('show_search', $this->config->isMobileSearchEnabled());
            $topmenuMobile->setData('show_socials', $this->config->isMobileSocialsEnabled());
            $topmenuMobile->setData('show_settings_nav', $this->config->isMobileSettingsNavEnabled());
            $topmenuMobile->setData('additional_menu_items_before', $this->config->getAdditionalMenuItemsBefore());
            $topmenuMobile->setData('additional_menu_items', $this->config->getAdditionalMenuItems());
        }

        $messagesBlock = $layout->getBlock('messages');
        if ($messagesBlock) {
            $messagesBlock->setData('display_mode', $this->config->getMessagesDisplayMode());
            $messagesBlock->setData('show_icon', $this->config->isMessagesIconEnabled());
        }
    }
}
