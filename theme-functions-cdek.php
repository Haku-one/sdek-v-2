<?php
/**
 * СДЭК Доставка - Функции для темы
 * Добавьте этот код в файл functions.php вашей темы
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация СДЭК функций для темы
 */
function cdek_theme_init() {
    // Добавляем поддержку СДЭК шаблонов email
    add_action('init', 'cdek_setup_email_templates');
    
    // Хуки для отображения информации о доставке в админке заказа
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_delivery_info_in_admin', 20);
    
    // Хуки для сохранения дополнительных данных
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_additional_delivery_meta', 20);
    
    // Добавляем AJAX обработчики для получения информации о доставке
    add_action('wp_ajax_get_cdek_delivery_info', 'cdek_ajax_get_delivery_info');
    add_action('wp_ajax_nopriv_get_cdek_delivery_info', 'cdek_ajax_get_delivery_info');
    
    // Добавляем функционал "Обсудить доставку с менеджером"
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_discuss_delivery_choice', 25);
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_show_discuss_delivery_admin', 25);
    add_action('woocommerce_email_order_details', 'cdek_email_discuss_delivery_info', 30, 4);
}
add_action('after_setup_theme', 'cdek_theme_init');

/**
 * Настройка шаблонов email
 */
function cdek_setup_email_templates() {
    // Проверяем, что WooCommerce активен
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Добавляем информацию о доставке в email уведомления через хуки
    add_action('woocommerce_email_order_details', 'cdek_add_delivery_info_to_any_email', 25, 4);
}

/**
 * Добавление информации о доставке во все email уведомления
 * (используется как fallback если кастомные шаблоны не установлены)
 */
function cdek_add_delivery_info_to_any_email($order, $sent_to_admin, $plain_text, $email) {
    // Получаем данные о доставке СДЭК
    $cdek_point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
    
    if (!$cdek_point_code || !$cdek_point_data) {
        return;
    }
    
    // Получаем стоимость доставки
    $cdek_delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
    if (!$cdek_delivery_cost) {
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            if (strpos($shipping_method->get_method_id(), 'cdek') !== false) {
                $cdek_delivery_cost = $shipping_method->get_total();
                break;
            }
        }
    }
    
    // Формируем название пункта выдачи
    $point_name = $cdek_point_data['name'];
    if (isset($cdek_point_data['location']['city'])) {
        $city = $cdek_point_data['location']['city'];
        $point_name = $city . ', ' . str_replace($city, '', $point_name);
        $point_name = trim($point_name, ', ');
    }
    
    // Получаем адрес
    $address = '';
    if (isset($cdek_point_data['location']['address_full'])) {
        $address = $cdek_point_data['location']['address_full'];
    } elseif (isset($cdek_point_data['location']['address'])) {
        $address = $cdek_point_data['location']['address'];
    }
    
    if ($plain_text) {
        // Текстовый формат email
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК\n";
        echo str_repeat('=', 50) . "\n";
        echo "Пункт выдачи: " . $point_name . "\n";
        if ($cdek_delivery_cost) {
            echo "Стоимость доставки: " . $cdek_delivery_cost . " руб.\n";
        }
        if ($address) {
            echo "Адрес: " . $address . "\n";
        }
        echo "Код пункта: " . $cdek_point_code . "\n";
        echo str_repeat('=', 50) . "\n\n";
    } else {
        // HTML формат email
        echo '<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">';
        echo '<h3 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;">📦 Информация о доставке СДЭК</h3>';
        echo '<p><strong>Пункт выдачи:</strong> ' . esc_html($point_name) . '</p>';
        
        if ($cdek_delivery_cost) {
            echo '<p><strong>Стоимость доставки:</strong> <span style="color: #28a745; font-weight: bold;">' . esc_html($cdek_delivery_cost) . ' руб.</span></p>';
        }
        
        if ($address) {
            echo '<p><strong>Адрес:</strong> ' . esc_html($address) . '</p>';
        }
        
        echo '<p><strong>Код пункта:</strong> <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . esc_html($cdek_point_code) . '</code></p>';
        echo '<div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border-radius: 4px; font-size: 14px;">';
        echo '<strong>💡 Важно:</strong> Сохраните эту информацию для получения заказа в пункте выдачи СДЭК.';
        echo '</div>';
        echo '</div>';
    }
}

/**
 * Отображение информации о доставке в админке заказа
 */
function cdek_display_delivery_info_in_admin($order) {
    $order_id = $order->get_id();
    $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
    $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
    
    if (!$cdek_point_code || !$cdek_point_data) {
        return;
    }
    
    // Получаем стоимость доставки если не сохранена
    if (!$cdek_delivery_cost) {
        $shipping_methods = $order->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            if (strpos($shipping_method->get_method_id(), 'cdek') !== false) {
                $cdek_delivery_cost = $shipping_method->get_total();
                break;
            }
        }
    }
    
    // Формируем название пункта выдачи
    $point_name = $cdek_point_data['name'];
    if (isset($cdek_point_data['location']['city'])) {
        $city = $cdek_point_data['location']['city'];
        $point_name = $city . ', ' . str_replace($city, '', $point_name);
        $point_name = trim($point_name, ', ');
    }
    
    // Получаем адрес
    $address = '';
    if (isset($cdek_point_data['location']['address_full'])) {
        $address = $cdek_point_data['location']['address_full'];
    } elseif (isset($cdek_point_data['location']['address'])) {
        $address = $cdek_point_data['location']['address'];
    }
    
    // Получаем телефон
    $phone = '';
    if (isset($cdek_point_data['phones']) && is_array($cdek_point_data['phones']) && !empty($cdek_point_data['phones'])) {
        $phone = $cdek_point_data['phones'][0]['number'] ?? $cdek_point_data['phones'][0];
    }
    
    // Получаем режим работы
    $work_time = isset($cdek_point_data['work_time']) ? $cdek_point_data['work_time'] : '';
    ?>
    
    <div class="cdek-delivery-info-theme" style="margin-top: 20px; padding: 15px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 4px;">
        <h3 style="color: #2e7d32; margin-top: 0;">📦 Информация о доставке СДЭК</h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <p><strong>Пункт выдачи:</strong><br><?php echo esc_html($point_name); ?></p>
                <?php if ($cdek_delivery_cost): ?>
                <p><strong>Стоимость доставки:</strong><br><span style="color: #2e7d32; font-weight: bold;"><?php echo esc_html($cdek_delivery_cost); ?> руб.</span></p>
                <?php endif; ?>
                <p><strong>Код пункта:</strong><br><code style="background: #fff; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px;"><?php echo esc_html($cdek_point_code); ?></code></p>
            </div>
            
            <div>
                <?php if ($address): ?>
                <p><strong>Адрес:</strong><br><?php echo esc_html($address); ?></p>
                <?php endif; ?>
                
                <?php if ($phone): ?>
                <p><strong>Телефон:</strong><br><a href="tel:<?php echo esc_attr($phone); ?>" style="color: #007cba;"><?php echo esc_html($phone); ?></a></p>
                <?php endif; ?>
                
                <?php if ($work_time): ?>
                <p><strong>Режим работы:</strong><br><?php echo esc_html($work_time); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="border-top: 1px solid #4caf50; padding-top: 10px; text-align: center;">
            <button type="button" class="button button-secondary" onclick="cdekCopyDeliveryInfoTheme()" title="Скопировать информацию о доставке">
                📋 Копировать информацию
            </button>
        </div>
    </div>
    
    <script>
    function cdekCopyDeliveryInfoTheme() {
        var text = "Пункт выдачи СДЭК: <?php echo esc_js($point_name); ?>\n";
        text += "Стоимость: <?php echo esc_js($cdek_delivery_cost); ?> руб.\n";
        text += "Адрес: <?php echo esc_js($address); ?>\n";
        text += "Код: <?php echo esc_js($cdek_point_code); ?>";
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert("Информация о доставке скопирована в буфер обмена!");
            });
        } else {
            // Fallback для старых браузеров
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert("Информация о доставке скопирована в буфер обмена!");
        }
    }
    </script>
    
    <?php
}

/**
 * Сохранение дополнительных метаданных доставки
 */
function cdek_save_additional_delivery_meta($order_id) {
    // Сохраняем дополнительную информацию о доставке
    if (isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
        $delivery_cost = sanitize_text_field($_POST['cdek_delivery_cost']);
        update_post_meta($order_id, '_cdek_delivery_cost', $delivery_cost);
    }
    
    if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
        $point_code = sanitize_text_field($_POST['cdek_selected_point_code']);
        update_post_meta($order_id, '_cdek_point_code', $point_code);
    }
    
    if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
        $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
        if ($point_data && is_array($point_data)) {
            update_post_meta($order_id, '_cdek_point_data', $point_data);
            
            // Сохраняем структурированные данные для удобного доступа
            if (isset($point_data['name'])) {
                $point_name = $point_data['name'];
                if (isset($point_data['location']['city'])) {
                    $city = $point_data['location']['city'];
                    $point_name = $city . ', ' . str_replace($city, '', $point_name);
                    $point_name = trim($point_name, ', ');
                }
                update_post_meta($order_id, '_cdek_point_display_name', $point_name);
            }
            
            if (isset($point_data['location']['address_full'])) {
                update_post_meta($order_id, '_cdek_point_address', $point_data['location']['address_full']);
            }
            
            if (isset($point_data['location']['city'])) {
                update_post_meta($order_id, '_cdek_point_city', $point_data['location']['city']);
            }
        }
    }
}

/**
 * AJAX обработчик для получения информации о доставке
 */
function cdek_ajax_get_delivery_info() {
    if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
        wp_die('Security check failed');
    }
    
    $order_id = intval($_POST['order_id']);
    
    if (!$order_id) {
        wp_send_json_error('Неверный ID заказа');
        return;
    }
    
    $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
    $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
    
    if (!$cdek_point_code || !$cdek_point_data) {
        wp_send_json_error('Информация о доставке СДЭК не найдена');
        return;
    }
    
    // Формируем название пункта выдачи
    $point_name = $cdek_point_data['name'];
    if (isset($cdek_point_data['location']['city'])) {
        $city = $cdek_point_data['location']['city'];
        $point_name = $city . ', ' . str_replace($city, '', $point_name);
        $point_name = trim($point_name, ', ');
    }
    
    $delivery_info = array(
        'point_code' => $cdek_point_code,
        'point_name' => $point_name,
        'delivery_cost' => $cdek_delivery_cost,
        'address' => isset($cdek_point_data['location']['address_full']) ? $cdek_point_data['location']['address_full'] : '',
        'phone' => isset($cdek_point_data['phones'][0]['number']) ? $cdek_point_data['phones'][0]['number'] : '',
        'work_time' => isset($cdek_point_data['work_time']) ? $cdek_point_data['work_time'] : '',
        'city' => isset($cdek_point_data['location']['city']) ? $cdek_point_data['location']['city'] : ''
    );
    
    wp_send_json_success($delivery_info);
}

/**
 * Добавляем стили для блока СДЭК в админке
 */
function cdek_admin_styles() {
    if (is_admin()) {
        echo '<style>
        .cdek-delivery-info-theme {
            border-left: 4px solid #4caf50 !important;
        }
        .cdek-delivery-info-theme h3 {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cdek-delivery-info-theme code {
            font-family: "Courier New", monospace;
            font-size: 14px;
        }
        </style>';
    }
}
add_action('admin_head', 'cdek_admin_styles');

/**
 * Функция для получения информации о доставке СДЭК (для использования в шаблонах)
 */
function get_cdek_delivery_info($order_id) {
    $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
    $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
    
    if (!$cdek_point_code || !$cdek_point_data) {
        return false;
    }
    
    // Формируем название пункта выдачи
    $point_name = $cdek_point_data['name'];
    if (isset($cdek_point_data['location']['city'])) {
        $city = $cdek_point_data['location']['city'];
        $point_name = $city . ', ' . str_replace($city, '', $point_name);
        $point_name = trim($point_name, ', ');
    }
    
    return array(
        'point_code' => $cdek_point_code,
        'point_name' => $point_name,
        'delivery_cost' => $cdek_delivery_cost,
        'address' => isset($cdek_point_data['location']['address_full']) ? $cdek_point_data['location']['address_full'] : '',
        'phone' => isset($cdek_point_data['phones'][0]['number']) ? $cdek_point_data['phones'][0]['number'] : '',
        'work_time' => isset($cdek_point_data['work_time']) ? $cdek_point_data['work_time'] : '',
        'city' => isset($cdek_point_data['location']['city']) ? $cdek_point_data['location']['city'] : '',
        'raw_data' => $cdek_point_data
    );
}

/**
 * Сохранение выбора "Обсудить доставку с менеджером"
 */
function cdek_save_discuss_delivery_choice($order_id) {
    // Добавляем подробную отладочную информацию
    error_log('СДЭК DEBUG: Функция cdek_save_discuss_delivery_choice вызвана для заказа #' . $order_id);
    error_log('СДЭК DEBUG: $_POST данные: ' . print_r($_POST, true));
    
    if (isset($_POST['discuss_delivery_selected'])) {
        error_log('СДЭК DEBUG: Поле discuss_delivery_selected найдено в $_POST со значением: ' . $_POST['discuss_delivery_selected']);
        
        if ($_POST['discuss_delivery_selected'] == '1') {
            update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
            error_log('СДЭК DEBUG: Сохранено в мета поле _discuss_delivery_selected значение "Да"');
            
            $order = wc_get_order($order_id);
            if ($order) {
                // Добавляем как кастомное поле заказа для отображения в email и админке
                $order->update_meta_data('Тип доставки', 'Обсудить с менеджером');
                $order->update_meta_data('Статус доставки', 'Требуется обсуждение');
                $order->save();
                
                $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
                error_log('СДЭК: Сохранен выбор "Обсудить доставку с менеджером" для заказа #' . $order_id);
            }
        } else {
            error_log('СДЭК DEBUG: Значение discuss_delivery_selected не равно "1": ' . $_POST['discuss_delivery_selected']);
        }
    } else {
        error_log('СДЭК DEBUG: Поле discuss_delivery_selected НЕ найдено в $_POST');
        error_log('СДЭК DEBUG: Доступные POST поля: ' . implode(', ', array_keys($_POST)));
    }
}

/**
 * Отображение информации об обсуждении доставки в админке заказа
 */
function cdek_show_discuss_delivery_admin($order) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        ?>
        <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="color: #e65100; margin: 0; font-size: 16px; display: flex; align-items: center;">
                <span style="font-size: 20px; margin-right: 8px;">🗣️</span>
                ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
            </h4>
            <p style="color: #e65100; font-weight: bold; margin: 8px 0 0 0; font-size: 14px;">
                ⚠️ Необходимо связаться с клиентом для обсуждения условий доставки!
            </p>
            <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 4px;">
                <small style="color: #bf360c; font-weight: bold;">
                    💡 Рекомендации: уточнить адрес, время, стоимость и способ доставки
                </small>
            </div>
        </div>
        <?php
    }
}

/**
 * Добавление информации об обсуждении доставки в email уведомления
 */
function cdek_email_discuss_delivery_info($order, $sent_to_admin, $plain_text, $email) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        if ($plain_text) {
            echo "\n" . str_repeat('=', 50) . "\n";
            echo "ДОСТАВКА: Обсуждается с менеджером\n";
            echo str_repeat('=', 50) . "\n";
            
            if ($sent_to_admin) {
                echo "⚠️ ВНИМАНИЕ: Необходимо связаться с клиентом для обсуждения доставки!\n";
                echo "Уточните: адрес, время, стоимость и способ доставки.\n";
            } else {
                echo "Наш менеджер свяжется с вами для обсуждения условий доставки.\n";
                echo "Ожидайте звонка в рабочее время.\n";
            }
            echo "\n";
        } else {
            if ($sent_to_admin) {
                ?>
                <!-- Информация об обсуждении доставки для администратора -->
                <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
                    <h2 style="color: #e65100; margin-top: 0; border-bottom: 2px solid #ff9800; padding-bottom: 10px; text-align: center;">
                        🗣️ ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
                    </h2>
                    <div style="background: #fff3e0; padding: 15px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
                        <p style="margin: 0; color: #e65100; font-size: 16px; font-weight: bold;">
                            ⚠️ ТРЕБУЕТСЯ ДЕЙСТВИЕ: Связаться с клиентом
                        </p>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ffcc02; background: #fffde7; color: #e65100; font-weight: bold;">
                                📞 Что обсудить:
                            </td>
                            <td style="padding: 10px; border: 1px solid #ffcc02; background: #ffffff; color: #e65100;">
                                Адрес, время, стоимость и способ доставки
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ffcc02; background: #fffde7; color: #e65100; font-weight: bold;">
                                🕐 Приоритет:
                            </td>
                            <td style="padding: 10px; border: 1px solid #ffcc02; background: #ffffff; color: #e65100;">
                                Высокий - связаться в течение рабочего дня
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 15px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; text-align: center;">
                        <strong style="color: #155724;">💡 Совет:</strong><br>
                        <span style="color: #155724; font-size: 14px;">
                            После обсуждения обновите информацию о доставке в заказе
                        </span>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <!-- Информация об обсуждении доставки для клиента -->
                <div style="background: #e3f2fd; border: 2px solid #1976d2; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
                    <h2 style="color: #1976d2; margin-top: 0; border-bottom: 2px solid #1976d2; padding-bottom: 10px; text-align: center;">
                        🗣️ Обсуждение условий доставки
                    </h2>
                    <div style="background: #bbdefb; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                        <p style="margin: 0; color: #0d47a1; font-size: 16px; font-weight: bold;">
                            📞 Наш менеджер свяжется с вами для обсуждения доставки
                        </p>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold; width: 40%;">
                                📋 Что обсудим:
                            </td>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0;">
                                Удобный для вас адрес, время и способ доставки
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold;">
                                🕐 Когда ожидать звонка:
                            </td>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0;">
                                В рабочее время (пн-пт: 9:00-18:00)
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #e1f5fe; color: #0d47a1; font-weight: bold;">
                                💰 Стоимость:
                            </td>
                            <td style="padding: 12px; border: 1px solid #64b5f6; background: #ffffff; color: #1565c0;">
                                Будет рассчитана индивидуально
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 20px; padding: 15px; background: #c8e6c9; border: 1px solid #a5d6a7; border-radius: 6px;">
                        <h3 style="margin: 0 0 10px 0; color: #2e7d32; font-size: 16px;">📱 Убедитесь, что ваш телефон доступен</h3>
                        <p style="margin: 0; color: #2e7d32; line-height: 1.5;">
                            Наш менеджер свяжется с вами по указанному в заказе номеру телефона. 
                            Если номер изменился, пожалуйста, сообщите нам по email или через поддержку на сайте.
                        </p>
                    </div>
                </div>
                <?php
            }
        }
    }
}