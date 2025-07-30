<?php
/**
 * СДЭК - Тестировщик полей
 * Этот файл поможет проверить работу инъекции полей
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация тестера
 */
function cdek_test_init() {
    // Добавляем тестовые функции только на checkout
    add_action('wp_footer', 'cdek_test_add_debug_panel');
    
    // Логирование сохранения
    add_action('woocommerce_checkout_update_order_meta', 'cdek_test_log_save', 5);
}
add_action('init', 'cdek_test_init');

/**
 * Добавляем панель отладки на checkout
 */
function cdek_test_add_debug_panel() {
    if (!is_checkout()) return;
    ?>
    <div id="cdek-debug-panel" style="
        position: fixed; 
        top: 10px; 
        right: 10px; 
        background: #fff; 
        border: 2px solid #007cba; 
        padding: 15px; 
        border-radius: 5px; 
        z-index: 9999; 
        max-width: 300px;
        font-size: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    ">
        <h4 style="margin: 0 0 10px 0; color: #007cba;">🧪 СДЭК Тестер</h4>
        <div id="cdek-status">Загрузка...</div>
        <button onclick="cdekRunTests()" style="margin-top: 10px; padding: 5px 10px;">🔄 Тест</button>
        <button onclick="cdekTogglePanel()" style="margin-top: 10px; padding: 5px 10px;">👁️ Скрыть</button>
    </div>
    
    <script>
    var cdekPanelVisible = true;
    
    function cdekTogglePanel() {
        var panel = document.getElementById('cdek-debug-panel');
        if (cdekPanelVisible) {
            panel.style.right = '-280px';
            cdekPanelVisible = false;
        } else {
            panel.style.right = '10px';
            cdekPanelVisible = true;
        }
    }
    
    function cdekUpdateStatus() {
        jQuery(function($) {
            var status = $('#cdek-status');
            var html = '';
            
            // Проверяем формы
            var forms = $('form').length;
            html += '📋 Форм: ' + forms + '<br>';
            
            // Проверяем СДЭК поля
            var cdekFields = $('input[name*="cdek_point_"]').length;
            html += '🎯 СДЭК полей: ' + cdekFields + '<br>';
            
            if (cdekFields > 0) {
                html += '<div style="color: green; font-weight: bold;">✅ Поля найдены!</div>';
                $('input[name*="cdek_point_"]').each(function() {
                    var val = this.value ? this.value.substring(0, 20) + '...' : '(пусто)';
                    html += '• ' + this.name.replace('cdek_point_', '') + ': ' + val + '<br>';
                });
            } else {
                html += '<div style="color: red; font-weight: bold;">❌ Поля не найдены!</div>';
            }
            
            // Проверяем доставочные блоки
            var shippingBlocks = $('.wc-block-components-totals-item').length;
            html += '🚚 Блоков доставки: ' + shippingBlocks + '<br>';
            
            if (shippingBlocks > 0) {
                $('.wc-block-components-totals-item').each(function() {
                    var label = $(this).find('.wc-block-components-totals-item__label').text().trim();
                    if (label && label.length > 0) {
                        var shortLabel = label.length > 25 ? label.substring(0, 25) + '...' : label;
                        var color = label.includes('ул.') || label.includes('пр-т') ? 'green' : 'orange';
                        html += '<div style="color: ' + color + ';">• ' + shortLabel + '</div>';
                    }
                });
            }
            
            status.html(html);
        });
    }
    
    function cdekRunTests() {
        jQuery(function($) {
            console.log('🧪 СДЭК: Запуск тестов...');
            
            // Тест 1: Поиск форм
            console.log('📋 Тест 1: Поиск форм');
            var formSelectors = [
                'form.wc-block-components-form',
                'form.woocommerce-checkout', 
                '.wc-block-checkout__form',
                'form[name="checkout"]',
                '.woocommerce-checkout',
                'form'
            ];
            
            formSelectors.forEach(function(selector) {
                var found = $(selector).length;
                console.log('  ' + selector + ': ' + found);
            });
            
            // Тест 2: Поиск СДЭК полей
            console.log('🎯 Тест 2: Поиск СДЭК полей');
            var fieldSelectors = [
                'input[name*="cdek_point_"]',
                'input[data-cdek="field"]',
                '#cdek_point_name',
                '#cdek_point_address',
                '#cdek_point_cost',
                '#cdek_point_code'
            ];
            
            fieldSelectors.forEach(function(selector) {
                var found = $(selector).length;
                console.log('  ' + selector + ': ' + found);
            });
            
            // Тест 3: Поиск доставочных блоков
            console.log('🚚 Тест 3: Поиск доставочных блоков');
            var shippingSelectors = [
                '.wc-block-components-totals-item',
                '.woocommerce-shipping-totals tr',
                '[class*="shipping"]',
                '[class*="delivery"]'
            ];
            
            shippingSelectors.forEach(function(selector) {
                var found = $(selector).length;
                console.log('  ' + selector + ': ' + found);
                if (found > 0) {
                    $(selector).each(function(index) {
                        if (index < 3) { // Показываем только первые 3
                            var text = $(this).text().trim();
                            if (text.length > 50) text = text.substring(0, 50) + '...';
                            console.log('    [' + index + '] ' + text);
                        }
                    });
                }
            });
            
            // Тест 4: Попытка принудительного добавления полей
            console.log('⚡ Тест 4: Принудительное добавление полей');
            $('input[name*="cdek_point_"]').remove();
            
            var targetForm = $('form').first();
            if (targetForm.length === 0) targetForm = $('body');
            
            var testFields = [
                '<input type="hidden" name="cdek_point_name" value="ТЕСТ" data-test="true">',
                '<input type="hidden" name="cdek_point_address" value="ТЕСТ АДРЕС" data-test="true">',
                '<input type="hidden" name="cdek_point_cost" value="999" data-test="true">',
                '<input type="hidden" name="cdek_point_code" value="TEST123" data-test="true">'
            ];
            
            testFields.forEach(function(field) {
                targetForm.append(field);
            });
            
            setTimeout(function() {
                var addedFields = $('input[data-test="true"]').length;
                console.log('  Добавлено тестовых полей: ' + addedFields);
                
                $('input[data-test="true"]').each(function() {
                    console.log('  ✓ ' + this.name + ' = ' + this.value);
                });
                
                // Убираем тестовые поля
                $('input[data-test="true"]').remove();
                
                cdekUpdateStatus();
            }, 500);
            
            console.log('🧪 СДЭК: Тесты завершены');
        });
    }
    
    // Обновляем статус каждые 3 секунды
    setInterval(cdekUpdateStatus, 3000);
    
    // Первое обновление через 2 секунды
    setTimeout(cdekUpdateStatus, 2000);
    </script>
    <?php
}

/**
 * Логирование попыток сохранения
 */
function cdek_test_log_save($order_id) {
    error_log('🧪 СДЭК ТЕСТ: Попытка сохранения для заказа #' . $order_id);
    error_log('🧪 СДЭК ТЕСТ: $_POST ключи: ' . implode(', ', array_keys($_POST)));
    
    $cdek_fields = array();
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'cdek_') === 0) {
            $cdek_fields[$key] = $value;
        }
    }
    
    if (!empty($cdek_fields)) {
        error_log('🧪 СДЭК ТЕСТ: Найдены СДЭК поля: ' . print_r($cdek_fields, true));
    } else {
        error_log('🧪 СДЭК ТЕСТ: СДЭК поля НЕ найдены в $_POST');
    }
}