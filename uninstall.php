<?php
/**
 * Remove plugin options on uninstall.
 *
 * @package delivery-date-time-slot-picker-for-woocommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'delidaam_delivery_blackout_dates' );
delete_option( 'delidaam_delivery_slot_limit' );
delete_option( 'delidaam_delivery_time_slots' );
