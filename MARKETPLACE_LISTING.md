# Adobe Commerce Marketplace Listing

## Extension Name
RealROAS Server-Side Conversions

## Short Description (max 250 chars)
Free server-side conversion tracking for Google Ads, Meta, TikTok & Pinterest. Sends purchase events via API. Works alongside your existing pixel — never replaces it. Improves match rate and bypasses ad blockers.

## Long Description

### Stop losing conversions to ad blockers

Ad blockers and browser privacy features block up to 30% of client-side conversion pixels. This free module solves the problem by sending purchase data directly from your server to ad platforms via their official APIs.

### Works alongside your current setup — changes nothing

This module does NOT replace your existing Google Tag, Meta Pixel, TikTok Pixel, or Pinterest Tag. It sends an additional server-side signal that platforms use to deduplicate and enrich your existing conversion data. Your current tracking stays exactly as it is.

### What it does

- Automatically captures click IDs (gclid, fbclid, ttclid, epik) and UTM parameters when visitors arrive
- Saves tracking data on each order
- When an order is completed, sends a Purchase event via server-side API to each enabled platform
- Includes enhanced matching data (hashed email, phone, address) for better match rates
- Includes cart data (SKUs, quantities, prices) for Shopping campaign optimization

### Supported platforms

- **Google Ads** — via Data Manager API with OAuth2 authentication
- **Meta (Facebook/Instagram)** — via Conversions API v20.0 with event deduplication
- **TikTok** — via Events API v1.3
- **Pinterest** — via Conversions API v5

### Key benefits

- 🔒 **Bypasses ad blockers** — Server-side signals are never blocked
- 📈 **Better match rates** — Enhanced matching with hashed customer data improves attribution
- 🛒 **Cart-level data** — Sends product SKUs, quantities, and prices for advanced optimization
- ⚡ **Zero performance impact** — API calls are made after order completion, never during checkout
- 🔄 **Automatic deduplication** — Uses event IDs so platforms don't double-count with your existing pixel
- 🆓 **100% free** — No subscription, no hidden fees, no limits

### Optional: RealROAS Dashboard Integration

Configure your RealROAS API key to automatically feed order and attribution data to the RealROAS dashboard for true ROAS calculation. Compare what platforms report vs your actual revenue.

RealROAS dashboard is a separate paid product. This module works perfectly standalone without it.

### Configuration

Simple admin configuration at Stores → Configuration → RealROAS → Server-Side Conversions. Enable each platform individually and enter your API credentials.

### Need help?

- Documentation: https://realroas.net/en/modules/magento2
- Email: support@realroas.net
- Paid setup assistance available (€99 one-time)

## Category
Marketing > Analytics & Tracking

## Compatibility
- Magento Open Source 2.4.x
- Adobe Commerce 2.4.x
- PHP 8.1+

## License
MIT (Free)

## Version
1.0.0

## Tags/Keywords
server-side tracking, conversions api, google ads, meta capi, facebook pixel, tiktok, pinterest, server conversions, capi, gclid, fbclid, enhanced conversions, ad blockers
