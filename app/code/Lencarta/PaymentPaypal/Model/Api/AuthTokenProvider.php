<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Api;

use Lencarta\PaymentPaypal\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;

class AuthTokenProvider
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config
    ) {
    }

    public function getAccessToken(?int $storeId = null): string
    {
        $clientId = $this->config->getClientId($storeId);
        $clientSecret = $this->config->getClientSecret($storeId);

        if ($clientId === '' || $clientSecret === '') {
            throw new LocalizedException(__('PayPal API credentials are not configured.'));
        }

        $curl = $this->curlFactory->create();
        $curl->setHeaders([
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ]);

        $curl->post(
            rtrim($this->config->getApiBaseUrl($storeId), '/') . '/v1/oauth2/token',
            'grant_type=client_credentials'
        );

        $status = $curl->getStatus();
        $body = json_decode($curl->getBody(), true);

        if ($status < 200 || $status >= 300 || empty($body['access_token'])) {
            throw new LocalizedException(__('Unable to retrieve PayPal access token.'));
        }

        return (string) $body['access_token'];
    }
}
