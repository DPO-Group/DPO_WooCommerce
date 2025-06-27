=== DPO Pay for WooCommerce ===
Contributors: appinlet
Tags: e-commerce, woocommerce, dpo, dpo pay, dpo group, app inlet, dpo pay by network
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official DPO Pay extension to receive payments for WooCommerce.

== Description ==

The DPO Pay plugin for WooCommerce lets you accept online payments, including credit and debit cards, digital wallets and other payment methods.

== Why Choose DPO Pay? ==

We provide a secure checkout experience for your shoppers with a wealth of payment methods to choose from, knowing that intelligent fraud protection engines monitor your transactions around the clock.

== FAQ's ==

= Does this require a DPO Pay merchant account? =

Yes! You need to sign up with DPO Pay to receive a Company Token key and other details for this gateway to function. You can do so at [dpogroup.com/get-started/](https://dpogroup.com/get-started/) or by emailing [sales1@dpogroup.com](mailto:sales1@dpogroup.com).

= Does this require an SSL certificate? =

We do recommend obtaining an SSL certificate to allow an additional layer of safety for your online shoppers.

= Where can I find API documentation? =

For help setting up and configuring the DPO Pay plugin, please refer to our [user guide](https://github.com/DPO-Group/DPO_WooCommerce).

= I need some assistance. Whom can I contact? =

Need help to configure this plugin? Feel free to connect with our DPO Pay Support Team by emailing us at [support@dpogroup.com](mailto:support@dpogroup.com) or give us a call at +254 (0) 709 947947.

== Screenshots ==
1. WooCommerce Admin Payments Screen
2. WooCommerce Admin DPO Pay Primary Settings
3. WooCommerce Admin DPO Pay Additional Settings
4. WooCommerce Admin Product Page Settings

== Changelog ==

= 1.3.0 2025-07-27 =
 * Added plugin support for the markup API to accurately represent fees on WooCommerce orders.
 * Resolved an issue with the cron debugging tool causing cancelled orders to not update.
 * Verified compatibility with WooCommerce 9.8.5 and WordPress 6.8.1.

 = 1.2.2 2025-05-05 =
 * Fixed an issue in the “Add Order Meta to Service” functionality, where entering an invalid Meta Key caused errors during checkout.
 * Updated the default placeholder examples on the “Add Order Meta to Service” setting.
 * Fixed issues causing the cron job to fail under certain conditions.

= 1.2.1 2025-01-16 =
 * Resolved an issue with the Service Type configuration that caused an “Unable to connect to payment gateway” error during checkout on some servers.

[See changelog for all versions](https://raw.githubusercontent.com/DPO-Group/DPO_WooCommerce/master/CHANGELOG.md).
