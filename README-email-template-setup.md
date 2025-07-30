# Установка Email Шаблонов СДЭК в Тему

## Описание

Для корректной работы кастомного функционала СДЭК (отображение данных доставки в email, обработка опции "Обсудить с менеджером") необходимо установить специальные email шаблоны в вашу тему WordPress.

## ⚠️ Почему это необходимо?

По умолчанию плагин включает резервный функционал, но для полной кастомизации email уведомлений требуются специальные шаблоны в теме.

## 📋 Инструкция по установке

### Шаг 1: Создание папки для шаблонов

Создайте папку в вашей активной теме:
```
/wp-content/themes/ваша-тема/woocommerce/emails/
```

### Шаг 2: Копирование шаблонов

Скопируйте следующие файлы из папки плагина `/woocommerce-email-templates/` в папку темы:

1. **admin-new-order.php** → `/wp-content/themes/ваша-тема/woocommerce/emails/admin-new-order.php`
2. **customer-completed-order.php** → `/wp-content/themes/ваша-тема/woocommerce/emails/customer-completed-order.php`

### Шаг 3: Добавление функций в functions.php

Добавьте в файл `functions.php` вашей темы следующий код:

```php
<?php
/**
 * СДЭК Доставка - Функции для темы
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
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Добавляем информацию о доставке в email уведомления через хуки
    add_action('woocommerce_email_order_details', 'cdek_add_delivery_info_to_any_email', 25, 4);
}

/**
 * Сохранение выбора "Обсудить доставку с менеджером"
 */
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
            // HTML версии уже включены в кастомные шаблоны
        }
    }
}
```

### Шаг 4: Проверка установки

После выполнения всех шагов:

1. **Перейдите в админку** WordPress
2. **Откройте журнал ошибок** (если включен WP_DEBUG)
3. **Ищите сообщение:** `"СДЭК: Найдены кастомные шаблоны в теме, резервный функционал отключен"`

## ✅ Что изменится после установки

### В Email уведомлениях:
- **Администратору:** красивый блок с информацией о доставке СДЭК
- **Клиенту:** подробная информация о пункте выдачи
- **При выборе "Обсудить с менеджером":** специальные блоки с инструкциями

### В админке заказа:
- Отображение информации о пункте выдачи СДЭК
- Предупреждение при выборе "Обсудить с менеджером"
- Заметки в заказе о специальных требованиях

## 🔧 Альтернативный способ (автоматическое подключение)

Если не хотите копировать файлы в тему, можете использовать плагин как есть - он автоматически подключит резервный функционал.

## 🐞 Отладка

Для включения отладочных сообщений добавьте в `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Логи будут записываться в `/wp-content/debug.log`

## 📞 Поддержка

При возникновении проблем проверьте:
1. Версию WooCommerce (минимум 8.0)
2. Активность плагина СДЭК Доставка
3. Права доступа к файлам темы
4. Журнал ошибок WordPress

## 🔄 Обновления

При обновлении плагина проверьте, не изменились ли шаблоны email. В случае изменений повторите шаги 2-3.