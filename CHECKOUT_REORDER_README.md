# 🔄 Перестановка блоков в классическом чекауте WooCommerce

## Описание задачи

Данное решение меняет местами блоки "Детали" и "Ваш заказ" в правой колонке классического чекаута WooCommerce.

### Текущий порядок:
1. Оплата и доставка (левая колонка)
2. **Детали** (правая колонка, сверху)  
3. **Ваш заказ** (правая колонка, снизу)

### Новый порядок:
1. Оплата и доставка (левая колонка)
2. **Ваш заказ** (правая колонка, сверху)
3. **Детали** (правая колонка, снизу)

## 🛠️ Техническая реализация

### Подходы к решению:

1. **CSS Flexbox** - Основной метод через `order` свойство
2. **JavaScript DOM манипуляции** - Для более надежной работы
3. **AJAX совместимость** - Поддержка динамических обновлений чекаута

## 📁 Файлы решения

### 1. `functions.php` (Автономное решение)
Полнофункциональный файл для добавления в тему WordPress:
- ✅ Готов к использованию
- ✅ Не требует изменения плагинов
- ✅ Работает с любой темой

### 2. Интеграция в CDEK плагин
Функциональность добавлена в существующий плагин `cdek-delivery-plugin.php`:
- ✅ Интегрировано в класс `CdekDeliveryPlugin`
- ✅ Совместимо с функциями CDEK
- ✅ Автоматически активируется

## 🚀 Установка и использование

### Вариант 1: Использование functions.php

1. Скопируйте файл `functions.php` в папку активной темы WordPress
2. Или добавьте содержимое в существующий `functions.php` вашей темы
3. Изменения применятся автоматически

### Вариант 2: Интеграция в CDEK плагин

Функциональность уже интегрирована в плагин и активируется автоматически при:
- Активации плагина CDEK
- Посещении страницы чекаута

## 🎯 Ключевые особенности

### CSS решение
```css
.woocommerce-checkout .col-2 {
    display: flex !important;
    flex-direction: column !important;
}

.woocommerce-checkout #order_review_heading {
    order: -2 !important;
}

.woocommerce-checkout #order_review {
    order: -1 !important;
}
```

### JavaScript решение
```javascript
// Физическая перестановка элементов DOM
$orderReviewHeading.prependTo($rightColumn);
$orderReview.insertAfter($orderReviewHeading);
```

### AJAX совместимость
```javascript
// Обработка обновлений чекаута
$('body').on('updated_checkout', function() {
    setTimeout(function() {
        performReorder();
    }, 50);
});
```

## 🧪 Тестирование

### Файл `test-checkout-reorder.html`
Интерактивная страница для тестирования функциональности:

1. Откройте `test-checkout-reorder.html` в браузере
2. Сравните макеты "ДО" и "ПОСЛЕ"
3. Протестируйте JavaScript функции
4. Проверьте адаптивность

### Проверка в реальном WooCommerce

1. Добавьте товар в корзину
2. Перейдите к оформлению заказа
3. Убедитесь, что блок "Ваш заказ" находится сверху
4. Проверьте работу AJAX обновлений

## 🔧 Конфигурация

### Хуки WordPress
- `wp_head` - Добавление CSS стилей
- `wp_footer` - Добавление JavaScript кода
- `woocommerce_checkout_update_order_review` - AJAX совместимость
- `woocommerce_checkout_init` - Инициализация

### CSS селекторы
- `.woocommerce-checkout .col-2` - Правая колонка чекаута
- `#order_review_heading` - Заголовок "Ваш заказ"
- `#order_review` - Блок с таблицей заказа
- `#customer_details` - Блок с деталями клиента

## 🌐 Совместимость

### Темы WordPress
- ✅ Storefront
- ✅ Astra
- ✅ OceanWP
- ✅ Twenty Twenty/Twenty One/Twenty Two
- ✅ Большинство стандартных тем

### Браузеры
- ✅ Chrome 60+
- ✅ Firefox 60+
- ✅ Safari 12+
- ✅ Edge 79+

### WooCommerce
- ✅ WooCommerce 5.0+
- ✅ Классический чекаут
- ⚠️ Блочный чекаут требует дополнительной настройки

## 📱 Адаптивность

Решение включает специальные CSS правила для мобильных устройств:

```css
@media (max-width: 768px) {
    .woocommerce-checkout .col-2 {
        display: flex !important;
        flex-direction: column !important;
    }
}
```

## 🔍 Отладка

### Консоль браузера
При успешной работе в консоли появляются сообщения:
```
✅ CDEK: Checkout blocks reordered - "Ваш заказ" moved to top
```

### Принудительная активация
```javascript
// В консоли браузера
window.cdekForceCheckoutReorder();
```

### CSS класс проверки
После успешной перестановки добавляется класс:
```html
<div class="col-2 cdek-blocks-reordered">
```

## ⚠️ Возможные проблемы

### 1. Конфликт с темой
**Решение:** Добавить специфичные CSS правила для вашей темы

### 2. Конфликт с другими плагинами
**Решение:** Увеличить приоритет CSS правил (`!important`)

### 3. AJAX не работает
**Решение:** Проверить подключение jQuery и корректность селекторов

## 🎨 Кастомизация

### Изменение стилей блока "Ваш заказ"
```css
.woocommerce-checkout #order_review {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}
```

### Добавление анимации
```css
.woocommerce-checkout .col-2 > * {
    transition: all 0.3s ease;
}
```

## 📞 Поддержка

Если у вас возникли проблемы:

1. Проверьте консоль браузера на ошибки
2. Убедитесь, что WooCommerce использует классический чекаут
3. Проверьте совместимость с темой
4. Отключите другие плагины для исключения конфликтов

## 📚 Дополнительные ресурсы

- [Документация WooCommerce](https://docs.woocommerce.com/)
- [CSS Flexbox Guide](https://css-tricks.com/snippets/css/a-guide-to-flexbox/)
- [WordPress Hooks Reference](https://developer.wordpress.org/reference/hooks/)

---

**Автор:** Assistant Claude  
**Дата:** Декабрь 2024  
**Версия:** 1.0.0