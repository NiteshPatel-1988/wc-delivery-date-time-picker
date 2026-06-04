=== Delivery Date & Time Slot Picker for WooCommerce ===
Contributors: NitsPatel
Tags: woocommerce, delivery date, time slot, checkout, shipping
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers choose a delivery date and time slot at WooCommerce checkout. Perfect for grocery, bakery, or flower delivery services.

== Description ==
Adds a delivery date and time slot picker to WooCommerce checkout (Classic and Blocks) with blackout dates, custom slots, and slot limits.

== Features ==
* Compatible with **WooCommerce Blocks Checkout** and **Classic Checkout**
* Consistent field design on both checkout types — label always above the input
* Customers can select a **delivery date** via date picker with "Select a delivery date" placeholder
* Choose from **custom time slots** (configured in settings)
* Delivery Time Slot select styled to match other WooCommerce checkout fields
* Add **multiple blackout dates** via calendar
* Limit max orders per time slot
* Works with all WooCommerce shipping methods
* **HPOS compatible** - works with WooCommerce High Performance Order Storage
* Delivery details displayed on the Classic checkout thank-you page and order confirmation
* Settings available at **WooCommerce > Settings > Shipping > Delivery Settings**

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Configure delivery settings in **WooCommerce > Settings > Shipping**

== Frequently Asked Questions ==

= Does this plugin support WooCommerce Blocks Checkout? =
Yes. The plugin supports both Classic Checkout and WooCommerce Blocks Checkout. Both checkout types display the same consistent field design.

= Can I disable certain delivery dates? =
Yes, use the "Blackout Dates" calendar in plugin settings.

= How can I define time slots? =
Use the "Delivery Time Slots" field to define one time slot per line.

= Does it support WooCommerce shipping zones? =
Yes, it is fully compatible with shipping zones and shipping methods.

= Is this plugin compatible with WooCommerce HPOS? =
Yes. Version 1.3 uses `$order->get_meta()` and `$order->update_meta_data()` throughout, which are fully compatible with WooCommerce High Performance Order Storage (custom order tables).

== Screenshots ==
1. Delivery date and time slot fields on Classic & Block-based checkout
2. Admin settings for configuring blackout dates and slots

== Changelog ==

= 1.3 =
* Added consistent label-above-input design for delivery fields on both Classic and Block checkout
* Added "Select a delivery date" placeholder to the delivery date field on both Classic and Block checkout
* Delivery Time Slot select on Classic checkout now uses WooCommerce enhanced select (Select2), matching other checkout fields
* Fixed floating label inside Block checkout Delivery Time Slot select - label now always appears above the field
* Fixed delivery details not appearing on the Classic checkout thank-you (order-received) page
* Fixed HPOS (High Performance Order Storage) compatibility - all order meta read and write operations now use WooCommerce HPOS-safe methods (`$order->get_meta()`, `$order->update_meta_data()`)
* Added dedicated `delivery-checkout.css` for consistent field styling across Classic and Block checkout

= 1.2 =
* Added support for WooCommerce Blocks Checkout
* Improved compatibility with the latest WooCommerce version
* Minor bug fixes and performance improvements

= 1.0.1 =
* Fixed the issue of the slug in the datepicker functionality.

= 1.0.0 =
* Initial release
* Delivery date picker + custom time slot
* Admin settings for blackout dates and slot limit

== Upgrade Notice ==

= 1.3 =
Fixes delivery details not showing on the Classic checkout thank-you page and resolves HPOS compatibility issues. Unifies the field design across Classic and Block checkout. Recommended update for all users.

= 1.2 =
Added support for WooCommerce Blocks Checkout.
