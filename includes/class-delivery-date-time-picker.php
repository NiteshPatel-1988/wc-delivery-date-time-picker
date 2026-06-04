<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DELIDAAM_Delivery_Date_Time_Picker {
    private static $instance = null;
    private static $rendered_confirmation_orders = [];
    const DELIVERY_DATE_META_KEY = 'Delivery Date';
    const DELIVERY_TIME_SLOT_META_KEY = 'Delivery Time Slot';
    
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {

        add_action('woocommerce_after_order_notes', [$this, 'delidaam_add_delivery_fields']);
        add_action('woocommerce_after_order_notes', function () {
            wp_nonce_field('delidaam_delivery_nonce_action', 'delidaam_delivery_nonce_field');
        });
        add_action('woocommerce_checkout_process', [$this, 'delidaam_validate_delivery_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'delidaam_add_delivery_fields_to_order'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'delidaam_save_delivery_fields']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'delidaam_display_delivery_fields_admin'], 10, 1);
        // Classic checkout thank-you page (always fires, HPOS-safe).
        add_action( 'woocommerce_thankyou', [ $this, 'delidaam_display_delivery_thankyou' ], 10 );
        // My-account order-details page.
        add_action('woocommerce_order_details_after_customer_address', [$this, 'delidaam_display_delivery_fields_order_confirmation'], 15, 2);
        add_action('woocommerce_order_details_after_customer_details', [$this, 'delidaam_display_delivery_fields_order_confirmation_fallback'], 15, 1);

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'delidaam_enqueue_scripts'], 99);

        add_action('admin_enqueue_scripts', [$this, 'delidaam_delivery_admin_scripts']);
        
        // Load settings page
        if (is_admin()) {
            include_once DELIDAAM_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-settings.php';
        }
    }

    public function delidaam_enqueue_scripts() {
        if ( $this->delidaam_is_checkout_page() ) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/jquery-ui.css', [], '1.12.1');
            wp_enqueue_style(
                'delidaam-delivery-checkout',
                DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/delivery-checkout.css',
                [],
                filemtime(plugin_dir_path(__DIR__) . '/assets/css/delivery-checkout.css')
            );
            wp_enqueue_script(
                'delidaam-delivery-datepicker',
                DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/js/delivery-datepicker.js',
                ['jquery', 'jquery-ui-datepicker'],
                filemtime(plugin_dir_path(__DIR__) . '/assets/js/delivery-datepicker.js'),
                true
            );


            $blackout_dates = explode(',', get_option('delidaam_delivery_blackout_dates', ''));
            if (empty($blackout_dates[0])) {
                $blackout_dates = [];
            }
            wp_localize_script(
                'delidaam-delivery-datepicker',
                'delidaamCheckout',
                [
                    'blackoutDates' => array_values( array_map( 'trim', $blackout_dates ) ),
                    'dateFieldLabel' => __( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ),
                ]
            );

        }
    }

    private function delidaam_is_checkout_page() {
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return true;
        }

        if ( is_singular() ) {
            return has_block( 'woocommerce/checkout', get_the_ID() );
        }

        return false;
    }
  
    public function delidaam_delivery_admin_scripts($hook) {
        // Only load on WooCommerce settings pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab  = isset($_GET['tab']) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

        if ($page === 'wc-settings' && $tab === 'shipping') {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script(
                'delidaam-delivery-multidate',
                plugin_dir_url(__FILE__) . '../assets/js/admin-datepicker.js',
                ['jquery', 'jquery-ui-datepicker'],
                '1.0',
                true
            );
            wp_enqueue_style('jquery-ui-css', DELIDAAM_DELIVERY_PLUGIN_URL . 'assets/css/jquery-ui.css', [], '1.12.1');
        }
    }



    public function delidaam_add_delivery_fields($checkout) {

        echo '<div id="delidaam_delivery_date_time">';
        echo '<h3>' . esc_html__('Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce') . '</h3>';

        woocommerce_form_field( 'delidaam_delivery_date', [
            'type'              => 'text',
            'class'             => ['form-row-wide'],
            'label'             => __( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ),
            'required'          => true,
            'placeholder'       => __( 'Select a delivery date', 'delivery-date-time-slot-picker-for-woocommerce' ),
            'custom_attributes' => [
                'autocomplete'  => 'off',
                'data-delidaam' => 'delivery_date',
            ],
        ], $checkout->get_value( 'delidaam_delivery_date' ) );

        woocommerce_form_field( 'delidaam_delivery_time_slot', [
            'type'        => 'select',
            'class'       => ['form-row-wide'],
            'input_class' => ['wc-enhanced-select'],
            'label'       => __( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ),
            'required'    => true,
            'options'     => $this->delidaam_get_time_slots(),
        ], $checkout->get_value( 'delidaam_delivery_time_slot' ) );

        echo '</div>';
    }

    public function delidaam_validate_delivery_fields() {
        $nonce = isset($_POST['delidaam_delivery_nonce_field']) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_nonce_field'] ) ) : '';

        if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'delidaam_delivery_nonce_action' ) ) {
            wc_add_notice(__('Delivery details could not be verified. Please refresh and try again.', 'delivery-date-time-slot-picker-for-woocommerce'), 'error');
        }

        $delivery_date = isset($_POST['delidaam_delivery_date']) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_date'] ) ) : '';
        $delivery_time_slot = isset($_POST['delidaam_delivery_time_slot']) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_time_slot'] ) ) : '';
  
        if (empty($delivery_date)) {
            wc_add_notice(__('Please select a delivery date.', 'delivery-date-time-slot-picker-for-woocommerce'), 'error');
        }

        if (empty($delivery_time_slot)) {
            wc_add_notice(__('Please select a delivery time slot.', 'delivery-date-time-slot-picker-for-woocommerce'), 'error');
        }
    }

    public function delidaam_save_delivery_fields( $order_id ) {
        $nonce = isset( $_POST['delidaam_delivery_nonce_field'] ) ? sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_nonce_field'] ) ) : '';

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'delidaam_delivery_nonce_action' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $needs_save = false;

        if ( isset( $_POST['delidaam_delivery_date'] ) ) {
            $delivery_date = sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_date'] ) );
            if ( ! empty( $delivery_date ) ) {
                $order->update_meta_data( self::DELIVERY_DATE_META_KEY, $delivery_date );
                $needs_save = true;
            }
        }

        if ( isset( $_POST['delidaam_delivery_time_slot'] ) ) {
            $delivery_time_slot = sanitize_text_field( wp_unslash( $_POST['delidaam_delivery_time_slot'] ) );
            if ( ! empty( $delivery_time_slot ) ) {
                $order->update_meta_data( self::DELIVERY_TIME_SLOT_META_KEY, $delivery_time_slot );
                $needs_save = true;
            }
        }

        if ( $needs_save ) {
            $order->save();
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


    public function delidaam_display_delivery_fields_admin($order) {
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
     * Display delivery fields on the classic thank you / order details page.
     *
     * @param string   $address_type Address type.
     * @param WC_Order $order        Order object.
     * @return void
     */
    public function delidaam_display_delivery_fields_order_confirmation( $address_type, $order ) {
        if ( 'billing' !== $address_type ) {
            return;
        }

        if ( $this->delidaam_has_rendered_confirmation_fields( $order ) ) {
            return;
        }

        $delivery_date      = $order->get_meta( self::DELIVERY_DATE_META_KEY );
        $delivery_time_slot = $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

        if ( '' === $delivery_date && '' === $delivery_time_slot ) {
            return;
        }

        self::$rendered_confirmation_orders[ $order->get_id() ] = true;

        echo '<div class="woocommerce-order-delivery-details">';

        if ( '' !== $delivery_date ) {
            echo '<p><strong>' . esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_date ) . '</p>';
        }

        if ( '' !== $delivery_time_slot ) {
            echo '<p><strong>' . esc_html__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_time_slot ) . '</p>';
        }

        echo '</div>';
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

        $delivery_date      = $order->get_meta( self::DELIVERY_DATE_META_KEY );
        $delivery_time_slot = $order->get_meta( self::DELIVERY_TIME_SLOT_META_KEY );

        if ( '' === $delivery_date && '' === $delivery_time_slot ) {
            return;
        }

        self::$rendered_confirmation_orders[ $order->get_id() ] = true;

        echo '<div class="woocommerce-order-delivery-details">';

        if ( '' !== $delivery_date ) {
            echo '<p><strong>' . esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_date ) . '</p>';
        }

        if ( '' !== $delivery_time_slot ) {
            echo '<p><strong>' . esc_html__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) . ':</strong> ' . esc_html( $delivery_time_slot ) . '</p>';
        }

        echo '</div>';
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

        echo '<section class="woocommerce-order-delivery-details">';
        echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Delivery Details', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</h2>';
        echo '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">';
        echo '<tbody>';

        if ( '' !== $delivery_date ) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__( 'Delivery Date', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</th>';
            echo '<td>' . esc_html( $delivery_date ) . '</td>';
            echo '</tr>';
        }

        if ( '' !== $delivery_time_slot ) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__( 'Delivery Time Slot', 'delivery-date-time-slot-picker-for-woocommerce' ) . '</th>';
            echo '<td>' . esc_html( $delivery_time_slot ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</section>';
    }

    /**
     * Check whether WooCommerce Blocks already stores and renders the additional fields.
     *
     * @param WC_Order $order Order object.
     * @return bool
     */
    private function delidaam_order_uses_blocks_additional_fields( $order ) {
        if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) || ! class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' ) ) {
            return false;
        }

        try {
            $checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
                \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
            );

            $delivery_date = $checkout_fields->get_field_from_object( 'delidaam/delivery_date', $order, 'other' );
            $delivery_time_slot = $checkout_fields->get_field_from_object( 'delidaam/delivery_time_slot', $order, 'other' );

            return ( '' !== (string) $delivery_date || '' !== (string) $delivery_time_slot );
        } catch ( \Throwable $exception ) {
            return false;
        }
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
        $time_slots_raw = get_option('delidaam_delivery_time_slots', '');
        $lines = array_filter(array_map('trim', explode("\n", $time_slots_raw)));

        $slots = ['' => __('Select a time slot', 'delivery-date-time-slot-picker-for-woocommerce')];
        foreach ($lines as $line) {
            $slots[$line] = $line;
        }

        return $slots;
    }

}
