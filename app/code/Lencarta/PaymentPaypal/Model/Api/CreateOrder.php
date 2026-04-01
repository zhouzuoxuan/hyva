<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Api;

use Lencarta\PaymentPaypal\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

class CreateOrder
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly AuthTokenProvider $authTokenProvider
    ) {
    }

    public function execute(array $payload, ?int $storeId = null): array
    {
        $accessToken = $this->authTokenProvider->getAccessToken($storeId);
        $requestId = bin2hex(random_bytes(16));

        $this->curl->reset();
        $this->curl->setHeaders([
            'Authorization'      => 'Bearer ' . $accessToken,
            'Content-Type'       => 'application/json',
            'PayPal-Request-Id'  => $requestId,
        ]);

        $this->curl->post(
            rtrim($this->config->getApiBaseUrl($storeId), '/') . '/v2/checkout/orders',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $status = $this->curl->getStatus();
        $body = json_decode($this->curl->getBody(), true);

        if ($status < 200 || $status >= 300 || empty($body['id'])) {
            throw new LocalizedException(__('Unable to create PayPal order.'));
        }

        return [
            'request_id' => $requestId,
            'response'   => $body,
        ];
    }
}
