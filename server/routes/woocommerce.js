const express = require('express');
const router = express.Router();
const axios = require('axios');

// Получение данных корзины WooCommerce
router.post('/cart-data', async (req, res) => {
  try {
    const { cartItems, wooApiUrl, wooApiKey, wooApiSecret } = req.body;
    
    if (!cartItems || !Array.isArray(cartItems)) {
      return res.status(400).json({
        success: false,
        error: 'Некорректные данные корзины'
      });
    }

    console.log('🛒 Обработка данных корзины WooCommerce:', cartItems.length, 'товаров');

    const processedCart = await processCartData(cartItems, wooApiUrl, wooApiKey, wooApiSecret);

    res.json({
      success: true,
      data: processedCart
    });
  } catch (error) {
    console.error('❌ Ошибка обработки данных корзины:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Обновление стоимости доставки в WooCommerce
router.post('/update-shipping', async (req, res) => {
  try {
    const { 
      orderId, 
      shippingCost, 
      pointData, 
      wooApiUrl, 
      wooApiKey, 
      wooApiSecret 
    } = req.body;

    if (!orderId || !shippingCost) {
      return res.status(400).json({
        success: false,
        error: 'Не указаны ID заказа или стоимость доставки'
      });
    }

    console.log('📦 Обновление доставки для заказа:', orderId, 'стоимость:', shippingCost);

    // Формируем данные для обновления заказа
    const updateData = {
      shipping_lines: [{
        method_id: 'cdek_delivery',
        method_title: 'СДЭК Доставка',
        total: shippingCost.toString()
      }]
    };

    // Добавляем мета-данные о пункте выдачи
    if (pointData) {
      updateData.meta_data = [
        {
          key: '_cdek_point_code',
          value: pointData.code
        },
        {
          key: '_cdek_point_data',
          value: JSON.stringify(pointData)
        }
      ];
    }

    // Если предоставлены данные API WooCommerce, обновляем заказ
    if (wooApiUrl && wooApiKey && wooApiSecret) {
      const wooResponse = await updateWooCommerceOrder(
        orderId, 
        updateData, 
        wooApiUrl, 
        wooApiKey, 
        wooApiSecret
      );
      
      res.json({
        success: true,
        data: {
          orderId: orderId,
          shippingCost: shippingCost,
          wooResponse: wooResponse
        }
      });
    } else {
      // Возвращаем данные для ручного обновления
      res.json({
        success: true,
        data: {
          orderId: orderId,
          shippingCost: shippingCost,
          updateData: updateData,
          message: 'Данные для обновления заказа подготовлены'
        }
      });
    }
  } catch (error) {
    console.error('❌ Ошибка обновления доставки:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Получение информации о заказе
router.get('/order/:orderId', async (req, res) => {
  try {
    const { orderId } = req.params;
    const { wooApiUrl, wooApiKey, wooApiSecret } = req.query;

    if (!wooApiUrl || !wooApiKey || !wooApiSecret) {
      return res.status(400).json({
        success: false,
        error: 'Не указаны данные для подключения к WooCommerce API'
      });
    }

    console.log('📋 Получение информации о заказе:', orderId);

    const orderData = await getWooCommerceOrder(orderId, wooApiUrl, wooApiKey, wooApiSecret);

    res.json({
      success: true,
      data: orderData
    });
  } catch (error) {
    console.error('❌ Ошибка получения заказа:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Функция обработки данных корзины
async function processCartData(cartItems, wooApiUrl, wooApiKey, wooApiSecret) {
  let totalWeight = 0;
  let totalValue = 0;
  let totalVolume = 0;
  let maxLength = 0;
  let maxWidth = 0;
  let maxHeight = 0;
  let hasValidDimensions = false;
  let totalItems = 0;

  for (const item of cartItems) {
    const quantity = parseInt(item.quantity) || 1;
    totalItems += quantity;

    // Получаем данные товара из WooCommerce API если доступно
    let productData = item;
    if (wooApiUrl && wooApiKey && wooApiSecret && item.product_id) {
      try {
        productData = await getWooCommerceProduct(item.product_id, wooApiUrl, wooApiKey, wooApiSecret);
      } catch (error) {
        console.warn('⚠️ Не удалось получить данные товара из API:', error.message);
      }
    }

    // Обрабатываем габариты
    const length = parseFloat(productData.dimensions?.length || item.length || 0);
    const width = parseFloat(productData.dimensions?.width || item.width || 0);
    const height = parseFloat(productData.dimensions?.height || item.height || 0);

    if (length && width && height) {
      hasValidDimensions = true;
      const itemVolume = length * width * height * quantity;
      totalVolume += itemVolume;
      
      maxLength = Math.max(maxLength, length);
      maxWidth = Math.max(maxWidth, width);
      maxHeight = Math.max(maxHeight, height);
    }

    // Обрабатываем вес
    const weight = parseFloat(productData.weight || item.weight || 0);
    if (weight) {
      totalWeight += weight * quantity;
    }

    // Обрабатываем стоимость
    const price = parseFloat(productData.price || item.price || 0);
    totalValue += price * quantity;
  }

  // Рассчитываем размеры упаковки
  let dimensions;
  let packagesCount = 1;

  if (hasValidDimensions && totalVolume > 0) {
    if (totalItems <= 2) {
      dimensions = {
        length: Math.ceil(maxLength * 1.05),
        width: Math.ceil(maxWidth * 1.05),
        height: Math.ceil(maxHeight * 1.05)
      };
    } else {
      const volumeRatio = Math.pow(totalVolume / (maxLength * maxWidth * maxHeight), 1/3);
      
      dimensions = {
        length: Math.ceil(maxLength * Math.max(volumeRatio, 1) * 1.1),
        width: Math.ceil(maxWidth * Math.max(volumeRatio, 1) * 1.1),
        height: Math.ceil(maxHeight * Math.max(volumeRatio, 1) * 1.1)
      };
    }

    // Ограничиваем размеры
    dimensions.length = Math.max(10, Math.min(dimensions.length, 150));
    dimensions.width = Math.max(10, Math.min(dimensions.width, 150));
    dimensions.height = Math.max(5, Math.min(dimensions.height, 150));

    // Проверяем лимит СДЭК (сумма длины, ширины и высоты не должна превышать 300 см)
    const totalSize = dimensions.length + dimensions.width + dimensions.height;
    if (totalSize > 300) {
      packagesCount = Math.ceil(totalSize / 280);
      
      // Пересчитываем размеры для одной коробки
      const scaleFactor = 280 / totalSize;
      dimensions = {
        length: Math.max(10, Math.ceil(dimensions.length * scaleFactor)),
        width: Math.max(10, Math.ceil(dimensions.width * scaleFactor)),
        height: Math.max(5, Math.ceil(dimensions.height * scaleFactor))
      };

      totalWeight = totalWeight / packagesCount;
    }
  } else {
    // Размеры по умолчанию
    dimensions = {
      length: 30,
      width: 20,
      height: 15
    };
  }

  // Минимальные значения
  if (totalWeight === 0) totalWeight = 500;
  if (totalValue === 0) totalValue = 1000;

  return {
    weight: totalWeight,
    value: totalValue,
    dimensions: dimensions,
    hasRealDimensions: hasValidDimensions,
    packagesCount: packagesCount,
    totalItems: totalItems,
    totalVolume: totalVolume
  };
}

// Функция получения товара из WooCommerce API
async function getWooCommerceProduct(productId, apiUrl, apiKey, apiSecret) {
  const auth = Buffer.from(`${apiKey}:${apiSecret}`).toString('base64');
  
  const response = await axios.get(`${apiUrl}/wp-json/wc/v3/products/${productId}`, {
    headers: {
      'Authorization': `Basic ${auth}`,
      'Content-Type': 'application/json'
    }
  });

  return response.data;
}

// Функция обновления заказа в WooCommerce
async function updateWooCommerceOrder(orderId, updateData, apiUrl, apiKey, apiSecret) {
  const auth = Buffer.from(`${apiKey}:${apiSecret}`).toString('base64');
  
  const response = await axios.put(`${apiUrl}/wp-json/wc/v3/orders/${orderId}`, updateData, {
    headers: {
      'Authorization': `Basic ${auth}`,
      'Content-Type': 'application/json'
    }
  });

  return response.data;
}

// Функция получения заказа из WooCommerce
async function getWooCommerceOrder(orderId, apiUrl, apiKey, apiSecret) {
  const auth = Buffer.from(`${apiKey}:${apiSecret}`).toString('base64');
  
  const response = await axios.get(`${apiUrl}/wp-json/wc/v3/orders/${orderId}`, {
    headers: {
      'Authorization': `Basic ${auth}`,
      'Content-Type': 'application/json'
    }
  });

  return response.data;
}

module.exports = router;