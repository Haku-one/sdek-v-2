<?php
/**
 * СДЭК - Простое решение для захвата и отправки данных
 * Компактная версия без лишнего кода
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация
 */
function cdek_simple_init() {
    // Сохранение захваченных данных (приоритет 5 - раньше других функций)
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_data', 5);
    
    // Показ данных в email
    add_action('woocommerce_email_order_details', 'cdek_show_in_email', 25, 4);
    add_filter('woocommerce_email_order_meta_fields', 'cdek_add_to_email_meta', 10, 3);
    
    // JavaScript для захвата данных
    add_action('wp_footer', 'cdek_add_script');
    
    // Админка
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_show_in_admin', 20);
}
add_action('after_setup_theme', 'cdek_simple_init');

/**
 * JavaScript для захвата данных (15 строк)
 */
function cdek_add_script() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(function($) {
        function saveShippingData() {
            var text = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item__label').text().trim();
            var cost = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item__value').text().replace(/[^\d]/g, '');
            
            if (text && text !== 'Выберите пункт выдачи' && text.length > 10) {
                $('input[name="cdek_shipping_label"]').remove();
                $('input[name="cdek_shipping_cost"]').remove();
                $('input[name="cdek_shipping_captured"]').remove();
                
                $('body').append('<input type="hidden" name="cdek_shipping_label" value="' + text + '">');
                $('body').append('<input type="hidden" name="cdek_shipping_cost" value="' + cost + '">');
                $('body').append('<input type="hidden" name="cdek_shipping_captured" value="1">');
                console.log('СДЭК: ' + text + ' (' + cost + ' руб.)');
            }
        }
        
        setTimeout(saveShippingData, 2000);
        $(document.body).on('updated_checkout', saveShippingData);
    });
    </script>
    <?php
}

/**
 * Сохранение захваченных данных (10 строк)
 */
function cdek_save_data($order_id) {
    if (isset($_POST['cdek_shipping_captured']) && $_POST['cdek_shipping_captured'] === '1') {
        $label = sanitize_text_field($_POST['cdek_shipping_label']);
        $cost = sanitize_text_field($_POST['cdek_shipping_cost']);
        
        update_post_meta($order_id, '_cdek_shipping_label', $label);
        update_post_meta($order_id, '_cdek_shipping_cost', $cost);
        update_post_meta($order_id, '_cdek_shipping_captured', '1');
        
        // Создаем структурированные данные
        cdek_create_structured_data($order_id, $label, $cost);
        
        error_log('СДЭК: Сохранено - ' . $label . ' (' . $cost . ' руб.)');
    }
}

/**
 * Создание структурированных данных СДЭК
 */
function cdek_create_structured_data($order_id, $address, $cost) {
    $point_data = array(
        'name' => $address,
        'location' => array(
            'city' => 'Саратов',
            'address' => $address,
            'address_full' => $address
        )
    );
    
    $point_code = 'CAPTURED_' . substr(md5($address . time()), 0, 8);
    
    update_post_meta($order_id, '_cdek_point_code', $point_code);
    update_post_meta($order_id, '_cdek_point_data', $point_data);
    update_post_meta($order_id, '_cdek_delivery_cost', $cost);
    update_post_meta($order_id, '_cdek_point_display_name', $address);
}

/**
 * Показ в email через основную функцию
 */
function cdek_show_in_email($order, $sent_to_admin, $plain_text, $email) {
    $order_id = $order->get_id();
    $label = get_post_meta($order_id, '_cdek_shipping_label', true);
    $cost = get_post_meta($order_id, '_cdek_shipping_cost', true);
    
    if (!$label || $label === 'Выберите пункт выдачи') return;
    
    if ($plain_text) {
        echo "\n" . str_repeat('=', 40) . "\n";
        echo "ДОСТАВКА СДЭК\n";
        echo "Пункт выдачи: " . $label . "\n";
        if ($cost) echo "Стоимость: " . $cost . " руб.\n";
        echo str_repeat('=', 40) . "\n\n";
    } else {
        echo '<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 5px;">';
        echo '<h3 style="color: #28a745; margin-top: 0;">📦 Доставка СДЭК</h3>';
        echo '<p><strong>Пункт выдачи:</strong> ' . esc_html($label) . '</p>';
        if ($cost) echo '<p><strong>Стоимость:</strong> <span style="color: #28a745; font-weight: bold;">' . esc_html($cost) . ' руб.</span></p>';
        echo '</div>';
    }
}

/**
 * Резервный показ через мета-поля
 */
function cdek_add_to_email_meta($fields, $sent_to_admin, $order) {
    $order_id = $order->get_id();
    $label = get_post_meta($order_id, '_cdek_shipping_label', true);
    $cost = get_post_meta($order_id, '_cdek_shipping_cost', true);
    
    if ($label && $label !== 'Выберите пункт выдачи') {
        $fields['cdek_info'] = array(
            'label' => __('🚚 Доставка СДЭК'),
            'value' => $label . ($cost ? ' (' . $cost . ' руб.)' : ''),
        );
    }
    
    return $fields;
}

/**
 * Показ в админке заказа
 */
function cdek_show_in_admin($order) {
    $order_id = $order->get_id();
    $label = get_post_meta($order_id, '_cdek_shipping_label', true);
    $cost = get_post_meta($order_id, '_cdek_shipping_cost', true);
    
    if (!$label || $label === 'Выберите пункт выдачи') return;
    
    ?>
    <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; margin: 15px 0; border-radius: 5px;">
        <h3 style="color: #2e7d32; margin-top: 0;">📦 Информация о доставке СДЭК</h3>
        <p><strong>Пункт выдачи:</strong> <?php echo esc_html($label); ?></p>
        <?php if ($cost): ?>
        <p><strong>Стоимость:</strong> <span style="color: #2e7d32; font-weight: bold;"><?php echo esc_html($cost); ?> руб.</span></p>
        <?php endif; ?>
    </div>
    <?php
}