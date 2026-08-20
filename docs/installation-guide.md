# RealROAS Server-Side Conversions for Magento 2
## Installation Guide v1.0.0

---

## Requirements

- Magento Open Source 2.4.x or Adobe Commerce 2.4.x
- PHP 8.1 or later
- Composer
- SSH access to your server

---

## Step 1: Install via Composer

Connect to your server via SSH and navigate to your Magento root directory:

    cd /path/to/magento

Run the Composer require command:

    composer require realroas/magento2-server-conversions

---

## Step 2: Enable the Module

    bin/magento module:enable RealROAS_ServerConversions

---

## Step 3: Run Setup Upgrade

This creates the required database columns on the sales_order table:

    bin/magento setup:upgrade

---

## Step 4: Clear Cache

    bin/magento cache:flush

---

## Step 5: Verify Installation

Check that the module is enabled:

    bin/magento module:status | grep RealROAS

Expected output:

    RealROAS_ServerConversions

---

## Step 6: Configure

1. Log in to Magento Admin
2. Navigate to **Stores → Configuration → RealROAS → Server-Side Conversions**
3. Enable the platforms you want to use (Google Ads, Meta, TikTok, Pinterest)
4. Enter the required API credentials for each platform
5. Save Configuration

---

## Uninstallation

To remove the module:

    bin/magento module:disable RealROAS_ServerConversions
    composer remove realroas/magento2-server-conversions
    bin/magento setup:upgrade
    bin/magento cache:flush

Note: The database columns added to sales_order will remain but will not cause any issues.

---

## Upgrading

To upgrade to a newer version:

    composer update realroas/magento2-server-conversions
    bin/magento setup:upgrade
    bin/magento cache:flush

---

## Support

- Documentation: https://realroas.net/en/modules/magento2
- Email: support@realroas.net
