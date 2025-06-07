<?php

add_filter('woocommerce_get_sections_checkout', function($sections) {
    $sections['delivery_settings'] = __('Delivery Settings', 'wc-delivery');
    return $sections;
});

add_filter('woocommerce_get_settings_checkout', function($settings, $current_section) {
    if ($current_section == 'delivery_settings') {
        $settings = [
            [
                'name' => __('Delivery Settings', 'wc-delivery'),
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_delivery_settings'
            ],
            [
                'name' => __('Blackout Dates (comma separated YYYY-MM-DD)', 'wc-delivery'),
                'type' => 'text',
                'id' => 'wc_delivery_blackout_dates'
            ],
            [
                'name' => __('Max Orders per Slot', 'wc-delivery'),
                'type' => 'number',
                'id' => 'wc_delivery_slot_limit',
                'default' => 5
            ],
            [
                'type' => 'sectionend',
                'id' => 'wc_delivery_settings'
            ]
        ];
    }

    return $settings;
}, 10, 2);
