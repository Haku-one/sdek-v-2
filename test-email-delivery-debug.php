<?php
/**
 * Диагностика проблемы с отправкой данных о доставке в email
 * Поместите этот файл в корень WordPress и откройте в браузере
 * УДАЛИТЕ ФАЙЛ ПОСЛЕ ИСПОЛЬЗОВАНИЯ!
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    // Пытаемся подключить WordPress
    $wp_config_path = dirname(__FILE__) . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        die('WordPress не найден. Поместите файл в корень WordPress.');
    }
    require_once($wp_config_path);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Диагностика СДЭК доставки - Email проблема</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .test-item { padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
        .delete-notice { background: #dc3545; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Диагностика проблемы с email доставки СДЭК</h1>
        
        <div class="delete-notice">
            ⚠️ НЕ ЗАБУДЬТЕ УДАЛИТЬ ЭТОТ ФАЙЛ ПОСЛЕ ИСПОЛЬЗОВАНИЯ!
        </div>

        <?php
        echo '<div class="info">Время проверки: ' . date('Y-m-d H:i:s') . '</div>';
        
        // 1. Проверка основных файлов
        echo '<div class="section">';
        echo '<h2>📁 Проверка файлов</h2>';
        
        $files_to_check = [
            'cdek-delivery.js' => 'JavaScript файл доставки',
            'cdek-delivery-plugin.php' => 'Основной плагин',
            'cdek-delivery-data-handler.php' => 'Обработчик данных',
            'theme-functions-cdek.php' => 'Функции темы',
            'woocommerce-email-templates/admin-new-order.php' => 'Email шаблон для админа',
            'woocommerce-email-templates/customer-completed-order.php' => 'Email шаблон для клиента'
        ];
        
        foreach ($files_to_check as $file => $description) {
            if (file_exists($file)) {
                echo '<div class="success">✅ ' . $description . ' найден: ' . $file . '</div>';
            } else {
                echo '<div class="error">❌ ' . $description . ' НЕ НАЙДЕН: ' . $file . '</div>';
            }
        }
        echo '</div>';
        
        // 2. Проверка функций темы
        echo '<div class="section">';
        echo '<h2>🔧 Проверка функций</h2>';
        
        $functions_to_check = [
            'cdek_theme_init' => 'Инициализация темы',
            'cdek_save_discuss_delivery_choice' => 'Сохранение выбора доставки',
            'cdek_show_discuss_delivery_admin' => 'Отображение в админке',
            'cdek_email_discuss_delivery_info' => 'Отображение в email'
        ];
        
        foreach ($functions_to_check as $function => $description) {
            if (function_exists($function)) {
                echo '<div class="success">✅ ' . $description . ': ' . $function . '()</div>';
            } else {
                echo '<div class="error">❌ ' . $description . ' НЕ НАЙДЕНА: ' . $function . '()</div>';
            }
        }
        echo '</div>';
        
        // 3. Проверка хуков WordPress
        echo '<div class="section">';
        echo '<h2>🎣 Проверка хуков WordPress</h2>';
        
        global $wp_filter;
        
        $hooks_to_check = [
            'woocommerce_checkout_update_order_meta' => 'Обновление мета данных заказа',
            'woocommerce_email_order_details' => 'Детали заказа в email',
            'woocommerce_admin_order_data_after_shipping_address' => 'Данные в админке после адреса'
        ];
        
        foreach ($hooks_to_check as $hook => $description) {
            if (isset($wp_filter[$hook])) {
                echo '<div class="success">✅ ' . $description . ' (' . $hook . '): ' . count($wp_filter[$hook]->callbacks) . ' callback(s)</div>';
                
                // Показываем какие функции привязаны к хуку
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $function_name = 'неизвестная функция';
                        if (is_array($callback['function'])) {
                            if (is_object($callback['function'][0])) {
                                $function_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                            } else {
                                $function_name = $callback['function'][0] . '::' . $callback['function'][1];
                            }
                        } elseif (is_string($callback['function'])) {
                            $function_name = $callback['function'];
                        }
                        echo '<div class="info" style="margin-left: 20px;">Приоритет ' . $priority . ': ' . $function_name . '</div>';
                    }
                }
            } else {
                echo '<div class="warning">⚠️ ' . $description . ' (' . $hook . '): НЕТ ПРИВЯЗАННЫХ ФУНКЦИЙ</div>';
            }
        }
        echo '</div>';
        
        // 4. Проверка активности WooCommerce
        echo '<div class="section">';
        echo '<h2>🛒 Проверка WooCommerce</h2>';
        
        if (class_exists('WooCommerce')) {
            echo '<div class="success">✅ WooCommerce активен</div>';
            
            global $woocommerce;
            if ($woocommerce) {
                echo '<div class="info">Версия WooCommerce: ' . $woocommerce->version . '</div>';
            }
            
            // Проверяем настройки email
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            echo '<div class="info">Доступные email шаблоны: ' . count($emails) . '</div>';
            
            foreach ($emails as $email_id => $email) {
                echo '<div class="info" style="margin-left: 20px;">' . $email_id . ': ' . $email->get_title() . ' (включен: ' . ($email->is_enabled() ? 'да' : 'нет') . ')</div>';
            }
            
        } else {
            echo '<div class="error">❌ WooCommerce НЕ АКТИВЕН</div>';
        }
        echo '</div>';
        
        // 5. Проверка последних заказов
        echo '<div class="section">';
        echo '<h2>📦 Проверка последних заказов</h2>';
        
        if (class_exists('WC_Order')) {
            $orders = wc_get_orders([
                'limit' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => ['processing', 'completed', 'pending']
            ]);
            
            if (!empty($orders)) {
                echo '<div class="success">✅ Найдено ' . count($orders) . ' последних заказов</div>';
                
                foreach ($orders as $order) {
                    echo '<div class="test-item">';
                    echo '<h4>Заказ #' . $order->get_id() . ' (' . $order->get_status() . ')</h4>';
                    echo '<p>Дата: ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';
                    
                    // Проверяем мета данные СДЭК
                    $cdek_point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
                    $cdek_point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
                    $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
                    
                    if ($cdek_point_code) {
                        echo '<div class="success">✅ СДЭК пункт: ' . $cdek_point_code . '</div>';
                    }
                    
                    if ($discuss_delivery) {
                        echo '<div class="success">✅ Обсуждение доставки: ' . $discuss_delivery . '</div>';
                    }
                    
                    if (!$cdek_point_code && !$discuss_delivery) {
                        echo '<div class="warning">⚠️ Нет данных о доставке СДЭК</div>';
                    }
                    
                    // Проверяем все мета данные заказа
                    $meta_data = $order->get_meta_data();
                    if (!empty($meta_data)) {
                        echo '<details><summary>Все мета данные (' . count($meta_data) . ')</summary><pre>';
                        foreach ($meta_data as $meta) {
                            echo $meta->key . ' = ' . print_r($meta->value, true) . "\n";
                        }
                        echo '</pre></details>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div class="warning">⚠️ Заказы не найдены</div>';
            }
        }
        echo '</div>';
        
        // 6. Проверка логов
        echo '<div class="section">';
        echo '<h2>📋 Проверка логов</h2>';
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file) && is_readable($log_file)) {
            echo '<div class="success">✅ Лог файл найден: ' . $log_file . '</div>';
            
            // Читаем последние 50 строк лога
            $lines = file($log_file);
            $recent_lines = array_slice($lines, -50);
            
            // Фильтруем строки, связанные с СДЭК
            $cdek_lines = array_filter($recent_lines, function($line) {
                return strpos($line, 'СДЭК') !== false || strpos($line, 'CDEK') !== false || strpos($line, 'cdek') !== false;
            });
            
            if (!empty($cdek_lines)) {
                echo '<div class="info">Найдено ' . count($cdek_lines) . ' записей о СДЭК в логах:</div>';
                echo '<pre>' . implode('', array_slice($cdek_lines, -10)) . '</pre>';
            } else {
                echo '<div class="warning">⚠️ Записи о СДЭК в логах не найдены</div>';
            }
        } else {
            echo '<div class="error">❌ Лог файл не найден или недоступен</div>';
            echo '<div class="info">Включите логирование в wp-config.php: define(\'WP_DEBUG_LOG\', true);</div>';
        }
        echo '</div>';
        
        // 7. Тест создания заказа
        echo '<div class="section">';
        echo '<h2>🧪 Тест симуляции заказа</h2>';
        
        if (isset($_GET['test_order']) && $_GET['test_order'] == '1') {
            try {
                // Создаем тестовый заказ
                $order = wc_create_order();
                $order->set_billing_first_name('Тест');
                $order->set_billing_last_name('Тестов');
                $order->set_billing_email('test@example.com');
                
                // Добавляем тестовый товар
                $product_id = 1; // ID любого товара
                $product = wc_get_product($product_id);
                if ($product) {
                    $order->add_product($product, 1);
                }
                
                $order->calculate_totals();
                $order->save();
                
                $order_id = $order->get_id();
                
                // Симулируем данные СДЭК
                $_POST['discuss_delivery_selected'] = '1';
                
                // Вызываем функцию сохранения
                if (function_exists('cdek_save_discuss_delivery_choice')) {
                    cdek_save_discuss_delivery_choice($order_id);
                    echo '<div class="success">✅ Тестовый заказ #' . $order_id . ' создан и обработан</div>';
                    
                    // Проверяем сохранение
                    $saved_value = get_post_meta($order_id, '_discuss_delivery_selected', true);
                    if ($saved_value) {
                        echo '<div class="success">✅ Данные сохранены: ' . $saved_value . '</div>';
                    } else {
                        echo '<div class="error">❌ Данные НЕ сохранены</div>';
                    }
                    
                    // Удаляем тестовый заказ
                    wp_delete_post($order_id, true);
                    echo '<div class="info">Тестовый заказ удален</div>';
                } else {
                    echo '<div class="error">❌ Функция cdek_save_discuss_delivery_choice не найдена</div>';
                }
                
                unset($_POST['discuss_delivery_selected']);
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Ошибка создания тестового заказа: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<a href="?test_order=1" class="button" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">Запустить тест создания заказа</a>';
        }
        echo '</div>';
        
        // 8. Рекомендации
        echo '<div class="section">';
        echo '<h2>💡 Рекомендации по исправлению</h2>';
        
        echo '<div class="info">';
        echo '<h3>Возможные причины проблемы:</h3>';
        echo '<ul>';
        echo '<li><strong>Функции темы не подключены:</strong> Убедитесь, что код из theme-functions-cdek.php добавлен в functions.php вашей темы</li>';
        echo '<li><strong>JavaScript не создает поле:</strong> Проверьте консоль браузера на ошибки при выборе "Обсудить доставку"</li>';
        echo '<li><strong>Неправильная форма:</strong> WooCommerce Blocks использует другую структуру форм</li>';
        echo '<li><strong>Конфликт плагинов:</strong> Другие плагины могут перехватывать данные формы</li>';
        echo '<li><strong>Кэширование:</strong> Очистите все кэши (плагины, браузер, CDN)</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<div class="warning">';
        echo '<h3>Шаги для исправления:</h3>';
        echo '<ol>';
        echo '<li>Убедитесь, что все файлы загружены корректно</li>';
        echo '<li>Добавьте функции из theme-functions-cdek.php в functions.php темы</li>';
        echo '<li>Включите логирование WordPress (WP_DEBUG_LOG = true)</li>';
        echo '<li>Проверьте консоль браузера при оформлении заказа</li>';
        echo '<li>Протестируйте на странице checkout с выбором "Обсудить доставку"</li>';
        echo '<li>Проверьте логи после создания заказа</li>';
        echo '</ol>';
        echo '</div>';
        echo '</div>';
        
        // Кнопка самоудаления
        if (isset($_GET['delete_me']) && $_GET['delete_me'] == '1') {
            if (unlink(__FILE__)) {
                echo '<div class="success">✅ Файл успешно удален!</div>';
                echo '<script>setTimeout(function(){ window.location.href = "/"; }, 2000);</script>';
            } else {
                echo '<div class="error">❌ Не удалось удалить файл. Удалите вручную: ' . __FILE__ . '</div>';
            }
        } else {
            echo '<div class="delete-notice">';
            echo '<a href="?delete_me=1" onclick="return confirm(\'Вы уверены, что хотите удалить этот файл?\')" style="color: white; text-decoration: underline;">🗑️ УДАЛИТЬ ЭТОТ ФАЙЛ СЕЙЧАС</a>';
            echo '</div>';
        }
        ?>
        
    </div>
</body>
</html>