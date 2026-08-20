# RealROAS Server-Side Conversions for Magento 2
## User Guide v1.0.0

---

## Overview

RealROAS Server-Side Conversions is a free Magento 2 module that sends purchase conversion data via server-side API to Google Ads, Meta (Facebook/Instagram), TikTok, and Pinterest.

It works alongside your existing frontend tracking (Google Tag, Meta Pixel, TikTok Pixel, Pinterest Tag) without replacing or interfering with it. The ad platforms deduplicate events automatically using the event ID.

---

## Installation

Run the following commands from your Magento 2 root directory:

    composer require realroas/magento2-server-conversions
    bin/magento module:enable RealROAS_ServerConversions
    bin/magento setup:upgrade
    bin/magento cache:flush

---

## Configuration

Navigate to: **Stores → Configuration → RealROAS → Server-Side Conversions**

### Google Ads

1. Set **Enabled** to Yes
2. Enter your **Customer ID** (without dashes, e.g. 1234567890)
3. Enter your **Conversion Action ID** (found in Google Ads → Tools → Conversions → your action → URL contains the ID)
4. Enter your **OAuth Client ID** (from Google Cloud Console → Credentials)
5. Enter your **OAuth Client Secret**
6. Enter your **OAuth Refresh Token** (generated via OAuth playground or your app)

### Meta (Facebook/Instagram)

1. Set **Enabled** to Yes
2. Enter your **Pixel ID** (from Meta Events Manager → your pixel)
3. Enter your **Conversions API Access Token** (from Events Manager → Settings → Generate Access Token)

### TikTok

1. Set **Enabled** to Yes
2. Enter your **Pixel ID** (from TikTok Ads Manager → Assets → Events)
3. Enter your **Access Token** (generated from TikTok Business Center)

### Pinterest

1. Set **Enabled** to Yes
2. Enter your **Ad Account ID** (from Pinterest Business → Ads)
3. Enter your **Access Token** (generated from Pinterest Developer Portal)

### RealROAS Dashboard (Optional)

1. Set **Enabled** to Yes
2. Enter your **Store API Key** (from your RealROAS dashboard → Stores section)
3. **API URL** defaults to https://realroas.net/api/v1

---

## How It Works

1. When a visitor arrives on your store from an ad (with gclid, fbclid, ttclid, or epik parameter), the module captures the click ID and stores it in the customer session.

2. UTM parameters (utm_source, utm_medium, utm_campaign, utm_content, utm_term) are also captured automatically.

3. When the visitor places an order, all tracking data is saved on the order record in the database.

4. When the order status changes to "complete" or "closed" (i.e., payment confirmed), the module sends a Purchase event to each enabled platform via their server-side API.

5. Each event includes an event_id for deduplication with your existing client-side pixel.

---

## Important: Does Not Replace Your Current Tracking

This module adds a server-side signal IN ADDITION to your existing tracking. It does not modify, disable, or interfere with:

- Google Tag (gtag.js)
- Meta Pixel (fbevents.js)
- TikTok Pixel
- Pinterest Tag
- Google Tag Manager
- Any other tracking solution

The ad platforms handle deduplication automatically using the event ID.

---

## Data Sent to Platforms

For each completed order, the module sends:

- Order ID (for deduplication)
- Revenue amount and currency
- Customer email (SHA-256 hashed)
- Customer name, phone, address (SHA-256 hashed, for enhanced matching)
- Click ID (gclid/fbclid/ttclid/epik)
- Product data: SKU, quantity, unit price
- Event timestamp
- User agent and IP (for Meta/TikTok matching)

No personal data is sent in plain text. All PII is hashed before transmission.

---

## Conversion Timing

Conversions are sent only when the order reaches "complete" or "closed" status. This means:

- Orders paid by bank transfer are NOT counted until payment is confirmed
- Cancelled or refunded orders are never sent as conversions
- Only real, paid orders count

---

## Troubleshooting

### Conversions not being sent

1. Check that the platform is Enabled in configuration
2. Verify your API credentials are correct
3. Check `var/log/system.log` for entries starting with `[RealROAS]`
4. Ensure the order has reached "complete" or "closed" status

### Duplicate conversions

The module uses a flag per platform (google_conversion_sent, meta_conversion_sent, etc.) to prevent sending the same order twice. If you see duplicates, check that no other module is also sending server-side conversions.

---

## Support

- Documentation: https://realroas.net/en/modules/magento2
- Email: support@realroas.net
- Paid setup assistance: €99 one-time (includes installation, configuration, testing, and 30-day email support)

---

## License

Open Software License 3.0 (OSL-3.0)
