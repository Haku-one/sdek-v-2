<?php
/**
 * Тестовый скрипт для проверки функциональности СДЭК
 * Поместите этот файл в корень сайта и откройте в браузере
 * После тестирования удалите файл!
 */

// Проверяем, что файл запущен в контексте WordPress
if (!defined('ABSPATH')) {
    // Подключаем WordPress
    require_once('./wp-config.php');
    require_once('./wp-load.php');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест функциональности СДЭК</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        .test-section { background: #f9f9f9; border: 1px solid #ddd; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #cce8ff; border-color: #86cfda; color: #0c5460; }
        h1, h2 { color: #333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>🧪 Тест функциональности СДЭК</h1>
    
    <div class="test-section warning">
        <h2>⚠️ Внимание!</h2>
        <p>Этот файл содержит диагностическую информацию о вашем сайте. <strong>Удалите его после тестирования!</strong></p>
    </div>

    <?php
    // 1. Проверка активности WooCommerce
    echo '<div class="test-section">';
    echo '<h2>1. Проверка WooCommerce</h2>';
    
    if (class_exists('WooCommerce')) {
        echo '<div class="test-result success">✅ WooCommerce активен</div>';
        if (defined('WC_VERSION')) {
            echo '<div class="test-result info">ℹ️ Версия WooCommerce: ' . WC_VERSION . '</div>';
        }
    } else {
        echo '<div class="test-result error">❌ WooCommerce не активен</div>';
    }
    echo '</div>';

    // 2. Проверка плагина СДЭК
    echo '<div class="test-section">';
    echo '<h2>2. Проверка плагина СДЭК</h2>';
    
    if (class_exists('CdekDeliveryPlugin')) {
        echo '<div class="test-result success">✅ Плагин СДЭК загружен</div>';
    } else {
        echo '<div class="test-result error">❌ Плагин СДЭК не найден</div>';
    }
    
    if (class_exists('CdekAPI')) {
        echo '<div class="test-result success">✅ СДЭК API класс доступен</div>';
    } else {
        echo '<div class="test-result error">❌ СДЭК API класс не найден</div>';
    }
    echo '</div>';

    // 3. Проверка функций темы
    echo '<div class="test-section">';
    echo '<h2>3. Проверка функций темы</h2>';
    
    if (function_exists('cdek_theme_init')) {
        echo '<div class="test-result success">✅ Функция cdek_theme_init существует</div>';
    } else {
        echo '<div class="test-result error">❌ Функция cdek_theme_init не найдена</div>';
    }
    
    if (function_exists('cdek_save_discuss_delivery_choice')) {
        echo '<div class="test-result success">✅ Функция cdek_save_discuss_delivery_choice существует</div>';
    } else {
        echo '<div class="test-result error">❌ Функция cdek_save_discuss_delivery_choice не найдена</div>';
    }
    
    if (function_exists('cdek_show_discuss_delivery_admin')) {
        echo '<div class="test-result success">✅ Функция cdek_show_discuss_delivery_admin существует</div>';
    } else {
        echo '<div class="test-result error">❌ Функция cdek_show_discuss_delivery_admin не найдена</div>';
    }
    echo '</div>';

    // 4. Проверка email шаблонов
    echo '<div class="test-section">';
    echo '<h2>4. Проверка email шаблонов</h2>';
    
    $theme_dir = get_template_directory();
    $admin_template = $theme_dir . '/woocommerce/emails/admin-new-order.php';
    $customer_template = $theme_dir . '/woocommerce/emails/customer-completed-order.php';
    
    if (file_exists($admin_template)) {
        echo '<div class="test-result success">✅ Шаблон admin-new-order.php найден в теме</div>';
    } else {
        echo '<div class="test-result warning">⚠️ Шаблон admin-new-order.php не найден в теме</div>';
        echo '<div class="test-result info">ℹ️ Путь: ' . $admin_template . '</div>';
    }
    
    if (file_exists($customer_template)) {
        echo '<div class="test-result success">✅ Шаблон customer-completed-order.php найден в теме</div>';
    } else {
        echo '<div class="test-result warning">⚠️ Шаблон customer-completed-order.php не найден в теме</div>';
        echo '<div class="test-result info">ℹ️ Путь: ' . $customer_template . '</div>';
    }
    echo '</div>';

    // 5. Проверка настроек СДЭК
    echo '<div class="test-section">';
    echo '<h2>5. Настройки СДЭК</h2>';
    
    $yandex_key = get_option('cdek_yandex_api_key');
    if ($yandex_key) {
        echo '<div class="test-result success">✅ API ключ Яндекс.Карт: ' . substr($yandex_key, 0, 10) . '...</div>';
    } else {
        echo '<div class="test-result warning">⚠️ API ключ Яндекс.Карт не установлен</div>';
    }
    
    $cdek_account = get_option('cdek_account');
    if ($cdek_account) {
        echo '<div class="test-result success">✅ СДЭК Account: ' . substr($cdek_account, 0, 8) . '...</div>';
    } else {
        echo '<div class="test-result warning">⚠️ СДЭК Account не установлен</div>';
    }
    
    $sender_city = get_option('cdek_sender_city');
    if ($sender_city) {
        echo '<div class="test-result success">✅ Город отправителя: ' . $sender_city . '</div>';
    } else {
        echo '<div class="test-result warning">⚠️ Город отправителя не установлен</div>';
    }
    echo '</div>';

    // 6. Проверка последних заказов с СДЭК
    echo '<div class="test-section">';
    echo '<h2>6. Последние заказы с СДЭК данными</h2>';
    
    if (class_exists('WooCommerce')) {
        $orders = wc_get_orders(array(
            'limit' => 5,
            'meta_key' => '_cdek_point_code',
            'meta_compare' => 'EXISTS'
        ));
        
        if (!empty($orders)) {
            echo '<div class="test-result success">✅ Найдено заказов с СДЭК данными: ' . count($orders) . '</div>';
            
            foreach ($orders as $order) {
                $cdek_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
                $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
                
                echo '<div class="test-result info">';
                echo 'Заказ #' . $order->get_id() . ' - СДЭК код: ' . $cdek_code;
                if ($discuss_delivery) {
                    echo ' | Обсуждение: ' . $discuss_delivery;
                }
                echo '</div>';
            }
        } else {
            echo '<div class="test-result warning">⚠️ Заказы с СДЭК данными не найдены</div>';
        }
    }
    echo '</div>';

    // 7. Проверка активных хуков
    echo '<div class="test-section">';
    echo '<h2>7. Активные хуки СДЭК</h2>';
    
    global $wp_filter;
    
    $cdek_hooks = array(
        'woocommerce_checkout_update_order_meta',
        'woocommerce_admin_order_data_after_shipping_address',
        'woocommerce_email_order_details'
    );
    
    foreach ($cdek_hooks as $hook) {
        if (isset($wp_filter[$hook])) {
            $hook_functions = array();
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && is_string($callback['function'][1])) {
                        if (strpos($callback['function'][1], 'cdek') !== false) {
                            $hook_functions[] = $callback['function'][1] . ' (приоритет: ' . $priority . ')';
                        }
                    } elseif (is_string($callback['function']) && strpos($callback['function'], 'cdek') !== false) {
                        $hook_functions[] = $callback['function'] . ' (приоритет: ' . $priority . ')';
                    }
                }
            }
            
            if (!empty($hook_functions)) {
                echo '<div class="test-result success">✅ ' . $hook . ':</div>';
                foreach ($hook_functions as $func) {
                    echo '<div class="test-result info">   - ' . $func . '</div>';
                }
            } else {
                echo '<div class="test-result warning">⚠️ ' . $hook . ': СДЭК функции не найдены</div>';
            }
        } else {
            echo '<div class="test-result warning">⚠️ ' . $hook . ': хук не зарегистрирован</div>';
        }
    }
    echo '</div>';

    // 8. Рекомендации
    echo '<div class="test-section info">';
    echo '<h2>8. Рекомендации</h2>';
    
    if (!function_exists('cdek_theme_init')) {
        echo '<div class="test-result warning">';
        echo '⚠️ <strong>Функции темы не подключены</strong><br>';
        echo 'Следуйте инструкции в файле <code>README-email-template-setup.md</code>';
        echo '</div>';
    }
    
    if (!file_exists($admin_template) && !file_exists($customer_template)) {
        echo '<div class="test-result warning">';
        echo '⚠️ <strong>Email шаблоны не установлены в тему</strong><br>';
        echo 'Скопируйте файлы из папки <code>woocommerce-email-templates/</code> в тему';
        echo '</div>';
    }
    
    echo '<div class="test-result success">';
    echo '✅ <strong>Для полного тестирования:</strong><br>';
    echo '1. Создайте тестовый заказ с доставкой СДЭК<br>';
    echo '2. Попробуйте выбрать "Обсудить доставку с менеджером"<br>';
    echo '3. Проверьте email уведомления<br>';
    echo '4. Посмотрите заказ в админке';
    echo '</div>';
    echo '</div>';
    ?>

    <div class="test-section error">
        <h2>🗑️ Удаление файла</h2>
        <p>После завершения тестирования <strong>обязательно удалите этот файл</strong> с сервера для безопасности!</p>
        <a href="#" onclick="if(confirm('Удалить тестовый файл?')) { window.location.href='?delete_test_file=1'; }" class="btn">Удалить файл</a>
    </div>

    <?php
    // Самоудаление файла
    if (isset($_GET['delete_test_file']) && $_GET['delete_test_file'] == '1') {
        if (unlink(__FILE__)) {
            echo '<div class="test-section success"><h2>✅ Файл успешно удален</h2></div>';
            echo '<script>setTimeout(function(){ window.location.href = "/"; }, 2000);</script>';
        } else {
            echo '<div class="test-section error"><h2>❌ Ошибка удаления файла</h2><p>Удалите файл вручную</p></div>';
        }
        exit;
    }
    ?>

</body>
</html>