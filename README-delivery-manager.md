# Кастомное поле для отслеживания информации о доставке

## Описание
Добавлено скрытое поле для автоматического сохранения информации о выбранном методе доставки в чекауте WooCommerce. Поле работает как с классическим, так и с блочным чекаутом.

## Как это работает

### Блочный чекаут
1. JavaScript отслеживает изменения в блоке:
   ```html
   <div class="wp-block-woocommerce-checkout-order-summary-shipping-block">
     <div class="wc-block-components-totals-item">
       <span class="wc-block-components-totals-item__label">Санкт-Петербург, пр. Энгельса</span>
       <div class="wc-block-components-totals-item__value">295 руб.</div>
       <div class="wc-block-components-totals-item__description">
         <small>194156, Россия, Санкт-Петербург, пр. Энгельса, 18</small>
       </div>
     </div>
   </div>
   ```

2. При изменении информации автоматически обновляется скрытое поле ACF
3. Данные сохраняются в JSON формате:
   ```json
   {
     "label": "Санкт-Петербург, пр. Энгельса",
     "price": "295 руб.",
     "description": "194156, Россия, Санкт-Петербург, пр. Энгельса, 18",
     "full_text": "Санкт-Петербург, пр. Энгельса - 295 руб. (194156, Россия, Санкт-Петербург, пр. Энгельса, 18)"
   }
   ```

### Классический чекаут
Для обратной совместимости также поддерживается сохранение в старом формате.

## Отображение данных

### В админке заказов
Информация отображается в блоке "Адрес доставки" с детальной разбивкой:
- Способ доставки
- Стоимость
- Адрес (если указан)

### В email уведомлениях
Добавляется отдельный блок "Информация о доставке" с полной информацией.

## Технические детали

### Файлы
- `delivery-tracker.js` - JavaScript для отслеживания изменений
- `cdek-delivery-styles.css` - CSS стили (поле скрыто)
- Обновлена функциональность в `cdek-delivery-plugin.php`

### Хуки и функции
- `register_delivery_manager_field()` - регистрация поля для блочного чекаута
- `save_delivery_manager_field()` - сохранение данных
- `display_delivery_manager_in_admin()` - отображение в админке
- `add_delivery_manager_to_emails()` - добавление в emails

### Мета поля в базе данных
- `_wc_other/cdek-delivery/delivery-manager` - для блочного чекаута (новый API)
- `_cdek_delivery_manager` - для данных из JavaScript
- `_delivery_manager` - для классического чекаута (обратная совместимость)

## Отладка
Для отладки можно посмотреть:
1. Консоль браузера - логи JavaScript
2. localStorage: `cdek_delivery_manager` и `cdek_delivery_manager_last`
3. Мета поля заказа в админке WordPress

## Совместимость
- WooCommerce 8.0+
- WordPress 5.0+
- Работает с блочным и классическим чекаутом
- Требует jQuery