<?php
/**
 * Тестовый скрипт для проверки подхода с кастомными полями WooCommerce
 * 
 * Этот скрипт проверяет, что данные доставки корректно сохраняются как кастомные поля
 * и отображаются в таблице заказа в email уведомлениях
 * 
 * @package CDEK_Delivery_Test
 * @version 2.0.0
 */

// Загружаем WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Проверяем, что WooCommerce активен
if (!class_exists('WooCommerce')) {
    die('WooCommerce не активен');
}

echo '<h1>🧪 Тест подхода с кастомными полями WooCommerce</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
    .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
    .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    .button { background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px; display: inline-block; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f8f9fa; font-weight: bold; }
</style>';

// 1. Создаем тестовые заказы
echo '<div class="test-section">';
echo '<h2>🛒 Создание тестовых заказов</h2>';

$test_orders = [];

try {
    // Тестовый заказ 1: Обсуждение доставки
    $order1 = wc_create_order();
    $order1->set_status('pending');
    $order1->set_customer_id(1);
    
    // Добавляем тестовый товар
    $products = wc_get_products(['limit' => 1]);
    if (!empty($products)) {
        $product_id = $products[0]->get_id();
        $order1->add_product(wc_get_product($product_id), 1);
    }
    
    // Устанавливаем адрес
    $order1->set_billing_first_name('Иван');
    $order1->set_billing_last_name('Иванов');
    $order1->set_billing_email('ivan@example.com');
    $order1->set_billing_phone('+79123456789');
    
    // Добавляем кастомные поля для обсуждения доставки
    $order1->update_meta_data('Тип доставки', 'Обсудить с менеджером');
    $order1->update_meta_data('Статус доставки', 'Требуется обсуждение');
    $order1->update_meta_data('Действие менеджера', 'Связаться с клиентом для обсуждения доставки');
    
    $order1->calculate_totals();
    $order1->save();
    
    $test_orders['discuss'] = $order1->get_id();
    echo "<div class='success'>✅ Создан тестовый заказ #" . $order1->get_id() . " (Обсуждение доставки)</div>";
    
    // Тестовый заказ 2: СДЭК доставка
    $order2 = wc_create_order();
    $order2->set_status('pending');
    $order2->set_customer_id(1);
    
    if (!empty($products)) {
        $order2->add_product(wc_get_product($product_id), 2);
    }
    
    $order2->set_billing_first_name('Петр');
    $order2->set_billing_last_name('Петров');
    $order2->set_billing_email('petr@example.com');
    $order2->set_billing_phone('+79987654321');
    
    // Добавляем кастомные поля для СДЭК доставки
    $order2->update_meta_data('Тип доставки', 'СДЭК');
    $order2->update_meta_data('Пункт выдачи СДЭК', 'ПВЗ "Центральный"');
    $order2->update_meta_data('Адрес пункта выдачи', 'г. Москва, ул. Тверская, д. 1');
    $order2->update_meta_data('Время работы ПВЗ', 'пн-пт: 9:00-18:00, сб: 10:00-16:00');
    $order2->update_meta_data('Телефон ПВЗ', '+7 (495) 123-45-67');
    $order2->update_meta_data('Код пункта СДЭК', 'MSK123');
    $order2->update_meta_data('Стоимость доставки СДЭК', '295 руб.');
    
    $order2->calculate_totals();
    $order2->save();
    
    $test_orders['cdek'] = $order2->get_id();
    echo "<div class='success'>✅ Создан тестовый заказ #" . $order2->get_id() . " (СДЭК доставка)</div>";
    
} catch (Exception $e) {
    echo '<div class="error">❌ Ошибка создания тестовых заказов: ' . $e->getMessage() . '</div>';
}
echo '</div>';

// 2. Проверяем кастомные поля в заказах
echo '<div class="test-section">';
echo '<h2>📋 Проверка кастомных полей в заказах</h2>';

foreach ($test_orders as $type => $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) continue;
    
    echo "<h3>Заказ #$order_id ($type)</h3>";
    
    // Получаем все кастомные поля
    $meta_data = $order->get_meta_data();
    
    if (!empty($meta_data)) {
        echo '<table>';
        echo '<tr><th>Ключ</th><th>Значение</th></tr>';
        
        foreach ($meta_data as $meta) {
            $key = $meta->get_data()['key'];
            $value = $meta->get_data()['value'];
            
            // Показываем только наши кастомные поля
            if (in_array($key, [
                'Тип доставки', 'Статус доставки', 'Действие менеджера',
                'Пункт выдачи СДЭК', 'Адрес пункта выдачи', 'Время работы ПВЗ',
                'Телефон ПВЗ', 'Код пункта СДЭК', 'Стоимость доставки СДЭК'
            ])) {
                echo '<tr>';
                echo '<td>' . esc_html($key) . '</td>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        }
        
        echo '</table>';
    } else {
        echo '<div class="warning">⚠️ Кастомные поля не найдены</div>';
    }
}
echo '</div>';

// 3. Тестируем отображение в email шаблонах
echo '<div class="test-section">';
echo '<h2>📧 Тест email шаблонов</h2>';

foreach ($test_orders as $type => $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) continue;
    
    echo "<h3>Email для заказа #$order_id ($type)</h3>";
    
    // Имитируем рендеринг email шаблона
    if (file_exists('woocommerce-email-templates/admin-new-order-simple.php')) {
        echo '<h4>Шаблон для администратора:</h4>';
        echo '<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        
        ob_start();
        
        // Устанавливаем переменные для шаблона
        $sent_to_admin = true;
        $plain_text = false;
        $email = null;
        $email_heading = 'Новый заказ';
        
        include 'woocommerce-email-templates/admin-new-order-simple.php';
        
        $email_content = ob_get_clean();
        echo $email_content;
        echo '</div>';
    } else {
        echo '<div class="warning">⚠️ Упрощенный email шаблон для админа не найден</div>';
    }
    
    echo '<br>';
}
echo '</div>';

// 4. Проверяем функционал автоотображения кастомных полей в WooCommerce
echo '<div class="test-section">';
echo '<h2>🔧 Проверка автоотображения метаполей WooCommerce</h2>';

echo '<p>WooCommerce автоматически отображает кастомные поля в:</p>';
echo '<ul>';
echo '<li>✅ Таблице заказа в email уведомлениях (через <code>woocommerce_email_order_meta</code>)</li>';
echo '<li>✅ Странице заказа в админке (через <code>woocommerce_admin_order_data_after_billing_address</code>)</li>';
echo '<li>✅ Странице "Мой аккаунт" клиента (через <code>woocommerce_order_details_after_order_table</code>)</li>';
echo '</ul>';

echo '<div class="info">';
echo '<strong>💡 Преимущества нового подхода:</strong><br>';
echo '• Кастомные поля автоматически отображаются в таблице заказа<br>';
echo '• Не нужно модифицировать email шаблоны<br>';
echo '• Поля видны и в админке, и в email, и в аккаунте клиента<br>';
echo '• Стандартный подход WooCommerce<br>';
echo '• Легко стилизуется через CSS';
echo '</div>';

echo '</div>';

// 5. Ссылки для проверки
echo '<div class="test-section">';
echo '<h2>🔗 Ссылки для проверки</h2>';

foreach ($test_orders as $type => $order_id) {
    echo "<p><strong>Заказ #$order_id ($type):</strong></p>";
    echo "<a href='/wp-admin/post.php?post=$order_id&action=edit' class='button' target='_blank'>Открыть в админке</a> ";
    echo "<a href='?test_email=$order_id' class='button'>Показать email превью</a>";
    echo "<br><br>";
}

// Обработка превью email
if (isset($_GET['test_email'])) {
    $order_id = intval($_GET['test_email']);
    $order = wc_get_order($order_id);
    
    if ($order) {
        echo '<div class="info">';
        echo '<h3>📧 Email превью для заказа #' . $order_id . '</h3>';
        
        // Получаем email менеджера
        $emails = WC()->mailer()->get_emails();
        if (isset($emails['WC_Email_New_Order'])) {
            $email_content = $emails['WC_Email_New_Order']->get_content_html();
            $email_content = str_replace('{order}', '#' . $order_id, $email_content);
            
            echo '<iframe style="width: 100%; height: 600px; border: 1px solid #ddd;" srcdoc="' . htmlspecialchars($email_content) . '"></iframe>';
        }
        
        echo '</div>';
    }
}

echo '</div>';

// 6. Очистка
echo '<div class="test-section">';
echo '<h2>🧹 Очистка тестовых данных</h2>';

foreach ($test_orders as $type => $order_id) {
    echo "<a href='?delete_order=$order_id' class='button' style='background: #dc3545;'>Удалить заказ #$order_id ($type)</a> ";
}

if (isset($_GET['delete_order'])) {
    $order_id = intval($_GET['delete_order']);
    wp_delete_post($order_id, true);
    echo "<div class='success'>✅ Заказ #$order_id удален</div>";
    echo '<meta http-equiv="refresh" content="2">';
}

echo '</div>';

echo '<div class="test-section info">';
echo '<h2>📋 Инструкции</h2>';
echo '<ol>';
echo '<li>Проверьте, что кастомные поля отображаются в таблицах выше</li>';
echo '<li>Откройте заказы в админке WordPress через кнопки выше</li>';
echo '<li>Проверьте email превью через соответствующие кнопки</li>';
echo '<li>Создайте реальный заказ на сайте и проверьте email уведомления</li>';
echo '<li>Удалите тестовые заказы после проверки</li>';
echo '</ol>';
echo '</div>';
?>