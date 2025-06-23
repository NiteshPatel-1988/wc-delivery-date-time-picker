<?php
/**
 * Plugin Name: Delivery Date & Time Slot Picker for WooCommerce
 * Description: Allows customers to choose a delivery date and time slot during WooCommerce checkout.
 * Version: 1.0.0
 * Author: Nitesh Patel
 * Text Domain: wc-delivery
 * Domain Path: /languages
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
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