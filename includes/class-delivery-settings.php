<?php
    
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add section under WooCommerce > Settings > Shipping
add_filter('woocommerce_get_sections_shipping', function ($sections) {
    $sections['delidaam_delivery_settings'] = __('Delivery Settings', 'delivery-date-time-slot-picker-for-woocommerce');
    return $sections;
});

// Add settings fields
add_filter('woocommerce_get_settings_shipping', function ($settings, $current_section) {
    if ('delidaam_delivery_settings' === $current_section) {
        $settings = [
            [
                'name' => __('Delivery Settings', 'delivery-date-time-slot-picker-for-woocommerce'),
                'type' => 'title',
                'desc' => __('Configure delivery date and time slot options.', 'delivery-date-time-slot-picker-for-woocommerce'),
                'id'   => 'delidaam_delivery_settings'
            ],
            [
                'name' => __('Blackout Dates', 'delivery-date-time-slot-picker-for-woocommerce'),
                'type' => 'text',
                'id'   => 'delidaam_delivery_blackout_dates',
                'desc' => __('Select blackout dates (you can select multiple).', 'delivery-date-time-slot-picker-for-woocommerce')
            ],
            [
                'name' => __('Max Orders per Time Slot', 'delivery-date-time-slot-picker-for-woocommerce'),
                'type' => 'number',
                'id'   => 'delidaam_delivery_slot_limit',
                'default' => 5,
                'desc' => __('Limit the number of deliveries allowed per slot per day.', 'delivery-date-time-slot-picker-for-woocommerce')
            ],
            [
                'name' => __('Delivery Time Slots (one per line)', 'delivery-date-time-slot-picker-for-woocommerce'),
                'type' => 'textarea',
                'id'   => 'delidaam_delivery_time_slots',
                'desc' => __('Enter one time slot per line. Example: 10:00 AM - 12:00 PM', 'delivery-date-time-slot-picker-for-woocommerce')
            ],
            [
                'type' => 'sectionend',
                'id'   => 'delidaam_delivery_settings'
            ]
        ];
    }

    return $settings;
}, 10, 2);

// Sanitize blackout dates: keep only valid, unique Y-m-d dates.
add_filter('woocommerce_admin_settings_sanitize_option_delidaam_delivery_blackout_dates', function ($value) {
    $dates = [];

    foreach (explode(',', (string) $value) as $date) {
        $date = trim(sanitize_text_field($date));
        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if ($parsed && $parsed->format('Y-m-d') === $date) {
            $dates[$date] = $date;
        }
    }

    ksort($dates);

    return implode(',', $dates);
});

// Sanitize the per-slot limit: non-negative integer.
add_filter('woocommerce_admin_settings_sanitize_option_delidaam_delivery_slot_limit', function ($value) {
    return (string) absint($value);
});

// Sanitize time slots: plain text, one per line, no blanks or duplicates.
add_filter('woocommerce_admin_settings_sanitize_option_delidaam_delivery_time_slots', function ($value) {
    $lines = array_map('sanitize_text_field', preg_split("/\r\n|\n|\r/", (string) $value));

    return implode("\n", array_unique(array_filter($lines)));
});
