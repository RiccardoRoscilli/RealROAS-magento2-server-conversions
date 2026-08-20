<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;

/**
 * Transfers tracking data from session onto the order before it's saved.
 * Fires on sales_order_place_before.
 */
class SaveTrackingData implements ObserverInterface
{
    private CustomerSession $customerSession;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $fields = [
            'gclid', 'gclid_timestamp', 'user_agent', 'client_ip',
            'meta_fbc', 'meta_fbp',
            'ttclid', 'epik',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        ];

        foreach ($fields as $field) {
            $value = $this->customerSession->getData($field);
            if ($value) {
                $order->setData($field, $value);
            }
        }

        $this->logger->info('[RealROAS] Tracking data saved on order ' . $order->getIncrementId(), [
            'gclid' => $order->getData('gclid') ? 'yes' : 'no',
            'meta_fbc' => $order->getData('meta_fbc') ? 'yes' : 'no',
            'ttclid' => $order->getData('ttclid') ? 'yes' : 'no',
            'utm_source' => $order->getData('utm_source') ?: '-',
        ]);
    }
}
