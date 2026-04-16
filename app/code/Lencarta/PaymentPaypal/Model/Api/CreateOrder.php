<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Api;

use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;

class CreateOrder
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config,
        private readonly AuthTokenProvider $authTokenProvider,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function execute(array $payload, ?int $storeId = null): array
    {
        $accessToken = $this->authTokenProvider->getAccessToken($storeId);
        $requestId = bin2hex(random_bytes(16));
        $url = rtrim($this->config->getApiBaseUrl($storeId), '/') . '/v2/checkout/orders';

        $curl = $this->curlFactory->create();
        $curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'PayPal-Request-Id' => $requestId,
            'Prefer' => 'return=representation',
        ]);

        $this->debugLogger->debug('PayPal create order request', [
            'url' => $url,
            'payload' => $payload,
            'request_id' => $requestId,
        ], $storeId);

        $curl->post(
            $url,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $status = $curl->getStatus();
        $body = json_decode($curl->getBody(), true) ?: [];

        $this->debugLogger->debug('PayPal create order response', [
            'http_status' => $status,
            'response' => $body,
        ], $storeId);

        if ($status < 200 || $status >= 300 || empty($body['id'])) {
            throw new LocalizedException(__('Unable to create PayPal order.'));
        }

        return [
            'request_id' => $requestId,
            'response' => $body,
        ];
    }
}
