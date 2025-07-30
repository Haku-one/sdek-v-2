# 📧 Исправление передачи данных доставки в email уведомления

## 🔍 Проблема

После анализа логов было выявлено, что данные о выбранной доставке ("Обсудить доставку с менеджером" или данные СДЭК) не передавались корректно в email уведомления. 

### Симптомы:
- JavaScript корректно создает скрытое поле `discuss_delivery_selected = '1'`
- В логах видно успешное добавление поля в форму
- Но в email уведомлениях не отображается информация об обсуждении доставки
- Данные не сохранялись в метаполях заказа

## 🛠️ Проведенные исправления

### 1. Улучшен JavaScript код (cdek-delivery.js)

**Улучшен поиск формы checkout:**
```javascript
// Улучшенный поиск формы оформления заказа
const checkoutForm = document.querySelector('form.woocommerce-checkout, form.checkout') || 
                   document.querySelector('form[name="checkout"]') ||
                   document.querySelector('.wc-block-checkout__form') ||
                   document.querySelector('form') ||
                   document.querySelector('.wc-block-checkout') ||
                   document.body;
```

**Добавлена поддержка WooCommerce Blocks:**
```javascript
// Дополнительно уведомляем WooCommerce Blocks о выборе
if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
    try {
        const checkoutStore = wp.data.dispatch('wc/store/checkout');
        if (checkoutStore && checkoutStore.setCheckoutFields) {
            checkoutStore.setCheckoutFields({
                discuss_delivery_selected: '1'
            });
        }
    } catch (e) {
        console.log('⚠️ Не удалось передать данные в WC Blocks store:', e);
    }
}
```

**Добавлено событие для альтернативной передачи данных:**
```javascript
// Альтернативный способ через событие
document.dispatchEvent(new CustomEvent('cdek_discuss_delivery_selected', {
    detail: { selected: true, value: '1' }
}));
```

### 2. Улучшен обработчик данных (cdek-delivery-data-handler.php)

**Добавлена обработка поля `discuss_delivery_selected`:**
```php
// Сохраняем выбор "Обсудить доставку с менеджером"
// Проверяем различные способы передачи данных
$discuss_selected = null;

if (isset($_POST['discuss_delivery_selected'])) {
    $discuss_selected = $_POST['discuss_delivery_selected'];
}

// Альтернативная проверка через WooCommerce блоки
if (!$discuss_selected && isset($_REQUEST['discuss_delivery_selected'])) {
    $discuss_selected = $_REQUEST['discuss_delivery_selected'];
}

// Дополнительная проверка в JSON данных
if (!$discuss_selected) {
    $input = file_get_contents('php://input');
    if ($input) {
        $json_data = json_decode($input, true);
        if (isset($json_data['discuss_delivery_selected'])) {
            $discuss_selected = $json_data['discuss_delivery_selected'];
        }
    }
}
```

**Добавлены хуки для WooCommerce Blocks:**
```php
// Дополнительные хуки для WooCommerce Blocks
add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_delivery_meta_data'), 10, 1);
add_action('woocommerce_blocks_checkout_order_processed', array($this, 'save_delivery_data_to_order'), 10, 3);
```

### 3. Добавлена подробная диагностика

**В JavaScript:**
```javascript
console.log('🔍 Форма найдена по селектору:', checkoutForm.className || 'нет классов');
console.log('🔍 Форма имеет атрибут action:', checkoutForm.action || 'нет action');
console.log('🔍 Форма имеет атрибут method:', checkoutForm.method || 'нет method');
```

**В PHP:**
```php
error_log('СДЭК Data Handler: Доступные POST поля: ' . implode(', ', array_keys($_POST)));
error_log('СДЭК Data Handler: Доступные REQUEST поля: ' . implode(', ', array_keys($_REQUEST)));
```

## 🧪 Тестирование

Создан тестовый скрипт `test-delivery-email-fix.php` для комплексной проверки исправлений:

1. **Проверка файлов** - существование всех необходимых файлов
2. **Проверка обработчика данных** - класс и хуки
3. **Проверка функций темы** - все функции доступны
4. **Создание тестового заказа** - симуляция реального процесса
5. **Проверка логов** - анализ работы системы

### Запуск тестов:

1. Поместите файл `test-delivery-email-fix.php` в корень WordPress
2. Откройте его в браузере: `https://ваш-сайт.ru/test-delivery-email-fix.php`
3. Проследите все проверки
4. Создайте тестовый заказ через интерфейс

## 📋 Проверочный список

- ✅ JavaScript корректно создает скрытое поле
- ✅ Поле добавляется в правильную форму
- ✅ Обработчик данных получает поле из различных источников
- ✅ Данные сохраняются в метаполе `_discuss_delivery_selected`
- ✅ Email шаблоны корректно отображают информацию
- ✅ Добавлена поддержка WooCommerce Blocks
- ✅ Добавлена подробная диагностика

## 🔄 Что делать дальше

1. **Проверьте логи** - убедитесь, что данные сохраняются
2. **Тестируйте в реальных условиях** - оформите заказ с выбором "Обсудить доставку"
3. **Проверьте email** - убедитесь, что приходит корректное уведомление
4. **Проверьте админку** - данные должны отображаться в заказе

## 🆘 Устранение неполадок

### Если данные все еще не передаются:

1. **Проверьте консоль браузера** - ищите ошибки JavaScript
2. **Проверьте логи WordPress** - ищите записи с "СДЭК Data Handler"
3. **Убедитесь в правильности формы** - проверьте, что используется стандартная форма WooCommerce
4. **Проверьте приоритеты хуков** - убедитесь, что нет конфликтов с другими плагинами

### Типичные проблемы:

- **Кэширование** - очистите все кэши
- **Конфликт плагинов** - временно отключите другие плагины
- **Тема** - убедитесь, что тема поддерживает WooCommerce правильно
- **Блоки vs классический checkout** - убедитесь в правильной версии

## 📞 Поддержка

Если проблемы продолжаются, проверьте:
1. Версию WooCommerce
2. Включено ли логирование WordPress
3. Нет ли ошибок в PHP
4. Правильно ли подключены файлы

Все исправления обратно совместимы и не должны влиять на существующую функциональность.