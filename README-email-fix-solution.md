# 🔧 Исправление проблемы с отправкой данных о доставке в email

## 🎯 Проблема
Данные о заказах (особенно кастомные поля СДЭК и опция "Обсудить доставку с менеджером") не отправляются в email уведомления при использовании WooCommerce Blocks checkout.

## 🚀 Решение

### Шаг 1: Загрузка новых файлов

Загрузите следующие файлы в корень вашего WordPress:

1. **`cdek-delivery-blocks-integration.php`** - Улучшенный обработчик для WooCommerce Blocks
2. **`cdek-blocks-integration.js`** - JavaScript для корректной передачи данных
3. **`test-email-delivery-debug.php`** - Диагностический скрипт (временный)

### Шаг 2: Активация новой интеграции

Добавьте в файл `functions.php` вашей темы:

```php
// Подключение улучшенной интеграции СДЭК с WooCommerce Blocks
if (file_exists(ABSPATH . 'cdek-delivery-blocks-integration.php')) {
    require_once ABSPATH . 'cdek-delivery-blocks-integration.php';
    error_log('СДЭК: Подключена улучшенная интеграция с Blocks');
}

// Сохранение функций темы для совместимости
if (!function_exists('cdek_theme_init')) {
    function cdek_theme_init() {
        // Добавляем поддержку СДЭК шаблонов email
        add_action('init', 'cdek_setup_email_templates');
        
        // Хуки для отображения информации о доставке в админке заказа
        add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_delivery_info_in_admin', 20);
        
        // Хуки для сохранения дополнительных данных
        add_action('woocommerce_checkout_update_order_meta', 'cdek_save_additional_delivery_meta', 20);
        
        // Добавляем функционал "Обсудить доставку с менеджером"
        add_action('woocommerce_checkout_update_order_meta', 'cdek_save_discuss_delivery_choice', 25);
        add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_show_discuss_delivery_admin', 25);
        add_action('woocommerce_email_order_details', 'cdek_email_discuss_delivery_info', 30, 4);
    }
    add_action('after_setup_theme', 'cdek_theme_init');
}

// Функция сохранения выбора "Обсудить доставку с менеджером"
if (!function_exists('cdek_save_discuss_delivery_choice')) {
    function cdek_save_discuss_delivery_choice($order_id) {
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
            }
        } else {
            error_log('СДЭК DEBUG: Поле discuss_delivery_selected НЕ найдено в $_POST');
            error_log('СДЭК DEBUG: Доступные POST поля: ' . implode(', ', array_keys($_POST)));
        }
    }
}

// Функция отображения в админке
if (!function_exists('cdek_show_discuss_delivery_admin')) {
    function cdek_show_discuss_delivery_admin($order) {
        if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
            ?>
            <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h4 style="color: #e65100; margin: 0; font-size: 16px; display: flex; align-items: center;">
                    ⚠️ ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
                </h4>
                <p style="color: #e65100; margin: 10px 0 0 0; font-weight: 500;">
                    Клиент выбрал опцию "Обсудить доставку с менеджером". 
                    Необходимо связаться с клиентом для уточнения деталей доставки.
                </p>
            </div>
            <?php
        }
    }
}

// Функция отображения в email
if (!function_exists('cdek_email_discuss_delivery_info')) {
    function cdek_email_discuss_delivery_info($order, $sent_to_admin, $plain_text, $email) {
        if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
            if ($plain_text) {
                echo "\n" . str_repeat('=', 50) . "\n";
                echo "ВАЖНО: ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ\n";
                echo str_repeat('=', 50) . "\n";
                echo "Клиент выбрал опцию 'Обсудить доставку с менеджером'.\n";
                echo "Необходимо связаться с клиентом для уточнения деталей доставки.\n\n";
            } else {
                ?>
                <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="color: #856404; margin-top: 0; display: flex; align-items: center;">
                        ⚠️ ВАЖНО: ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
                    </h3>
                    <p style="color: #856404; margin-bottom: 0; font-size: 14px; line-height: 1.5;">
                        Клиент выбрал опцию <strong>"Обсудить доставку с менеджером"</strong>.<br>
                        Необходимо связаться с клиентом для уточнения деталей доставки.
                    </p>
                </div>
                <?php
            }
        }
    }
}
```

### Шаг 3: Включение логирования WordPress

Добавьте в `wp-config.php`:

```php
// Включение логирования для отладки
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Шаг 4: Диагностика

1. Загрузите `test-email-delivery-debug.php` в корень WordPress
2. Откройте в браузере: `https://ваш-сайт.ru/test-email-delivery-debug.php`
3. Проверьте все компоненты системы
4. **УДАЛИТЕ файл после использования!**

### Шаг 5: Тестирование

1. Перейдите на страницу оформления заказа
2. Выберите "Обсудить доставку с менеджером"
3. Оформите тестовый заказ
4. Проверьте:
   - Логи WordPress (`/wp-content/debug.log`)
   - Email уведомления
   - Админку заказа

## 🔍 Как работает новое решение

### Множественные способы перехвата данных

Новая интеграция использует несколько методов для гарантированного перехвата данных:

1. **Классические хуки WooCommerce**
2. **WooCommerce Blocks API**
3. **REST API перехват**
4. **AJAX обработчики**
5. **JavaScript события**

### Улучшенный JavaScript

Новый JavaScript файл:
- Корректно работает с WooCommerce Blocks
- Использует правильные селекторы форм
- Множественные способы передачи данных
- Подробное логирование для отладки

### Универсальный PHP обработчик

Новый PHP класс:
- Обрабатывает как классический, так и Blocks checkout
- Множественные точки перехвата данных
- Подробное логирование
- Автоматическое определение типа checkout

## 🛠️ Устранение неполадок

### Если данные все еще не передаются:

1. **Проверьте консоль браузера:**
   ```
   F12 → Console → ищите сообщения с "СДЭК"
   ```

2. **Проверьте логи WordPress:**
   ```
   /wp-content/debug.log
   ```

3. **Убедитесь в правильности подключения:**
   - Файлы загружены в корень WordPress
   - Код добавлен в `functions.php`
   - Логирование включено

4. **Очистите кэши:**
   - Плагины кэширования
   - Браузер
   - CDN

### Типичные ошибки:

- **Файлы в неправильной папке** - должны быть в корне WordPress
- **Синтаксические ошибки в functions.php** - проверьте логи
- **Конфликт с другими плагинами** - временно отключите
- **Старые версии WooCommerce** - обновите до последней версии

## 📊 Проверка работы

После применения исправлений:

1. **В консоли браузера должны появиться сообщения:**
   ```
   🚀 Инициализация СДЭК Blocks интеграции
   🎯 Выбрана опция "Обсудить доставку с менеджером"
   ✅ Добавлено скрытое поле discuss_delivery_selected
   ✅ Данные переданы через setExtensionData
   ```

2. **В логах WordPress:**
   ```
   СДЭК: Подключена улучшенная интеграция с Blocks
   СДЭК Blocks: Обработка данных заказа #123
   СДЭК: Сохранен выбор "Обсудить доставку" для заказа #123
   ```

3. **В email уведомлениях появится блок:**
   ```
   ⚠️ ВАЖНО: ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ
   Клиент выбрал опцию "Обсудить доставку с менеджером".
   Необходимо связаться с клиентом для уточнения деталей доставки.
   ```

## 🎉 Результат

После применения исправлений:
- ✅ Данные о доставке корректно передаются в email
- ✅ Информация отображается в админке заказа
- ✅ Работает с WooCommerce Blocks и классическим checkout
- ✅ Подробное логирование для отладки
- ✅ Множественные способы передачи данных для надежности

## 🔄 Поддержка

Если проблемы продолжаются:
1. Запустите диагностический скрипт
2. Проверьте логи
3. Убедитесь в правильности установки
4. Проверьте совместимость с темой и плагинами

---

**Все файлы готовы к использованию. Следуйте инструкции пошагово для гарантированного результата!** 🚀