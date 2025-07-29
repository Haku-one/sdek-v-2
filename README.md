# 🚚 Современная интеграция СДЭК доставки

Современная версия плагина для интеграции с API СДЭК, построенная на React/Node.js без использования кэширования, с поддержкой всех функций оригинального WordPress плагина.

## ✨ Особенности

- **🚀 Современный стек**: React + TypeScript + Node.js + Express
- **🗑️ Без кэширования**: Все данные получаются в реальном времени
- **📱 Адаптивный дизайн**: Оптимизировано для мобильных устройств
- **🗺️ Интеграция с картами**: Yandex Maps для выбора пунктов выдачи
- **🔍 Умный поиск**: Автокомплит городов России (1000+ городов)
- **💰 Точный расчет**: Реальные тарифы СДЭК через API
- **🛒 WooCommerce**: Полная интеграция с REST API
- **⚡ Высокая производительность**: Оптимизировано для быстрой работы

## 🏗️ Архитектура

```
├── server/                 # Node.js/Express API
│   ├── index.js           # Основной серверный файл
│   ├── services/          # Бизнес-логика
│   │   └── CdekAPI.js     # Сервис для работы с СДЭК API
│   └── routes/            # API маршруты
│       ├── cdek.js        # Пункты выдачи и расчет стоимости
│       ├── address.js     # Поиск адресов
│       └── woocommerce.js # Интеграция с WooCommerce
│
├── client/                # React приложение
│   ├── src/
│   │   ├── components/    # React компоненты
│   │   │   ├── AddressSearch/    # Поиск адресов
│   │   │   ├── CdekMap/          # Карта с пунктами
│   │   │   └── CdekDelivery/     # Основной компонент
│   │   ├── services/      # API клиент
│   │   └── types/         # TypeScript типы
│   └── public/
│
└── docs/                  # Документация
```

## 🚀 Быстрый старт

### Предварительные требования

- Node.js 16+ 
- npm или yarn
- Git

### 1. Клонирование и установка

```bash
# Клонируем репозиторий
git clone <repository-url>
cd cdek-delivery-modern

# Устанавливаем зависимости для сервера и клиента
npm run install:all
```

### 2. Настройка окружения

```bash
# Копируем примеры конфигурации
cp .env.example .env
cp client/.env.example client/.env

# Редактируем настройки при необходимости
nano .env
nano client/.env
```

### 3. Запуск в режиме разработки

```bash
# Запускаем сервер и клиент одновременно
npm run dev
```

Приложение будет доступно по адресам:
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:3001

## 📋 API Endpoints

### СДЭК API

```http
GET /api/cdek/points?city=Москва          # Получение пунктов выдачи
POST /api/cdek/calculate                  # Расчет стоимости доставки
GET /api/cdek/test-connection             # Тест подключения к СДЭК
```

### Поиск адресов

```http
GET /api/address/suggestions?query=Моск   # Автокомплит городов
POST /api/address/validate                # Валидация адреса
GET /api/address/city/:cityName           # Информация о городе
```

### WooCommerce интеграция

```http
POST /api/woocommerce/cart-data           # Обработка данных корзины
POST /api/woocommerce/update-shipping     # Обновление стоимости доставки
GET /api/woocommerce/order/:orderId       # Получение заказа
```

## 🛒 Интеграция с WooCommerce

### Способ 1: Прямая интеграция

```javascript
import { CdekDelivery } from './components/CdekDelivery';

// Получаем данные корзины из WooCommerce
const cartItems = [
  {
    product_id: 123,
    quantity: 2,
    length: 25,
    width: 15,
    height: 10,
    weight: 300,
    price: 1500
  }
];

// Конфигурация WooCommerce API
const wooConfig = {
  apiUrl: 'https://your-site.com',
  apiKey: 'ck_your_key',
  apiSecret: 'cs_your_secret'
};

// Используем компонент
<CdekDelivery
  cartItems={cartItems}
  wooConfig={wooConfig}
  onPointSelect={(point, cost) => {
    // Обновляем заказ в WooCommerce
    console.log('Выбран пункт:', point);
    console.log('Стоимость:', cost);
  }}
/>
```

### Способ 2: WordPress плагин

Создайте простой WordPress плагин:

```php
<?php
/**
 * Plugin Name: Modern CDEK Integration
 */

function enqueue_cdek_delivery() {
    if (is_checkout()) {
        wp_enqueue_script(
            'cdek-delivery', 
            'http://localhost:3000/static/js/main.js',
            array(),
            '1.0.0',
            true
        );
        
        wp_localize_script('cdek-delivery', 'cdekConfig', array(
            'apiUrl' => 'http://localhost:3001/api',
            'cartItems' => WC()->cart->get_cart()
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_cdek_delivery');
```

## 🔧 Конфигурация

### Серверные настройки (.env)

```env
# Порт сервера
PORT=3001

# Окружение
NODE_ENV=development

# URL фронтенда для CORS
FRONTEND_URL=http://localhost:3000

# Данные для API СДЭК
CDEK_ACCOUNT=your_account_id
CDEK_PASSWORD=your_password
CDEK_SENDER_CITY=428  # Код города отправителя

# Yandex Maps API ключ
YANDEX_MAPS_API_KEY=your_api_key
```

### Клиентские настройки (client/.env)

```env
# URL API сервера
REACT_APP_API_URL=http://localhost:3001/api
```

## 🎨 Кастомизация

### Стилизация компонентов

Все компоненты используют styled-components и легко кастомизируются:

```javascript
import styled from 'styled-components';

const CustomCdekContainer = styled.div`
  /* Ваши стили */
  background: #your-color;
  border-radius: 12px;
`;
```

### Добавление новых функций

1. **Добавление нового API endpoint**:

```javascript
// server/routes/custom.js
router.get('/my-endpoint', async (req, res) => {
  // Ваша логика
  res.json({ success: true, data: result });
});
```

2. **Создание нового React компонента**:

```javascript
// client/src/components/MyComponent/MyComponent.tsx
import React from 'react';
import { CdekPoint } from '../../types';

interface MyComponentProps {
  points: CdekPoint[];
}

const MyComponent: React.FC<MyComponentProps> = ({ points }) => {
  return <div>{/* Ваш компонент */}</div>;
};

export default MyComponent;
```

## 🧪 Тестирование

### Запуск тестов

```bash
# Тесты сервера
npm run test:server

# Тесты клиента
cd client && npm test

# Все тесты
npm run test
```

### Тестирование API

```bash
# Тест подключения к СДЭК
curl http://localhost:3001/api/cdek/test-connection

# Получение пунктов выдачи
curl "http://localhost:3001/api/cdek/points?city=Москва"

# Поиск городов
curl "http://localhost:3001/api/address/suggestions?query=Моск"
```

## 📦 Деплой в продакшн

### 1. Сборка приложения

```bash
# Сборка React приложения
npm run build

# Сборка для продакшна
npm run build:prod
```

### 2. Настройка сервера

```bash
# Установка PM2 для управления процессами
npm install -g pm2

# Запуск в продакшне
pm2 start server/index.js --name "cdek-api"
pm2 startup
pm2 save
```

### 3. Nginx конфигурация

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # React приложение
    location / {
        root /path/to/client/build;
        try_files $uri $uri/ /index.html;
    }

    # API прокси
    location /api {
        proxy_pass http://localhost:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

## 🔍 Отладка

### Логирование

Все операции логируются в консоль с эмодзи для удобства:

```
🔑 СДЭК AUTH: Получаем новый токен авторизации
✅ Токен СДЭК получен успешно
🔍 Поиск пунктов выдачи для города: Москва
📍 Отображено 125 пунктов выдачи на карте
💰 Расчет стоимости доставки для пункта: MSK123
✅ Стоимость доставки рассчитана: 350 руб.
```

### Включение отладочного режима

```env
NODE_ENV=development
DEBUG=cdek:*
```

## 🚨 Устранение неполадок

### Частые проблемы

**1. Ошибка подключения к СДЭК API**
```
❌ Ошибка авторизации в API СДЭК
```
*Решение*: Проверьте правильность CDEK_ACCOUNT и CDEK_PASSWORD в .env

**2. Карта не загружается**
```
❌ Yandex Maps API не загружен
```
*Решение*: Проверьте YANDEX_MAPS_API_KEY и доступность api-maps.yandex.ru

**3. CORS ошибки**
```
Access to fetch blocked by CORS policy
```
*Решение*: Убедитесь что FRONTEND_URL в .env соответствует адресу клиента

### Диагностика

```bash
# Проверка статуса сервера
curl http://localhost:3001/health

# Проверка подключения к СДЭК
curl http://localhost:3001/api/cdek/test-connection

# Проверка доступности пунктов
curl "http://localhost:3001/api/cdek/points?city=Москва"
```

## 📈 Производительность

### Оптимизации

- **Дебаунсинг**: Поиск адресов с задержкой 200мс
- **Мемоизация**: Кэширование результатов API запросов
- **Lazy Loading**: Подгрузка карт по требованию
- **Batch операции**: Группировка DOM обновлений
- **Мобильная оптимизация**: Уменьшенные таймауты и лимиты

### Мониторинг

```javascript
// Метрики производительности
console.time('cdek-points-load');
await apiService.getCdekPoints(city);
console.timeEnd('cdek-points-load');
```

## 🤝 Содействие

1. Форкните репозиторий
2. Создайте ветку для фичи (`git checkout -b feature/amazing-feature`)
3. Закоммитьте изменения (`git commit -m 'Add amazing feature'`)
4. Запушьте в ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## 📄 Лицензия

MIT License - см. файл [LICENSE](LICENSE)

## 🆘 Поддержка

- **GitHub Issues**: Для багов и предложений
- **Email**: your-email@domain.com
- **Telegram**: @your_telegram

## 🔄 Миграция с оригинального плагина

### Пошаговая миграция

1. **Сохраните настройки** оригинального плагина
2. **Деактивируйте** старый плагин
3. **Установите** новую версию по инструкции выше
4. **Перенесите** настройки СДЭК (account, password, sender_city)
5. **Протестируйте** функциональность
6. **Обновите** темы/плагины для использования нового API

### Совместимость

✅ Все функции оригинального плагина поддерживаются
✅ API совместимо с существующими интеграциями  
✅ Данные заказов сохраняются в том же формате
✅ Плавная миграция без потери данных

---

**🚀 Готово! Теперь у вас есть современная, быстрая и надежная интеграция с СДЭК без кэширования!**