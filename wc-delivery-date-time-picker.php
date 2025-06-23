<?php
/**
 * Plugin Name: Delivery Date & Time Slot Picker for WooCommerce
 * Plugin URI: 
 * Description: Allows customers to select delivery date and time slot at WooCommerce checkout. Supports blackout dates and slot limits.
 * Version: 1.0.0
 * Author: Nitesh Patel
 * Author URI: 
 * Text Domain: wc-delivery
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.5
 * Tested up to: 6.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_DELIVERY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_DELIVERY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load text domain for translations
function wc_delivery_load_textdomain() {
    load_plugin_textdomain( 'wc-delivery', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wc_delivery_load_textdomain' );

// Include core classes
require_once WC_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-date-time-picker.php';
require_once WC_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-settings.php';

// Init plugin
add_action( 'plugins_loaded', ['WC_Delivery_Date_Time_Picker', 'get_instance'] );