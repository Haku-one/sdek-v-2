<?php
/**
 * СДЭК - Принудительные поля для блочного checkout
 * Улучшенная версия с множественными стратегиями инъекции
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
        console.log('🚀 СДЭК: Улучшенная принудительная инициализация полей');
        
        // Стратегии поиска форм
        var formStrategies = [
            'form.wc-block-components-form',
            'form.woocommerce-checkout', 
            '.wc-block-checkout__form',
            'form[name="checkout"]',
            '.woocommerce-checkout',
            'form',
            'body'
        ];
        
        // Принудительно добавляем поля в форму
        function forceAddFields() {
            console.log('🔧 СДЭК: Попытка добавления полей...');
            
            // Удаляем старые поля если есть
            $('input[name*="cdek_point_"]').remove();
            
            var targetForm = null;
            var strategyUsed = '';
            
            // Пробуем разные стратегии поиска формы
            for (var i = 0; i < formStrategies.length; i++) {
                var strategy = formStrategies[i];
                var forms = $(strategy);
                if (forms.length > 0) {
                    targetForm = forms.first();
                    strategyUsed = strategy;
                    break;
                }
            }
            
            if (!targetForm || targetForm.length === 0) {
                console.log('❌ СДЭК: Не найдена подходящая форма!');
                return false;
            }
            
            // Создаем поля
            var fields = [
                '<input type="hidden" name="cdek_point_name" id="cdek_point_name" value="" data-cdek="field">',
                '<input type="hidden" name="cdek_point_address" id="cdek_point_address" value="" data-cdek="field">',
                '<input type="hidden" name="cdek_point_cost" id="cdek_point_cost" value="" data-cdek="field">',
                '<input type="hidden" name="cdek_point_code" id="cdek_point_code" value="" data-cdek="field">'
            ];
            
            // Добавляем поля
            for (var j = 0; j < fields.length; j++) {
                targetForm.append(fields[j]);
            }
            
            console.log('✅ СДЭК: Поля добавлены в', strategyUsed, '(тег:', targetForm.prop('tagName') + ')');
            
            // Проверяем что поля добавились
            setTimeout(function() {
                var addedFields = $('input[name*="cdek_point_"]').length;
                var fieldsByAttr = $('input[data-cdek="field"]').length;
                console.log('🔧 СДЭК: Проверка полей - по name:', addedFields, ', по атрибуту:', fieldsByAttr);
                
                if (addedFields === 0) {
                    console.log('⚠️ СДЭК: Поля не найдены после добавления, пробуем альтернативный способ...');
                    // Альтернативный способ - добавляем в body
                    $('body').append(fields.join(''));
                    console.log('🔄 СДЭК: Поля добавлены в body как fallback');
                }
            }, 200);
            
            return true;
        }
        
        // Функция обновления полей при выборе ПВЗ
        function updateCdekFields() {
            // Ищем доставочные блоки по разным селекторам
            var selectors = [
                '.wc-block-components-totals-item',
                '.woocommerce-shipping-totals tr',
                '[class*="shipping"]',
                '[class*="delivery"]'
            ];
            
            var found = false;
            
            for (var s = 0; s < selectors.length && !found; s++) {
                var shippingItems = $(selectors[s]);
                
                shippingItems.each(function() {
                    var $item = $(this);
                    var label = $item.find('.wc-block-components-totals-item__label, .shipping-method-label, .method-label').text().trim();
                    var value = $item.find('.wc-block-components-totals-item__value, .shipping-method-cost, .method-cost').text().trim();
                    var description = $item.find('.wc-block-components-totals-item__description small, .shipping-method-description, .method-description').text().trim();
                    
                    // Если не нашли в стандартных местах, ищем в любом тексте
                    if (!label) {
                        label = $item.text().trim();
                    }
                    
                    // Проверяем что это доставка с реальным адресом
                    if (label && label !== 'Выберите пункт выдачи' && label !== 'Select pickup point' &&
                        (label.includes('ул.') || label.includes('пр-т') || label.includes('пр.') || 
                         label.includes('пер.') || label.includes('улица') || label.includes('проспект') ||
                         (label.includes(',') && label.length > 15))) {
                        
                        var cost = value.replace(/[^\d]/g, '');
                        if (!cost && label.match(/\d+/)) {
                            cost = label.match(/\d+/)[0];
                        }
                        
                        // Обновляем поля (с fallback если поля пропали)
                        var nameField = $('input[name="cdek_point_name"]');
                        var addressField = $('input[name="cdek_point_address"]');
                        var costField = $('input[name="cdek_point_cost"]');
                        var codeField = $('input[name="cdek_point_code"]');
                        
                        // Если полей нет - добавляем заново
                        if (nameField.length === 0) {
                            console.log('⚠️ СДЭК: Поля пропали, добавляем заново');
                            if (forceAddFields()) {
                                nameField = $('input[name="cdek_point_name"]');
                                addressField = $('input[name="cdek_point_address"]');
                                costField = $('input[name="cdek_point_cost"]');
                                codeField = $('input[name="cdek_point_code"]');
                            }
                        }
                        
                        // Заполняем поля
                        if (nameField.length) nameField.val(label);
                        if (addressField.length) addressField.val(description || label);
                        if (costField.length) costField.val(cost);
                        if (codeField.length) codeField.val('AUTO_' + Math.random().toString(36).substr(2, 8));
                        
                        console.log('✅ СДЭК: Поля обновлены с селектором', selectors[s]);
                        console.log('📍 Название:', label);
                        console.log('💰 Стоимость:', cost);
                        console.log('📮 Адрес:', description || label);
                        console.log('🔧 Поля в DOM:', nameField.length, addressField.length, costField.length, codeField.length);
                        
                        found = true;
                        return false;
                    }
                });
            }
        }
        
        // Добавляем поля сразу и через таймеры для надежности
        forceAddFields();
        setTimeout(forceAddFields, 1000);
        setTimeout(forceAddFields, 3000);
        setTimeout(forceAddFields, 5000);
        
        // Запускаем обновление данных
        setTimeout(updateCdekFields, 2000);
        setInterval(updateCdekFields, 3000);
        
        // События WooCommerce
        $(document.body).on('updated_checkout updated_shipping_method wc_checkout_place_order', function() {
            setTimeout(function() {
                forceAddFields();
                updateCdekFields();
            }, 500);
        });
        
        // Отслеживаем изменения DOM более агрессивно
        var observer = new MutationObserver(function(mutations) {
            var shouldUpdate = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    var target = $(mutation.target);
                    if (target.closest('.wc-block-components-totals-item, .shipping, .delivery').length > 0 ||
                        target.find('.wc-block-components-totals-item, .shipping, .delivery').length > 0) {
                        shouldUpdate = true;
                    }
                }
            });
            if (shouldUpdate) {
                setTimeout(function() {
                    updateCdekFields();
                    // Проверяем что поля все еще на месте
                    if ($('input[name*="cdek_point_"]').length === 0) {
                        forceAddFields();
                    }
                }, 1000);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
        
        // Отслеживаем клики по кнопке отправки заказа
        $(document).on('click', '.wc-block-components-checkout-place-order-button, button[type="submit"], input[type="submit"]', function() {
            console.log('📤 СДЭК: Отправка заказа, финальная проверка полей');
            
            // Принудительно добавляем поля еще раз перед отправкой
            if ($('input[name*="cdek_point_"]').length === 0) {
                forceAddFields();
                updateCdekFields();
            }
            
            var fields = $('input[name*="cdek_point_"]');
            console.log('📊 СДЭК: Найдено полей перед отправкой:', fields.length);
            
            fields.each(function() {
                console.log('📝 СДЭК: Поле', this.name, '=', this.value);
            });
            
            // Последняя попытка для блочной формы
            setTimeout(function() {
                var finalFields = $('input[name*="cdek_point_"]');
                if (finalFields.length === 0) {
                    console.log('🆘 СДЭК: КРИТИЧНО! Поля пропали перед отправкой, экстренное восстановление...');
                    $('body').append('<input type="hidden" name="cdek_point_name" value="' + (localStorage.getItem('cdek_last_name') || '') + '">');
                    $('body').append('<input type="hidden" name="cdek_point_address" value="' + (localStorage.getItem('cdek_last_address') || '') + '">');
                    $('body').append('<input type="hidden" name="cdek_point_cost" value="' + (localStorage.getItem('cdek_last_cost') || '') + '">');
                    $('body').append('<input type="hidden" name="cdek_point_code" value="' + (localStorage.getItem('cdek_last_code') || '') + '">');
                }
            }, 100);
        });
        
        // Сохраняем данные в localStorage для экстренного восстановления
        setInterval(function() {
            var name = $('input[name="cdek_point_name"]').val();
            var address = $('input[name="cdek_point_address"]').val();
            var cost = $('input[name="cdek_point_cost"]').val();
            var code = $('input[name="cdek_point_code"]').val();
            
            if (name) localStorage.setItem('cdek_last_name', name);
            if (address) localStorage.setItem('cdek_last_address', address);
            if (cost) localStorage.setItem('cdek_last_cost', cost);
            if (code) localStorage.setItem('cdek_last_code', code);
        }, 5000);
        
        // Отладочная функция для консоли
        window.cdekDebug = function() {
            console.log('=== СДЭК DEBUG ===');
            console.log('Всего input полей:', $('input').length);
            console.log('СДЭК полей по name:', $('input[name*="cdek_point_"]').length);
            console.log('СДЭК полей по атрибуту:', $('input[data-cdek="field"]').length);
            console.log('Формы на странице:', $('form').length);
            $('form').each(function(i) {
                console.log('Форма', i, ':', this.className, this.id);
            });
            $('input[name*="cdek_point_"]').each(function(){ 
                console.log('Поле', this.name + ':', this.value); 
            });
            console.log('=================');
        };
        
        console.log('🎯 СДЭК: Инициализация завершена. Используйте cdekDebug() для отладки');
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