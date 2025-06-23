<?php

// Add section under WooCommerce > Settings > Shipping
add_filter('woocommerce_get_sections_shipping', function ($sections) {
    $sections['delivery_settings'] = __('Delivery Settings', 'wc-delivery');
    return $sections;
});

// Add settings fields
add_filter('woocommerce_get_settings_shipping', function ($settings, $current_section) {
    if ('delivery_settings' === $current_section) {
        $settings = [
            [
                'name' => __('Delivery Settings', 'wc-delivery'),
                'type' => 'title',
                'desc' => __('Configure delivery date and time slot options.', 'wc-delivery'),
                'id'   => 'wc_delivery_settings'
            ],
            [
                'name' => __('Blackout Dates', 'wc-delivery'),
                'type' => 'text',
                'id'   => 'wc_delivery_blackout_dates',
                'desc' => __('Select blackout dates (you can select multiple).', 'wc-delivery'),
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ]
            ],
            [
                'name' => __('Max Orders per Time Slot', 'wc-delivery'),
                'type' => 'number',
                'id'   => 'wc_delivery_slot_limit',
                'default' => 5,
                'desc' => __('Limit the number of deliveries allowed per slot per day.', 'wc-delivery')
            ],
            [
                'name' => __('Delivery Time Slots (one per line)', 'wc-delivery'),
                'type' => 'textarea',
                'id'   => 'wc_delivery_time_slots',
                'desc' => __('Enter one time slot per line. Example: 10:00 AM - 12:00 PM', 'wc-delivery')
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wc_delivery_settings'
            ]
        ];
    }

    return $settings;
}, 10, 2);