<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Add section under WooCommerce > Settings > Shipping
add_filter(
	'woocommerce_get_sections_shipping',
	function ( $sections ) {
		$sections['delidaam_delivery_settings'] = __( 'Delivery Settings', 'delivery-date-time-slot-picker-for-woocommerce' );
		return $sections;
	}
);

// Add settings fields
add_filter(
	'woocommerce_get_settings_shipping',
	function ( $settings, $current_section ) {
		if ( 'delidaam_delivery_settings' === $current_section ) {
			$settings = array(
				array(
					'name' => __( 'Delivery Settings', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type' => 'title',
					'desc' => __( 'Configure delivery date and time slot options.', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'id'   => 'delidaam_delivery_settings',
				),
				array(
					'name' => __( 'Blackout Dates', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type' => 'text',
					'id'   => 'delidaam_delivery_blackout_dates',
					'desc' => __( 'Select blackout dates (you can select multiple).', 'delivery-date-time-slot-picker-for-woocommerce' ),
				),
				array(
					'name'     => __( 'Disabled Weekdays', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type'     => 'multiselect',
					'id'       => 'delidaam_delivery_disabled_weekdays',
					'class'    => 'wc-enhanced-select',
					'default'  => array(),
					'options'  => array(
						'1' => __( 'Monday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'2' => __( 'Tuesday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'3' => __( 'Wednesday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'4' => __( 'Thursday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'5' => __( 'Friday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'6' => __( 'Saturday', 'delivery-date-time-slot-picker-for-woocommerce' ),
						'7' => __( 'Sunday', 'delivery-date-time-slot-picker-for-woocommerce' ),
					),
					'desc'     => __( 'Days of the week on which delivery is not available. Leave empty to allow every day.', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'desc_tip' => true,
				),
				array(
					'name'              => __( 'Minimum Lead Time (days)', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type'              => 'number',
					'id'                => 'delidaam_delivery_min_days',
					'default'           => 0,
					'custom_attributes' => array(
						'min'  => 0,
						'step' => 1,
					),
					'desc'              => __( 'Earliest delivery date, counted in days from today. 0 allows same-day delivery, 2 means the earliest date is the day after tomorrow.', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'desc_tip'          => true,
				),
				array(
					'name'              => __( 'Maximum Booking Window (days)', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type'              => 'number',
					'id'                => 'delidaam_delivery_max_days',
					'default'           => 0,
					'custom_attributes' => array(
						'min'  => 0,
						'step' => 1,
					),
					'desc'              => __( 'How many days ahead customers can book, counted from today. Leave 0 for no limit.', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'desc_tip'          => true,
				),
				array(
					'name'    => __( 'Max Orders per Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type'    => 'number',
					'id'      => 'delidaam_delivery_slot_limit',
					'default' => 5,
					'desc'    => __( 'Limit the number of deliveries allowed per slot per day.', 'delivery-date-time-slot-picker-for-woocommerce' ),
				),
				array(
					'name' => __( 'Delivery Time Slots (one per line)', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type' => 'textarea',
					'id'   => 'delidaam_delivery_time_slots',
					'desc' => __( 'Enter one time slot per line. Example: 10:00 AM - 12:00 PM', 'delivery-date-time-slot-picker-for-woocommerce' ),
				),
				array(
					'name'    => __( 'Show in Emails', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'type'    => 'checkbox',
					'id'      => 'delidaam_delivery_show_in_emails',
					'default' => 'yes',
					'desc'    => __( 'Include the delivery date and time slot in WooCommerce order emails (customer and admin).', 'delivery-date-time-slot-picker-for-woocommerce' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'delidaam_delivery_settings',
				),
				array( 'type' => 'delidaam_pro_box', 'id' => 'delidaam_pro_box' ),
			);
		}

		return $settings;
	},
	10,
	2
);

// Sanitize blackout dates: keep only valid, unique Y-m-d dates.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_blackout_dates',
	function ( $value ) {
		$dates = array();

		foreach ( explode( ',', (string) $value ) as $date ) {
			$date   = trim( sanitize_text_field( $date ) );
			$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

			if ( $parsed && $parsed->format( 'Y-m-d' ) === $date ) {
				$dates[ $date ] = $date;
			}
		}

		ksort( $dates );

		return implode( ',', $dates );
	}
);

// Sanitize disabled weekdays: only 1-7 (7 = Sunday; "0" would be dropped by WooCommerce's multiselect save), unique. Disabling all 7 days is ignored so checkout never has zero dates.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_disabled_weekdays',
	function ( $value ) {
		$days = array();
		foreach ( (array) $value as $day ) {
			if ( is_numeric( $day ) && (int) $day >= 1 && (int) $day <= 7 ) {
				$days[ (int) $day ] = (string) (int) $day;
			}
		}

		if ( count( $days ) >= 7 ) {
			return array();
		}

		return array_values( $days );
	}
);

// Sanitize lead time: non-negative integer.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_min_days',
	function ( $value ) {
		return (string) absint( $value );
	}
);

// Sanitize booking window: non-negative integer, never smaller than the lead time.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_max_days',
	function ( $value ) {
		$max = absint( $value );
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before saving settings.
		$min = isset( $_POST['delidaam_delivery_min_days'] ) ? absint( wp_unslash( $_POST['delidaam_delivery_min_days'] ) ) : (int) get_option( 'delidaam_delivery_min_days', 0 );

		if ( $max > 0 && $max < $min ) {
			$max = $min;
		}

		return (string) $max;
	}
);

// Sanitize the per-slot limit: non-negative integer.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_slot_limit',
	function ( $value ) {
		return (string) absint( $value );
	}
);

// Sanitize time slots: plain text, one per line, no blanks or duplicates.
add_filter(
	'woocommerce_admin_settings_sanitize_option_delidaam_delivery_time_slots',
	function ( $value ) {
		$lines = array_map( 'sanitize_text_field', preg_split( "/\r\n|\n|\r/", (string) $value ) );

		return implode( "\n", array_unique( array_filter( $lines ) ) );
	}
);

/**
 * Non-intrusive "Go Pro" information box shown at the bottom of the settings page only.
 * No nag, no tracking, no external requests: it only renders static text and links.
 */
add_action(
	'woocommerce_admin_field_delidaam_pro_box',
	function () {
		$features = array(
			__( 'Same-day and next-day delivery, preparation time and daily cut-off time', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Different time slots for each weekday', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Maximum orders per day, plus "Fully booked" and "N left" indicators at checkout', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Delivery fees for specific time slots (for example an Express slot)', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Delivery Groups: separate rules and slots for products and categories', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Delivery Calendar with capacity meter and upcoming deliveries by day', 'delivery-date-time-slot-picker-for-woocommerce' ),
			__( 'Sortable Delivery Date column and rescheduling from the order screen', 'delivery-date-time-slot-picker-for-woocommerce' ),
		);
		?>
		<div class="delidaam-pro-box" style="max-width:760px;margin:24px 0;padding:16px 20px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Need more control? Try Delivery Date & Time Slot Picker Pro', 'delivery-date-time-slot-picker-for-woocommerce' ); ?></h2>
			<ul style="list-style:disc;margin-left:20px;">
				<?php foreach ( $features as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php esc_html_e( 'All your current settings are kept when you upgrade. A 30-day free trial is available, no payment required to start.', 'delivery-date-time-slot-picker-for-woocommerce' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( 'https://devorbittechnologies.com/delivery-date-time-slot-picker-pro/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more about Pro', 'delivery-date-time-slot-picker-for-woocommerce' ); ?></a>
				<a class="button" href="<?php echo esc_url( 'https://www.youtube.com/watch?v=1QMYyY6Pqq8' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Watch demo', 'delivery-date-time-slot-picker-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}
);
