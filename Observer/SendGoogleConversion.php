<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends purchase conversion to Google Ads Data Manager API
 * when order status becomes 'complete' or 'closed'.
 */
class SendGoogleConversion implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    private const CONFIG = 'realroas_server_conversions/general/';

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
        if ($order->getData('google_conversion_sent')) {
            return;
        }
        if (!$this->scopeConfig->getValue(self::CONFIG . 'enabled')) {
            return;
        }

        $gclid = $order->getData('gclid');
        if (empty($gclid)) {
            return;
        }

        try {
            $this->send($order, $gclid);
            $order->setData('google_conversion_sent', 1);
            $order->getResource()->saveAttribute($order, 'google_conversion_sent');
        } catch (\Exception $e) {
            $this->logger->error('[RealROAS][Google] Error order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function send($order, string $gclid): void
    {
        $customerId = $this->scopeConfig->getValue(self::CONFIG . 'customer_id');
        $conversionActionId = $this->scopeConfig->getValue(self::CONFIG . 'conversion_action_id');
        $clientId = $this->scopeConfig->getValue(self::CONFIG . 'client_id');
        $clientSecret = $this->scopeConfig->getValue(self::CONFIG . 'client_secret');
        $refreshToken = $this->scopeConfig->getValue(self::CONFIG . 'refresh_token');

        $accessToken = $this->getAccessToken($clientId, $clientSecret, $refreshToken);

        // Build event
        $event = [
            'adIdentifiers' => ['gclid' => $gclid],
            'conversionValue' => (float) $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode() ?: 'EUR',
            'eventTimestamp' => (new \DateTime($order->getCreatedAt()))->format('Y-m-d\TH:i:sP'),
            'transactionId' => $order->getIncrementId(),
            'eventSource' => 'WEB',
        ];

        // Enhanced matching: hashed email
        $email = strtolower(trim($order->getCustomerEmail() ?? ''));
        if ($email) {
            $event['userData'] = [
                'userIdentifiers' => [
                    ['emailAddress' => strtoupper(hash('sha256', $email))]
                ]
            ];
        }

        // User agent
        $userAgent = $order->getData('user_agent');
        if ($userAgent) {
            $event['adIdentifiers']['landingPageDeviceInfo'] = ['userAgent' => $userAgent];
        }

        // Cart data
        $cartItems = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $cartItems[] = [
                'itemId' => $item->getSku(),
                'quantity' => (int) $item->getQtyOrdered(),
                'unitPrice' => (float) $item->getPrice(),
            ];
        }
        if ($cartItems) {
            $event['cartData'] = ['items' => $cartItems];
        }

        $payload = [
            'destinations' => [[
                'operatingAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $customerId],
                'loginAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $customerId],
                'productDestinationId' => $conversionActionId,
            ]],
            'encoding' => 'HEX',
            'events' => [$event],
        ];

        $response = $this->post('https://datamanager.googleapis.com/v1/events:ingest', $payload, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $this->logger->info('[RealROAS][Google] Conversion sent for order ' . $order->getIncrementId() . ' (gclid, value: ' . $event['conversionValue'] . ')');
    }

    private function getAccessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \Exception('Failed to get Google access token: ' . ($data['error_description'] ?? $response));
        }
        return $data['access_token'];
    }

    private function post(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("HTTP {$httpCode}: {$response}");
        }
        return json_decode($response, true) ?: [];
    }
}
