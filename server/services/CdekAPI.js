const axios = require('axios');

class CdekAPI {
  constructor() {
    this.account = process.env.CDEK_ACCOUNT || 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR';
    this.password = process.env.CDEK_PASSWORD || 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM';
    this.baseUrl = 'https://api.cdek.ru/v2';
    this.senderCityCode = process.env.CDEK_SENDER_CITY || '428'; // Саратов
    
    // Инициализируем axios instance с настройками
    this.api = axios.create({
      baseURL: this.baseUrl,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'User-Agent': 'Modern-CDEK-Integration/1.0'
      }
    });

    // Добавляем интерцептор для автоматической авторизации
    this.api.interceptors.request.use(async (config) => {
      if (!config.headers.Authorization && !config.url.includes('/oauth/token')) {
        const token = await this.getAuthToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
      }
      return config;
    });

    this.tokenCache = null;
    this.tokenExpiry = null;
  }

  async getAuthToken() {
    // Проверяем валидность текущего токена
    if (this.tokenCache && this.tokenExpiry && Date.now() < this.tokenExpiry) {
      return this.tokenCache;
    }

    try {
      console.log('🔑 Получаем новый токен авторизации СДЭК');
      
      const response = await axios.post(`${this.baseUrl}/oauth/token`, {
        grant_type: 'client_credentials',
        client_id: this.account,
        client_secret: this.password
      }, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        timeout: 30000
      });

      if (response.data && response.data.access_token) {
        this.tokenCache = response.data.access_token;
        const expiresIn = response.data.expires_in || 3600;
        this.tokenExpiry = Date.now() + (expiresIn - 60) * 1000; // -60 сек для безопасности
        
        console.log('✅ Токен СДЭК получен успешно');
        return this.tokenCache;
      }
      
      throw new Error('Не удалось получить access_token');
    } catch (error) {
      console.error('❌ Ошибка получения токена СДЭК:', error.message);
      throw new Error('Ошибка авторизации в API СДЭК');
    }
  }

  async getDeliveryPoints(city = null) {
    try {
      console.log('🔍 Получаем пункты выдачи СДЭК для города:', city || 'все города');
      
      const params = {
        country_code: 'RU',
        size: 5000,
        page: 0
      };

      if (city && city.trim()) {
        params.city = city.trim();
      }

      const response = await this.api.get('/deliverypoints', { params });
      
      if (response.data) {
        const points = Array.isArray(response.data) ? response.data : 
                      (response.data.entity ? response.data.entity : []);
        
        console.log(`✅ Получено ${points.length} пунктов выдачи СДЭК`);
        return points;
      }
      
      return [];
    } catch (error) {
      console.error('❌ Ошибка получения пунктов выдачи СДЭК:', error.message);
      throw new Error('Не удалось получить пункты выдачи');
    }
  }

  async calculateDeliveryCost(pointCode, pointData, cartWeight, cartDimensions, cartValue, hasRealDimensions = false) {
    try {
      console.log('💰 Расчет стоимости доставки для пункта:', pointCode);
      
      // Определяем локацию назначения
      const toLocation = this.determineDestinationLocation(pointCode, pointData);
      if (!toLocation) {
        throw new Error('Не удалось определить локацию назначения');
      }

      // Подготавливаем данные посылки
      const packages = [{
        weight: Math.max(100, parseInt(cartWeight)), // Минимум 100г
        length: Math.max(10, parseInt(cartDimensions.length)), // Минимум 10см
        width: Math.max(10, parseInt(cartDimensions.width)),
        height: Math.max(5, parseInt(cartDimensions.height))
      }];

      // Формируем запрос
      const requestData = {
        date: new Date().toISOString(),
        type: 1, // Интернет-магазин
        currency: 1, // RUB
        lang: 'rus',
        tariff_code: 136, // Посылка склад-постамат/пункт выдачи
        from_location: { code: parseInt(this.senderCityCode) },
        to_location: toLocation,
        packages: packages
      };

      // Добавляем страхование если нужно
      if (cartValue > 3000) {
        requestData.services = [{
          code: 'INSURANCE',
          parameter: String(parseInt(cartValue))
        }];
      }

      console.log('📤 Отправляем запрос расчета:', JSON.stringify(requestData, null, 2));

      const response = await this.api.post('/calculator/tariff', requestData);

      if (response.data && response.data.delivery_sum && response.data.delivery_sum > 0) {
        console.log('✅ Получена стоимость доставки:', response.data.delivery_sum, 'руб.');
        
        return {
          delivery_sum: parseInt(response.data.delivery_sum),
          period_min: response.data.period_min || null,
          period_max: response.data.period_max || null,
          api_success: true
        };
      }

      // Если основной тариф не сработал, пробуем альтернативные
      return await this.tryAlternativeCalculation(requestData);
      
    } catch (error) {
      console.error('❌ Ошибка расчета стоимости доставки:', error.message);
      
      // Возвращаем fallback расчет
      return this.calculateFallbackCost(cartWeight, cartValue, cartDimensions, hasRealDimensions);
    }
  }

  determineDestinationLocation(pointCode, pointData) {
    if (!pointData || !pointData.location) {
      return null;
    }

    // Способ 1: city_code
    if (pointData.location.city_code) {
      return { code: parseInt(pointData.location.city_code) };
    }

    // Способ 2: postal_code
    if (pointData.location.postal_code) {
      return { postal_code: pointData.location.postal_code };
    }

    // Способ 3: city name
    if (pointData.location.city) {
      return { city: pointData.location.city.trim() };
    }

    // Способ 4: определение по коду пункта
    const cityCodeMap = {
      'MSK': { code: 44, name: 'Москва' },
      'SPB': { code: 137, name: 'Санкт-Петербург' },
      'NSK': { code: 270, name: 'Новосибирск' },
      'EKB': { code: 51, name: 'Екатеринбург' },
      'KZN': { code: 172, name: 'Казань' },
      'SRT': { code: 354, name: 'Саратов' }
    };

    for (const [prefix, cityInfo] of Object.entries(cityCodeMap)) {
      if (pointCode.toUpperCase().startsWith(prefix)) {
        console.log(`🏙️ Определен город ${cityInfo.name} по префиксу пункта`);
        return { code: cityInfo.code };
      }
    }

    return null;
  }

  async tryAlternativeCalculation(originalData) {
    const alternativeTariffs = [138, 233, 234]; // Постамат, Эконом, Стандарт
    
    for (const tariff of alternativeTariffs) {
      try {
        const data = { ...originalData, tariff_code: tariff };
        const response = await this.api.post('/calculator/tariff', data);
        
        if (response.data && response.data.delivery_sum && response.data.delivery_sum > 0) {
          console.log(`✅ Альтернативный расчет успешен с тарифом ${tariff}:`, response.data.delivery_sum);
          
          return {
            delivery_sum: parseInt(response.data.delivery_sum),
            period_min: response.data.period_min || null,
            period_max: response.data.period_max || null,
            api_success: true,
            alternative_tariff: tariff
          };
        }
      } catch (error) {
        console.log(`⚠️ Тариф ${tariff} не сработал:`, error.message);
        continue;
      }
    }

    throw new Error('Все альтернативные тарифы не сработали');
  }

  calculateFallbackCost(weight, value, dimensions, hasRealDimensions) {
    console.log('🔄 Используем fallback расчет стоимости');
    
    let baseCost = 350;

    // Надбавка за вес
    if (weight > 500) {
      const extraWeight = Math.ceil((weight - 500) / 500);
      baseCost += extraWeight * 40;
    }

    // Надбавка за габариты
    if (hasRealDimensions && dimensions) {
      const volume = dimensions.length * dimensions.width * dimensions.height;
      if (volume > 12000) {
        const extraVolume = Math.ceil((volume - 12000) / 6000);
        baseCost += extraVolume * 60;
      }
    }

    // Надбавка за стоимость
    if (value > 3000) {
      baseCost += Math.ceil((value - 3000) / 1000) * 25;
    }

    return {
      delivery_sum: baseCost,
      fallback: true,
      api_success: false
    };
  }
}

module.exports = CdekAPI;