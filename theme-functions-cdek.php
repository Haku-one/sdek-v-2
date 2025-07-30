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