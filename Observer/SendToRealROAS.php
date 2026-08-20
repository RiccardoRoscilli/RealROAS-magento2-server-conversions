<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends order data to RealROAS dashboard API for true ROAS calculation.
 * This is optional and requires a RealROAS subscription + API key.
 */
class SendToRealROAS implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    private const CONFIG = 'realroas_server_conversions/realroas/';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        if (!$this->scopeConfig->getValue(self::CONFIG . 'enabled')) {
            return;
        }

        $apiKey = $this->scopeConfig->getValue(self::CONFIG . 'api_key');
        $apiUrl = $this->scopeConfig->getValue(self::CONFIG . 'api_url') ?: 'https://realroas.net/api/v1';

        if (empty($apiKey)) {
            return;
        }

        // Send on order place (any status) and on status change to complete/closed/cancelled/refunded
        $status = $order->getStatus();
        $trackStatuses = ['pending', 'processing', 'complete', 'closed', 'canceled', 'refunded'];

        if (!in_array($status, $trackStatuses)) {
            return;
        }

        try {
            $this->sendOrder($order, $apiKey, $apiUrl, $status);
        } catch (\Exception $e) {
            $this->logger->error('[RealROAS][Dashboard] Error order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function sendOrder($order, string $apiKey, string $apiUrl, string $status): void
    {
        // Map Magento status to RealROAS status
        $statusMap = [
            'complete' => 'completed',
            'closed' => 'completed',
            'canceled' => 'cancelled',
            'refunded' => 'refunded',
        ];
        $realroasStatus = $statusMap[$status] ?? 'pending';

        $payload = [
            'order_id' => $order->getIncrementId(),
            'order_number' => $order->getIncrementId(),
            'revenue' => (float) $order->getGrandTotal(),
            'shipping' => (float) $order->getShippingAmount(),
            'tax' => (float) $order->getTaxAmount(),
            'discount' => abs((float) $order->getDiscountAmount()),
            'currency' => $order->getOrderCurrencyCode() ?: 'EUR',
            'customer_email' => $order->getCustomerEmail(),
            'status' => $realroasStatus,
            'ordered_at' => $order->getCreatedAt(),
            'session_id' => session_id() ?: null,
            'utm_source' => $order->getData('utm_source'),
            'utm_medium' => $order->getData('utm_medium'),
            'utm_campaign' => $order->getData('utm_campaign'),
            'gclid' => $order->getData('gclid'),
            'fbclid' => $order->getData('meta_fbc'),
            'ttclid' => $order->getData('ttclid'),
        ];

        // If order already exists on RealROAS, update status
        $isUpdate = in_array($status, ['canceled', 'refunded', 'closed']);
        if ($isUpdate) {
            $url = rtrim($apiUrl, '/') . '/orders/' . $order->getIncrementId();
            $method = 'PATCH';
            $payload = ['status' => $realroasStatus, 'revenue' => (float) $order->getGrandTotal()];
        } else {
            $url = rtrim($apiUrl, '/') . '/orders';
            $method = 'POST';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 && $httpCode !== 404) {
            $this->logger->warning('[RealROAS][Dashboard] HTTP ' . $httpCode . ' for order ' . $order->getIncrementId() . ': ' . $response);
        }
    }
}
