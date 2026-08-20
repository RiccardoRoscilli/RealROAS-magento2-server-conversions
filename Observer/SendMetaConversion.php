<?php

namespace RealROAS\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends purchase conversion to Meta (Facebook/Instagram) Conversions API.
 * Fires when order status becomes 'complete' or 'closed'.
 * Uses event_id for deduplication with client-side pixel.
 */
class SendMetaConversion implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    private const CONFIG = 'realroas_server_conversions/meta/';

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
        if ($order->getData('meta_conversion_sent')) {
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
            $order->setData('meta_conversion_sent', 1);
            $order->getResource()->saveAttribute($order, 'meta_conversion_sent');
        } catch (\Exception $e) {
            $this->logger->error('[RealROAS][Meta] Error order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function send($order, string $pixelId, string $accessToken): void
    {
        $billing = $order->getBillingAddress();
        $userData = [];

        // Hashed user data
        $email = strtolower(trim($order->getCustomerEmail() ?? ''));
        if ($email) $userData['em'] = [hash('sha256', $email)];

        if ($billing) {
            $fn = strtolower(trim($billing->getFirstname() ?? ''));
            $ln = strtolower(trim($billing->getLastname() ?? ''));
            $ph = preg_replace('/[^0-9]/', '', $billing->getTelephone() ?? '');
            $zp = trim($billing->getPostcode() ?? '');
            $ct = strtolower(trim($billing->getCity() ?? ''));
            $country = strtolower(trim($billing->getCountryId() ?? ''));

            if ($fn) $userData['fn'] = [hash('sha256', $fn)];
            if ($ln) $userData['ln'] = [hash('sha256', $ln)];
            if ($ph) $userData['ph'] = [hash('sha256', $ph)];
            if ($zp) $userData['zp'] = [hash('sha256', $zp)];
            if ($ct) $userData['ct'] = [hash('sha256', $ct)];
            if ($country) $userData['country'] = [hash('sha256', $country)];
        }

        // Click identifiers
        $fbc = $order->getData('meta_fbc');
        $fbp = $order->getData('meta_fbp');
        if ($fbc) $userData['fbc'] = $fbc;
        if ($fbp) $userData['fbp'] = $fbp;

        // Client info
        $userAgent = $order->getData('user_agent');
        $clientIp = $order->getData('client_ip') ?: $order->getRemoteIp();
        if ($userAgent) $userData['client_user_agent'] = $userAgent;
        if ($clientIp) $userData['client_ip_address'] = $clientIp;

        // Contents
        $contents = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $contents[] = [
                'id' => $item->getSku(),
                'quantity' => (int) $item->getQtyOrdered(),
                'item_price' => (float) $item->getPrice(),
            ];
        }

        $eventData = [
            'event_name' => 'Purchase',
            'event_time' => (int) strtotime($order->getCreatedAt()),
            'event_id' => 'order_' . $order->getIncrementId(),
            'event_source_url' => $this->scopeConfig->getValue('web/secure/base_url') ?? '',
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => [
                'currency' => $order->getOrderCurrencyCode() ?: 'EUR',
                'value' => (float) $order->getGrandTotal(),
                'order_id' => $order->getIncrementId(),
                'contents' => $contents,
                'content_type' => 'product',
                'num_items' => count($contents),
            ],
        ];

        $url = "https://graph.facebook.com/v20.0/{$pixelId}/events";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['data' => [$eventData], 'access_token' => $accessToken]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Meta CAPI HTTP {$httpCode}: {$response}");
        }

        $this->logger->info('[RealROAS][Meta] Conversion sent for order ' . $order->getIncrementId());
    }
}
