=== Delivery Date & Time Slot Picker for WooCommerce ===
Contributors: niteshpatel
Tags: delivery, checkout, woocommerce, date picker, time slot, delivery date, delivery time, shipping
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let your customers choose a delivery date and time slot during WooCommerce checkout. Ideal for local delivery businesses like grocery shops, bakeries, or flower delivery services.

== Description ==
This plugin adds a delivery date and time slot picker to WooCommerce checkout. Customers can select a preferred delivery date and time slot before placing the order. Admins can configure blackout dates, add custom time slots, and limit the number of deliveries per slot — all from the WooCommerce settings.

== Features ==
* Customers can select a **delivery date** via a date picker during checkout
* Customers can select a **delivery time slot** from admin-defined options
* Admin can add **multiple blackout dates** via a multi-date picker (no typing required)
* Admin can limit the **number of orders per time slot**
* Settings available under **WooCommerce > Settings > Shipping > Delivery Settings**
* Uses native WooCommerce order meta to store and display selections
* Lightweight, simple, and effective — no bloated settings

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **WooCommerce > Settings > Shipping > Delivery Settings** to configure blackout dates and time slots

== Frequently Asked Questions ==
= Can I set holidays or off days? =  
Yes. Use the **Blackout Dates** picker to block any delivery days (supports multiple dates).

= Can I set different time slots like "10 AM – 12 PM", "2 PM – 4 PM"? =  
Yes. Add one time slot per line in the admin field. They will appear on checkout.

= Will it work with all shipping methods? =  
Yes. It’s compatible with standard WooCommerce shipping logic.

== Screenshots ==
1. Delivery date and time picker on checkout
2. Delivery settings in WooCommerce admin panel

== Changelog ==
= 1.0.0 =
* Initial stable release
* Added delivery date & time slot picker
* Admin settings for blackout dates and slot limits
* Enhanced with multi-date picker for holidays

== Upgrade Notice ==
= 1.0.0 =
This is the first stable release. Compatible with WooCommerce 7+ and WP 6.5+.
