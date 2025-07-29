// Типы для работы с СДЭК API

export interface CdekPoint {
  code: string;
  name: string;
  type?: string;
  location: {
    latitude: number;
    longitude: number;
    city: string;
    city_code?: number;
    postal_code?: string;
    address: string;
    address_full: string;
  };
  phones?: Array<{
    number: string;
  }>;
  work_time?: string;
  work_time_list?: Array<{
    day: number;
    time: string;
  }>;
  address_comment?: string;
}

export interface CartDimensions {
  length: number;
  width: number;
  height: number;
}

export interface CartData {
  weight: number;
  value: number;
  dimensions: CartDimensions;
  hasRealDimensions: boolean;
  packagesCount: number;
  totalItems: number;
  totalVolume: number;
}

export interface DeliveryCost {
  delivery_sum: number;
  period_min?: number;
  period_max?: number;
  api_success: boolean;
  fallback?: boolean;
  alternative_tariff?: number;
}

export interface AddressSuggestion {
  city: string;
  display: string;
  score: number;
  type: 'city';
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  count?: number;
  city?: string;
}

export interface CartItem {
  product_id?: number;
  quantity: number;
  length?: number;
  width?: number;
  height?: number;
  weight?: number;
  price?: number;
  dimensions?: {
    length?: number;
    width?: number;
    height?: number;
  };
}

export interface WooCommerceConfig {
  apiUrl?: string;
  apiKey?: string;
  apiSecret?: string;
}