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

		if ( in_array( $value, self::get_blackout_dates(), true ) ) {
			return new \WP_Error(
				'delidaam_blackout_date',
				__( 'The selected delivery date is not available.', 'delivery-date-time-slot-picker-for-woocommerce' )
			);
		}
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

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui-css', DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/jquery-ui.css', array(), '1.12.1' );
		wp_enqueue_style(
			'delidaam-delivery-checkout',
			DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/delivery-checkout.css',
			array(),
			filemtime( DELIDAAM_DELIVERY_PLUGIN_PATH . 'assets/css/delivery-checkout.css' )
		);
		wp_enqueue_script(
			'delidaam-delivery-datepicker',
			DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/js/delivery-datepicker.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			filemtime( DELIDAAM_DELIVERY_PLUGIN_PATH . 'assets/js/delivery-datepicker.js' ),
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
