<?php

class WC_Delivery_Date_Time_Picker {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('woocommerce_after_order_notes', [$this, 'add_delivery_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_delivery_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_delivery_fields']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_delivery_fields_admin'], 10, 1);

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Load settings page
        if (is_admin()) {
            include_once WC_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-settings.php';
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('wc-delivery-datepicker', plugin_dir_url(__FILE__) . '../assets/js/delivery-datepicker.js', ['jquery', 'jquery-ui-datepicker'], null, true);
        wp_localize_script('wc-delivery-datepicker', 'delivery_blackout_dates', explode(',', get_option('wc_delivery_blackout_dates', '')));
        wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }


    public function add_delivery_fields($checkout) {
        echo '<div id="wc_delivery_date_time"><h3>' . __('Delivery Details') . '</h3>';

        woocommerce_form_field('delivery_date', [
            'type' => 'text',
            'class' => ['form-row-wide'],
            'label' => __('Delivery Date'),
            'required' => true,
        ], $checkout->get_value('delivery_date'));

        woocommerce_form_field('delivery_time_slot', [
            'type' => 'select',
            'class' => ['form-row-wide'],
            'label' => __('Delivery Time Slot'),
            'required' => true,
            'options' => $this->get_time_slots(),
        ], $checkout->get_value('delivery_time_slot'));

        echo '</div>';
    }

    public function validate_delivery_fields() {
        if (empty($_POST['delivery_date'])) {
            wc_add_notice(__('Please select a delivery date.'), 'error');
        }
        if (empty($_POST['delivery_time_slot'])) {
            wc_add_notice(__('Please select a delivery time slot.'), 'error');
        }
    }

    public function save_delivery_fields($order_id) {
        if (!empty($_POST['delivery_date'])) {
            update_post_meta($order_id, 'Delivery Date', sanitize_text_field($_POST['delivery_date']));
        }
        if (!empty($_POST['delivery_time_slot'])) {
            update_post_meta($order_id, 'Delivery Time Slot', sanitize_text_field($_POST['delivery_time_slot']));
        }
    }

    public function display_delivery_fields_admin($order) {
        echo '<p><strong>' . __('Delivery Date') . ':</strong> ' . get_post_meta($order->get_id(), 'Delivery Date', true) . '</p>';
        echo '<p><strong>' . __('Delivery Time Slot') . ':</strong> ' . get_post_meta($order->get_id(), 'Delivery Time Slot', true) . '</p>';
    }

    private function get_time_slots() {
        return [
            '' => __('Select a time slot'),
            '9:00 AM - 12:00 PM' => '9:00 AM - 12:00 PM',
            '12:00 PM - 3:00 PM' => '12:00 PM - 3:00 PM',
            '3:00 PM - 6:00 PM' => '3:00 PM - 6:00 PM',
            '6:00 PM - 9:00 PM' => '6:00 PM - 9:00 PM',
        ];
    }
}
