=== DPO Group for WooCommerce ===
Contributors: appinlet
Tags: ecommerce, e-commerce, woocommerce, automattic, payment, dpo, dpo group, app inlet, credit card, payment request
Requires at least: 5.6
Tested up to: 5.9
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official DPO Group extension to receive payments for WooCommerce.

== Description ==

The DPO Group plugin for WooCommerce lets you accept online payments, including credit and debit cards, digital wallets and other payment methods.

== Why Choose DPO Group? ==

We provide a secure checkout experience for your shoppers with a wealth of payment methods to choose from, knowing that intelligent fraud protection engines monitor your transactions around the clock.

== FAQ's ==

= Does this require a DPO Group merchant account? =

Yes! You need to sign up with DPO Group to receive a Company Token key and other details for this gateway to function. You can do so at [dpogroup.com/get-started/](https://dpogroup.com/get-started/) or by emailing [sales1@dpogroup.com](mailto:sales1@dpogroup.com).

= Does this require an SSL certificate? =

We do recommend obtaining an SSL certificate to allow an additional layer of safety for your online shoppers.

= Where can I find API documentation? =

For help setting up and configuring the DPO Group plugin, please refer to our [user guide](https://github.com/DPO-Group/DPO_WooCommerce).

= I need some assistance. Whom can I contact? =

Need help to configure this plugin? Feel free to connect with our DPO Group Support Team by emailing us at [support@dpogroup.com](mailto:support@dpogroup.com) or give us a call at +254 (0) 709 947947.

== Screenshots ==
1. WooComemrce Admin Payments Screen
2. WooComemrce Admin DPO Group Primary Settings
3. WooComemrce Admin DPO Group Additional Settings
4. WooComemrce Admin Product Page Settings

== Changelog ==
= 1.1.1 - 2022-02-02 =
 * Tested on WooCommerce 6.0 and Wordpress 5.9.
 * Move plugin to WordPress.org.
 * Add debug logging in the order side.
 * Add test for curl extension.
 * Add card icon selection feature.

= 1.1.0 - 2021-06-28 =
 * Tested on WooCommerce 5.4.1 and Wordpress 5.7.2.
 * Add logging in cron job.
 * Add DPO order reference to narrative.
 * Add DPO payment icons.
 * Add Push Payments handling.
 * Add cron checks for old and unpaid orders using WP cron scheduling.
 * Add new <TransactionSource> tag.
 * Code quality improve.
 * Add support for MWK.
 * Change GitHub url.

= 1.0.16 - 2020-06-11 =
  * Improve HTTP error display.
  * Test with WC 4.2 and WP 5.4.2.
  * Add backwards compatibility with previous url structures.

[See changelog for all versions](https://raw.githubusercontent.com/DPO-Group/DPO_WooCommerce/master/changelog.txt).
