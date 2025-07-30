# СДЭК Доставка - Обработчик данных доставки

## Описание

Файл `cdek-delivery-data-handler.php` содержит отдельный класс `CDEK_Delivery_Data_Handler` для обработки данных доставки СДЭК. Этот модуль отвечает за передачу информации о доставке в email уведомления, сохранение в заказе и отображение в админке.

## Функциональность

### 📧 Email уведомления
- Автоматическое добавление блока с информацией о доставке во все email уведомления
- Поддержка HTML и текстового формата
- Красивое оформление с эмодзи и стилями
- Информация включает:
  - Название пункта выдачи (например, "Тюмень, Авторемонтная")
  - Стоимость доставки (например, "2190 руб.")
  - Полный адрес пункта выдачи
  - Код пункта, телефон и режим работы

### 💾 Сохранение в заказе
- Сохранение всех данных доставки в метаполях заказа
- Структурированное хранение для удобного доступа
- Автоматическое логирование операций
- Сохраняемые данные:
  - `_cdek_delivery_cost` - стоимость доставки
  - `_cdek_point_code` - код пункта выдачи
  - `_cdek_point_data` - полные данные пункта
  - `_cdek_point_display_name` - отображаемое название
  - `_cdek_point_address` - полный адрес
  - `_cdek_point_phone` - телефон пункта
  - `_cdek_point_work_time` - режим работы
  - `_cdek_point_city` - город

### 🔧 Админка
- Красивый блок с информацией о доставке в админке заказа
- Удобное расположение информации в двух колонках
- Кнопки "Копировать" и "Печать" для удобства работы
- JavaScript функции для интерактивности

## Использование

### Автоматическое подключение
Файл автоматически подключается основным плагином через хук `plugins_loaded`:

```php
add_action('plugins_loaded', array($this, 'load_delivery_data_handler'));
```

### Программное использование
Для получения данных доставки из другого кода:

```php
// Создание экземпляра класса
$handler = new CDEK_Delivery_Data_Handler();

// Получение форматированной информации о доставке
$delivery_info = $handler->get_formatted_delivery_info($order_id);

// Результат:
// array(
//     'display_name' => 'Тюмень, Авторемонтная',
//     'cost' => '2190 руб.',
//     'address' => '625017, Россия, Тюменская область, Тюмень, Авторемонтная, 47',
//     'code' => 'TYU123',
//     'phone' => '+7 (xxx) xxx-xx-xx',
//     'work_time' => 'Пн-Пт: 9:00-18:00',
//     'city' => 'Тюмень'
// )
```

## Хуки WordPress

Класс использует следующие хуки:

### Инициализация
```php
add_action('woocommerce_email_order_details', array($this, 'add_delivery_info_to_email'), 20, 4);
add_action('woocommerce_checkout_order_processed', array($this, 'save_delivery_data_to_order'), 10, 3);
add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_meta_data'), 10, 1);
add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_delivery_info_in_admin'), 15);
add_action('woocommerce_order_status_changed', array($this, 'log_delivery_data_change'), 10, 3);
```

### Приоритеты
- Email уведомления: приоритет 20 (после основной информации о заказе)
- Админка: приоритет 15 (после стандартных полей адреса доставки)

## Шаблоны

### HTML Email шаблон
```html
<div style="background: #f8f9fa; border: 1px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3 style="color: #28a745;">📦 Информация о доставке СДЭК</h3>
    <p><strong>Пункт выдачи:</strong> Тюмень, Авторемонтная</p>
    <p><strong>Стоимость доставки:</strong> <span style="color: #28a745;">2190 руб.</span></p>
    <p><strong>Адрес:</strong> 625017, Россия, Тюменская область, Тюмень, Авторемонтная, 47</p>
    <!-- ... остальная информация ... -->
</div>
```

### Текстовый Email шаблон
```
==================================================
ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК
==================================================
Пункт выдачи: Тюмень, Авторемонтная
Стоимость доставки: 2190 руб.
Адрес: 625017, Россия, Тюменская область, Тюмень, Авторемонтная, 47
Код пункта: TYU123
==================================================
```

## Логирование

Класс ведет подробное логирование всех операций:

```php
error_log('СДЭК Data Handler: Сохранена стоимость доставки для заказа ' . $order_id . ': ' . $delivery_cost . ' руб.');
error_log('СДЭК Data Handler: Сохранен код пункта выдачи для заказа ' . $order_id . ': ' . $point_code);
error_log('СДЭК Data Handler: Сохранены данные пункта выдачи для заказа ' . $order_id . ': ' . $point_name);
```

## Требования

- WordPress 5.0+
- WooCommerce 8.0+
- PHP 7.4+

## Совместимость

- ✅ Новые блоки WooCommerce (Block-based checkout)
- ✅ Классический оформление заказа
- ✅ Все темы WooCommerce
- ✅ Мобильные устройства
- ✅ Email клиенты (Gmail, Outlook, Apple Mail и др.)

## Безопасность

- Все данные проходят через `sanitize_text_field()`
- HTML вывод экранируется через `esc_html()` и `esc_attr()`
- Проверка существования данных перед обработкой
- Валидация JSON данных

## Производительность

- Минимальное количество запросов к базе данных
- Кэширование данных в переменных класса
- Оптимизированные SQL запросы через WooCommerce API
- Ленивая загрузка (загружается только при необходимости)

## Отладка

Для включения отладки добавьте в `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Логи будут записываться в `/wp-content/debug.log`

## Структура файла

```
cdek-delivery-data-handler.php
├── Class CDEK_Delivery_Data_Handler
│   ├── __construct()                      # Конструктор класса
│   ├── init_hooks()                       # Инициализация хуков
│   ├── add_delivery_info_to_email()       # Добавление в email
│   ├── save_delivery_data_to_order()      # Сохранение при оформлении
│   ├── save_delivery_meta_data()          # Сохранение метаданных
│   ├── save_structured_delivery_data()    # Структурированное сохранение
│   ├── display_delivery_info_in_admin()   # Отображение в админке
│   ├── get_delivery_data_from_order()     # Получение данных
│   ├── render_html_email_template()       # HTML шаблон
│   ├── render_text_email_template()       # Текстовый шаблон
│   ├── render_admin_template()            # Шаблон админки
│   ├── log_delivery_data_change()         # Логирование изменений
│   └── get_formatted_delivery_info()      # Публичный API
└── new CDEK_Delivery_Data_Handler()       # Автоматическая инициализация
```

## Автор

Разработано для плагина СДЭК Доставка для WooCommerce v1.0.0