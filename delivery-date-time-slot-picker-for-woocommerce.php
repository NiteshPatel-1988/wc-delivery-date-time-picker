<?php
/**
 * Plugin Name: Delivery Date & Time Slot Picker for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/delivery-date-time-slot-picker-for-woocommerce
 * Description: Allows customers to select delivery date and time slot at WooCommerce checkout. Supports blackout dates and slot limits.
 * Version: 1.3
 * Author: NitsPatel
 * Author URI: https://github.com/NiteshPatel-1988
 * Requires Plugins: woocommerce
 * Text Domain: delivery-date-time-slot-picker-for-woocommerce
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Tested up to: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if WooCommerce is active
 */
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

    add_action( 'admin_notices', 'delidaam_plugin_woocommerce_required_notice' );

    function delidaam_plugin_woocommerce_required_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e( 'Delivery Date & Time Slot Picker Plugin requires WooCommerce to be installed and active.', 'delivery-date-time-slot-picker-for-woocommerce' ); ?></p>
        </div>
        <?php
    }

    // Stop plugin execution.
    return;
}

define( 'DELIDAAM_DELIVERY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DELIDAAM_DELIVERY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
require_once DELIDAAM_DELIVERY_PLUGIN_PATH . 'includes/class-delidaam-blocks-compat.php';
add_action( 'plugins_loaded', [ 'DELIDAAM_Blocks_Compat', 'init' ] );

function delidaam_initialize_plugin() {
    
    require_once DELIDAAM_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-date-time-picker.php';
    require_once DELIDAAM_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-settings.php';

    if (class_exists('DELIDAAM_Delivery_Date_Time_Picker')) {
        DELIDAAM_Delivery_Date_Time_Picker::get_instance();
        
    }
}
add_action('woocommerce_init', 'delidaam_initialize_plugin');

