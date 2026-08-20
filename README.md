# RealROAS Server-Side Conversions for Magento 2

Free Magento 2 module that sends purchase conversions via server-side API to Google Ads, Meta (Facebook/Instagram), and TikTok.

## Key Features

- **Server-side conversion tracking** — Sends purchase data directly from your server to ad platforms
- **Works alongside existing tracking** — Does NOT replace your Google Tag, Meta Pixel, or TikTok Pixel. Events are deduplicated automatically
- **Bypasses ad blockers** — Server-side signals are never blocked by browser extensions
- **Enhanced matching** — Sends hashed customer data (email, phone, address) for better match rates
- **Cart data** — Includes product SKUs, quantities, and prices for advanced optimization
- **Optional RealROAS integration** — Feed data to RealROAS dashboard for true ROAS calculation

## Supported Platforms

- Google Ads (Data Manager API)
- Meta / Facebook / Instagram (Conversions API v20.0)
- TikTok (Events API v1.3)

## Requirements

- Magento 2.4.x or later
- PHP 8.1 or later

## Installation

```bash
composer require realroas/magento2-server-conversions
bin/magento module:enable RealROAS_ServerConversions
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

Go to **Stores → Configuration → RealROAS → Server-Side Conversions**

Each platform has its own section with Enable/Disable toggle and credential fields.

## How It Works

1. When a visitor arrives with a click ID (gclid, fbclid, ttclid) or UTM parameters, the module captures and stores them in the session
2. When the visitor places an order, the tracking data is saved on the order record
3. When the order status changes to `complete` or `closed`, the module sends a Purchase event to each enabled platform
4. Each platform deduplicates the server-side event with any client-side pixel event using the event ID

## Does NOT Impact Your Current Setup

This module adds an additional server-side signal. Your existing:
- Google Tag / gtag.js conversion tracking
- Meta Pixel (fbevents.js)
- TikTok Pixel

...all continue working exactly as before. The ad platforms handle deduplication automatically.

## Support

- Documentation: https://realroas.net/en/modules/magento2
- Email: support@realroas.net
- Setup assistance (paid): €99 one-time

## License

MIT
