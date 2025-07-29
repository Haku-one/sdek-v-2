import axios, { AxiosInstance } from 'axios';
import { 
  CdekPoint, 
  DeliveryCost, 
  AddressSuggestion, 
  CartData, 
  CartItem, 
  ApiResponse,
  WooCommerceConfig 
} from '../types';

class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = axios.create({
      baseURL: process.env.REACT_APP_API_URL || 'http://localhost:3001/api',
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Добавляем интерцептор для обработки ошибок
    this.api.interceptors.response.use(
      (response) => response,
      (error) => {
        console.error('API Error:', error.response?.data || error.message);
        return Promise.reject(error);
      }
    );
  }

  // Получение пунктов выдачи СДЭК
  async getCdekPoints(city?: string): Promise<CdekPoint[]> {
    try {
      const params = city ? { city } : {};
      const response = await this.api.get<ApiResponse<CdekPoint[]>>('/cdek/points', { params });
      
      if (response.data.success && response.data.data) {
        return response.data.data;
      }
      
      throw new Error(response.data.error || 'Не удалось получить пункты выдачи');
    } catch (error) {
      console.error('Ошибка получения пунктов СДЭК:', error);
      throw error;
    }
  }

  // Расчет стоимости доставки СДЭК
  async calculateDeliveryCost(
    pointCode: string,
    pointData: CdekPoint,
    cartData: CartData
  ): Promise<DeliveryCost> {
    try {
      const response = await this.api.post<ApiResponse<DeliveryCost>>('/cdek/calculate', {
        pointCode,
        pointData,
        cartWeight: cartData.weight,
        cartDimensions: cartData.dimensions,
        cartValue: cartData.value,
        hasRealDimensions: cartData.hasRealDimensions,
        packagesCount: cartData.packagesCount
      });

      if (response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(response.data.error || 'Не удалось рассчитать стоимость доставки');
    } catch (error) {
      console.error('Ошибка расчета стоимости:', error);
      throw error;
    }
  }

  // Тестирование подключения к СДЭК API
  async testCdekConnection(): Promise<string> {
    try {
      const response = await this.api.get<ApiResponse<{ message: string }>>('/cdek/test-connection');
      
      if (response.data.success && response.data.data) {
        return response.data.data.message;
      }

      throw new Error(response.data.error || 'Не удалось подключиться к API СДЭК');
    } catch (error) {
      console.error('Ошибка подключения к СДЭК:', error);
      throw error;
    }
  }

  // Поиск адресов с автокомплитом
  async getAddressSuggestions(query: string, limit: number = 10): Promise<AddressSuggestion[]> {
    try {
      if (!query || query.length < 2) {
        return [];
      }

      const response = await this.api.get<ApiResponse<AddressSuggestion[]>>('/address/suggestions', {
        params: { query, limit }
      });

      if (response.data.success && response.data.data) {
        return response.data.data;
      }

      return [];
    } catch (error) {
      console.error('Ошибка поиска адресов:', error);
      return [];
    }
  }

  // Валидация адреса
  async validateAddress(address: string): Promise<{ isValid: boolean; foundCity?: string }> {
    try {
      const response = await this.api.post<ApiResponse<{ isValid: boolean; foundCity?: string }>>('/address/validate', {
        address
      });

      if (response.data.success && response.data.data) {
        return response.data.data;
      }

      return { isValid: false };
    } catch (error) {
      console.error('Ошибка валидации адреса:', error);
      return { isValid: false };
    }
  }

  // Обработка данных корзины WooCommerce
  async processCartData(
    cartItems: CartItem[], 
    wooConfig?: WooCommerceConfig
  ): Promise<CartData> {
    try {
      const response = await this.api.post<ApiResponse<CartData>>('/woocommerce/cart-data', {
        cartItems,
        wooApiUrl: wooConfig?.apiUrl,
        wooApiKey: wooConfig?.apiKey,
        wooApiSecret: wooConfig?.apiSecret
      });

      if (response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(response.data.error || 'Не удалось обработать данные корзины');
    } catch (error) {
      console.error('Ошибка обработки корзины:', error);
      throw error;
    }
  }

  // Обновление стоимости доставки в WooCommerce
  async updateWooCommerceShipping(
    orderId: string,
    shippingCost: number,
    pointData: CdekPoint,
    wooConfig?: WooCommerceConfig
  ): Promise<any> {
    try {
      const response = await this.api.post<ApiResponse<any>>('/woocommerce/update-shipping', {
        orderId,
        shippingCost,
        pointData,
        wooApiUrl: wooConfig?.apiUrl,
        wooApiKey: wooConfig?.apiKey,
        wooApiSecret: wooConfig?.apiSecret
      });

      if (response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(response.data.error || 'Не удалось обновить стоимость доставки');
    } catch (error) {
      console.error('Ошибка обновления доставки:', error);
      throw error;
    }
  }
}

// Создаем единственный экземпляр сервиса
export const apiService = new ApiService();
export default apiService;