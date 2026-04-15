<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Api;

use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

class CaptureOrder
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly AuthTokenProvider $authTokenProvider,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function execute(string $paypalOrderId, ?int $storeId = null): array
    {
        if ($paypalOrderId === '') {
            throw new LocalizedException(__('Missing PayPal order ID.'));
        }

        $accessToken = $this->authTokenProvider->getAccessToken($storeId);
        $requestId = bin2hex(random_bytes(16));
        $url = rtrim($this->config->getApiBaseUrl($storeId), '/') . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture';

        $this->curl->reset();
        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'PayPal-Request-Id' => $requestId,
            'Prefer' => 'return=representation',
        ]);

        $this->debugLogger->debug('PayPal capture request', [
            'paypal_order_id' => $paypalOrderId,
            'url' => $url,
            'request_id' => $requestId,
        ], $storeId);

        $this->curl->post($url, '{}');

        $status = $this->curl->getStatus();
        $rawBody = $this->curl->getBody();
        $body = json_decode($rawBody, true) ?: [];

        $this->debugLogger->debug('PayPal capture response', [
            'paypal_order_id' => $paypalOrderId,
            'http_status' => $status,
            'response' => $body,
        ], $storeId);

        if ($status < 200 || $status >= 300 || empty($body['id'])) {
            throw new LocalizedException(__('Unable to capture PayPal order.'));
        }

        return [
            'request_id' => $requestId,
            'response' => $body,
        ];
    }
}
