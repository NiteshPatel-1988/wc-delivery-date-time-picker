<?php
/**
 * Remove plugin options on uninstall.
 *
 * @package delivery-date-time-slot-picker-for-woocommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Options only the free plugin uses: always safe to remove.
delete_option( 'delidaam_delivery_disabled_weekdays' );
delete_option( 'delidaam_delivery_show_in_emails' );

// If the Pro plugin is installed (active or not), it shares the settings
// below, so leave them in place for it.
$delidaam_pro_installed = ! empty( glob( WP_PLUGIN_DIR . '/*/delivery-date-time-slot-picker-pro.php' ) );

if ( $delidaam_pro_installed ) {
	return;
}

delete_option( 'delidaam_delivery_blackout_dates' );
delete_option( 'delidaam_delivery_slot_limit' );
delete_option( 'delidaam_delivery_min_days' );
delete_option( 'delidaam_delivery_max_days' );
delete_option( 'delidaam_delivery_time_slots' );
