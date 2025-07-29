import React, { useState, useEffect, useCallback } from 'react';
import styled from 'styled-components';
import AddressSearch from '../AddressSearch/AddressSearch';
import CdekMap from '../CdekMap/CdekMap';
import { CdekPoint, CartData, DeliveryCost, CartItem, WooCommerceConfig } from '../../types';
import { apiService } from '../../services/api';

interface CdekDeliveryProps {
  cartItems: CartItem[];
  wooConfig?: WooCommerceConfig;
  onPointSelect?: (point: CdekPoint, cost: DeliveryCost) => void;
  onError?: (error: string) => void;
  initialCity?: string;
}

const Container = styled.div`
  margin: 20px 0;
  padding: 20px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  
  @media (max-width: 768px) {
    margin: 15px 0;
    padding: 15px;
    border-radius: 6px;
  }
`;

const Title = styled.h4`
  margin: 0 0 20px 0;
  color: #333;
  font-size: 18px;
  font-weight: 600;
  
  @media (max-width: 768px) {
    font-size: 16px;
    margin-bottom: 15px;
  }
`;

const Section = styled.div`
  margin-bottom: 20px;
  
  &:last-child {
    margin-bottom: 0;
  }
`;

const Label = styled.label`
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
  font-size: 14px;
`;

const SelectedPointInfo = styled.div`
  margin-bottom: 15px;
  padding: 15px;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  
  @media (max-width: 768px) {
    padding: 12px;
  }
`;

const SelectedPointTitle = styled.div`
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
  font-size: 14px;
`;

const SelectedPointDetails = styled.div`
  font-size: 13px;
  color: #666;
  line-height: 1.4;
`;

const DeliveryCostInfo = styled.div<{ isCalculating?: boolean }>`
  margin-top: 10px;
  padding: 10px 12px;
  background: ${props => props.isCalculating ? '#fff3cd' : '#d4edda'};
  border: 1px solid ${props => props.isCalculating ? '#ffeaa7' : '#c3e6cb'};
  border-radius: 4px;
  color: ${props => props.isCalculating ? '#856404' : '#155724'};
  font-size: 13px;
  font-weight: 500;
`;

const ErrorMessage = styled.div`
  margin: 10px 0;
  padding: 12px;
  background: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  color: #721c24;
  font-size: 13px;
`;

const CartInfo = styled.div`
  margin-bottom: 15px;
  padding: 12px;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  font-size: 13px;
  color: #666;
`;

const CartInfoTitle = styled.div`
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
`;

const CartInfoGrid = styled.div`
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 8px;
  
  @media (max-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
`;

const CartInfoItem = styled.div`
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  border-bottom: 1px solid #f0f0f0;
  
  &:last-child {
    border-bottom: none;
  }
`;

const CdekDelivery: React.FC<CdekDeliveryProps> = ({
  cartItems,
  wooConfig,
  onPointSelect,
  onError,
  initialCity = ''
}) => {
  const [address, setAddress] = useState(initialCity);
  const [selectedCity, setSelectedCity] = useState<string>('');
  const [points, setPoints] = useState<CdekPoint[]>([]);
  const [selectedPoint, setSelectedPoint] = useState<CdekPoint | null>(null);
  const [cartData, setCartData] = useState<CartData | null>(null);
  const [deliveryCost, setDeliveryCost] = useState<DeliveryCost | null>(null);
  const [isLoadingPoints, setIsLoadingPoints] = useState(false);
  const [isCalculatingCost, setIsCalculatingCost] = useState(false);
  const [error, setError] = useState<string>('');

  // Обработка данных корзины при изменении
  useEffect(() => {
    const processCart = async () => {
      if (!cartItems || cartItems.length === 0) return;

      try {
        console.log('🛒 Обрабатываем данные корзины:', cartItems);
        const processed = await apiService.processCartData(cartItems, wooConfig);
        setCartData(processed);
        console.log('✅ Данные корзины обработаны:', processed);
      } catch (error) {
        console.error('❌ Ошибка обработки корзины:', error);
        const errorMessage = error instanceof Error ? error.message : 'Ошибка обработки корзины';
        setError(errorMessage);
        onError?.(errorMessage);
      }
    };

    processCart();
  }, [cartItems, wooConfig, onError]);

  // Поиск пунктов выдачи при выборе города
  const handleCitySelect = useCallback(async (city: string) => {
    setSelectedCity(city);
    setSelectedPoint(null);
    setDeliveryCost(null);
    setError('');
    
    if (!city.trim()) {
      setPoints([]);
      return;
    }

    setIsLoadingPoints(true);
    
    try {
      console.log('🔍 Поиск пунктов выдачи для города:', city);
      const foundPoints = await apiService.getCdekPoints(city);
      setPoints(foundPoints);
      console.log(`✅ Найдено ${foundPoints.length} пунктов выдачи`);
      
      if (foundPoints.length === 0) {
        setError(`В городе "${city}" не найдено пунктов выдачи СДЭК`);
      }
    } catch (error) {
      console.error('❌ Ошибка поиска пунктов:', error);
      const errorMessage = error instanceof Error ? error.message : 'Ошибка поиска пунктов выдачи';
      setError(errorMessage);
      onError?.(errorMessage);
      setPoints([]);
    } finally {
      setIsLoadingPoints(false);
    }
  }, [onError]);

  // Расчет стоимости доставки при выборе пункта
  const handlePointSelect = useCallback(async (point: CdekPoint) => {
    setSelectedPoint(point);
    setDeliveryCost(null);
    setError('');

    if (!cartData) {
      setError('Данные корзины не загружены');
      return;
    }

    setIsCalculatingCost(true);

    try {
      console.log('💰 Расчет стоимости доставки для пункта:', point.code);
      const cost = await apiService.calculateDeliveryCost(point.code, point, cartData);
      setDeliveryCost(cost);
      console.log('✅ Стоимость доставки рассчитана:', cost);

      // Вызываем callback если предоставлен
      onPointSelect?.(point, cost);
    } catch (error) {
      console.error('❌ Ошибка расчета стоимости:', error);
      const errorMessage = error instanceof Error ? error.message : 'Ошибка расчета стоимости доставки';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setIsCalculatingCost(false);
    }
  }, [cartData, onPointSelect, onError]);

  // Форматирование информации о выбранном пункте
  const formatSelectedPointInfo = (point: CdekPoint): string => {
    let pointName = point.name || 'Пункт выдачи';
    if (pointName.includes(',')) {
      pointName = pointName.split(',').slice(1).join(',').trim();
    }

    let info = pointName;
    
    if (point.location?.address_full) {
      info += `\nАдрес: ${point.location.address_full}`;
    }

    if (point.phones && point.phones.length > 0) {
      const phoneNumbers = point.phones.map(phone => phone.number).join(', ');
      info += `\nТелефон: ${phoneNumbers}`;
    }

    return info;
  };

  // Форматирование стоимости доставки
  const formatDeliveryCost = (cost: DeliveryCost): string => {
    let costText = `${cost.delivery_sum.toLocaleString('ru-RU')} руб.`;
    
    if (cost.period_min && cost.period_max) {
      costText += ` (${cost.period_min}-${cost.period_max} дней)`;
    }

    if (cost.fallback) {
      costText += ' (приблизительный расчет)';
    } else if (cost.api_success) {
      costText += ' (точный расчет)';
    }

    if (cost.alternative_tariff) {
      costText += ` (тариф ${cost.alternative_tariff})`;
    }

    return costText;
  };

  return (
    <Container>
      <Title>Выберите пункт выдачи СДЭК на карте:</Title>

      {/* Информация о корзине */}
      {cartData && (
        <CartInfo>
          <CartInfoTitle>📦 Информация о заказе:</CartInfoTitle>
          <CartInfoGrid>
            <CartInfoItem>
              <span>Вес:</span>
              <strong>{cartData.weight} г</strong>
            </CartInfoItem>
            <CartInfoItem>
              <span>Стоимость:</span>
              <strong>{cartData.value.toLocaleString('ru-RU')} руб.</strong>
            </CartInfoItem>
            <CartInfoItem>
              <span>Габариты:</span>
              <strong>
                {cartData.dimensions.length}×{cartData.dimensions.width}×{cartData.dimensions.height} см
              </strong>
            </CartInfoItem>
            {cartData.packagesCount > 1 && (
              <CartInfoItem>
                <span>Коробок:</span>
                <strong>{cartData.packagesCount}</strong>
              </CartInfoItem>
            )}
          </CartInfoGrid>
        </CartInfo>
      )}

      {/* Поиск города */}
      <Section>
        <Label htmlFor="city-search">Город доставки:</Label>
        <AddressSearch
          value={address}
          onChange={setAddress}
          onCitySelect={handleCitySelect}
          placeholder="Например: Москва"
        />
      </Section>

      {/* Информация о выбранном пункте */}
      {selectedPoint && (
        <SelectedPointInfo>
          <SelectedPointTitle>✅ Выбранный пункт выдачи:</SelectedPointTitle>
          <SelectedPointDetails>
            {formatSelectedPointInfo(selectedPoint).split('\n').map((line, index) => (
              <div key={index}>{line}</div>
            ))}
          </SelectedPointDetails>
          
          {/* Информация о стоимости */}
          {deliveryCost && (
            <DeliveryCostInfo>
              💰 Стоимость доставки: {formatDeliveryCost(deliveryCost)}
            </DeliveryCostInfo>
          )}
          
          {isCalculatingCost && (
            <DeliveryCostInfo isCalculating>
              🔄 Рассчитываем стоимость доставки...
            </DeliveryCostInfo>
          )}
        </SelectedPointInfo>
      )}

      {/* Ошибки */}
      {error && <ErrorMessage>❌ {error}</ErrorMessage>}

      {/* Карта */}
      <Section>
        <CdekMap
          points={points}
          selectedPoint={selectedPoint}
          onPointSelect={handlePointSelect}
          city={selectedCity}
          isLoading={isLoadingPoints}
        />
      </Section>

      {/* Подсказка */}
      <p style={{ fontSize: '14px', color: '#666', marginTop: '15px', marginBottom: '0' }}>
        💡 Введите город в поле выше, затем выберите пункт выдачи на карте
      </p>
    </Container>
  );
};

export default CdekDelivery;