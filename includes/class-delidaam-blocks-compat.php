<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks compatibility for Delivery Date & Time Slot.
 * - Registers Additional Checkout Fields for Checkout Block
 * - Mirrors values into legacy meta keys for admin/emails
 * - Adds validation/sanitization for Store API
 */
final class DELIDAAM_Blocks_Compat {

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'register_fields' ) );
		add_action( 'woocommerce_validate_additional_field', array( __CLASS__, 'validate' ), 10, 3 );
		add_action( 'woocommerce_blocks_validate_location_order_fields', array( __CLASS__, 'validate_order_fields' ), 10, 3 );
		add_filter( 'woocommerce_sanitize_additional_field', array( __CLASS__, 'sanitize' ), 10, 2 );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'mirror_to_legacy_meta' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'ensure_assets' ) );
	}

	/**
	 * Register checkout fields
	 */
	public static function register_fields() {
		$rows    = preg_split( "/\r\n|\n|\r/", (string) get_option( 'delidaam_delivery_time_slots', '' ) );
		$options = array();

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		foreach ( $rows as $line ) {
			$line = trim( $line );

			if ( '' !== $line ) {
				$options[] = array(
					'value' => $line,
					'label' => $line,
				);
			}
		}

		try {
			woocommerce_register_additional_checkout_field(
				array(
					'id'          => 'delidaam/delivery_date',
					'label'       => __( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'location'    => 'order',
					'type'        => 'text',
					'required'    => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_delivery_date' ),
					'validate_callback' => array( __CLASS__, 'validate_delivery_date_callback' ),
					'attributes'  => array(
						'autocomplete'  => 'off',
						'data-delidaam' => 'delivery_date',
						'placeholder'   => __( 'Select a delivery date', 'delivery-date-time-slot-picker-for-woocommerce' ),
					),
				)
			);

			woocommerce_register_additional_checkout_field(
				array(
					'id'          => 'delidaam/delivery_time_slot',
					'label'       => __( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'location'    => 'order',
					'type'        => 'select',
					'required'    => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_text_value' ),
					'validate_callback' => array( __CLASS__, 'validate_time_slot_callback' ),
					'placeholder' => __( 'Select a time slot', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'options'     => $options,
				)
			);
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				sprintf(
					'Delivery Date & Time Slot Picker: failed to register checkout fields. %s',
					esc_html( $exception->getMessage() )
				),
				E_USER_WARNING
			);
		}
	}

	/**
	 * Sanitize input
	 */
	public static function sanitize( $field_value, $field_key ) {
		if ( in_array( $field_key, array( 'delidaam/delivery_date', 'delidaam/delivery_time_slot' ), true ) ) {
			return self::sanitize_text_value( $field_value );
		}

		return $field_value;
	}

	/**
	 * Sanitize delivery date.
	 *
	 * @param mixed $field_value Field value.
	 * @return string
	 */
	public static function sanitize_delivery_date( $field_value ) {
		return self::sanitize_text_value( $field_value );
	}

	/**
	 * Sanitize plain text value.
	 *
	 * @param mixed $field_value Field value.
	 * @return string
	 */
	public static function sanitize_text_value( $field_value ) {
		if ( is_array( $field_value ) ) {
			$field_value = reset( $field_value );
		}

		return wc_clean( (string) $field_value );
	}

	/**
	 * Validate fields
	 */
	public static function validate( \WP_Error $errors, $field_key, $field_value ) {
		if ( 'delidaam/delivery_date' === $field_key ) {
			$error = self::validate_delivery_date_callback( $field_value );

			if ( is_wp_error( $error ) ) {
				foreach ( $error->get_error_messages() as $message ) {
					$errors->add( 'delidaam_invalid_delivery_date', $message );
				}
			}
		}

		if ( 'delidaam/delivery_time_slot' === $field_key ) {
			$error = self::validate_time_slot_callback( $field_value );

			if ( is_wp_error( $error ) ) {
				foreach ( $error->get_error_messages() as $message ) {
					$errors->add( 'delidaam_invalid_time_slot', $message );
				}
			}
		}
	}

	/**
	 * Validate delivery date callback.
	 *
	 * @param mixed $field_value Field value.
	 * @return void|\WP_Error
	 */
	public static function validate_delivery_date_callback( $field_value ) {
		$value = self::sanitize_delivery_date( $field_value );

		if ( '' === $value ) {
			return new \WP_Error(
				'delidaam_missing_date',
				__( 'Please select a delivery date.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}

		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error(
				'delidaam_invalid_date_format',
				__( 'Please enter a valid delivery date.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}

		if ( $value < current_time( 'Y-m-d' ) ) {
			return new \WP_Error(
				'delidaam_past_date',
				__( 'The selected delivery date has already passed. Please choose a different date.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}

		if ( in_array( $value, self::get_blackout_dates(), true ) ) {
			return new \WP_Error(
				'delidaam_blackout_date',
				__( 'The selected delivery date is not available.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}
	}

	/**
	 * Validate order-location fields together once both delivery date and time
	 * slot values are known, so the per-slot order limit can be enforced.
	 *
	 * @param \WP_Error $errors WP_Error object that extensions may add errors to.
	 * @param array     $fields Field key => value pairs for the "order" location.
	 * @param string    $group  Field group (shipping|billing|other).
	 * @return void
	 */
	public static function validate_order_fields( \WP_Error $errors, $fields, $group ) {
		$date = isset( $fields['delidaam/delivery_date'] ) ? self::sanitize_text_value( $fields['delidaam/delivery_date'] ) : '';
		$slot = isset( $fields['delidaam/delivery_time_slot'] ) ? self::sanitize_text_value( $fields['delidaam/delivery_time_slot'] ) : '';

		if ( '' === $date || '' === $slot ) {
			return;
		}

		if ( self::is_slot_full( $date, $slot ) ) {
			$errors->add(
				'delidaam_slot_full',
				__( 'The selected delivery time slot is fully booked for this date. Please choose another slot.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}
	}

	/**
	 * Whether a delivery date/time-slot combination has reached the configured order limit.
	 *
	 * No limit is enforced until a shop owner explicitly sets one greater than zero
	 * on the Delivery Settings screen.
	 *
	 * @param string $date Delivery date (Y-m-d).
	 * @param string $slot Delivery time slot.
	 * @return bool
	 */
	public static function is_slot_full( $date, $slot ) {
		$limit = (int) get_option( 'delidaam_delivery_slot_limit', 0 );

		if ( $limit <= 0 ) {
			return false;
		}

		return self::count_orders_for_slot( $date, $slot ) >= $limit;
	}

	/**
	 * Count existing (non-cancelled/failed/trashed) orders for a delivery date/time-slot combination.
	 * Works with both legacy post-meta storage and HPOS.
	 *
	 * @param string $date Delivery date (Y-m-d).
	 * @param string $slot Delivery time slot.
	 * @return int
	 */
	private static function count_orders_for_slot( $date, $slot ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'DELIDAAM_Delivery_Date_Time_Picker' ) ) {
			return 0;
		}

		$excluded_statuses = array( 'wc-cancelled', 'wc-failed', 'wc-trash' );
		$statuses          = array_diff( array_keys( wc_get_order_statuses() ), $excluded_statuses );

		$order_ids = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				'status'     => array_values( $statuses ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => DELIDAAM_Delivery_Date_Time_Picker::DELIVERY_DATE_META_KEY,
						'value' => $date,
					),
					array(
						'key'   => DELIDAAM_Delivery_Date_Time_Picker::DELIVERY_TIME_SLOT_META_KEY,
						'value' => $slot,
					),
				),
			)
		);

		return is_array( $order_ids ) ? count( $order_ids ) : 0;
	}

	/**
	 * Validate time slot callback.
	 *
	 * @param mixed $field_value Field value.
	 * @return void|\WP_Error
	 */
	public static function validate_time_slot_callback( $field_value ) {
		$value = self::sanitize_text_value( $field_value );

		if ( '' === $value ) {
			return new \WP_Error(
				'delidaam_missing_time_slot',
				__( 'Please select a delivery time slot.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}
	}

	/**
	 * Mirror Block values into legacy meta keys for admin/emails
	 */
	public static function mirror_to_legacy_meta( $key, $value, $group, $wc_object ) {
		if ( ! method_exists( $wc_object, 'update_meta_data' ) ) {
			return;
		}

		if ( 'delidaam/delivery_date' === $key ) {
			$wc_object->update_meta_data( DELIDAAM_Delivery_Date_Time_Picker::DELIVERY_DATE_META_KEY, sanitize_text_field( (string) $value ) );
		} elseif ( 'delidaam/delivery_time_slot' === $key ) {
			$wc_object->update_meta_data( DELIDAAM_Delivery_Date_Time_Picker::DELIVERY_TIME_SLOT_META_KEY, sanitize_text_field( (string) $value ) );
		}
	}

	/**
	 * Enqueue scripts for Blocks checkout
	 */
	public static function ensure_assets() {
		if ( ! self::is_checkout_page() ) {
			return;
		}

		$style_path  = DELIDAAM_DELIVERY_PLUGIN_PATH . 'assets/css/delivery-checkout.css';
		$script_path = DELIDAAM_DELIVERY_PLUGIN_PATH . 'assets/js/delivery-datepicker.js';

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui-css', DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/jquery-ui.css', array(), '1.12.1' );
		wp_enqueue_style(
			'delidaam-delivery-checkout',
			DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/delivery-checkout.css',
			array(),
			file_exists( $style_path ) ? filemtime( $style_path ) : false
		);
		wp_enqueue_script(
			'delidaam-delivery-datepicker',
			DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/js/delivery-datepicker.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			file_exists( $script_path ) ? filemtime( $script_path ) : false,
			true
		);

		wp_localize_script(
			'delidaam-delivery-datepicker',
			'delidaamCheckout',
			array(
				'blackoutDates' => self::get_blackout_dates(),
				'dateFieldLabel' => __( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ),
			)
		);
	}

	/**
	 * Get blackout dates.
	 *
	 * @return array
	 */
	private static function get_blackout_dates() {
		$raw = (string) get_option( 'delidaam_delivery_blackout_dates', '' );

		return array_values(
			array_filter(
				array_map( 'trim', explode( ',', $raw ) )
			)
		);
	}

	/**
	 * Check whether the current page is a checkout page.
	 *
	 * @return bool
	 */
	private static function is_checkout_page() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( is_singular() ) {
			return has_block( 'woocommerce/checkout', get_the_ID() );
		}

		return false;
	}
}
