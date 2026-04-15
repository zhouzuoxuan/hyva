<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Api;

use Lencarta\PaymentPaypal\Model\Config;
use Lencarta\PaymentPaypal\Model\DebugLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

class VerifyWebhookSignature
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly AuthTokenProvider $authTokenProvider,
        private readonly DebugLogger $debugLogger
    ) {
    }

    public function execute(array $headers, array $body, ?int $storeId = null): bool
    {
        $webhookId = $this->config->getWebhookId($storeId);
        if ($webhookId === '') {
            throw new LocalizedException(__('PayPal Webhook ID is not configured.'));
        }

        $requiredHeaders = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];

        foreach ($requiredHeaders as $requiredHeader) {
            if (empty($headers[$requiredHeader])) {
                throw new LocalizedException(__('Missing PayPal webhook header: %1', $requiredHeader));
            }
        }

        $accessToken = $this->authTokenProvider->getAccessToken($storeId);
        $payload = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'],
            'cert_url' => $headers['PAYPAL-CERT-URL'],
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'],
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'],
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
            'webhook_id' => $webhookId,
            'webhook_event' => $body,
        ];

        $url = rtrim($this->config->getApiBaseUrl($storeId), '/') . '/v1/notifications/verify-webhook-signature';

        $this->curl->reset();
        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ]);
        $this->curl->post($url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $status = $this->curl->getStatus();
        $response = json_decode($this->curl->getBody(), true) ?: [];

        $this->debugLogger->debug('PayPal webhook signature verification response', [
            'http_status' => $status,
            'response' => $response,
        ], $storeId);

        return $status >= 200 && $status < 300 && (($response['verification_status'] ?? '') === 'SUCCESS');
    }
}
