<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DELIDAAM_Delivery_Date_Time_Picker {
	private static $instance                     = null;
	private static $rendered_confirmation_orders = array();
	const DELIVERY_DATE_META_KEY                 = 'Delivery Date';
	const DELIVERY_TIME_SLOT_META_KEY            = 'Delivery Time Slot';


	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {

		add_action( 'woocommerce_after_order_notes', array( $this, 'delidaam_add_delivery_fields' ) );
		add_action(
			'woocommerce_after_order_notes',
			function () {
				wp_nonce_field( 'delidaam_delivery_nonce_action', 'delidaam_delivery_nonce_field' );
			}
		);
		add_action( 'woocommerce_checkout_process', array( $this, 'delidaam_validate_delivery_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'delidaam_add_delivery_fields_to_order' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'delidaam_display_delivery_fields_admin' ), 10, 1 );
		// Classic checkout thank-you page (always fires, HPOS-safe).
		add_action( 'woocommerce_thankyou', array( $this, 'delidaam_display_delivery_thankyou' ), 10 );
		// My-account order-details page.
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'delidaam_display_delivery_fields_order_confirmation_fallback' ), 15, 1 );

		// Order emails (customer and admin).
		add_action( 'woocommerce_email_after_order_table', array( $this, 'delidaam_display_delivery_fields_email' ), 10, 4 );
		// Block checkout orders: stop WooCommerce repeating our fields under "Additional information" in emails.
		add_filter( 'woocommerce_filter_fields_for_order_confirmation', array( $this, 'delidaam_hide_block_fields_in_email' ), 10, 4 );

		add_action( 'admin_enqueue_scripts', array( $this, 'delidaam_delivery_admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'delidaam_enqueue_frontend_styles' ) );
		// Blocks order confirmation: show "Delivery Details" instead of "Additional information".
		add_filter( 'gettext', array( $this, 'delidaam_filter_additional_info_heading' ), 10, 3 );
		add_filter( 'render_block', array( $this, 'delidaam_filter_additional_info_heading_block' ), 10, 2 );

		// Orders list column (HPOS and legacy post-based storage).
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'delidaam_add_orders_list_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'delidaam_render_orders_list_column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'delidaam_add_orders_list_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'delidaam_render_orders_list_column_legacy' ), 10, 2 );

		// Load settings page
		if ( is_admin() ) {
			include_once DELIDAAM_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-settings.php';
		}
	}

	/**
	 * Add a Delivery Date column after the order date column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function delidaam_add_orders_list_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_date' === $key ) {
				$new['delidaam_delivery_date'] = esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' );
			}
		}
		if ( ! isset( $new['delidaam_delivery_date'] ) ) {
			$new['delidaam_delivery_date'] = esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' );
		}
		return $new;
	}

	public function delidaam_render_orders_list_column_hpos( $column, $order ) {
		if ( 'delidaam_delivery_date' !== $column ) {
			return;
		}
		$this->delidaam_output_orders_list_value( $order );
	}

	public function delidaam_render_orders_list_column_legacy( $column, $post_id ) {
		if ( 'delidaam_delivery_date' !== $column ) {
			return;
		}
		$this->delidaam_output_orders_list_value( wc_get_order( $post_id ) );
	}

	private function delidaam_output_orders_list_value( $order ) {
		if ( ! $order instanceof WC_Order ) {
			echo '&ndash;';
			return;
		}
		$date = (string) $order->get_meta( self::DELIVERY_DATE_META_KEY );
		$slot = (string) $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );
		if ( '' === $date ) {
			echo '&ndash;';
			return;
		}
		echo esc_html( $date );
		if ( '' !== $slot ) {
			echo '<br><small>' . esc_html( $slot ) . '</small>';
		}
	}

	public function delidaam_delivery_admin_scripts() {
		// Only load on WooCommerce settings pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'wc-settings' === $page && 'shipping' === $tab ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script(
				'delidaam-delivery-multidate',
				DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/js/admin-datepicker.js',
				array( 'jquery', 'jquery-ui-datepicker' ),
				DELIDAAM_DELIVERY_VERSION,
				true
			);
			wp_localize_script(
				'delidaam-delivery-multidate',
				'delidaamAdminDatepicker',
				array(
					/* translators: %s: date being removed from the blackout dates list. */
					'removeLabel' => __( 'Remove %s', 'delivery-date-time-slot-picker-for-woocommerce' ),
					'placeholder' => __( 'Click to select blackout dates', 'delivery-date-time-slot-picker-for-woocommerce' ),
				)
			);
			wp_enqueue_style( 'delidaam-jquery-ui', DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/jquery-ui.css', array(), '1.12.1' );
			wp_enqueue_style(
				'delidaam-admin-datepicker',
				DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/admin-datepicker.css',
				array(),
				DELIDAAM_DELIVERY_VERSION
			);
		}
	}



	public function delidaam_add_delivery_fields( $checkout ) {

		echo '<div id="delidaam_delivery_date_time">';
		echo '<h3>' . esc_html__( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</h3>';

		woocommerce_form_field(
			'delidaam_delivery_date',
			array(
				'type'              => 'text',
				'class'             => array( 'form-row-wide' ),
				'label'             => __( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ),
				'required'          => true,
				'placeholder'       => __( 'Select a delivery date', 'delivery-date-time-slot-picker-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete'  => 'off',
					'data-delidaam' => 'delivery_date',
				),
			),
			$checkout->get_value( 'delidaam_delivery_date' )
		);

		woocommerce_form_field(
			'delidaam_delivery_time_slot',
			array(
				'type'        => 'select',
				'class'       => array( 'form-row-wide' ),
				'input_class' => array( 'wc-enhanced-select' ),
				'label'       => __( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ),
				'required'    => true,
				'options'     => $this->delidaam_get_time_slots(),
			),
			$checkout->get_value( 'delidaam_delivery_time_slot' )
		);

		echo '</div>';
	}

	public function delidaam_validate_delivery_fields() {
		$nonce = isset( $_POST['delidaam_delivery_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_nonce_field'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'delidaam_delivery_nonce_action' ) ) {
			wc_add_notice( __( 'Delivery details could not be verified. Please refresh and try again.', 'delivery-date-time-slot-picker-for-woocommerce' ), 'error' );
		}

		$delivery_date      = isset( $_POST['delidaam_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_date'] ) ) : '';
		$delivery_time_slot = isset( $_POST['delidaam_delivery_time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_time_slot'] ) ) : '';

		$date_valid = ! empty( $delivery_date );

		if ( ! $date_valid ) {
			wc_add_notice( __( 'Please select a delivery date.', 'delivery-date-time-slot-picker-for-woocommerce' ), 'error' );
		} elseif ( class_exists( 'DELIDAAM_Blocks_Compat' ) ) {
			$date_error = DELIDAAM_Blocks_Compat::validate_delivery_date_callback( $delivery_date );

			if ( is_wp_error( $date_error ) ) {
				$date_valid = false;

				foreach ( $date_error->get_error_messages() as $message ) {
					wc_add_notice( $message, 'error' );
				}
			}
		}

		if ( empty( $delivery_time_slot ) ) {
			wc_add_notice( __( 'Please select a delivery time slot.', 'delivery-date-time-slot-picker-for-woocommerce' ), 'error' );
		} elseif ( ! in_array( $delivery_time_slot, DELIDAAM_Blocks_Compat::get_time_slots(), true ) ) {
			wc_add_notice( __( 'Please select a valid delivery time slot.', 'delivery-date-time-slot-picker-for-woocommerce' ), 'error' );
		} elseif ( $date_valid && class_exists( 'DELIDAAM_Blocks_Compat' ) && DELIDAAM_Blocks_Compat::is_slot_full( $delivery_date, $delivery_time_slot ) ) {
			wc_add_notice( __( 'The selected delivery time slot is fully booked for this date. Please choose another slot.', 'delivery-date-time-slot-picker-for-woocommerce' ), 'error' );
		}
	}

	/**
	 * Save delivery fields to the order object during checkout creation.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Posted checkout data.
	 * @return void
	 */
	public function delidaam_add_delivery_fields_to_order( $order, $data ) {
		$nonce = isset( $_POST['delidaam_delivery_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_nonce_field'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'delidaam_delivery_nonce_action' ) ) {
			return;
		}

		if ( isset( $_POST['delidaam_delivery_date'] ) ) {
			$delivery_date = sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_date'] ) );

			if ( ! empty( $delivery_date ) ) {
				$order->update_meta_data( self::DELIVERY_DATE_META_KEY, $delivery_date );
			}
		}

		if ( isset( $_POST['delidaam_delivery_time_slot'] ) ) {
			$delivery_time_slot = sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_time_slot'] ) );

			if ( ! empty( $delivery_time_slot ) ) {
				$order->update_meta_data( self::DELIVERY_TIME_SLOT_META_KEY, $delivery_time_slot );
			}
		}
	}


	public function delidaam_display_delivery_fields_admin( $order ) {
		$delivery_date      = $order->get_meta( self::DELIVERY_DATE_META_KEY );
		$delivery_time_slot = $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

		if ( $this->delidaam_order_uses_blocks_additional_fields( $order ) ) {
			return;
		}

		if ( '' === $delivery_date && '' === $delivery_time_slot ) {
			return;
		}

		if ( '' !== $delivery_date ) {
			echo '<p><strong>' . esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_date ) . '</p>';
		}

		if ( '' !== $delivery_time_slot ) {
			echo '<p><strong>' . esc_html__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_time_slot ) . '</p>';
		}
	}

	/**
	 * Fallback display for the My Account order-details page.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function delidaam_display_delivery_fields_order_confirmation_fallback( $order ) {
		if ( $this->delidaam_has_rendered_confirmation_fields( $order ) ) {
			return;
		}

		// Blocks orders are rendered by WooCommerce itself (restyled via CSS); avoid a duplicate.
		if ( $this->delidaam_order_uses_blocks_additional_fields( $order ) ) {
			return;
		}

		$delivery_date      = $order->get_meta( self::DELIVERY_DATE_META_KEY );
		$delivery_time_slot = $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

		if ( '' === $delivery_date && '' === $delivery_time_slot ) {
			return;
		}

		self::$rendered_confirmation_orders[ $order->get_id() ] = true;

		$this->delidaam_render_delivery_table( $delivery_date, $delivery_time_slot );
	}

	/**
	 * Display delivery details on the classic checkout thank-you page.
	 *
	 * Uses the woocommerce_thankyou hook which is guaranteed to fire on the
	 * classic order-received page regardless of WooCommerce version or theme.
	 * Skipped for block checkout orders because WooCommerce Blocks already
	 * renders additional fields in its own "Additional information" section.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function delidaam_display_delivery_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( $this->delidaam_order_uses_blocks_additional_fields( $order ) ) {
			return;
		}

		$delivery_date      = $order->get_meta( self::DELIVERY_DATE_META_KEY );
		$delivery_time_slot = $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

		if ( '' === $delivery_date && '' === $delivery_time_slot ) {
			return;
		}

		$this->delidaam_render_delivery_table( $delivery_date, $delivery_time_slot );
	}

	/**
	 * Show the delivery details in WooCommerce order emails.
	 *
	 * Block checkout mirrors its values into the same order meta used by
	 * Classic checkout, so one lookup covers both.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether the email goes to the store admin.
	 * @param bool     $plain_text    Whether this is a plain-text email.
	 * @param WC_Email $email         Email object.
	 * @return void
	 */
	public function delidaam_display_delivery_fields_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( 'yes' !== get_option( 'delidaam_delivery_show_in_emails', 'yes' ) || ! $order instanceof WC_Order ) {
			return;
		}

		$delivery_date      = (string) $order->get_meta( self::DELIVERY_DATE_META_KEY );
		$delivery_time_slot = (string) $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

		// Fall back to the Block checkout additional-field meta if the mirror is missing.
		if ( '' === $delivery_date ) {
			$delivery_date = (string) $order->get_meta( '_wc_other/delidaam/delivery_date' );
		}
		if ( '' === $delivery_time_slot ) {
			$delivery_time_slot = (string) $order->get_meta( '_wc_other/delidaam/delivery_time_slot' );
		}

		if ( '' === $delivery_date && '' === $delivery_time_slot ) {
			return;
		}

		$rows = array(
			__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' )      => $delivery_date,
			__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) => $delivery_time_slot,
		);

		if ( $plain_text ) {
			echo "\n" . esc_html( strtoupper( __( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ) ) ) . "\n\n";
			foreach ( $rows as $label => $value ) {
				if ( '' !== $value ) {
					echo esc_html( $label ) . ': ' . esc_html( $value ) . "\n";
				}
			}
			echo "\n";
			return;
		}

		echo '<h2 style="margin:20px 0 10px;">' . esc_html__( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</h2>';
		echo '<table cellspacing="0" cellpadding="6" style="width:100%;margin-bottom:30px;border:1px solid #e5e5e5;" border="1"><tbody>';
		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			echo '<tr><th scope="row" style="text-align:left;width:40%;border:1px solid #e5e5e5;">' . esc_html( $label ) . '</th><td style="text-align:left;border:1px solid #e5e5e5;">' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Hide the delivery fields from WooCommerce's own "Additional information" email section.
	 *
	 * Our "Delivery Details" email table already shows them.
	 *
	 * @param bool  $show_field Whether the field should be shown.
	 * @param array $field      Field data.
	 * @param array $fields     All fields.
	 * @param array $context    Call context.
	 * @return bool
	 */
	public function delidaam_hide_block_fields_in_email( $show_field, $field = array(), $fields = array(), $context = array() ) {
		if (
			'yes' === get_option( 'delidaam_delivery_show_in_emails', 'yes' )
			&& isset( $context['caller'], $field['id'] )
			&& 'WC_Email::additional_checkout_fields' === $context['caller']
			&& in_array( $field['id'], array( 'delidaam/delivery_date', 'delidaam/delivery_time_slot' ), true )
		) {
			return false;
		}
		return $show_field;
	}

	/**
	 * Whether the order shown on the current order-received / view-order page has delivery data.
	 *
	 * @return bool
	 */
	private function delidaam_current_order_has_delivery_data() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return false;
		}
		$order_id = 0;
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$order_id = absint( get_query_var( 'order-received' ) );
		} elseif ( is_wc_endpoint_url( 'view-order' ) ) {
			$order_id = absint( get_query_var( 'view-order' ) );
		}
		if ( ! $order_id ) {
			return false;
		}
		$order = wc_get_order( $order_id );
		return $order && ( '' !== (string) $order->get_meta( self::DELIVERY_DATE_META_KEY ) || '' !== (string) $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY ) );
	}

	public function delidaam_filter_additional_info_heading( $translation, $text, $domain ) {
		if ( 'Additional information' === $text && 'woocommerce' === $domain && $this->delidaam_current_order_has_delivery_data() ) {
			return __( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' );
		}
		return $translation;
	}

	public function delidaam_filter_additional_info_heading_block( $block_content, $block ) {
		if ( isset( $block['blockName'] ) && 'core/heading' === $block['blockName'] && false !== strpos( $block_content, 'Additional information' ) && $this->delidaam_current_order_has_delivery_data() ) {
			return str_replace( 'Additional information', esc_html__( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ), $block_content );
		}
		return $block_content;
	}

	/**
	 * Output the delivery details as a styled table.
	 *
	 * @param string $delivery_date      Delivery date.
	 * @param string $delivery_time_slot Delivery time slot.
	 * @return void
	 */
	private function delidaam_render_delivery_table( $delivery_date, $delivery_time_slot ) {
		$rows = array(
			__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' )      => (string) $delivery_date,
			__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) => (string) $delivery_time_slot,
		);

		echo '<section class="woocommerce-order-delivery-details delidaam-delivery-details">';
		echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</h2>';
		echo '<table class="woocommerce-table shop_table delidaam-delivery-table"><tbody>';
		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</section>';
	}

	public function delidaam_enqueue_frontend_styles() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) ) {
			return;
		}
		wp_enqueue_style(
			'delidaam-order-details',
			DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/delivery-order-details.css',
			array(),
			DELIDAAM_DELIVERY_VERSION
		);
	}

	/**
	 * Check whether WooCommerce Blocks already stores and renders the additional fields.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	private function delidaam_order_uses_blocks_additional_fields( $order ) {
		// Block checkout stores "other" location fields as order meta prefixed with _wc_other/.
		return ( '' !== (string) $order->get_meta( '_wc_other/delidaam/delivery_date' ) || '' !== (string) $order->get_meta( '_wc_other/delidaam/delivery_time_slot' ) );
	}

	/**
	 * Check whether confirmation fields have already been rendered for this order.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	private function delidaam_has_rendered_confirmation_fields( $order ) {
		return isset( self::$rendered_confirmation_orders[ $order->get_id() ] );
	}

	private function delidaam_get_time_slots() {
		$slots = array( '' => __( 'Select a time slot', 'delivery-date-time-slot-picker-for-woocommerce' ) );
		foreach ( DELIDAAM_Blocks_Compat::get_time_slots() as $line ) {
			$slots[ $line ] = $line;
		}

		return $slots;
	}
}
