<?php
/**
 * Тестовый скрипт для проверки интеграции с WooCommerce Blocks
 * 
 * @package CDEK_Delivery_Blocks_Test
 * @version 1.0.0
 */

// Загружаем WordPress
require_once('wp-config.php');
require_once('wp-load.php');

if (!class_exists('WooCommerce')) {
    die('WooCommerce не активен');
}

echo '<h1>🧪 Тест интеграции с WooCommerce Blocks</h1>';
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

// 1. Проверяем файлы
echo '<div class="test-section">';
echo '<h2>📁 Проверка файлов интеграции</h2>';

$files_to_check = [
    'woocommerce-blocks-integration.php' => 'Интеграция с WooCommerce Blocks',
    'cdek-delivery.js' => 'JavaScript файл СДЭК',
    'cdek-delivery-plugin.php' => 'Основной файл плагина'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $description: $file</div>";
    } else {
        echo "<div class='error'>❌ $description: $file НЕ НАЙДЕН</div>";
    }
}
echo '</div>';

// 2. Проверяем классы
echo '<div class="test-section">';
echo '<h2>🔧 Проверка классов</h2>';

if (class_exists('CDEK_WooCommerce_Blocks_Integration')) {
    echo '<div class="success">✅ Класс CDEK_WooCommerce_Blocks_Integration найден</div>';
    
    // Проверяем методы класса
    $reflection = new ReflectionClass('CDEK_WooCommerce_Blocks_Integration');
    $methods = $reflection->getMethods();
    
    echo '<div class="info">Доступные методы:<ul>';
    foreach ($methods as $method) {
        if ($method->isPublic() && !$method->isConstructor()) {
            echo '<li>' . $method->getName() . '</li>';
        }
    }
    echo '</ul></div>';
} else {
    echo '<div class="error">❌ Класс CDEK_WooCommerce_Blocks_Integration не найден</div>';
}

if (class_exists('CdekDeliveryPlugin')) {
    echo '<div class="success">✅ Основной класс CdekDeliveryPlugin найден</div>';
} else {
    echo '<div class="error">❌ Основной класс CdekDeliveryPlugin не найден</div>';
}
echo '</div>';

// 3. Проверяем хуки WooCommerce
echo '<div class="test-section">';
echo '<h2>🪝 Проверка хуков WooCommerce</h2>';

global $wp_filter;

$hooks_to_check = [
    'woocommerce_store_api_checkout_update_order_meta',
    'woocommerce_blocks_checkout_order_processed', 
    'woocommerce_store_api_checkout_order_processed',
    'init'
];

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        $callbacks = $wp_filter[$hook]->callbacks;
        $found_cdek = false;
        
        foreach ($callbacks as $priority => $callback_group) {
            foreach ($callback_group as $callback) {
                if (is_array($callback['function']) && 
                    is_object($callback['function'][0]) && 
                    get_class($callback['function'][0]) === 'CDEK_WooCommerce_Blocks_Integration') {
                    $found_cdek = true;
                    echo "<div class='success'>✅ Хук $hook зарегистрирован (приоритет $priority, метод {$callback['function'][1]})</div>";
                    break 2;
                }
            }
        }
        
        if (!$found_cdek) {
            echo "<div class='warning'>⚠️ Хук $hook существует, но CDEK обработчик не найден</div>";
        }
    } else {
        echo "<div class='error'>❌ Хук $hook не зарегистрирован</div>";
    }
}
echo '</div>';

// 4. Тестируем WooCommerce Store API
echo '<div class="test-section">';
echo '<h2>🌐 Проверка WooCommerce Store API</h2>';

// Проверяем, доступен ли Store API
if (class_exists('\Automattic\WooCommerce\StoreApi\StoreApi')) {
    echo '<div class="success">✅ WooCommerce Store API доступен</div>';
    
    // Проверяем версию
    if (defined('WC_VERSION')) {
        echo '<div class="info">WooCommerce версия: ' . WC_VERSION . '</div>';
        
        if (version_compare(WC_VERSION, '5.0', '>=')) {
            echo '<div class="success">✅ Версия WooCommerce поддерживает Blocks</div>';
        } else {
            echo '<div class="warning">⚠️ Версия WooCommerce может не полностью поддерживать Blocks</div>';
        }
    }
} else {
    echo '<div class="error">❌ WooCommerce Store API недоступен</div>';
}

// Проверяем endpoint checkout
$checkout_url = home_url('/wp-json/wc/store/v1/checkout');
echo "<div class='info'>Endpoint checkout: <a href='$checkout_url' target='_blank'>$checkout_url</a></div>";

echo '</div>';

// 5. Симуляция данных blocks
echo '<div class="test-section">';
echo '<h2>🧪 Симуляция обработки данных</h2>';

if (class_exists('CDEK_WooCommerce_Blocks_Integration')) {
    try {
        // Создаем тестовый заказ
        $order = wc_create_order();
        $order->set_status('pending');
        $order->set_billing_email('test@example.com');
        $order->save();
        
        echo "<div class='success'>✅ Создан тестовый заказ #{$order->get_id()}</div>";
        
        // Симулируем данные из blocks
        $_POST['discuss_delivery_selected'] = '1';
        
        // Тестируем обработчик
        $blocks_integration = new CDEK_WooCommerce_Blocks_Integration();
        $blocks_integration->save_blocks_delivery_data($order);
        
        // Проверяем результат
        $delivery_type = $order->get_meta('Тип доставки');
        if ($delivery_type === 'Обсудить с менеджером') {
            echo '<div class="success">✅ Кастомные поля корректно сохранены</div>';
            echo '<div class="info">Тип доставки: ' . $delivery_type . '</div>';
        } else {
            echo '<div class="warning">⚠️ Кастомные поля не сохранились или некорректны</div>';
        }
        
        // Очищаем тестовые данные
        wp_delete_post($order->get_id(), true);
        unset($_POST['discuss_delivery_selected']);
        echo "<div class='info'>Тестовый заказ удален</div>";
        
    } catch (Exception $e) {
        echo '<div class="error">❌ Ошибка симуляции: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div class="error">❌ Класс интеграции недоступен для тестирования</div>';
}

echo '</div>';

// 6. Проверяем логи
echo '<div class="test-section">';
echo '<h2>📋 Проверка логов</h2>';

$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $recent_logs = array_slice(explode("\n", $logs), -30);
    $blocks_logs = array_filter($recent_logs, function($log) {
        return strpos($log, 'СДЭК Blocks') !== false || strpos($log, 'CDEK_WooCommerce_Blocks') !== false;
    });
    
    if (!empty($blocks_logs)) {
        echo '<div class="info">Последние записи Blocks в логах:</div>';
        echo '<pre>' . implode("\n", $blocks_logs) . '</pre>';
    } else {
        echo '<div class="warning">⚠️ Записи Blocks в логах не найдены</div>';
    }
} else {
    echo '<div class="warning">⚠️ Файл логов не найден или логирование отключено</div>';
}
echo '</div>';

// 7. Инструкции
echo '<div class="test-section info">';
echo '<h2>📋 Следующие шаги</h2>';
echo '<ol>';
echo '<li>Убедитесь, что все проверки выше прошли успешно</li>';
echo '<li>Очистите кэши WordPress и WooCommerce</li>';
echo '<li>Перейдите на страницу checkout вашего сайта</li>';
echo '<li>Откройте консоль браузера (F12)</li>';
echo '<li>Попробуйте выбрать "Обсудить доставку с менеджером"</li>';
echo '<li>Проверьте логи в консоли и логи WordPress</li>';
echo '<li>Попробуйте оформить тестовый заказ</li>';
echo '</ol>';

echo '<div class="warning">';
echo '<strong>⚠️ Если все еще ошибка 500:</strong><br>';
echo '1. Проверьте логи ошибок PHP<br>';
echo '2. Временно отключите другие плагины<br>';
echo '3. Убедитесь в совместимости темы с WooCommerce Blocks<br>';
echo '4. Проверьте настройки checkout (возможно используется классический checkout)';
echo '</div>';

echo '</div>';
?>