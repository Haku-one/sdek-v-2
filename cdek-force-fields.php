<?php
/**
 * СДЭК - Принудительные поля для блочного checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация
 */
function cdek_force_fields_init() {
    // Сохраняем поля при создании заказа
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_force_fields', 10);
    
    // Показываем поля в админке заказа
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_force_fields', 10);
    
    // Добавляем поля в email
    add_filter('woocommerce_email_order_meta_fields', 'cdek_add_force_email_fields', 10, 3);
    
    // Принудительно добавляем поля через JavaScript в footer
    add_action('wp_footer', 'cdek_force_add_fields_script');
}
add_action('init', 'cdek_force_fields_init');

/**
 * Принудительно добавляем поля и скрипт через JavaScript
 */
function cdek_force_add_fields_script() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(function($) {
        console.log('🚀 СДЭК: Принудительная инициализация полей');
        
        // Принудительно добавляем поля в форму
        function forceAddFields() {
            // Удаляем старые поля если есть
            $('input[name*="cdek_point_"]').remove();
            
            // Ищем форму checkout
            var form = $('form.wc-block-components-form, form.woocommerce-checkout, form').first();
            if (form.length === 0) {
                form = $('body');
            }
            
            // Добавляем поля принудительно
            form.append('<input type="hidden" name="cdek_point_name" id="cdek_point_name" value="">');
            form.append('<input type="hidden" name="cdek_point_address" id="cdek_point_address" value="">');
            form.append('<input type="hidden" name="cdek_point_cost" id="cdek_point_cost" value="">');
            form.append('<input type="hidden" name="cdek_point_code" id="cdek_point_code" value="">');
            
            console.log('✅ СДЭК: Поля добавлены принудительно в', form.prop('tagName'));
            
            // Проверяем что поля добавились
            setTimeout(function() {
                var addedFields = $('input[name*="cdek_point_"]').length;
                console.log('🔧 СДЭК: Добавлено полей:', addedFields);
            }, 100);
        }
        
        // Добавляем поля сразу и через таймеры
        forceAddFields();
        setTimeout(forceAddFields, 1000);
        setTimeout(forceAddFields, 3000);
        
        // Функция обновления полей при выборе ПВЗ
        function updateCdekFields() {
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
                    
                    // Обновляем поля (с fallback если поля пропали)
                    var nameField = $('input[name="cdek_point_name"]');
                    var addressField = $('input[name="cdek_point_address"]');
                    var costField = $('input[name="cdek_point_cost"]');
                    var codeField = $('input[name="cdek_point_code"]');
                    
                    // Если полей нет - добавляем заново
                    if (nameField.length === 0) {
                        console.log('⚠️ СДЭК: Поля пропали, добавляем заново');
                        forceAddFields();
                        nameField = $('input[name="cdek_point_name"]');
                        addressField = $('input[name="cdek_point_address"]');
                        costField = $('input[name="cdek_point_cost"]');
                        codeField = $('input[name="cdek_point_code"]');
                    }
                    
                    // Заполняем поля
                    nameField.val(label);
                    addressField.val(description || label);
                    costField.val(cost);
                    codeField.val('AUTO_' + Math.random().toString(36).substr(2, 8));
                    
                    console.log('✅ СДЭК: Поля обновлены принудительно');
                    console.log('📍 Название:', label);
                    console.log('💰 Стоимость:', cost);
                    console.log('📮 Адрес:', description || label);
                    console.log('🔧 Поля в DOM:', nameField.length, addressField.length, costField.length, codeField.length);
                    
                    return false;
                }
            });
        }
        
        // Запускаем обновление
        setInterval(updateCdekFields, 2000);
        $(document.body).on('updated_checkout updated_shipping_method', updateCdekFields);
        
        // Отслеживаем изменения DOM
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
        
        // Отслеживаем клики по кнопке "Размещение заказа"
        $(document).on('click', '.wc-block-components-checkout-place-order-button, button[type="submit"]', function() {
            console.log('📤 СДЭК: Отправка заказа, проверяем поля');
            var fields = $('input[name*="cdek_point_"]');
            fields.each(function() {
                if (this.value) {
                    console.log('📝 СДЭК: Поле', this.name, '=', this.value);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Сохраняем поля при создании заказа
 */
function cdek_save_force_fields($order_id) {
    error_log('СДЭК FORCE: Попытка сохранения полей для заказа #' . $order_id);
    error_log('СДЭК FORCE: POST данные: ' . print_r(array_keys($_POST), true));
    
    $fields = array(
        'cdek_point_name' => 'Название пункта',
        'cdek_point_address' => 'Адрес пункта', 
        'cdek_point_cost' => 'Стоимость',
        'cdek_point_code' => 'Код пункта'
    );
    
    $saved_any = false;
    foreach ($fields as $field => $label) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            update_post_meta($order_id, $field, $value);
            error_log('СДЭК FORCE: Сохранено поле ' . $field . ' = ' . $value);
            $saved_any = true;
        }
    }
    
    if ($saved_any) {
        error_log('СДЭК FORCE: Успешно сохранены поля СДЭК для заказа #' . $order_id);
    } else {
        error_log('СДЭК FORCE: Не найдено полей СДЭК в $_POST для заказа #' . $order_id);
    }
}

/**
 * Показываем поля в админке заказа
 */
function cdek_display_force_fields($order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, 'cdek_point_name', true);
    $point_address = get_post_meta($order_id, 'cdek_point_address', true);
    $point_cost = get_post_meta($order_id, 'cdek_point_cost', true);
    $point_code = get_post_meta($order_id, 'cdek_point_code', true);
    
    if (!$point_name) return;
    
    ?>
    <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; margin: 15px 0; border-radius: 5px;">
        <h3 style="color: #2e7d32; margin-top: 0;">📦 Доставка СДЭК (принудительно)</h3>
        
        <p><strong>Пункт выдачи:</strong> <?php echo esc_html($point_name); ?></p>
        
        <?php if ($point_cost): ?>
        <p><strong>Стоимость:</strong> <span style="color: #2e7d32; font-weight: bold;"><?php echo esc_html($point_cost); ?> руб.</span></p>
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

/**
 * Добавляем поля в email
 */
function cdek_add_force_email_fields($fields, $sent_to_admin, $order) {
    $order_id = $order->get_id();
    
    $point_name = get_post_meta($order_id, 'cdek_point_name', true);
    $point_address = get_post_meta($order_id, 'cdek_point_address', true);
    $point_cost = get_post_meta($order_id, 'cdek_point_cost', true);
    
    if ($point_name) {
        $value = $point_name;
        if ($point_cost) $value .= ' (' . $point_cost . ' руб.)';
        if ($point_address && $point_address !== $point_name) $value .= "\n" . $point_address;
        
        $fields['cdek_delivery_force'] = array(
            'label' => 'Доставка СДЭК',
            'value' => $value,
        );
    }
    
    return $fields;
}