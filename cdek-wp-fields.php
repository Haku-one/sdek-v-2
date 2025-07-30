<?php
/**
 * СДЭК - Кастомные поля для заказов через стандартный WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация
 */
function cdek_wp_fields_init() {
    // Добавляем кастомные поля на страницу checkout (несколько хуков для совместимости)
    add_action('woocommerce_checkout_after_customer_details', 'cdek_add_checkout_fields');
    add_action('woocommerce_checkout_after_order_review', 'cdek_add_checkout_fields');
    add_action('wp_footer', 'cdek_add_checkout_fields_fallback');
    
    // Сохраняем поля при создании заказа
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_checkout_fields', 10);
    
    // Показываем поля в админке заказа
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_admin_fields', 10);
    
    // Добавляем поля в email
    add_filter('woocommerce_email_order_meta_fields', 'cdek_add_email_fields', 10, 3);
}
add_action('init', 'cdek_wp_fields_init');

/**
 * Добавляем скрытые поля на checkout
 */
function cdek_add_checkout_fields() {
    // Предотвращаем повторное добавление
    static $fields_added = false;
    if ($fields_added) return;
    $fields_added = true;
    
    ?>
    <div id="cdek_hidden_fields" style="display: none !important; visibility: hidden;">
        <input type="hidden" id="cdek_point_name" name="cdek_point_name" value="">
        <input type="hidden" id="cdek_point_address" name="cdek_point_address" value="">
        <input type="hidden" id="cdek_point_cost" name="cdek_point_cost" value="">
        <input type="hidden" id="cdek_point_code" name="cdek_point_code" value="">
    </div>
    <?php
}

/**
 * Fallback для блочного checkout
 */
function cdek_add_checkout_fields_fallback() {
    if (!is_checkout()) return;
    
    ?>
    <!-- Fallback для блочного checkout -->
    <div id="cdek_hidden_fields_fallback" style="display: none !important; visibility: hidden;">
        <input type="hidden" id="cdek_point_name_fb" name="cdek_point_name" value="">
        <input type="hidden" id="cdek_point_address_fb" name="cdek_point_address" value="">
        <input type="hidden" id="cdek_point_cost_fb" name="cdek_point_cost" value="">
        <input type="hidden" id="cdek_point_code_fb" name="cdek_point_code" value="">
    </div>
    
    <script>
    jQuery(function($) {
        // Добавляем поля принудительно если их нет
        setTimeout(function() {
            if ($('#cdek_point_name').length === 0) {
                console.log('🔧 СДЭК: Добавляем поля принудительно для блочного checkout');
                var form = $('form').first();
                if (form.length === 0) form = $('body');
                
                form.append('<input type="hidden" id="cdek_point_name" name="cdek_point_name" value="">');
                form.append('<input type="hidden" id="cdek_point_address" name="cdek_point_address" value="">');
                form.append('<input type="hidden" id="cdek_point_cost" name="cdek_point_cost" value="">');
                form.append('<input type="hidden" id="cdek_point_code" name="cdek_point_code" value="">');
            }
        }, 1000);
        function updateCdekFields() {
            // Ищем блок с информацией о доставке
            var shippingItems = $('.wc-block-components-totals-item');
            
            shippingItems.each(function() {
                var $item = $(this);
                var label = $item.find('.wc-block-components-totals-item__label').text().trim();
                var value = $item.find('.wc-block-components-totals-item__value').text().trim();
                var description = $item.find('.wc-block-components-totals-item__description small').text().trim();
                
                // Проверяем что это доставка с реальным адресом
                if (label && label !== 'Выберите пункт выдачи' && 
                    (label.includes('ул.') || label.includes('пр-т') || label.includes('пр.') || 
                     label.includes('пер.') || (label.includes(',') && label.length > 15))) {
                    
                    var cost = value.replace(/[^\d]/g, '');
                    
                    // Обновляем поля (ищем любые варианты)
                    var nameField = $('#cdek_point_name, #cdek_point_name_fb, input[name="cdek_point_name"]').first();
                    var addressField = $('#cdek_point_address, #cdek_point_address_fb, input[name="cdek_point_address"]').first();
                    var costField = $('#cdek_point_cost, #cdek_point_cost_fb, input[name="cdek_point_cost"]').first();
                    var codeField = $('#cdek_point_code, #cdek_point_code_fb, input[name="cdek_point_code"]').first();
                    
                    if (nameField.length) nameField.val(label);
                    if (addressField.length) addressField.val(description || label);
                    if (costField.length) costField.val(cost);
                    if (codeField.length) codeField.val('AUTO_' + Math.random().toString(36).substr(2, 8));
                    
                    console.log('✅ СДЭК поля обновлены:');
                    console.log('📍 ' + label);
                    console.log('💰 ' + cost + ' руб.');
                    console.log('📮 ' + (description || label));
                    console.log('🔧 Найдено полей: name=' + nameField.length + ', address=' + addressField.length + ', cost=' + costField.length);
                    
                    return false;
                }
            });
        }
        
        // Запускаем проверку
        setInterval(updateCdekFields, 2000);
        $(document.body).on('updated_checkout updated_shipping_method', function() {
            setTimeout(updateCdekFields, 1000);
        });
        
        // MutationObserver для отслеживания изменений DOM
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    var target = $(mutation.target);
                    if (target.closest('.wc-block-components-totals-item').length > 0) {
                        setTimeout(updateCdekFields, 500);
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
 * Сохраняем поля при создании заказа
 */
function cdek_save_checkout_fields($order_id) {
    $fields = array(
        'cdek_point_name',
        'cdek_point_address', 
        'cdek_point_cost',
        'cdek_point_code'
    );
    
    foreach ($fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            update_post_meta($order_id, $field, $value);
            error_log('СДЭК: Сохранено поле ' . $field . ' = ' . $value);
        }
    }
}

/**
 * Показываем поля в админке заказа
 */
function cdek_display_admin_fields($order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, 'cdek_point_name', true);
    $point_address = get_post_meta($order_id, 'cdek_point_address', true);
    $point_cost = get_post_meta($order_id, 'cdek_point_cost', true);
    $point_code = get_post_meta($order_id, 'cdek_point_code', true);
    
    if (!$point_name) return;
    
    ?>
    <div style="background: #f0f8ff; border: 1px solid #007cba; padding: 15px; margin: 15px 0; border-radius: 5px;">
        <h3 style="color: #007cba; margin-top: 0;">📦 Доставка СДЭК</h3>
        
        <table style="width: 100%;">
            <tr>
                <td style="padding: 5px 10px 5px 0;"><strong>Пункт выдачи:</strong></td>
                <td style="padding: 5px 0;"><?php echo esc_html($point_name); ?></td>
            </tr>
            
            <?php if ($point_cost): ?>
            <tr>
                <td style="padding: 5px 10px 5px 0;"><strong>Стоимость:</strong></td>
                <td style="padding: 5px 0; color: #007cba; font-weight: bold;"><?php echo esc_html($point_cost); ?> руб.</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($point_address): ?>
            <tr>
                <td style="padding: 5px 10px 5px 0;"><strong>Адрес:</strong></td>
                <td style="padding: 5px 0;"><?php echo esc_html($point_address); ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($point_code): ?>
            <tr>
                <td style="padding: 5px 10px 5px 0;"><strong>Код пункта:</strong></td>
                <td style="padding: 5px 0;"><code><?php echo esc_html($point_code); ?></code></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php
}

/**
 * Добавляем поля в email
 */
function cdek_add_email_fields($fields, $sent_to_admin, $order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, 'cdek_point_name', true);
    $point_address = get_post_meta($order_id, 'cdek_point_address', true);
    $point_cost = get_post_meta($order_id, 'cdek_point_cost', true);
    
    if ($point_name) {
        $value = $point_name;
        if ($point_cost) $value .= ' (' . $point_cost . ' руб.)';
        if ($point_address && $point_address !== $point_name) $value .= "\n" . $point_address;
        
        $fields['cdek_delivery'] = array(
            'label' => 'Доставка СДЭК',
            'value' => $value,
        );
    }
    
    return $fields;
}