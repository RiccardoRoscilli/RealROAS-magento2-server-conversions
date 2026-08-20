<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends purchase conversion to Pinterest Conversions API v5.
 */
class SendPinterestConversion implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    private const CONFIG = 'realroas_server_conversions/pinterest/';

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
        if ($order->getData('pinterest_conversion_sent')) {
            return;
        }
        if (!$this->scopeConfig->getValue(self::CONFIG . 'enabled')) {
            return;
        }

        $adAccountId = $this->scopeConfig->getValue(self::CONFIG . 'ad_account_id');
        $accessToken = $this->scopeConfig->getValue(self::CONFIG . 'access_token');
        if (empty($adAccountId) || empty($accessToken)) {
            return;
        }

        try {
            $this->send($order, $adAccountId, $accessToken);
            $order->setData('pinterest_conversion_sent', 1);
            $order->getResource()->saveAttribute($order, 'pinterest_conversion_sent');
        } catch (\Exception $e) {
            $this->logger->error('[RealROAS][Pinterest] Error order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }

    private function send($order, string $adAccountId, string $accessToken): void
    {
        $email = strtolower(trim($order->getCustomerEmail() ?? ''));
        $billing = $order->getBillingAddress();

        $userData = [];
        if ($email) $userData['em'] = [hash('sha256', $email)];

        if ($billing) {
            $fn = strtolower(trim($billing->getFirstname() ?? ''));
            $ln = strtolower(trim($billing->getLastname() ?? ''));
            $phone = preg_replace('/[^0-9]/', '', $billing->getTelephone() ?? '');
            $zp = trim($billing->getPostcode() ?? '');
            $country = strtolower(trim($billing->getCountryId() ?? ''));

            if ($fn) $userData['fn'] = [hash('sha256', $fn)];
            if ($ln) $userData['ln'] = [hash('sha256', $ln)];
            if ($phone) $userData['ph'] = [hash('sha256', $phone)];
            if ($zp) $userData['zp'] = [hash('sha256', $zp)];
            if ($country) $userData['country'] = [hash('sha256', $country)];
        }

        // Pinterest click ID
        $epik = $order->getData('epik');
        if ($epik) {
            $userData['click_id'] = $epik;
        }

        // Line items
        $lineItems = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $lineItems[] = [
                'product_id' => $item->getSku(),
                'product_name' => $item->getName(),
                'product_quantity' => (int) $item->getQtyOrdered(),
                'product_price' => number_format((float) $item->getPrice(), 2, '.', ''),
            ];
        }

        $event = [
            'event_name' => 'checkout',
            'action_source' => 'web',
            'event_time' => (int) strtotime($order->getCreatedAt()),
            'event_id' => 'order_' . $order->getIncrementId(),
            'user_data' => $userData,
            'custom_data' => [
                'currency' => $order->getOrderCurrencyCode() ?: 'EUR',
                'value' => number_format((float) $order->getGrandTotal(), 2, '.', ''),
                'order_id' => $order->getIncrementId(),
                'line_items' => $lineItems,
                'num_items' => count($lineItems),
            ],
        ];

        $url = "https://api.pinterest.com/v5/ad_accounts/{$adAccountId}/events";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['data' => [$event]]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Pinterest API HTTP {$httpCode}: {$response}");
        }

        $this->logger->info('[RealROAS][Pinterest] Conversion sent for order ' . $order->getIncrementId());
    }
}
