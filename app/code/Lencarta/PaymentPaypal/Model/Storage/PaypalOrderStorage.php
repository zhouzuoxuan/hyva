<?php
declare(strict_types=1);

namespace Lencarta\PaymentPaypal\Model\Storage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;

class PaypalOrderStorage
{
    private const TABLE = 'lencarta_paypal_order';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime
    ) {
    }

    public function saveCreated(Quote $quote, string $paypalOrderId, string $paypalRequestId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $now = $this->dateTime->gmtDate();

        $data = [
            'quote_id' => (int) $quote->getId(),
            'reserved_order_id' => (string) $quote->getReservedOrderId(),
            'store_id' => (int) $quote->getStoreId(),
            'paypal_order_id' => $paypalOrderId,
            'paypal_request_id' => $paypalRequestId,
            'amount' => (float) $quote->getGrandTotal(),
            'currency_code' => (string) $quote->getQuoteCurrencyCode(),
            'status' => 'created',
            'updated_at' => $now,
        ];

        $existing = $this->getByPaypalOrderId($paypalOrderId);
        if ($existing) {
            $connection->update($table, $data, ['entity_id = ?' => (int) $existing['entity_id']]);
            return;
        }

        $data['created_at'] = $now;
        $connection->insert($table, $data);
    }

    public function getByPaypalOrderId(string $paypalOrderId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $select = $connection->select()->from($table)->where('paypal_order_id = ?', $paypalOrderId)->limit(1);
        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    public function getByPaypalCaptureId(string $paypalCaptureId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $select = $connection->select()->from($table)->where('paypal_capture_id = ?', $paypalCaptureId)->limit(1);
        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    public function markCaptured(string $paypalOrderId, string $paypalCaptureId): void
    {
        $this->updateByPaypalOrderId($paypalOrderId, [
            'paypal_capture_id' => $paypalCaptureId,
            'status' => 'captured',
            'updated_at' => $this->dateTime->gmtDate(),
        ]);
    }

    public function attachMagentoOrder(string $paypalOrderId, int $orderId): void
    {
        $this->updateByPaypalOrderId($paypalOrderId, [
            'order_id' => $orderId,
            'status' => 'ordered',
            'updated_at' => $this->dateTime->gmtDate(),
        ]);
    }

    public function updateWebhookState(string $paypalOrderId, string $eventType, string $status): void
    {
        $this->updateByPaypalOrderId($paypalOrderId, [
            'last_event_type' => $eventType,
            'status' => $status,
            'updated_at' => $this->dateTime->gmtDate(),
        ]);
    }

    private function updateByPaypalOrderId(string $paypalOrderId, array $data): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $connection->update($table, $data, ['paypal_order_id = ?' => $paypalOrderId]);
    }
}
