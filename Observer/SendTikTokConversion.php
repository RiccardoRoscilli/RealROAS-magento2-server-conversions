<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends purchase conversion to TikTok Events API.
 */
class SendTikTokConversion implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    private const CONFIG = 'realroas_server_conversions/tiktok/';

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
        if (!$order || !in_array($order->getStatus(), ['complete', 'closed'])) {
            return;
        }
        if ($order->getData('tiktok_conversion_sent')) {
            return;
        }
        if (!$this->scopeConfig->getValue(self::CONFIG . 'enabled')) {
            return;
        }

        $pixelId = $this->scopeConfig->getValue(self::CONFIG . 'pixel_id');
        $accessToken = $this->scopeConfig->getValue(self::CONFIG . 'access_token');
        if (empty($pixelId) || empty($accessToken)) {
            return;
        }

        try {
            $this->send($order, $pixelId, $accessToken);
            $order->setData('tiktok_conversion_sent', 1);
            $order->getResource()->saveAttribute($order, 'tiktok_conversion_sent');
        } catch (\Exception $e) {
            $this->logger->error('[RealROAS][TikTok] Error order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function send($order, string $pixelId, string $accessToken): void
    {
        $email = strtolower(trim($order->getCustomerEmail() ?? ''));
        $billing = $order->getBillingAddress();
        $phone = $billing ? preg_replace('/[^0-9]/', '', $billing->getTelephone() ?? '') : '';

        $user = [];
        if ($email) $user['email'] = hash('sha256', $email);
        if ($phone) $user['phone'] = hash('sha256', $phone);

        $ttclid = $order->getData('ttclid');
        if ($ttclid) $user['ttclid'] = $ttclid;

        $userAgent = $order->getData('user_agent');
        $clientIp = $order->getData('client_ip') ?: $order->getRemoteIp();

        $contents = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $contents[] = [
                'content_id' => $item->getSku(),
                'content_name' => $item->getName(),
                'quantity' => (int) $item->getQtyOrdered(),
                'price' => (float) $item->getPrice(),
            ];
        }

        $event = [
            'event' => 'CompletePayment',
            'event_time' => (int) strtotime($order->getCreatedAt()),
            'event_id' => 'order_' . $order->getIncrementId(),
            'context' => [
                'user' => $user,
                'user_agent' => $userAgent ?: '',
                'ip' => $clientIp ?: '',
            ],
            'properties' => [
                'order_id' => $order->getIncrementId(),
                'value' => (float) $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode() ?: 'EUR',
                'contents' => $contents,
            ],
        ];

        $payload = [
            'pixel_code' => $pixelId,
            'data' => [$event],
        ];

        $ch = curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Access-Token: ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("TikTok API HTTP {$httpCode}: {$response}");
        }

        $this->logger->info('[RealROAS][TikTok] Conversion sent for order ' . $order->getIncrementId());
    }
}
