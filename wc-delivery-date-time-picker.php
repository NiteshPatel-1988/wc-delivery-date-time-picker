<?php
/*
Plugin Name: WC Delivery Date & Time Picker
Description: Allows customers to select a delivery date and time slot at checkout.
Version: 1.0
Author: Nitesh Patel
*/

if (!defined('ABSPATH')) exit;

define('WC_DELIVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
include_once WC_DELIVERY_PLUGIN_PATH . 'includes/class-delivery-date-time-picker.php';

// Init Plugin
add_action('plugins_loaded', ['WC_Delivery_Date_Time_Picker', 'get_instance']);
