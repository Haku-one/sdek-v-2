import React, { useState } from 'react';
import styled from 'styled-components';
import CdekDelivery from './components/CdekDelivery/CdekDelivery';
import { CdekPoint, DeliveryCost, CartItem } from './types';
import { apiService } from './services/api';

const AppContainer = styled.div`
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
  
  @media (max-width: 768px) {
    padding: 15px;
  }
`;

const Header = styled.header`
  text-align: center;
  margin-bottom: 30px;
  
  h1 {
    color: #333;
    font-size: 28px;
    margin-bottom: 10px;
    
    @media (max-width: 768px) {
      font-size: 24px;
    }
  }
  
  p {
    color: #666;
    font-size: 16px;
    margin: 0;
    
    @media (max-width: 768px) {
      font-size: 14px;
    }
  }
`;

const DemoControls = styled.div`
  margin-bottom: 30px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #dee2e6;
`;

const ControlsTitle = styled.h3`
  margin: 0 0 15px 0;
  color: #333;
  font-size: 16px;
`;

const ButtonGroup = styled.div`
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 15px;
`;

const Button = styled.button<{ variant?: 'primary' | 'secondary' }>`
  padding: 8px 16px;
  border: 1px solid ${props => props.variant === 'primary' ? '#007cba' : '#6c757d'};
  background: ${props => props.variant === 'primary' ? '#007cba' : 'white'};
  color: ${props => props.variant === 'primary' ? 'white' : '#6c757d'};
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
  
  &:hover {
    background: ${props => props.variant === 'primary' ? '#006ba3' : '#f8f9fa'};
  }
  
  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
`;

const TestResult = styled.div<{ type?: 'success' | 'error' }>`
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 4px;
  font-size: 14px;
  background: ${props => props.type === 'error' ? '#f8d7da' : '#d4edda'};
  border: 1px solid ${props => props.type === 'error' ? '#f5c6cb' : '#c3e6cb'};
  color: ${props => props.type === 'error' ? '#721c24' : '#155724'};
`;

const SelectedPointDisplay = styled.div`
  margin-top: 20px;
  padding: 15px;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
`;

const SelectedPointTitle = styled.h4`
  margin: 0 0 10px 0;
  color: #333;
  font-size: 16px;
`;

const SelectedPointInfo = styled.pre`
  margin: 0;
  font-family: 'Courier New', monospace;
  font-size: 12px;
  color: #666;
  white-space: pre-wrap;
  background: #f8f9fa;
  padding: 10px;
  border-radius: 4px;
`;

// Демо данные корзины
const demoCartItems: CartItem[] = [
  {
    product_id: 1,
    quantity: 2,
    length: 25,
    width: 15,
    height: 10,
    weight: 300,
    price: 1500
  },
  {
    product_id: 2,
    quantity: 1,
    length: 30,
    width: 20,
    height: 5,
    weight: 200,
    price: 800
  }
];

function App() {
  const [selectedPoint, setSelectedPoint] = useState<CdekPoint | null>(null);
  const [deliveryCost, setDeliveryCost] = useState<DeliveryCost | null>(null);
  const [testResult, setTestResult] = useState<string>('');
  const [testError, setTestError] = useState<boolean>(false);
  const [isTestingConnection, setIsTestingConnection] = useState(false);

  // Обработка выбора пункта выдачи
  const handlePointSelect = (point: CdekPoint, cost: DeliveryCost) => {
    setSelectedPoint(point);
    setDeliveryCost(cost);
    console.log('🎯 Выбран пункт выдачи:', point);
    console.log('💰 Стоимость доставки:', cost);
  };

  // Обработка ошибок
  const handleError = (error: string) => {
    console.error('❌ Ошибка СДЭК:', error);
    setTestResult(error);
    setTestError(true);
  };

  // Тестирование подключения к API
  const testConnection = async () => {
    setIsTestingConnection(true);
    setTestResult('');
    setTestError(false);

    try {
      const message = await apiService.testCdekConnection();
      setTestResult(message);
      setTestError(false);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Ошибка подключения';
      setTestResult(errorMessage);
      setTestError(true);
    } finally {
      setIsTestingConnection(false);
    }
  };

  // Очистка выбранного пункта
  const clearSelection = () => {
    setSelectedPoint(null);
    setDeliveryCost(null);
  };

  return (
    <AppContainer>
      <Header>
        <h1>🚚 Современная интеграция СДЭК доставки</h1>
        <p>React/Node.js версия плагина для WooCommerce без кэширования</p>
      </Header>

      <DemoControls>
        <ControlsTitle>🔧 Инструменты разработчика:</ControlsTitle>
        
        <ButtonGroup>
          <Button 
            variant="primary" 
            onClick={testConnection}
            disabled={isTestingConnection}
          >
            {isTestingConnection ? '🔄 Тестирование...' : '🔗 Тест подключения к СДЭК'}
          </Button>
          
          <Button onClick={clearSelection}>
            🗑️ Очистить выбор
          </Button>
        </ButtonGroup>

        {testResult && (
          <TestResult type={testError ? 'error' : 'success'}>
            {testError ? '❌' : '✅'} {testResult}
          </TestResult>
        )}
      </DemoControls>

      <CdekDelivery
        cartItems={demoCartItems}
        onPointSelect={handlePointSelect}
        onError={handleError}
        initialCity=""
      />

      {selectedPoint && deliveryCost && (
        <SelectedPointDisplay>
          <SelectedPointTitle>📋 Выбранные данные для интеграции:</SelectedPointTitle>
          <SelectedPointInfo>
{`Выбранный пункт выдачи:
Код: ${selectedPoint.code}
Название: ${selectedPoint.name}
Адрес: ${selectedPoint.location?.address_full || selectedPoint.location?.address || 'Не указан'}
Координаты: ${selectedPoint.location?.latitude}, ${selectedPoint.location?.longitude}

Стоимость доставки:
Сумма: ${deliveryCost.delivery_sum} руб.
Сроки: ${deliveryCost.period_min && deliveryCost.period_max ? `${deliveryCost.period_min}-${deliveryCost.period_max} дней` : 'Не указаны'}
Источник: ${deliveryCost.api_success ? 'API СДЭК' : 'Fallback расчет'}
${deliveryCost.alternative_tariff ? `Альтернативный тариф: ${deliveryCost.alternative_tariff}` : ''}

Эти данные можно передать в WooCommerce для обновления заказа.`}
          </SelectedPointInfo>
        </SelectedPointDisplay>
      )}
    </AppContainer>
  );
}

export default App;
