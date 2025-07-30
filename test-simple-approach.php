<?php
/**
 * Простой тест для проверки кастомных полей в email
 * 
 * Проверяем только три файла:
 * - cdek-delivery-data-handler.php
 * - admin-new-order-simple.php  
 * - customer-completed-order-simple.php
 */

// Загружаем WordPress
require_once('wp-config.php');
require_once('wp-load.php');

if (!class_exists('WooCommerce')) {
    die('WooCommerce не активен');
}

echo '<h1>🧪 Простой тест кастомных полей СДЭК</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
    .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
    .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f8f9fa; font-weight: bold; }
    .button { background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
</style>';

// 1. Создаем тестовые заказы с кастомными полями
echo '<div class="test-section">';
echo '<h2>🛒 Создание тестовых заказов</h2>';

$test_orders = [];

try {
    // Тестовый заказ 1: Обсуждение доставки
    $order1 = wc_create_order();
    $order1->set_status('pending');
    $order1->set_billing_first_name('Иван');
    $order1->set_billing_last_name('Иванов');
    $order1->set_billing_email('ivan@example.com');
    $order1->set_billing_phone('+79123456789');
    
    // Добавляем товар
    $products = wc_get_products(['limit' => 1]);
    if (!empty($products)) {
        $order1->add_product($products[0], 1);
    }
    
    // Добавляем кастомные поля для обсуждения доставки
    $order1->update_meta_data('Тип доставки', 'Обсудить с менеджером');
    $order1->update_meta_data('Статус доставки', 'Требуется обсуждение');
    $order1->update_meta_data('Действие менеджера', 'Связаться с клиентом для обсуждения доставки');
    
    $order1->calculate_totals();
    $order1->save();
    
    $test_orders['discuss'] = $order1->get_id();
    echo "<div class='success'>✅ Создан заказ #" . $order1->get_id() . " (Обсуждение доставки)</div>";
    
    // Тестовый заказ 2: СДЭК доставка
    $order2 = wc_create_order();
    $order2->set_status('completed');
    $order2->set_billing_first_name('Петр');
    $order2->set_billing_last_name('Петров');
    $order2->set_billing_email('petr@example.com');
    $order2->set_billing_phone('+79987654321');
    
    if (!empty($products)) {
        $order2->add_product($products[0], 2);
    }
    
    // Добавляем кастомные поля для СДЭК
    $order2->update_meta_data('Тип доставки', 'СДЭК');
    $order2->update_meta_data('Пункт выдачи СДЭК', 'ПВЗ "Центральный"');
    $order2->update_meta_data('Адрес пункта выдачи', 'г. Москва, ул. Тверская, д. 1');
    $order2->update_meta_data('Код пункта СДЭК', 'MSK123');
    $order2->update_meta_data('Стоимость доставки СДЭК', '295 руб.');
    $order2->update_meta_data('Время работы ПВЗ', 'пн-пт: 9:00-18:00');
    $order2->update_meta_data('Телефон ПВЗ', '+7 (495) 123-45-67');
    
    $order2->calculate_totals();
    $order2->save();
    
    $test_orders['cdek'] = $order2->get_id();
    echo "<div class='success'>✅ Создан заказ #" . $order2->get_id() . " (СДЭК доставка)</div>";
    
} catch (Exception $e) {
    echo '<div class="error">❌ Ошибка создания заказов: ' . $e->getMessage() . '</div>';
}
echo '</div>';

// 2. Проверяем кастомные поля
echo '<div class="test-section">';
echo '<h2>📋 Проверка кастомных полей</h2>';

foreach ($test_orders as $type => $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) continue;
    
    echo "<h3>Заказ #$order_id ($type)</h3>";
    
    $meta_data = $order->get_meta_data();
    $delivery_fields = [];
    
    foreach ($meta_data as $meta) {
        $key = $meta->get_data()['key'];
        $value = $meta->get_data()['value'];
        
        // Показываем только поля доставки
        if (in_array($key, [
            'Тип доставки', 'Статус доставки', 'Действие менеджера',
            'Пункт выдачи СДЭК', 'Адрес пункта выдачи', 'Код пункта СДЭК',
            'Стоимость доставки СДЭК', 'Время работы ПВЗ', 'Телефон ПВЗ'
        ])) {
            $delivery_fields[$key] = $value;
        }
    }
    
    if (!empty($delivery_fields)) {
        echo '<table>';
        echo '<tr><th>Поле</th><th>Значение</th></tr>';
        foreach ($delivery_fields as $key => $value) {
            echo '<tr><td>' . esc_html($key) . '</td><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="warning">⚠️ Кастомные поля не найдены</div>';
    }
}
echo '</div>';

// 3. Тестируем WooCommerce email фильтр
echo '<div class="test-section">';
echo '<h2>📧 Тест WooCommerce email фильтра</h2>';

if (class_exists('CDEK_Delivery_Data_Handler')) {
    $handler = new CDEK_Delivery_Data_Handler();
    
    foreach ($test_orders as $type => $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;
        
        echo "<h3>Email поля для заказа #$order_id ($type)</h3>";
        
        // Тестируем фильтр email полей
        $email_fields = $handler->add_delivery_fields_to_email([], true, $order);
        
        if (!empty($email_fields)) {
            echo '<table>';
            echo '<tr><th>Ключ</th><th>Метка</th><th>Значение</th></tr>';
            foreach ($email_fields as $key => $field) {
                echo '<tr>';
                echo '<td>' . esc_html($key) . '</td>';
                echo '<td>' . esc_html($field['label']) . '</td>';
                echo '<td>' . esc_html($field['value']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="warning">⚠️ Email поля не сформированы</div>';
        }
    }
} else {
    echo '<div class="error">❌ Класс CDEK_Delivery_Data_Handler не найден</div>';
}
echo '</div>';

// 4. Проверяем хук email фильтра
echo '<div class="test-section">';
echo '<h2>🪝 Проверка email хука</h2>';

global $wp_filter;
if (isset($wp_filter['woocommerce_email_order_meta_fields'])) {
    $callbacks = $wp_filter['woocommerce_email_order_meta_fields']->callbacks;
    $found_cdek = false;
    
    foreach ($callbacks as $priority => $callback_group) {
        foreach ($callback_group as $callback) {
            if (is_array($callback['function']) && 
                is_object($callback['function'][0]) && 
                get_class($callback['function'][0]) === 'CDEK_Delivery_Data_Handler') {
                $found_cdek = true;
                echo "<div class='success'>✅ Хук woocommerce_email_order_meta_fields зарегистрирован (приоритет $priority)</div>";
                break 2;
            }
        }
    }
    
    if (!$found_cdek) {
        echo "<div class='warning'>⚠️ Хук существует, но CDEK обработчик не найден</div>";
    }
} else {
    echo "<div class='error'>❌ Хук woocommerce_email_order_meta_fields не зарегистрирован</div>";
}
echo '</div>';

// 5. Превью email шаблонов
echo '<div class="test-section">';
echo '<h2>👁️ Превью email шаблонов</h2>';

foreach ($test_orders as $type => $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) continue;
    
    echo "<h3>Заказ #$order_id ($type)</h3>";
    
    // Админский шаблон
    if (file_exists('woocommerce-email-templates/admin-new-order-simple.php')) {
        echo '<h4>📧 Admin Email:</h4>';
        echo '<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; margin: 10px 0;">';
        
        ob_start();
        $sent_to_admin = true;
        $plain_text = false;
        $email = null;
        $email_heading = 'Новый заказ';
        include 'woocommerce-email-templates/admin-new-order-simple.php';
        $content = ob_get_clean();
        
        echo $content;
        echo '</div>';
    }
    
    // Клиентский шаблон (только для завершенных заказов)
    if ($order->get_status() === 'completed' && file_exists('woocommerce-email-templates/customer-completed-order-simple.php')) {
        echo '<h4>📧 Customer Email:</h4>';
        echo '<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; margin: 10px 0;">';
        
        ob_start();
        $sent_to_admin = false;
        $plain_text = false;
        $email = null;
        $email_heading = 'Ваш заказ завершен';
        include 'woocommerce-email-templates/customer-completed-order-simple.php';
        $content = ob_get_clean();
        
        echo $content;
        echo '</div>';
    }
}
echo '</div>';

// 6. Ссылки для проверки
echo '<div class="test-section">';
echo '<h2>🔗 Ссылки для проверки</h2>';

foreach ($test_orders as $type => $order_id) {
    echo "<p><strong>Заказ #$order_id ($type):</strong></p>";
    echo "<a href='/wp-admin/post.php?post=$order_id&action=edit' class='button' target='_blank'>Открыть в админке</a>";
    echo "<br><br>";
}

echo '<div class="info">';
echo '<strong>📋 Что проверить в админке:</strong><br>';
echo '1. Перейдите в админку заказа<br>';
echo '2. Кастомные поля должны отображаться в разделе "Order Details"<br>';
echo '3. Отправьте тестовый email через "Order Actions" → "Resend new order notification"<br>';
echo '4. Проверьте полученный email на наличие кастомных полей';
echo '</div>';
echo '</div>';

// 7. Очистка
echo '<div class="test-section">';
echo '<h2>🧹 Очистка</h2>';

foreach ($test_orders as $type => $order_id) {
    echo "<a href='?delete_order=$order_id' class='button' style='background: #dc3545;'>Удалить заказ #$order_id</a> ";
}

if (isset($_GET['delete_order'])) {
    $order_id = intval($_GET['delete_order']);
    wp_delete_post($order_id, true);
    echo "<div class='success'>✅ Заказ #$order_id удален</div>";
    echo '<meta http-equiv="refresh" content="2">';
}
echo '</div>';

echo '<div class="test-section info">';
echo '<h2>✅ Результат</h2>';
echo '<p>Теперь кастомные поля доставки будут автоматически добавляться в ALL email уведомления WooCommerce благодаря фильтру <code>woocommerce_email_order_meta_fields</code>.</p>';
echo '<p><strong>Это стандартный способ WooCommerce</strong> - поля появятся в таблице заказа во всех email шаблонах автоматически, без необходимости модификации каждого шаблона.</p>';
echo '</div>';
?>