# СРОЧНОЕ ИСПРАВЛЕНИЕ: Циклические вызовы API

## 🚨 Проблема
Функция `updateCheckoutFieldsForBlocksAPI()` вызывается слишком часто и создает бесконечные циклы, что приводит к:
- Тысячам ошибок в консоли
- Замедлению работы сайта
- Нестабильной работе

## ✅ Решение

### Шаг 1: Найти проблемные места в `assets/js/textarea-auto-fill.js`

Найти и закомментировать/исправить следующие строки:

#### 1. Периодический вызов (строка ~862):
```javascript
// БЫЛО:
setInterval(forceUpdateCheckoutFields, 2000);

// ДОЛЖНО БЫТЬ:
// setInterval(forceUpdateCheckoutFields, 2000); // ОТКЛЮЧЕНО - создает циклы
```

#### 2. Подписка на wp.data.subscribe (строка ~850):
```javascript
// БЫЛО:
const unsubscribe = window.wp.data.subscribe(() => {
    updateCheckoutFieldsForBlocksAPI();
});

// ДОЛЖНО БЫТЬ:
// Подписка отключена - создает бесконечный цикл
// const unsubscribe = window.wp.data.subscribe(() => {
//     updateCheckoutFieldsForBlocksAPI();
// });
```

#### 3. Прямые вызовы в событиях:
```javascript
// БЫЛО:
$(document.body).on('update_checkout', function() {
    updateCheckoutFieldsForBlocksAPI();
});

// ДОЛЖНО БЫТЬ:
$(document.body).on('update_checkout', function() {
    debouncedAPIUpdate(); // Используем дебаунсированную версию
});
```

### Шаг 2: Добавить защиту от частых вызовов

В функцию `updateCheckoutFieldsForBlocksAPI()` добавить в начало:

```javascript
function updateCheckoutFieldsForBlocksAPI() {
    // Защита от слишком частых вызовов (не чаще раза в секунду)
    const now = Date.now();
    if (now - lastAPIUpdateTime < 1000) {
        console.log('🕐 API вызов пропущен - слишком рано');
        return;
    }
    
    // Проверяем, изменились ли данные с последнего вызова
    const currentData = { 
        dostavka: window.currentDeliveryData?.dostavka || '',
        manager: window.currentDeliveryData?.manager || ''
    };
    
    if (JSON.stringify(currentData) === JSON.stringify(lastAPIUpdateData)) {
        console.log('ℹ️ API вызов пропущен - данные не изменились');
        return;
    }
    
    // ... остальной код функции
}
```

### Шаг 3: Добавить переменные контроля

В начало файла добавить:
```javascript
// Переменные для контроля частоты вызовов API
let lastAPIUpdateTime = 0;
let lastAPIUpdateData = { dostavka: '', manager: '' };
let apiUpdateTimeout;
```

### Шаг 4: Использовать дебаунсированную версию

Заменить все прямые вызовы `updateCheckoutFieldsForBlocksAPI()` на `debouncedAPIUpdate()`:

```javascript
// Дебаунсированная версия функции API обновления
function debouncedAPIUpdate() {
    clearTimeout(apiUpdateTimeout);
    apiUpdateTimeout = setTimeout(updateCheckoutFieldsForBlocksAPI, 500);
}
```

## 🧪 Проверка исправления

После внесения изменений в консоли должны появиться сообщения:
- `🕐 API вызов пропущен - слишком рано`
- `ℹ️ API вызов пропущен - данные не изменились`

Это значит, что защита работает правильно.

## 📋 Резервная копия

Создана резервная копия: `assets/js/textarea-auto-fill-backup.js`

## ⚡ Быстрое исправление

Если нужно быстро отключить все проблемные функции:

1. Закомментировать весь setInterval
2. Закомментировать wp.data.subscribe  
3. Заменить все updateCheckoutFieldsForBlocksAPI() на debouncedAPIUpdate()

Автозаполнение будет работать, но без циклических вызовов API.