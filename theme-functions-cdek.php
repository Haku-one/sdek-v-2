<?php
/**
 * Theme Functions for CDEK Delivery
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom checkout fields for CDEK delivery
 */
function cdek_add_checkout_fields($checkout) {
    echo '<div id="cdek_custom_checkout_field">';
    
    woocommerce_form_field('cdek_point_code', array(
        'type'          => 'hidden',
        'class'         => array('cdek-point-code'),
        'label'         => __('Код пункта выдачи СДЭК', 'cdek-delivery'),
        'required'      => false,
    ), $checkout->get_value('cdek_point_code'));
    
    woocommerce_form_field('cdek_point_address', array(
        'type'          => 'hidden',
        'class'         => array('cdek-point-address'),
        'label'         => __('Адрес пункта выдачи СДЭК', 'cdek-delivery'),
        'required'      => false,
    ), $checkout->get_value('cdek_point_address'));
    
    woocommerce_form_field('cdek_delivery_cost', array(
        'type'          => 'hidden',
        'class'         => array('cdek-delivery-cost'),
        'label'         => __('Стоимость доставки СДЭК', 'cdek-delivery'),
        'required'      => false,
    ), $checkout->get_value('cdek_delivery_cost'));
    
    echo '</div>';
}
add_action('woocommerce_checkout_before_order_review', 'cdek_add_checkout_fields');

/**
 * Save CDEK delivery data to order
 */
function cdek_save_checkout_fields($order_id) {
    if (!empty($_POST['cdek_point_code'])) {
        update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_point_code']));
    }
    if (!empty($_POST['cdek_point_address'])) {
        update_post_meta($order_id, '_cdek_point_address', sanitize_text_field($_POST['cdek_point_address']));
    }
    if (!empty($_POST['cdek_delivery_cost'])) {
        update_post_meta($order_id, '_cdek_delivery_cost', sanitize_text_field($_POST['cdek_delivery_cost']));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'cdek_save_checkout_fields');

/**
 * Display CDEK delivery info in order admin
 */
function cdek_display_order_data_in_admin($order) {
    $cdek_point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
    $cdek_point_address = get_post_meta($order->get_id(), '_cdek_point_address', true);
    $cdek_delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
    
    if ($cdek_point_code || $cdek_point_address) {
        echo '<h3>' . __('Информация о доставке СДЭК', 'cdek-delivery') . '</h3>';
        
        if ($cdek_point_code) {
            echo '<p><strong>' . __('Код пункта выдачи:', 'cdek-delivery') . '</strong> ' . esc_html($cdek_point_code) . '</p>';
        }
        
        if ($cdek_point_address) {
            echo '<p><strong>' . __('Адрес пункта выдачи:', 'cdek-delivery') . '</strong> ' . esc_html($cdek_point_address) . '</p>';
        }
        
        if ($cdek_delivery_cost) {
            echo '<p><strong>' . __('Стоимость доставки:', 'cdek-delivery') . '</strong> ' . esc_html($cdek_delivery_cost) . '</p>';
        }
    }
}
add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_order_data_in_admin');

/**
 * Add CDEK delivery info to order emails
 */
function cdek_add_delivery_info_to_emails($order, $sent_to_admin, $plain_text, $email) {
    $cdek_point_address = get_post_meta($order->get_id(), '_cdek_point_address', true);
    
    if ($cdek_point_address) {
        if ($plain_text) {
            echo "\n" . __('Пункт выдачи СДЭК:', 'cdek-delivery') . ' ' . $cdek_point_address . "\n";
        } else {
            echo '<h2>' . __('Информация о доставке', 'cdek-delivery') . '</h2>';
            echo '<p><strong>' . __('Пункт выдачи СДЭК:', 'cdek-delivery') . '</strong> ' . esc_html($cdek_point_address) . '</p>';
        }
    }
}
add_action('woocommerce_email_order_details', 'cdek_add_delivery_info_to_emails', 20, 4);