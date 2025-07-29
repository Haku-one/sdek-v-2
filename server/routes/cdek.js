const express = require('express');
const router = express.Router();
const CdekAPI = require('../services/CdekAPI');

const cdekAPI = new CdekAPI();

// Получение пунктов выдачи
router.get('/points', async (req, res) => {
  try {
    const { city } = req.query;
    
    console.log('📍 Запрос пунктов выдачи для города:', city || 'все города');
    
    const points = await cdekAPI.getDeliveryPoints(city);
    
    res.json({
      success: true,
      data: points,
      count: points.length,
      city: city || null
    });
  } catch (error) {
    console.error('❌ Ошибка получения пунктов выдачи:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Расчет стоимости доставки
router.post('/calculate', async (req, res) => {
  try {
    const {
      pointCode,
      pointData,
      cartWeight,
      cartDimensions,
      cartValue,
      hasRealDimensions
    } = req.body;

    // Валидация входных данных
    if (!pointCode) {
      return res.status(400).json({
        success: false,
        error: 'Не указан код пункта выдачи'
      });
    }

    if (!cartDimensions || !cartDimensions.length || !cartDimensions.width || !cartDimensions.height) {
      return res.status(400).json({
        success: false,
        error: 'Некорректные габариты товара'
      });
    }

    console.log('💰 Запрос расчета стоимости:', {
      pointCode,
      cartWeight,
      cartValue,
      cartDimensions,
      hasRealDimensions
    });

    const costData = await cdekAPI.calculateDeliveryCost(
      pointCode,
      pointData,
      cartWeight,
      cartDimensions,
      cartValue,
      hasRealDimensions
    );

    res.json({
      success: true,
      data: costData
    });
  } catch (error) {
    console.error('❌ Ошибка расчета стоимости:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Проверка подключения к API СДЭК
router.get('/test-connection', async (req, res) => {
  try {
    console.log('🔧 Тестирование подключения к API СДЭК');
    
    const token = await cdekAPI.getAuthToken();
    
    if (token) {
      res.json({
        success: true,
        message: 'Подключение к API СДЭК успешно установлено',
        token_preview: token.substring(0, 20) + '...'
      });
    } else {
      throw new Error('Не удалось получить токен');
    }
  } catch (error) {
    console.error('❌ Ошибка подключения к API СДЭК:', error.message);
    res.status(500).json({
      success: false,
      error: 'Не удалось подключиться к API СДЭК. Проверьте учетные данные.'
    });
  }
});

module.exports = router;