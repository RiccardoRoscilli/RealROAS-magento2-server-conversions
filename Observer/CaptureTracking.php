<?php

namespace Pwsmage\ServerConversions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;

/**
 * Captures click IDs (gclid, fbclid, ttclid, epik) and UTM parameters
 * from the URL on every page load. Stores them in customer session.
 * Uses first-touch attribution for UTM params (won't overwrite existing).
 */
class CaptureTracking implements ObserverInterface
{
    private CustomerSession $customerSession;
    private RequestInterface $request;

    public function __construct(
        CustomerSession $customerSession,
        RequestInterface $request
    ) {
        $this->customerSession = $customerSession;
        $this->request = $request;
    }

    public function execute(Observer $observer): void
    {
        // Google Click ID
        $gclid = $this->request->getParam('gclid');
        if ($gclid) {
            $this->customerSession->setData('gclid', $gclid);
            $this->customerSession->setData('gclid_timestamp', date('Y-m-d\TH:i:sP'));
            $this->customerSession->setData('user_agent', $this->request->getServer('HTTP_USER_AGENT'));
        }

        // Meta (Facebook) Click ID - build _fbc format
        $fbclid = $this->request->getParam('fbclid');
        if ($fbclid) {
            $fbc = 'fb.1.' . (int)(microtime(true) * 1000) . '.' . $fbclid;
            $this->customerSession->setData('meta_fbc', $fbc);
        } elseif (!$this->customerSession->getData('meta_fbc')) {
            // Fallback: read _fbc cookie
            $fbcCookie = $_COOKIE['_fbc'] ?? null;
            if ($fbcCookie) {
                $this->customerSession->setData('meta_fbc', $fbcCookie);
            }
        }

        // Meta Browser ID (_fbp cookie)
        $fbp = $_COOKIE['_fbp'] ?? null;
        if ($fbp && !$this->customerSession->getData('meta_fbp')) {
            $this->customerSession->setData('meta_fbp', $fbp);
        }

        // TikTok Click ID
        $ttclid = $this->request->getParam('ttclid');
        if ($ttclid) {
            $this->customerSession->setData('ttclid', $ttclid);
        }

        // Pinterest Click ID
        $epik = $this->request->getParam('_epik') ?: $this->request->getParam('epik');
        if ($epik) {
            $this->customerSession->setData('epik', $epik);
        }

        // UTM Parameters - first-touch (don't overwrite)
        $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
        foreach ($utmParams as $param) {
            $value = $this->request->getParam($param) ?: ($_COOKIE[$param] ?? null);
            if ($value && !$this->customerSession->getData($param)) {
                $this->customerSession->setData($param, $value);
            }
        }

        // User agent (save on first visit if not already set)
        if (!$this->customerSession->getData('user_agent')) {
            $this->customerSession->setData('user_agent', $this->request->getServer('HTTP_USER_AGENT'));
        }

        // Client IP
        if (!$this->customerSession->getData('client_ip')) {
            $this->customerSession->setData('client_ip', $this->request->getClientIp());
        }
    }
}
