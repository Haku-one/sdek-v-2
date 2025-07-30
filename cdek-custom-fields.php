<?php
/**
 * СДЭК - Кастомные скрытые поля для заказов
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация
 */
function cdek_custom_fields_init() {
    // Добавляем скрытые поля на страницу checkout
    add_action('woocommerce_checkout_after_customer_details', 'cdek_add_hidden_fields');
    
    // Сохраняем данные при создании заказа
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_custom_fields', 10);
    
    // Показываем в email
    add_filter('woocommerce_email_order_meta_fields', 'cdek_add_fields_to_email', 10, 3);
    
    // Показываем в админке заказа
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_show_fields_in_admin', 10);
}
add_action('init', 'cdek_custom_fields_init');

/**
 * Добавляем скрытые поля на страницу checkout
 */
function cdek_add_hidden_fields() {
    ?>
    <div style="display: none;">
        <input type="hidden" id="cdek_point_name" name="cdek_point_name" value="">
        <input type="hidden" id="cdek_point_address" name="cdek_point_address" value="">
        <input type="hidden" id="cdek_point_cost" name="cdek_point_cost" value="">
        <input type="hidden" id="cdek_point_code" name="cdek_point_code" value="">
        <input type="hidden" id="cdek_data_captured" name="cdek_data_captured" value="">
    </div>
    
    <script>
    jQuery(function($) {
        function checkAndUpdateCdekFields() {
            // Ищем блок с информацией о доставке
            var shippingBlock = $('.wc-block-components-totals-item');
            
            shippingBlock.each(function() {
                var label = $(this).find('.wc-block-components-totals-item__label').text().trim();
                var value = $(this).find('.wc-block-components-totals-item__value').text().trim();
                var description = $(this).find('.wc-block-components-totals-item__description small').text().trim();
                
                // Проверяем что это блок доставки с реальным адресом (не "Выберите пункт выдачи")
                if (label && label !== 'Выберите пункт выдачи' && 
                    (label.includes('ул.') || label.includes('пр-т') || label.includes('пер.') || 
                     label.includes(',') && label.length > 10)) {
                    
                    var cost = value.replace(/[^\d]/g, '');
                    
                    // Обновляем скрытые поля
                    document.getElementById('cdek_point_name').value = label;
                    document.getElementById('cdek_point_address').value = description || label;
                    document.getElementById('cdek_point_cost').value = cost;
                    document.getElementById('cdek_point_code').value = '';
                    document.getElementById('cdek_data_captured').value = '1';
                    
                    console.log('✅ СДЭК: Поля обновлены автоматически');
                    console.log('📍 Пункт: ' + label);
                    console.log('💰 Стоимость: ' + cost + ' руб.');
                    console.log('📮 Адрес: ' + (description || label));
                    
                    return false; // прерываем поиск
                }
            });
        }
        
        // Запускаем проверку через интервалы
        setInterval(checkAndUpdateCdekFields, 2000);
        
        // Также запускаем при событиях WooCommerce
        $(document.body).on('updated_checkout updated_shipping_method', function() {
            setTimeout(checkAndUpdateCdekFields, 1000);
        });
        
        // Отслеживаем изменения в DOM
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    var target = $(mutation.target);
                    if (target.closest('.wc-block-components-totals-item').length > 0) {
                        setTimeout(checkAndUpdateCdekFields, 500);
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    });
    </script>
    <?php
}

/**
 * Сохраняем кастомные поля при создании заказа
 */
function cdek_save_custom_fields($order_id) {
    $fields = array(
        'cdek_point_name' => 'СДЭК: Пункт выдачи',
        'cdek_point_address' => 'СДЭК: Адрес',
        'cdek_point_cost' => 'СДЭК: Стоимость',
        'cdek_point_code' => 'СДЭК: Код пункта',
        'cdek_data_captured' => 'СДЭК: Данные захвачены'
    );
    
    foreach ($fields as $field => $label) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            update_post_meta($order_id, '_' . $field, $value);
            error_log('СДЭК: Сохранено поле ' . $field . ' = ' . $value);
        }
    }
}

/**
 * Добавляем поля в email
 */
function cdek_add_fields_to_email($fields, $sent_to_admin, $order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, '_cdek_point_name', true);
    $point_address = get_post_meta($order_id, '_cdek_point_address', true);
    $point_cost = get_post_meta($order_id, '_cdek_point_cost', true);
    
    if ($point_name) {
        $value = $point_name;
        if ($point_cost) $value .= ' (' . $point_cost . ' руб.)';
        if ($point_address) $value .= "\n" . $point_address;
        
        $fields['cdek_delivery'] = array(
            'label' => 'Доставка СДЭК',
            'value' => $value,
        );
    }
    
    return $fields;
}

/**
 * Показываем поля в админке заказа
 */
function cdek_show_fields_in_admin($order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, '_cdek_point_name', true);
    $point_address = get_post_meta($order_id, '_cdek_point_address', true);
    $point_cost = get_post_meta($order_id, '_cdek_point_cost', true);
    $point_code = get_post_meta($order_id, '_cdek_point_code', true);
    
    if (!$point_name) return;
    
    ?>
    <div style="background: #f0f8ff; border: 1px solid #007cba; padding: 15px; margin: 15px 0; border-radius: 5px;">
        <h3 style="color: #007cba; margin-top: 0;">📦 Доставка СДЭК</h3>
        
        <p><strong>Пункт выдачи:</strong> <?php echo esc_html($point_name); ?></p>
        
        <?php if ($point_cost): ?>
        <p><strong>Стоимость:</strong> <?php echo esc_html($point_cost); ?> руб.</p>
        <?php endif; ?>
        
        <?php if ($point_address): ?>
        <p><strong>Адрес:</strong> <?php echo esc_html($point_address); ?></p>
        <?php endif; ?>
        
        <?php if ($point_code): ?>
        <p><strong>Код пункта:</strong> <code><?php echo esc_html($point_code); ?></code></p>
        <?php endif; ?>
    </div>
    <?php
}