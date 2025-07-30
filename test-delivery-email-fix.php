<?php
/**
 * Тестовый скрипт для проверки исправлений передачи данных доставки в email
 * 
 * Этот файл нужно поместить в корень WordPress сайта и вызвать через браузер
 * для тестирования работы исправлений
 * 
 * @package CDEK_Delivery_Test
 * @version 1.0.0
 */

// Загружаем WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Проверяем, что WooCommerce активен
if (!class_exists('WooCommerce')) {
    die('WooCommerce не активен');
}

echo '<h1>🧪 Тест исправлений передачи данных доставки в email</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
    .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
    .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    .button { background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
</style>';

// 1. Проверяем существование необходимых файлов
echo '<div class="test-section">';
echo '<h2>📁 Проверка файлов</h2>';

$required_files = [
    'cdek-delivery-data-handler.php',
    'cdek-delivery.js', 
    'theme-functions-cdek.php',
    'woocommerce-email-templates/admin-new-order.php',
    'woocommerce-email-templates/customer-completed-order.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ Файл существует: $file</div>";
    } else {
        echo "<div class='error'>❌ Файл не найден: $file</div>";
    }
}
echo '</div>';

// 2. Проверяем активацию обработчика данных
echo '<div class="test-section">';
echo '<h2>🔧 Проверка обработчика данных</h2>';

if (class_exists('CDEK_Delivery_Data_Handler')) {
    echo '<div class="success">✅ Класс CDEK_Delivery_Data_Handler найден</div>';
    
    // Создаем экземпляр и проверяем хуки
    $handler = new CDEK_Delivery_Data_Handler();
    
    // Проверяем зарегистрированные хуки
    global $wp_filter;
    $hooks_to_check = [
        'woocommerce_checkout_update_order_meta',
        'woocommerce_store_api_checkout_update_order_meta', 
        'woocommerce_blocks_checkout_order_processed'
    ];
    
    foreach ($hooks_to_check as $hook) {
        if (isset($wp_filter[$hook])) {
            echo "<div class='success'>✅ Хук зарегистрирован: $hook</div>";
        } else {
            echo "<div class='warning'>⚠️ Хук не найден: $hook</div>";
        }
    }
} else {
    echo '<div class="error">❌ Класс CDEK_Delivery_Data_Handler не найден</div>';
}
echo '</div>';

// 3. Проверяем функции темы
echo '<div class="test-section">';
echo '<h2>🎨 Проверка функций темы</h2>';

if (function_exists('cdek_save_discuss_delivery_choice')) {
    echo '<div class="success">✅ Функция cdek_save_discuss_delivery_choice найдена</div>';
} else {
    echo '<div class="error">❌ Функция cdek_save_discuss_delivery_choice не найдена</div>';
}

if (function_exists('cdek_show_discuss_delivery_admin')) {
    echo '<div class="success">✅ Функция cdek_show_discuss_delivery_admin найдена</div>';
} else {
    echo '<div class="error">❌ Функция cdek_show_discuss_delivery_admin не найдена</div>';
}

if (function_exists('cdek_email_discuss_delivery_info')) {
    echo '<div class="success">✅ Функция cdek_email_discuss_delivery_info найдена</div>';
} else {
    echo '<div class="error">❌ Функция cdek_email_discuss_delivery_info не найдена</div>';
}
echo '</div>';

// 4. Создаем тестовый заказ для проверки
echo '<div class="test-section">';
echo '<h2>🛒 Создание тестового заказа</h2>';

try {
    // Создаем простой заказ
    $order = wc_create_order();
    $order->set_status('pending');
    $order->set_customer_id(1);
    
    // Добавляем тестовый товар
    $product_id = wc_get_products(['limit' => 1])[0]->get_id();
    $order->add_product(wc_get_product($product_id), 1);
    
    // Устанавливаем адрес
    $order->set_billing_first_name('Тест');
    $order->set_billing_last_name('Тестович');
    $order->set_billing_email('test@example.com');
    $order->set_billing_phone('+79876543210');
    
    $order->calculate_totals();
    $order->save();
    
    $order_id = $order->get_id();
    echo "<div class='success'>✅ Создан тестовый заказ #$order_id</div>";
    
    // Тестируем сохранение данных обсуждения доставки
    echo '<h3>Тест 1: Обсуждение доставки</h3>';
    
    // Симулируем $_POST данные
    $_POST['discuss_delivery_selected'] = '1';
    
    // Вызываем функцию сохранения
    if (function_exists('cdek_save_discuss_delivery_choice')) {
        cdek_save_discuss_delivery_choice($order_id);
        
        // Проверяем результат
        $saved_value = get_post_meta($order_id, '_discuss_delivery_selected', true);
        if ($saved_value === 'Да') {
            echo '<div class="success">✅ Данные обсуждения доставки сохранены корректно</div>';
        } else {
            echo '<div class="error">❌ Данные обсуждения доставки не сохранены или некорректны: ' . $saved_value . '</div>';
        }
    }
    
    // Тестируем сохранение через обработчик данных
    echo '<h3>Тест 2: Обработчик данных</h3>';
    
    if (class_exists('CDEK_Delivery_Data_Handler')) {
        $handler = new CDEK_Delivery_Data_Handler();
        $handler->save_delivery_meta_data($order_id);
        
        // Проверяем результат
        $saved_value = get_post_meta($order_id, '_discuss_delivery_selected', true);
        if ($saved_value === 'Да') {
            echo '<div class="success">✅ Обработчик данных работает корректно</div>';
        } else {
            echo '<div class="warning">⚠️ Обработчик данных: значение ' . $saved_value . '</div>';
        }
    }
    
    // Тестируем email шаблон
    echo '<h3>Тест 3: Email шаблон</h3>';
    
    // Проверяем email шаблон админа
    if (file_exists('woocommerce-email-templates/admin-new-order.php')) {
        ob_start();
        include 'woocommerce-email-templates/admin-new-order.php';
        $email_content = ob_get_clean();
        
        if (strpos($email_content, 'ТРЕБУЕТСЯ ОБСУЖДЕНИЕ ДОСТАВКИ') !== false) {
            echo '<div class="success">✅ Email шаблон админа работает корректно</div>';
        } else {
            echo '<div class="error">❌ Email шаблон админа не показывает информацию об обсуждении</div>';
        }
    }
    
    // Очищаем $_POST
    unset($_POST['discuss_delivery_selected']);
    
    echo "<div class='info'>📧 Проверьте заказ #$order_id в админке WordPress</div>";
    echo "<a href='/wp-admin/post.php?post=$order_id&action=edit' class='button' target='_blank'>Открыть заказ в админке</a>";
    
    // Удаляем тестовый заказ
    echo '<br><br>';
    echo "<a href='?delete_test_order=$order_id' class='button' style='background: #dc3545;'>Удалить тестовый заказ</a>";
    
} catch (Exception $e) {
    echo '<div class="error">❌ Ошибка создания тестового заказа: ' . $e->getMessage() . '</div>';
}
echo '</div>';

// Обработка удаления тестового заказа
if (isset($_GET['delete_test_order'])) {
    $order_id = intval($_GET['delete_test_order']);
    wp_delete_post($order_id, true);
    echo '<div class="success">✅ Тестовый заказ #' . $order_id . ' удален</div>';
}

// 5. Проверяем логи
echo '<div class="test-section">';
echo '<h2>📋 Последние записи в логах</h2>';

$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $recent_logs = array_slice(explode("\n", $logs), -20);
    $cdek_logs = array_filter($recent_logs, function($log) {
        return strpos($log, 'СДЭК') !== false;
    });
    
    if (!empty($cdek_logs)) {
        echo '<div class="info">Последние записи СДЭК в логах:</div>';
        echo '<pre>' . implode("\n", $cdek_logs) . '</pre>';
    } else {
        echo '<div class="warning">⚠️ Записи СДЭК в логах не найдены</div>';
    }
} else {
    echo '<div class="warning">⚠️ Файл логов не найден или логирование отключено</div>';
}
echo '</div>';

echo '<div class="test-section info">';
echo '<h2>📋 Инструкции для тестирования</h2>';
echo '<ol>
    <li>Убедитесь, что все проверки выше прошли успешно</li>
    <li>Перейдите на страницу оформления заказа на вашем сайте</li>
    <li>Выберите "Обсудить доставку с менеджером"</li>
    <li>Оформите заказ</li>
    <li>Проверьте email уведомление администратору</li>
    <li>Проверьте заказ в админке WordPress</li>
</ol>';
echo '</div>';

echo '<div class="test-section">';
echo '<h2>🔄 Перезапустить тест</h2>';
echo '<a href="?" class="button">Обновить страницу</a>';
echo '</div>';
?>