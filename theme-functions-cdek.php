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
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_captured_shipping_data', 5);
    
    // Добавляем AJAX обработчики для получения информации о доставке
    add_action('wp_ajax_get_cdek_delivery_info', 'cdek_ajax_get_delivery_info');
    add_action('wp_ajax_nopriv_get_cdek_delivery_info', 'cdek_ajax_get_delivery_info');
    
    // Добавляем функционал "Обсудить доставку с менеджером"
    add_action('woocommerce_checkout_update_order_meta', 'cdek_save_discuss_delivery_choice', 25);
    add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_show_discuss_delivery_admin', 25);
    add_action('woocommerce_email_order_details', 'cdek_email_discuss_delivery_info', 30, 4);
    
    // Дополнительные хуки для извлечения данных СДЭК
    add_action('woocommerce_checkout_order_processed', 'cdek_process_order_shipping_data', 30, 3);
    add_action('woocommerce_order_status_changed', 'cdek_reprocess_shipping_data_on_status_change', 10, 3);
    
    // ПРИНУДИТЕЛЬНО включаем обработку email независимо от других обработчиков
    add_action('woocommerce_email_order_details', 'cdek_force_delivery_info_in_email', 5, 4);
    
    // Добавляем информацию СДЭК через стандартный фильтр WooCommerce
    add_filter('woocommerce_email_order_meta_fields', 'cdek_add_email_order_meta_fields', 10, 3);
    
    // Добавляем админ функцию для ручного исправления заказов
    add_action('admin_init', 'cdek_maybe_fix_order_745');
    
    // Добавляем JavaScript для сохранения данных из блока доставки
    add_action('wp_footer', 'cdek_add_shipping_data_capture_script');
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
 * Определение типа доставки на основе всех доступных данных
 */
function cdek_determine_delivery_type($order, $discuss_delivery, $pickup_delivery, $shipping_method) {
    $order_id = $order->get_id();
    
    // 1. Проверяем прямые мета-поля
    if ($discuss_delivery == 'Да') {
        return 'discuss';
    }
    
    if ($pickup_delivery == 'Да') {
        return 'pickup';
    }
    
    // 2. Проверяем наличие данных СДЭК
    $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
    $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
    
    if ($cdek_point_code && $cdek_point_data) {
        return 'cdek';
    }
    
    // 3. Проверяем по способу доставки
    if ($shipping_method) {
        $method_title = strtolower($shipping_method->get_method_title());
        $method_id = strtolower($shipping_method->get_method_id());
        
        // Проверяем самовывоз
        if (strpos($method_title, 'самовывоз') !== false || 
            strpos($method_title, 'pickup') !== false ||
            strpos($method_title, 'самовызов') !== false ||
            strpos($method_id, 'pickup') !== false) {
            return 'pickup';
        }
        
        // Проверяем СДЭК - если есть адрес в названии, скорее всего это СДЭК
        if (strpos($method_title, 'сдэк') !== false || 
            strpos($method_title, 'cdek') !== false ||
            strpos($method_id, 'cdek') !== false ||
            // Если в названии есть конкретный адрес с улицей, это вероятно СДЭК
            (preg_match('/ул\.|улица|пр\.|проспект|пер\.|переулок/', $method_title))) {
            
            // Пытаемся извлечь данные прямо сейчас
            cdek_extract_shipping_data_from_order($order_id, $order);
            return 'cdek';
        }
    }
    
    // 4. Дополнительная проверка по содержимому заказа
    $shipping_lines = $order->get_items('shipping');
    foreach ($shipping_lines as $shipping_line) {
        $shipping_data = $shipping_line->get_data();
        error_log('СДЭК DEBUG: Дополнительные данные доставки: ' . print_r($shipping_data, true));
        
        // Проверяем есть ли в метаданных информация о СДЭК
        if (isset($shipping_data['meta_data'])) {
            foreach ($shipping_data['meta_data'] as $meta) {
                if (isset($meta->key) && (
                    strpos(strtolower($meta->key), 'cdek') !== false ||
                    strpos(strtolower($meta->key), 'сдэк') !== false
                )) {
                    return 'cdek';
                }
            }
        }
    }
    
    // 5. По умолчанию обычная доставка
    return 'standard';
}

/**
 * Сохранение данных, захваченных JavaScript из блока доставки
 */
function cdek_save_captured_shipping_data($order_id) {
    error_log('СДЭК CAPTURE: Сохранение захваченных данных для заказа #' . $order_id);
    error_log('СДЭК CAPTURE: Проверяем $_POST данные: ' . print_r(array_keys($_POST), true));
    
    // Логируем конкретные поля СДЭК
    $cdek_fields_in_post = array();
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'cdek_shipping') !== false) {
            $cdek_fields_in_post[$key] = $value;
        }
    }
    error_log('СДЭК CAPTURE: Найденные CDEK поля в $_POST: ' . print_r($cdek_fields_in_post, true));
    
    // Сохраняем данные из скрытых полей формы
    $fields_to_save = array(
        'cdek_shipping_label' => '_cdek_shipping_label',
        'cdek_shipping_cost' => '_cdek_shipping_cost', 
        'cdek_shipping_full_address' => '_cdek_shipping_full_address',
        'cdek_shipping_captured' => '_cdek_shipping_captured'
    );
    
    foreach ($fields_to_save as $post_field => $meta_field) {
        if (isset($_POST[$post_field]) && !empty($_POST[$post_field])) {
            $value = sanitize_text_field($_POST[$post_field]);
            update_post_meta($order_id, $meta_field, $value);
            error_log('СДЭК CAPTURE: Сохранено ' . $meta_field . ': ' . $value);
        }
    }
    
    // Если данные захвачены, отмечаем заказ как содержащий правильные данные СДЭК
    if (isset($_POST['cdek_shipping_captured']) && $_POST['cdek_shipping_captured'] === '1') {
        $order = wc_get_order($order_id);
        if ($order) {
            $shipping_label = isset($_POST['cdek_shipping_label']) ? sanitize_text_field($_POST['cdek_shipping_label']) : '';
            $shipping_cost = isset($_POST['cdek_shipping_cost']) ? sanitize_text_field($_POST['cdek_shipping_cost']) : '';
            $full_address = isset($_POST['cdek_shipping_full_address']) ? sanitize_text_field($_POST['cdek_shipping_full_address']) : '';
            
            // Используем полный адрес если он есть, иначе лейбл
            $address_to_use = ($full_address && strlen($full_address) > strlen($shipping_label)) ? $full_address : $shipping_label;
            
            // Создаем правильные данные СДЭК на основе захваченных данных
            if ($address_to_use && $address_to_use !== 'Выберите пункт выдачи') {
                cdek_force_create_correct_data($order_id, $address_to_use, $shipping_cost);
                error_log('СДЭК CAPTURE: Созданы правильные данные СДЭК на основе захваченных: ' . $address_to_use);
            }
            
            $order->add_order_note('Захвачены данные СДЭК из блока доставки: ' . $shipping_label);
            error_log('СДЭК CAPTURE: Добавлена заметка к заказу о захваченных данных');
        }
    }
}

/**
 * Добавление информации о доставке во все email уведомления
 * (используется как fallback если кастомные шаблоны не установлены)
 */
function cdek_add_delivery_info_to_any_email($order, $sent_to_admin, $plain_text, $email) {
    $order_id = $order->get_id();
    
    // Получаем информацию о выбранном способе доставки
    $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
    $pickup_delivery = get_post_meta($order_id, '_pickup_delivery_selected', true);
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods); // Получаем первый метод доставки
    
    // Функция для определения типа доставки
    $delivery_type = cdek_determine_delivery_type($order, $discuss_delivery, $pickup_delivery, $shipping_method);
    
    if ($plain_text) {
        // Текстовый формат email
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ИНФОРМАЦИЯ О ДОСТАВКЕ\n";
        echo str_repeat('=', 50) . "\n";
        
        if ($delivery_type === 'discuss') {
            // Обсуждение доставки с менеджером
            if ($sent_to_admin) {
                echo "⚠️ ВНИМАНИЕ: Необходимо связаться с клиентом для обсуждения доставки!\n";
                echo "Уточните: адрес, время, стоимость и способ доставки.\n";
            } else {
                echo "Способ доставки: Обсудить доставку с менеджером\n";
                echo "Наш менеджер свяжется с вами для обсуждения условий доставки.\n";
                echo "Ожидайте звонка в рабочее время.\n";
            }
        } elseif ($delivery_type === 'pickup') {
            // Самовывоз
            echo "Способ доставки: Самовывоз (г.Саратов, ул. Осипова, д. 18а)\n";
            echo "Адрес пункта выдачи: г.Саратов, ул. Осипова, д. 18а\n";
            echo "Режим работы: пн-пт 9:00-18:00, сб 10:00-16:00\n";
        } elseif ($delivery_type === 'cdek') {
            // СДЭК доставка
            $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
            $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
            
            // Получаем стоимость доставки
            $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
            if (!$cdek_delivery_cost && $shipping_method) {
                $cdek_delivery_cost = $shipping_method->get_total();
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
            
            echo "Способ доставки: СДЭК\n";
            echo "Пункт выдачи: " . $point_name . "\n";
            if ($cdek_delivery_cost) {
                echo "Стоимость доставки: " . $cdek_delivery_cost . " руб.\n";
            }
            if ($address) {
                echo "Адрес: " . $address . "\n";
            }
            echo "Код пункта: " . $cdek_point_code . "\n";
        } else {
            // Обычная доставка
            if ($shipping_method) {
                echo "Способ доставки: " . $shipping_method->get_method_title() . "\n";
                if ($shipping_method->get_total()) {
                    echo "Стоимость доставки: " . $shipping_method->get_total() . " руб.\n";
                }
            }
        }
        echo str_repeat('=', 50) . "\n\n";
    } else {
        // HTML формат email
        if ($delivery_type === 'discuss') {
            // Обсуждение доставки с менеджером
            if ($sent_to_admin) {
                ?>
                <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
                    <h3 style="color: #e65100; margin-top: 0; border-bottom: 2px solid #ff9800; padding-bottom: 10px;">
                        🗣️ ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
                    </h3>
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
                    </table>
                </div>
                <?php
            } else {
                ?>
                <div style="background: #e3f2fd; border: 2px solid #1976d2; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
                    <h3 style="color: #1976d2; margin-top: 0; border-bottom: 2px solid #1976d2; padding-bottom: 10px;">
                        🗣️ Обсуждение условий доставки
                    </h3>
                    <div style="background: #bbdefb; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                        <p style="margin: 0; color: #0d47a1; font-size: 16px; font-weight: bold;">
                            📞 Наш менеджер свяжется с вами для обсуждения доставки
                        </p>
                    </div>
                    <p style="color: #1565c0; text-align: center; margin: 15px 0;">
                        <strong>Ожидайте звонка в рабочее время (пн-пт: 9:00-18:00)</strong>
                    </p>
                </div>
                                 <?php
            }
         } elseif ($delivery_type === 'pickup') {
            // Самовывоз
            ?>
            <div style="background: #f0f8ff; border: 2px solid #4169e1; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">
                <h3 style="color: #4169e1; margin-top: 0; border-bottom: 2px solid #4169e1; padding-bottom: 10px;">
                    🏪 Самовывоз
                </h3>
                <div style="background: #e6f3ff; padding: 15px; border-radius: 6px;">
                    <p style="margin: 0; color: #2c5aa0; font-size: 16px; font-weight: bold;">
                        📍 Адрес: г.Саратов, ул. Осипова, д. 18а
                    </p>
                    <p style="margin: 10px 0 0 0; color: #2c5aa0; font-size: 14px;">
                        🕐 Режим работы: пн-пт 9:00-18:00, сб 10:00-16:00
                    </p>
                </div>
            </div>
            <?php
        } elseif ($delivery_type === 'cdek') {
            // СДЭК доставка
            $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
            $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
            
            // Получаем стоимость доставки
            $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
            if (!$cdek_delivery_cost && $shipping_method) {
                $cdek_delivery_cost = $shipping_method->get_total();
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
            
            echo '<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">';
            echo '<h3 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;">📦 Доставка СДЭК</h3>';
            echo '<p><strong>Пункт выдачи:</strong> ' . esc_html($point_name) . '</p>';
            
            if ($cdek_delivery_cost) {
                echo '<p><strong>Стоимость доставки:</strong> <span style="color: #28a745; font-weight: bold;">' . esc_html($cdek_delivery_cost) . ' руб.</span></p>';
            }
            
            if ($address) {
                echo '<p><strong>Адрес:</strong> <small style="color: #666;">' . esc_html($address) . '</small></p>';
            }
            
            echo '<p><strong>Код пункта:</strong> <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . esc_html($cdek_point_code) . '</code></p>';
            echo '<div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border-radius: 4px; font-size: 14px;">';
            echo '<strong>💡 Важно:</strong> Сохраните эту информацию для получения заказа в пункте выдачи СДЭК.';
            echo '</div>';
            echo '</div>';
        } else {
            // Обычная доставка
            if ($shipping_method) {
                echo '<div style="background: #f8f9fa; border: 1px solid #007cba; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">';
                echo '<h3 style="color: #007cba; margin-top: 0; border-bottom: 2px solid #007cba; padding-bottom: 10px;">🚚 Доставка</h3>';
                echo '<p><strong>Способ доставки:</strong> ' . esc_html($shipping_method->get_method_title()) . '</p>';
                if ($shipping_method->get_total()) {
                    echo '<p><strong>Стоимость:</strong> <span style="color: #007cba; font-weight: bold;">' . esc_html($shipping_method->get_total()) . ' руб.</span></p>';
                }
                echo '</div>';
            }
        }
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
 * Сохранение выбора типа доставки
 */
function cdek_save_discuss_delivery_choice($order_id) {
    // Добавляем подробную отладочную информацию
    error_log('СДЭК DEBUG: Функция cdek_save_discuss_delivery_choice вызвана для заказа #' . $order_id);
    error_log('СДЭК DEBUG: $_POST данные: ' . print_r($_POST, true));
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Сохраняем выбор "Обсудить доставку с менеджером"
    if (isset($_POST['discuss_delivery_selected'])) {
        error_log('СДЭК DEBUG: Поле discuss_delivery_selected найдено в $_POST со значением: ' . $_POST['discuss_delivery_selected']);
        
        if ($_POST['discuss_delivery_selected'] == '1') {
            update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
            error_log('СДЭК DEBUG: Сохранено в мета поле _discuss_delivery_selected значение "Да"');
            
            $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
            error_log('СДЭК: Сохранен выбор "Обсудить доставку с менеджером" для заказа #' . $order_id);
        } else {
            error_log('СДЭК DEBUG: Значение discuss_delivery_selected не равно "1": ' . $_POST['discuss_delivery_selected']);
        }
    } else {
        // Анализируем данные о доставке из заказа
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method = reset($shipping_methods);
        
        if ($shipping_method) {
            $method_title = $shipping_method->get_method_title();
            $method_id = $shipping_method->get_method_id();
            
            error_log('СДЭК DEBUG: Анализ доставки - Title: ' . $method_title . ', ID: ' . $method_id);
            
            // Определяем тип доставки по названию метода
            if (strpos(strtolower($method_title), 'самовывоз') !== false || 
                strpos(strtolower($method_title), 'pickup') !== false ||
                strpos(strtolower($method_id), 'pickup') !== false) {
                
                update_post_meta($order_id, '_pickup_delivery_selected', 'Да');
                $order->add_order_note('Клиент выбрал самовывоз');
                error_log('СДЭК: Сохранен выбор "Самовывоз" для заказа #' . $order_id);
                
            } elseif (strpos(strtolower($method_title), 'сдэк') !== false || 
                      strpos(strtolower($method_title), 'cdek') !== false ||
                      strpos(strtolower($method_id), 'cdek') !== false) {
                
                update_post_meta($order_id, '_cdek_delivery_selected', 'Да');
                $order->add_order_note('Клиент выбрал доставку СДЭК');
                error_log('СДЭК: Сохранен выбор "Доставка СДЭК" для заказа #' . $order_id);
                
                // Проверяем, есть ли уже захваченные JavaScript'ом данные
                $captured_flag = get_post_meta($order_id, '_cdek_shipping_captured', true);
                $captured_label = get_post_meta($order_id, '_cdek_shipping_label', true);
                
                if ($captured_flag && $captured_label && $captured_label !== 'Выберите пункт выдачи') {
                    error_log('СДЭК: Найдены захваченные данные, пропускаем извлечение из названия: ' . $captured_label);
                } else {
                    // Пытаемся извлечь данные СДЭК из названия доставки в заказе только если нет захваченных данных
                    error_log('СДЭК: Захваченные данные не найдены, извлекаем из названия доставки');
                    cdek_extract_shipping_data_from_order($order_id, $order);
                }
            }
        }
        
        error_log('СДЭК DEBUG: Поле discuss_delivery_selected НЕ найдено в $_POST');
        error_log('СДЭК DEBUG: Доступные POST поля: ' . implode(', ', array_keys($_POST)));
    }
}

/**
 * Извлечение данных о доставке СДЭК из заказа
 */
function cdek_extract_shipping_data_from_order($order_id, $order) {
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods);
    
    if (!$shipping_method) {
        return;
    }
    
    // Получаем метаданные метода доставки
    $method_meta = $shipping_method->get_meta_data();
    $shipping_total = $shipping_method->get_total();
    
    error_log('СДЭК DEBUG: Метаданные доставки: ' . print_r($method_meta, true));
    error_log('СДЭК DEBUG: Стоимость доставки: ' . $shipping_total);
    
    // Пытаемся извлечь данные из названия доставки
    $method_title = $shipping_method->get_method_title();
    
    // Пытаемся извлечь данные из любого места в названии доставки
    $extracted = false;
    
    // Вариант 1: Полный адрес с улицей
    if (preg_match('/(.+),\s*(.+)/', $method_title, $matches)) {
        $point_name = trim($matches[1]);
        $address_info = trim($matches[2]);
        
        // Определяем полный адрес
        $full_address = $method_title;
        if ($shipping_method->get_instance_id()) {
            // Пытаемся найти дополнительную информацию в метаданных
            $instance_settings = get_option('woocommerce_' . $shipping_method->get_method_id() . '_' . $shipping_method->get_instance_id() . '_settings', array());
            if (!empty($instance_settings)) {
                error_log('СДЭК DEBUG: Настройки метода доставки: ' . print_r($instance_settings, true));
            }
        }
        
        $extracted = true;
    }
    // Вариант 2: Просто название с адресом
    elseif (preg_match('/ул\.|улица|пр\.|проспект|пер\.|переулок/', $method_title)) {
        $point_name = $method_title;
        $address_info = $method_title;
        $full_address = $method_title;
        $extracted = true;
    }
    // Вариант 3: Любой текст, если это не самовывоз
    elseif (!preg_match('/самовывоз|pickup/i', $method_title) && strlen(trim($method_title)) > 5) {
        $point_name = $method_title;
        $address_info = $method_title;
        $full_address = $method_title;
        $extracted = true;
    }
    
    if ($extracted) {
        // Создаем псевдо-данные пункта СДЭК на основе информации из заказа
        $point_data = array(
            'name' => $point_name,
            'location' => array(
                'city' => 'Саратов',
                'address' => $full_address,
                'address_full' => $full_address
            )
        );
        
        // Генерируем псевдо-код пункта
        $point_code = 'AUTO_' . substr(md5($method_title), 0, 8);
        
        // Сохраняем данные
        update_post_meta($order_id, '_cdek_point_code', $point_code);
        update_post_meta($order_id, '_cdek_point_data', $point_data);
        update_post_meta($order_id, '_cdek_delivery_cost', $shipping_total);
        update_post_meta($order_id, '_cdek_point_display_name', $point_name);
        update_post_meta($order_id, '_cdek_point_address', $full_address);
        
        error_log('СДЭК: Автоматически извлечены данные из названия доставки для заказа #' . $order_id);
        error_log('СДЭК: Пункт: ' . $point_name . ', Код: ' . $point_code);
        error_log('СДЭК: Полный адрес: ' . $full_address);
        
        $order->add_order_note('Данные СДЭК автоматически извлечены: ' . $point_name);
    }
    
    // Дополнительно проверяем данные заказа на наличие скрытых полей СДЭК
    if (isset($_POST['shipping_cdek_point_info'])) {
        $cdek_info = sanitize_text_field($_POST['shipping_cdek_point_info']);
        update_post_meta($order_id, '_cdek_shipping_info', $cdek_info);
        error_log('СДЭК: Сохранена дополнительная информация о доставке: ' . $cdek_info);
    }
}

/**
 * Принудительное исправление заказа #745 и подобных
 */
function cdek_maybe_fix_order_745() {
    // Проверяем, нужно ли исправлять заказ 745
    if (isset($_GET['fix_cdek_745']) && current_user_can('manage_woocommerce')) {
        $order_id = 745;
        $order = wc_get_order($order_id);
        
        if ($order) {
            error_log('СДЭК MANUAL FIX: Ручное исправление заказа #' . $order_id);
            
            // Принудительно устанавливаем правильный адрес
            $correct_address = 'Саратов, ул. имени Г.К. Орджоникидзе';
            cdek_force_create_correct_data($order_id, $correct_address, 157);
            
            $order->add_order_note('Данные СДЭК исправлены вручную: ' . $correct_address);
            
            wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&message=cdek_fixed'));
            exit;
        }
    }
}

/**
 * ПРИНУДИТЕЛЬНАЯ обработка информации о доставке в email
 * Работает независимо от других систем
 */
function cdek_force_delivery_info_in_email($order, $sent_to_admin, $plain_text, $email) {
    error_log('СДЭК FORCE: Принудительная обработка email для заказа #' . $order->get_id());
    
    $order_id = $order->get_id();
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods);
    
    if (!$shipping_method) {
        error_log('СДЭК FORCE: Нет метода доставки в заказе');
        return;
    }
    
    $method_title = $shipping_method->get_method_title();
    error_log('СДЭК FORCE: Метод доставки: ' . $method_title);
    
    // Проверяем, что это не самовывоз и не обсуждение
    if (preg_match('/самовывоз|pickup|обсудить/i', $method_title)) {
        error_log('СДЭК FORCE: Это самовывоз или обсуждение, пропускаем');
        return;
    }
    
    // Сначала пытаемся найти ПРАВИЛЬНЫЕ данные СДЭК
    $real_address = cdek_find_real_shipping_address($order_id, $order);
    
    if ($real_address) {
        error_log('СДЭК FORCE: Найден реальный адрес: ' . $real_address);
        
        // Принудительно создаем правильные данные
        cdek_force_create_correct_data($order_id, $real_address, $shipping_method->get_total());
        
        if ($plain_text) {
            cdek_force_render_text_email($real_address, $shipping_method->get_total());
        } else {
            cdek_force_render_html_email($real_address, $shipping_method->get_total());
        }
        return;
    }
    
    // Проверяем, содержит ли название конкретный адрес
    if (preg_match('/ул\.|улица|пр\.|проспект|пер\.|переулок/i', $method_title) || 
        strpos($method_title, ',') !== false) {
        
        error_log('СДЭК FORCE: Найден адрес в названии доставки, принудительно выводим');
        
        // Принудительно извлекаем данные
        cdek_force_extract_shipping_data($order_id, $order);
        
        // Получаем обновленные данные
        $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
        $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
        
        if ($cdek_point_code && $cdek_point_data) {
            error_log('СДЭК FORCE: Данные найдены, выводим в email');
            
            if ($plain_text) {
                cdek_force_render_text_email($method_title, $shipping_method->get_total());
            } else {
                cdek_force_render_html_email($method_title, $shipping_method->get_total());
            }
        } else {
            error_log('СДЭК FORCE: Данные не найдены после извлечения');
        }
    } else {
        error_log('СДЭК FORCE: Не найдено признаков адреса в названии: ' . $method_title);
    }
}

/**
 * Поиск реального адреса доставки СДЭК
 */
function cdek_find_real_shipping_address($order_id, $order) {
    error_log('СДЭК FIND: Ищем реальный адрес для заказа #' . $order_id);
    
    // 1. ПРИОРИТЕТ: Проверяем данные, захваченные JavaScript из блока доставки
    $captured_label = get_post_meta($order_id, '_cdek_shipping_label', true);
    $captured_full = get_post_meta($order_id, '_cdek_shipping_full_address', true);
    $captured_flag = get_post_meta($order_id, '_cdek_shipping_captured', true);
    
    error_log('СДЭК FIND: Проверяем захваченные данные - Flag: ' . ($captured_flag ? 'да' : 'нет') . ', Label: ' . ($captured_label ? $captured_label : 'нет') . ', Full: ' . ($captured_full ? $captured_full : 'нет'));
    
    if ($captured_flag && $captured_label && $captured_label !== 'Выберите пункт выдачи') {
        error_log('СДЭК FIND: Найден адрес из JavaScript захвата: ' . $captured_label);
        
        // Возвращаем полный адрес если есть, иначе лейбл
        if ($captured_full && strlen($captured_full) > strlen($captured_label)) {
            error_log('СДЭК FIND: Используем полный адрес: ' . $captured_full);
            return $captured_full;
        }
        return $captured_label;
    }
    
    // 2. Проверяем данные из $_POST (если они есть при создании заказа)
    if (isset($_POST['cdek_shipping_label']) && $_POST['cdek_shipping_label'] !== 'Выберите пункт выдачи') {
        $post_label = sanitize_text_field($_POST['cdek_shipping_label']);
        error_log('СДЭК FIND: Найден адрес в POST данных: ' . $post_label);
        
        // Сохраняем для будущего использования
        update_post_meta($order_id, '_cdek_shipping_label', $post_label);
        if (isset($_POST['cdek_shipping_full_address'])) {
            update_post_meta($order_id, '_cdek_shipping_full_address', sanitize_text_field($_POST['cdek_shipping_full_address']));
        }
        update_post_meta($order_id, '_cdek_shipping_captured', '1');
        
        return $post_label;
    }
    
    // 3. Проверяем сохраненные данные о выбранном пункте
    $saved_point_data = get_post_meta($order_id, '_cdek_selected_point_data', true);
    if ($saved_point_data) {
        $point_data = json_decode(stripslashes($saved_point_data), true);
        if ($point_data && isset($point_data['name'])) {
            error_log('СДЭК FIND: Найден адрес в _cdek_selected_point_data: ' . $point_data['name']);
            return $point_data['name'];
        }
    }
    
    // 4. Проверяем другие возможные поля
    $possible_fields = array(
        '_cdek_point_display_name',
        '_cdek_point_address',
        '_shipping_cdek_address',
        '_cdek_delivery_address',
        '_selected_pickup_point'
    );
    
    foreach ($possible_fields as $field) {
        $value = get_post_meta($order_id, $field, true);
        if ($value && $value !== 'Выберите пункт выдачи' && strlen($value) > 10) {
            error_log('СДЭК FIND: Найден адрес в поле ' . $field . ': ' . $value);
            return $value;
        }
    }
    
    // 3. Ищем в мета-данных метода доставки
    $shipping_methods = $order->get_shipping_methods();
    foreach ($shipping_methods as $shipping_method) {
        $meta_data = $shipping_method->get_meta_data();
        foreach ($meta_data as $meta) {
            if (isset($meta->key) && isset($meta->value)) {
                $key = $meta->key;
                $value = $meta->value;
                
                // Ищем поля, которые могут содержать адрес
                if (strpos($key, 'address') !== false || 
                    strpos($key, 'point') !== false || 
                    strpos($key, 'cdek') !== false) {
                    
                    if (is_string($value) && $value !== 'Выберите пункт выдачи' && 
                        strlen($value) > 10 && (strpos($value, 'ул.') !== false || strpos($value, ',') !== false)) {
                        error_log('СДЭК FIND: Найден адрес в мета-данных ' . $key . ': ' . $value);
                        return $value;
                    }
                }
            }
        }
    }
    
    // 4. Ищем в данных всего заказа
    $all_meta = get_post_meta($order_id);
    foreach ($all_meta as $key => $values) {
        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_string($value) && $value !== 'Выберите пункт выдачи' && strlen($value) > 10) {
                    
                    // Пропускаем сериализованные данные
                    if (strpos($value, 'a:') === 0) {
                        error_log('СДЭК FIND: Пропускаем сериализованные данные в ' . $key);
                        continue;
                    }
                    
                    // Ищем адресные признаки
                    if (strpos($value, 'ул.') !== false || strpos($value, 'Саратов') !== false) {
                        error_log('СДЭК FIND: Найден возможный адрес в ' . $key . ': ' . $value);
                        return $value;
                    }
                }
            }
        }
    }
    
    error_log('СДЭК FIND: Реальный адрес не найден');
    return false;
}

/**
 * Создание правильных данных СДЭК
 */
function cdek_force_create_correct_data($order_id, $address, $cost) {
    error_log('СДЭК CREATE: Создаем правильные данные для заказа #' . $order_id . ' с адресом: ' . $address);
    
    // ВАЖНО: Если $address это массив (сериализованные данные), извлекаем строку
    if (is_array($address)) {
        error_log('СДЭК CREATE: Адрес передан как массив, извлекаем строку');
        if (isset($address['name'])) {
            $address = $address['name'];
        } elseif (isset($address['location']['address'])) {
            $address = $address['location']['address'];
        } else {
            $address = 'Саратов'; // fallback
        }
    }
    
    // Если это сериализованная строка, пытаемся десериализовать
    if (is_string($address) && strpos($address, 'a:') === 0) {
        error_log('СДЭК CREATE: Обнаружены сериализованные данные, десериализуем');
        $unserialized = @unserialize($address);
        if ($unserialized && is_array($unserialized)) {
            if (isset($unserialized['name'])) {
                $address = $unserialized['name'];
            } elseif (isset($unserialized['location']['address'])) {
                $address = $unserialized['location']['address'];
            } else {
                $address = 'Саратов'; // fallback
            }
        }
    }
    
    // Убеждаемся, что у нас нормальная строка
    if (!is_string($address) || $address === 'Выберите пункт выдачи' || empty($address)) {
        $address = 'Саратов';
    }
    
    error_log('СДЭК CREATE: Итоговый адрес: ' . $address);
    
    $point_data = array(
        'name' => $address,
        'location' => array(
            'city' => 'Саратов',
            'address' => $address,
            'address_full' => $address
        )
    );
    
    $point_code = 'CORRECT_' . substr(md5($address . time()), 0, 8);
    
    // Сохраняем правильные данные
    update_post_meta($order_id, '_cdek_point_code', $point_code);
    update_post_meta($order_id, '_cdek_point_data', $point_data);
    update_post_meta($order_id, '_cdek_delivery_cost', $cost);
    update_post_meta($order_id, '_cdek_point_display_name', $address);
    update_post_meta($order_id, '_cdek_point_address', $address);
    
    error_log('СДЭК CREATE: Сохранены правильные данные - Код: ' . $point_code);
}

/**
 * Принудительное извлечение данных о доставке
 */
function cdek_force_extract_shipping_data($order_id, $order) {
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods);
    
    if (!$shipping_method) {
        return;
    }
    
    $method_title = $shipping_method->get_method_title();
    $shipping_total = $shipping_method->get_total();
    
    error_log('СДЭК FORCE EXTRACT: Обрабатываем: ' . $method_title);
    
    // Принудительно создаем данные на основе названия
    $point_data = array(
        'name' => $method_title,
        'location' => array(
            'city' => 'Саратов',
            'address' => $method_title,
            'address_full' => $method_title
        )
    );
    
    $point_code = 'FORCE_' . substr(md5($method_title . time()), 0, 8);
    
    // Сохраняем данные принудительно
    update_post_meta($order_id, '_cdek_point_code', $point_code);
    update_post_meta($order_id, '_cdek_point_data', $point_data);
    update_post_meta($order_id, '_cdek_delivery_cost', $shipping_total);
    update_post_meta($order_id, '_cdek_point_display_name', $method_title);
    update_post_meta($order_id, '_cdek_point_address', $method_title);
    
    error_log('СДЭК FORCE EXTRACT: Принудительно сохранены данные - Код: ' . $point_code . ', Название: ' . $method_title);
}

/**
 * Принудительный вывод HTML email
 */
function cdek_force_render_html_email($address, $cost) {
    echo '<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: Arial, sans-serif;">';
    echo '<h3 style="color: #28a745; margin-top: 0; border-bottom: 2px solid #28a745; padding-bottom: 10px;">📦 Доставка СДЭК</h3>';
    echo '<p><strong>Пункт выдачи:</strong> ' . esc_html($address) . '</p>';
    
    if ($cost) {
        echo '<p><strong>Стоимость доставки:</strong> <span style="color: #28a745; font-weight: bold;">' . esc_html($cost) . ' руб.</span></p>';
    }
    
    echo '<p><strong>Адрес:</strong> <small style="color: #666;">' . esc_html($address) . '</small></p>';
    echo '<div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border-radius: 4px; font-size: 14px;">';
    echo '<strong>💡 Важно:</strong> Сохраните эту информацию для получения заказа в пункте выдачи СДЭК.';
    echo '</div>';
    echo '</div>';
}

/**
 * Принудительный вывод текстового email
 */
function cdek_force_render_text_email($address, $cost) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК\n";
    echo str_repeat('=', 50) . "\n";
    echo "Пункт выдачи: " . $address . "\n";
    if ($cost) {
        echo "Стоимость доставки: " . $cost . " руб.\n";
    }
    echo "Адрес: " . $address . "\n";
    echo str_repeat('=', 50) . "\n\n";
}

/**
 * Обработка данных доставки после создания заказа
 */
function cdek_process_order_shipping_data($order_id, $posted_data, $order) {
    error_log('СДЭК DEBUG: Дополнительная обработка данных доставки для заказа #' . $order_id);
    
    // Принудительно пытаемся извлечь данные СДЭК
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods);
    
    if ($shipping_method) {
        $method_title = $shipping_method->get_method_title();
        error_log('СДЭК DEBUG: Обработка метода доставки: ' . $method_title);
        
        // Проверяем, не является ли это проблемным заказом с "Выберите пункт выдачи"
        if ($method_title === 'Выберите пункт выдачи') {
            error_log('СДЭК DEBUG: Найден проблемный заказ с "Выберите пункт выдачи", пытаемся исправить');
            cdek_fix_broken_order_shipping($order_id, $order);
        }
        // Если это не самовывоз и не обсуждение с менеджером
        else if (!preg_match('/самовывоз|pickup|обсудить/i', $method_title)) {
            cdek_extract_shipping_data_from_order($order_id, $order);
        }
    }
}

/**
 * Исправление заказов с неправильными данными доставки
 */
function cdek_fix_broken_order_shipping($order_id, $order) {
    error_log('СДЭК FIX: Попытка исправить заказ #' . $order_id);
    
    // Ищем любые данные, которые могли сохраниться при оформлении заказа
    $all_meta = get_post_meta($order_id);
    
    error_log('СДЭК FIX: Все метаданные заказа: ' . print_r(array_keys($all_meta), true));
    
    // Проверяем, есть ли какие-то данные о выбранном пункте
    $saved_point_data = get_post_meta($order_id, '_cdek_selected_point_data', true);
    $saved_point_code = get_post_meta($order_id, '_cdek_selected_point_code', true);
    
    if ($saved_point_data && $saved_point_code) {
        error_log('СДЭК FIX: Найдены сохраненные данные пункта, восстанавливаем');
        
        $point_data = json_decode(stripslashes($saved_point_data), true);
        if ($point_data && is_array($point_data)) {
            // Восстанавливаем правильные данные
            update_post_meta($order_id, '_cdek_point_code', $saved_point_code);
            update_post_meta($order_id, '_cdek_point_data', $point_data);
            
            $point_name = $point_data['name'];
            if (isset($point_data['location']['city'])) {
                $city = $point_data['location']['city'];
                $point_name = $city . ', ' . str_replace($city, '', $point_name);
                $point_name = trim($point_name, ', ');
            }
            
            update_post_meta($order_id, '_cdek_point_display_name', $point_name);
            
            error_log('СДЭК FIX: Восстановлены данные - Код: ' . $saved_point_code . ', Название: ' . $point_name);
            
            $order->add_order_note('Данные СДЭК восстановлены автоматически: ' . $point_name);
            
            return true;
        }
    }
    
    error_log('СДЭК FIX: Не удалось найти сохраненные данные для восстановления');
    return false;
}

/**
 * Добавление JavaScript для захвата данных из блока доставки
 */
function cdek_add_shipping_data_capture_script() {
    // Проверяем, что мы на странице оформления заказа
    if (!function_exists('is_checkout') || !is_checkout()) {
        // Альтернативная проверка для страницы checkout
        global $wp;
        if (!(isset($wp->query_vars['pagename']) && $wp->query_vars['pagename'] === 'checkout') && 
            !is_page('checkout') && strpos($_SERVER['REQUEST_URI'], '/checkout') === false) {
            return;
        }
    }
    ?>
    <script type="text/javascript">
    // Проверяем загрузку jQuery
    if (typeof jQuery === 'undefined') {
        console.error('❌ СДЭК: jQuery не загружен!');
    } else {
        console.log('✅ СДЭК: jQuery найден, версия:', jQuery.fn.jquery);
    }
    
    // Основной код
    (function($) {
        if (typeof $ === 'undefined') {
            console.error('❌ СДЭК: $ не определен, используем прямой вызов jQuery');
            $ = jQuery;
        }
        
        console.log('🔧 СДЭК: Инициализация захвата данных доставки');
        console.log('🔧 СДЭК: URL страницы:', window.location.href);
        
        // Проверяем, что мы на странице checkout
        if (window.location.href.indexOf('checkout') === -1) {
            console.log('⚠️ СДЭК: Не на странице checkout, но скрипт загружен');
        }
        
        // Функция для извлечения данных из блока доставки
        function extractShippingData() {
            console.log('🔍 СДЭК: Ищем данные в блоке доставки');
            
            // Ищем блок с информацией о доставке
            var shippingBlock = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item__label');
            
            if (shippingBlock.length > 0) {
                var shippingText = shippingBlock.text().trim();
                console.log('📍 СДЭК: Найден текст доставки:', shippingText);
                
                // Проверяем, что это не "Выберите пункт выдачи"
                if (shippingText && shippingText !== 'Выберите пункт выдачи' && shippingText.length > 10) {
                    
                    // Ищем стоимость доставки
                    var costElement = shippingBlock.closest('.wc-block-components-totals-item').find('.wc-block-components-totals-item__value');
                    var shippingCost = costElement.length > 0 ? costElement.text().trim().replace(/[^\d]/g, '') : '';
                    
                    // Ищем описание адреса
                    var descElement = shippingBlock.closest('.wc-block-components-totals-item').find('.wc-block-components-totals-item__description small');
                    var fullAddress = descElement.length > 0 ? descElement.text().trim() : shippingText;
                    
                    console.log('💰 СДЭК: Стоимость:', shippingCost);
                    console.log('📍 СДЭК: Полный адрес:', fullAddress);
                    
                    // Создаем или обновляем скрытые поля
                    updateHiddenField('cdek_shipping_label', shippingText);
                    updateHiddenField('cdek_shipping_cost', shippingCost);
                    updateHiddenField('cdek_shipping_full_address', fullAddress);
                    updateHiddenField('cdek_shipping_captured', '1');
                    
                    console.log('✅ СДЭК: Данные сохранены в скрытые поля');
                    return true;
                }
            }
            
            console.log('❌ СДЭК: Данные доставки не найдены');
            return false;
        }
        
        // Функция для создания/обновления скрытого поля
        function updateHiddenField(name, value) {
            var field = $('input[name="' + name + '"]');
            if (field.length === 0) {
                // Ищем форму более агрессивно
                var form = $('form.woocommerce-checkout').first();
                if (form.length === 0) {
                    form = $('form.checkout').first();
                }
                if (form.length === 0) {
                    form = $('.wc-block-checkout__form').first();
                }
                if (form.length === 0) {
                    form = $('form').first();
                }
                if (form.length === 0) {
                    form = $('body');
                }
                
                field = $('<input type="hidden" name="' + name + '" />');
                form.append(field);
                console.log('🔧 СДЭК: Создано скрытое поле:', name, 'в форме:', form.prop('tagName'));
            }
            field.val(value);
            console.log('📝 СДЭК: Обновлено поле', name + ':', value);
            
            // Дополнительная проверка
            setTimeout(function() {
                var checkField = $('input[name="' + name + '"]');
                if (checkField.length > 0 && checkField.val() === value) {
                    console.log('✅ СДЭК: Поле', name, 'успешно создано и содержит:', checkField.val());
                } else {
                    console.error('❌ СДЭК: Проблема с полем', name, '- длина:', checkField.length, 'значение:', checkField.val());
                }
            }, 100);
        }
        
        // Запускаем захват данных при загрузке
        setTimeout(extractShippingData, 1000);
        
        // Отслеживаем изменения в блоке доставки
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    var target = $(mutation.target);
                    if (target.closest('.wp-block-woocommerce-checkout-order-summary-shipping-block').length > 0) {
                        console.log('🔄 СДЭК: Обнаружены изменения в блоке доставки');
                        setTimeout(extractShippingData, 500);
                    }
                }
            });
        });
        
        // Начинаем отслеживание изменений
        var targetNode = document.body;
        observer.observe(targetNode, {
            childList: true,
            subtree: true,
            characterData: true
        });
        
        // Дополнительно запускаем при событиях WooCommerce
        $(document.body).on('updated_checkout updated_shipping_method', function() {
            console.log('🔄 СДЭК: Checkout обновлен, перезапускаем захват данных');
            setTimeout(extractShippingData, 1000);
        });
        
        // Запускаем перед отправкой формы
        $('form.woocommerce-checkout').on('submit', function() {
            console.log('📤 СДЭК: Форма отправляется, финальный захват данных');
            extractShippingData();
        });
        
    })(jQuery); // Передаем jQuery явно
    </script>
    <?php
}

/**
 * Переобработка данных доставки при изменении статуса заказа
 */
function cdek_reprocess_shipping_data_on_status_change($order_id, $old_status, $new_status) {
    // Переобрабатываем только при переходе в обработку/завершение
    if (in_array($new_status, array('processing', 'completed'))) {
        $order = wc_get_order($order_id);
        if ($order) {
            error_log('СДЭК DEBUG: Переобработка данных доставки при смене статуса заказа #' . $order_id);
            cdek_process_order_shipping_data($order_id, array(), $order);
        }
    }
}

/**
 * Отображение информации о типе доставки в админке заказа
 */
function cdek_show_discuss_delivery_admin($order) {
    $order_id = $order->get_id();
    
    // Проверяем тип доставки
    $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
    $pickup_delivery = get_post_meta($order_id, '_pickup_delivery_selected', true);
    $cdek_delivery = get_post_meta($order_id, '_cdek_delivery_selected', true);
    
    if ($discuss_delivery == 'Да') {
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
    } elseif ($pickup_delivery == 'Да') {
        ?>
        <div style="background: #e3f2fd; border: 2px solid #1976d2; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="color: #1976d2; margin: 0; font-size: 16px; display: flex; align-items: center;">
                <span style="font-size: 20px; margin-right: 8px;">🏪</span>
                САМОВЫВОЗ
            </h4>
            <p style="color: #1976d2; font-weight: bold; margin: 8px 0 0 0; font-size: 14px;">
                📍 Адрес: г.Саратов, ул. Осипова, д. 18а
            </p>
            <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 4px;">
                <small style="color: #0d47a1; font-weight: bold;">
                    🕐 Режим работы: пн-пт 9:00-18:00, сб 10:00-16:00
                </small>
            </div>
        </div>
        <?php
    } elseif ($cdek_delivery == 'Да') {
        ?>
        <div style="background: #e8f5e8; border: 2px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="color: #2e7d32; margin: 0; font-size: 16px; display: flex; align-items: center;">
                <span style="font-size: 20px; margin-right: 8px;">📦</span>
                ДОСТАВКА СДЭК
            </h4>
            <p style="color: #2e7d32; font-weight: bold; margin: 8px 0 0 0; font-size: 14px;">
                ✅ Данные доставки сохранены в заказе
            </p>
        </div>
        <?php
    }
}

/**
 * Добавление информации об обсуждении доставки в email уведомления
 * (отключено, так как обработка перенесена в cdek_add_delivery_info_to_any_email)
 */
function cdek_email_discuss_delivery_info($order, $sent_to_admin, $plain_text, $email) {
    // Функция отключена - обработка всех типов доставки происходит в cdek_add_delivery_info_to_any_email
    return;
}