import React, { useEffect, useRef, useState } from 'react';
import styled from 'styled-components';
import { CdekPoint } from '../../types';

// Расширяем глобальный объект window для Yandex Maps
declare global {
  interface Window {
    ymaps: any;
  }
}

interface CdekMapProps {
  points: CdekPoint[];
  selectedPoint?: CdekPoint | null;
  onPointSelect: (point: CdekPoint) => void;
  city?: string;
  isLoading?: boolean;
}

const MapContainer = styled.div`
  position: relative;
  width: 100%;
  height: 450px;
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background-color: #f5f5f5;
  
  @media (max-width: 768px) {
    height: 350px;
    border-radius: 6px;
  }
`;

const MapElement = styled.div`
  width: 100%;
  height: 100%;
  display: block !important;
  visibility: visible !important;
  position: relative !important;
`;

const LoadingOverlay = styled.div`
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  border-radius: 8px;
`;

const LoadingSpinner = styled.div`
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #007cba;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 15px;
  
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
`;

const LoadingText = styled.div`
  color: #666;
  font-size: 14px;
  text-align: center;
  
  div:first-child {
    font-weight: 500;
    margin-bottom: 4px;
  }
  
  div:last-child {
    font-size: 12px;
    opacity: 0.8;
  }
`;

const ErrorMessage = styled.div`
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: #666;
  font-size: 14px;
  padding: 20px;
`;

const PointsInfo = styled.div`
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(255, 255, 255, 0.95);
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  color: #666;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  z-index: 100;
  
  @media (max-width: 768px) {
    font-size: 11px;
    padding: 6px 10px;
  }
`;

const CdekMap: React.FC<CdekMapProps> = ({
  points,
  selectedPoint,
  onPointSelect,
  city,
  isLoading = false
}) => {
  const mapRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<any>(null);
  const [mapReady, setMapReady] = useState(false);
  const [mapError, setMapError] = useState<string | null>(null);

  // Инициализация Yandex Maps
  useEffect(() => {
    const initializeMap = () => {
      if (!window.ymaps) {
        setMapError('Yandex Maps API не загружен');
        return;
      }

      if (!mapRef.current) return;

      try {
        // Создаем карту
        const map = new window.ymaps.Map(mapRef.current, {
          center: [55.753994, 37.622093], // Москва по умолчанию
          zoom: 10,
          controls: ['zoomControl', 'searchControl']
        });

        mapInstanceRef.current = map;
        setMapReady(true);
        setMapError(null);

        console.log('✅ Yandex Maps инициализирована');
      } catch (error) {
        console.error('❌ Ошибка инициализации карты:', error);
        setMapError('Ошибка инициализации карты');
      }
    };

    // Загружаем Yandex Maps API если еще не загружен
    if (!window.ymaps) {
      const script = document.createElement('script');
      script.src = 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU';
      script.onload = () => {
        window.ymaps.ready(initializeMap);
      };
      script.onerror = () => {
        setMapError('Не удалось загрузить Yandex Maps API');
      };
      document.head.appendChild(script);
    } else if (window.ymaps.ready) {
      window.ymaps.ready(initializeMap);
    } else {
      initializeMap();
    }

    // Cleanup при размонтировании
    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.destroy();
        mapInstanceRef.current = null;
      }
    };
  }, []);

  // Отображение пунктов на карте
  useEffect(() => {
    if (!mapReady || !mapInstanceRef.current || !points.length) return;

    const map = mapInstanceRef.current;
    
    // Очищаем предыдущие метки
    map.geoObjects.removeAll();

    const bounds: number[][] = [];
    let validPointsCount = 0;

    points.forEach((point) => {
      if (!point.location?.latitude || !point.location?.longitude) return;

      const coords = [point.location.latitude, point.location.longitude];
      bounds.push(coords);
      validPointsCount++;

      // Создаем метку
      const placemark = new window.ymaps.Placemark(coords, {
        balloonContent: formatPointInfo(point),
        hintContent: point.name
      }, {
        preset: selectedPoint?.code === point.code ? 'islands#blueIcon' : 'islands#redIcon'
      });

      // Обработчик клика по метке
      placemark.events.add('click', () => {
        onPointSelect(point);
      });

      map.geoObjects.add(placemark);
    });

    // Автоматическое позиционирование карты
    if (bounds.length > 0) {
      if (bounds.length === 1) {
        map.setCenter(bounds[0], 14);
      } else {
        // Вычисляем границы для отображения всех точек
        const minLat = Math.min(...bounds.map(coord => coord[0]));
        const maxLat = Math.max(...bounds.map(coord => coord[0]));
        const minLon = Math.min(...bounds.map(coord => coord[1]));
        const maxLon = Math.max(...bounds.map(coord => coord[1]));

        const centerLat = (minLat + maxLat) / 2;
        const centerLon = (minLon + maxLon) / 2;
        
        const latDiff = maxLat - minLat;
        const lonDiff = maxLon - minLon;
        const maxDiff = Math.max(latDiff, lonDiff);

        let zoom = 12;
        if (maxDiff < 0.01) zoom = 15;
        else if (maxDiff < 0.05) zoom = 13;
        else if (maxDiff < 0.1) zoom = 12;
        else if (maxDiff < 0.5) zoom = 10;
        else zoom = 8;

        map.setCenter([centerLat, centerLon], zoom);
      }
    }

    console.log(`📍 Отображено ${validPointsCount} пунктов выдачи на карте`);
  }, [points, mapReady, selectedPoint, onPointSelect]);

  // Функция форматирования информации о пункте
  const formatPointInfo = (point: CdekPoint): string => {
    let pointName = point.name || 'Пункт выдачи';
    if (pointName.includes(',')) {
      pointName = pointName.split(',').slice(1).join(',').trim();
    }

    let html = `<strong>${pointName}</strong><br>`;
    
    if (point.location?.address_full) {
      html += `Адрес: ${point.location.address_full}<br>`;
    } else if (point.location?.address) {
      html += `Адрес: ${point.location.address}<br>`;
    }

    if (point.phones && point.phones.length > 0) {
      const phoneNumbers = point.phones.map(phone => phone.number).join(', ');
      html += `Телефон: ${phoneNumbers}<br>`;
    }

    html += `Режим работы: ${formatWorkTime(point)}<br>`;
    
    if (point.code) {
      html += `Код: ${point.code}<br>`;
    }

    return html;
  };

  // Функция форматирования времени работы
  const formatWorkTime = (point: CdekPoint): string => {
    if (point.work_time_list && point.work_time_list.length > 0) {
      const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
      let schedule = '';
      
      point.work_time_list.forEach((time) => {
        if (time.day !== undefined && time.time) {
          schedule += `${days[time.day - 1]}: ${time.time} `;
        }
      });
      
      return schedule || 'Не указан';
    }
    
    if (point.work_time && typeof point.work_time === 'string') {
      return point.work_time;
    }
    
    return 'Не указан';
  };

  // Получение информации о количестве пунктов
  const getPointsInfo = (): string => {
    if (isLoading) return 'Загружаем пункты выдачи...';
    if (points.length === 0) return 'Пункты выдачи не найдены';
    
    const locationInfo = city ? ` в городе "${city}"` : '';
    return `Найдено ${points.length} пунктов выдачи${locationInfo}`;
  };

  return (
    <MapContainer>
      <MapElement ref={mapRef} />
      
      {(isLoading || !mapReady) && (
        <LoadingOverlay>
          <LoadingSpinner />
          <LoadingText>
            <div>
              {isLoading ? 'Загружаем пункты выдачи...' : 'Инициализация карты...'}
            </div>
            <div>
              {isLoading ? 'Это может занять несколько секунд' : 'Подключение к Yandex Maps'}
            </div>
          </LoadingText>
        </LoadingOverlay>
      )}

      {mapError && (
        <ErrorMessage>
          ❌ {mapError}
          <br />
          <small>Попробуйте обновить страницу</small>
        </ErrorMessage>
      )}

      {mapReady && <PointsInfo>{getPointsInfo()}</PointsInfo>}
    </MapContainer>
  );
};

export default CdekMap;