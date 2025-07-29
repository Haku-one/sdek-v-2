const express = require('express');
const router = express.Router();

// Список российских городов (расширенный из оригинального кода)
const RUSSIAN_CITIES = [
  // Федеральные города и миллионники
  'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
  'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
  'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул',
  
  // Крупные региональные центры
  'Ульяновск', 'Владивосток', 'Ярославль', 'Иркутск', 'Хабаровск', 'Махачкала', 'Томск',
  'Оренбург', 'Кемерово', 'Новокузнецк', 'Рязань', 'Астрахань', 'Пенза', 'Липецк',
  'Тула', 'Киров', 'Чебоксары', 'Калининград', 'Брянск', 'Курск', 'Иваново', 'Магнитогорск',
  'Тверь', 'Ставрополь', 'Симферополь', 'Белгород', 'Архангельск', 'Владимир', 'Сочи',
  'Курган', 'Смоленск', 'Калуга', 'Чита', 'Орёл', 'Волжский', 'Череповец', 'Владикавказ',
  'Мурманск', 'Сургут', 'Вологда', 'Тамбов', 'Стерлитамак', 'Грозный', 'Якутск',
  'Кострома', 'Комсомольск-на-Амуре', 'Петрозаводск', 'Таганрог', 'Нижневартовск', 'Йошкар-Ола',
  
  // Города с населением более 200 тысяч
  'Братск', 'Новороссийск', 'Дзержинск', 'Шахты', 'Нижнекамск', 'Орск', 'Ангарск',
  'Старый Оскол', 'Великий Новгород', 'Благовещенск', 'Прокопьевск', 'Химки', 'Бийск',
  'Энгельс', 'Рыбинск', 'Балашиха', 'Северодвинск', 'Армавир', 'Подольск', 'Королёв',
  'Сызрань', 'Норильск', 'Каменск-Уральский', 'Альметьевск', 'Уссурийск', 'Мытищи', 
  'Люберцы', 'Электросталь', 'Салават', 'Миасс', 'Абакан', 'Рубцовск', 'Коломна', 
  'Майкоп', 'Ковров', 'Красногорск', 'Нальчик', 'Усть-Илимск', 'Серпухов', 'Новочебоксарск', 
  'Нефтеюганск', 'Димитровград', 'Нефтекамск', 'Черкесск', 'Дербент', 'Камышин', 
  'Новый Уренгой', 'Муром', 'Ачинск', 'Кисловодск', 'Первоуральск', 'Елец', 'Евпатория', 
  'Арзамас', 'Тобольск', 'Жуковский', 'Ноябрьск', 'Невинномысск', 'Березники', 'Назрань', 
  'Южно-Сахалинск', 'Волгодонск', 'Сыктывкар', 'Новочеркасск', 'Каспийск', 'Обнинск', 
  'Пятигорск', 'Октябрьский', 'Ломоносов'
];

// Поиск городов с автокомплитом
router.get('/suggestions', (req, res) => {
  try {
    const { query, limit = 10 } = req.query;
    
    if (!query || query.length < 2) {
      return res.json({
        success: true,
        data: [],
        message: 'Введите минимум 2 символа для поиска'
      });
    }

    const queryLower = query.toLowerCase().trim();
    const results = [];
    const maxResults = Math.min(parseInt(limit), 20);

    // Поиск с подсчетом релевантности
    RUSSIAN_CITIES.forEach((city, index) => {
      const cityLower = city.toLowerCase();
      let score = 0;

      if (cityLower === queryLower) {
        score = 1000; // Точное совпадение
      } else if (cityLower.startsWith(queryLower)) {
        score = 500; // Начинается с запроса
      } else if (cityLower.includes(queryLower)) {
        score = 200; // Содержит запрос
      } else if (queryLower.length >= 3) {
        // Простая проверка похожести для длинных запросов
        const similarity = calculateSimilarity(queryLower, cityLower);
        if (similarity > 0.6) {
          score = similarity * 100;
        }
      }

      if (score > 0) {
        // Бонус за популярность (чем раньше в списке, тем популярнее)
        const popularityBonus = (RUSSIAN_CITIES.length - index) * 2;
        score += popularityBonus;

        results.push({
          city: city,
          display: city,
          score: score,
          type: 'city'
        });
      }
    });

    // Сортируем по релевантности и ограничиваем количество
    results.sort((a, b) => b.score - a.score);
    const limitedResults = results.slice(0, maxResults);

    console.log(`🔍 Поиск городов: "${query}" -> ${limitedResults.length} результатов`);

    res.json({
      success: true,
      data: limitedResults,
      query: query,
      count: limitedResults.length
    });
  } catch (error) {
    console.error('❌ Ошибка поиска адресов:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Получение информации о городе
router.get('/city/:cityName', (req, res) => {
  try {
    const { cityName } = req.params;
    
    const city = RUSSIAN_CITIES.find(c => 
      c.toLowerCase() === cityName.toLowerCase()
    );

    if (!city) {
      return res.status(404).json({
        success: false,
        error: 'Город не найден'
      });
    }

    // Можно добавить дополнительную информацию о городе
    res.json({
      success: true,
      data: {
        city: city,
        country: 'Россия',
        type: 'city'
      }
    });
  } catch (error) {
    console.error('❌ Ошибка получения информации о городе:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Валидация адреса
router.post('/validate', (req, res) => {
  try {
    const { address } = req.body;
    
    if (!address || !address.trim()) {
      return res.status(400).json({
        success: false,
        error: 'Адрес не указан'
      });
    }

    // Простая валидация - проверяем, есть ли город в нашем списке
    const addressLower = address.toLowerCase().trim();
    const foundCity = RUSSIAN_CITIES.find(city => 
      addressLower.includes(city.toLowerCase())
    );

    res.json({
      success: true,
      data: {
        isValid: !!foundCity,
        foundCity: foundCity || null,
        originalAddress: address
      }
    });
  } catch (error) {
    console.error('❌ Ошибка валидации адреса:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Простая функция подсчета похожести строк
function calculateSimilarity(str1, str2) {
  if (str1.length === 0) return str2.length === 0 ? 1 : 0;
  if (str2.length === 0) return 0;
  
  let matches = 0;
  const minLen = Math.min(str1.length, str2.length);
  
  for (let i = 0; i < minLen; i++) {
    if (str1[i] === str2[i]) {
      matches++;
    }
  }
  
  return matches / Math.max(str1.length, str2.length);
}

module.exports = router;