/**
 * СДЭК Доставка - ES6 МОДУЛЬ для WordPress 6.5+
 * 🚀 РЕВОЛЮЦИОННАЯ АРХИТЕКТУРА С МОДУЛЯМИ
 */

// ES6 импорты для современных браузеров
const { performance, IntersectionObserver, AbortController, fetch } = window;

// Экспорт основного класса для WordPress Script Modules API
export class CdekDeliveryModule {
    constructor(config = {}) {
        this.config = { ...window.cdekConfig, ...config };
        this.performance = {
            startTime: performance.now(),
            marks: new Map()
        };
        
        this.init();
    }
    
    async init() {
        console.log('🚀 СДЭК ES6 Module v4.0 - МАКСИМАЛЬНАЯ МОЩНОСТЬ!');
        
        // Динамический импорт Яндекс карт только при необходимости
        if (this.needsMaps()) {
            await this.loadMapsModule();
        }
        
        this.initDeliveryLogic();
        
        console.log('✅ ES6 модуль инициализирован за:', 
                   (performance.now() - this.performance.startTime).toFixed(2) + 'ms');
    }
    
    needsMaps() {
        return document.querySelector('.cdek-map-container') !== null;
    }
    
    async loadMapsModule() {
        try {
            // Динамический импорт только при необходимости
            const mapsModule = await import('@cdek/maps-api');
            this.maps = mapsModule;
            console.log('🗺️ Яндекс карты загружены динамически');
        } catch (error) {
            console.warn('⚠️ Не удалось загрузить карты:', error);
        }
    }
    
    initDeliveryLogic() {
        // Основная логика доставки без кэширования
        // Используем современные возможности
        
        // Performance Observer для мониторинга
        if (typeof PerformanceObserver !== 'undefined') {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.name.includes('cdek')) {
                        console.log(`📊 ${entry.name}: ${entry.duration.toFixed(2)}ms`);
                    }
                });
            });
            observer.observe({ entryTypes: ['measure'] });
        }
        
        // Intersection Observer для lazy loading
        if (IntersectionObserver) {
            this.setupLazyLoading();
        }
        
        // Service Worker для офлайн поддержки
        if ('serviceWorker' in navigator) {
            this.registerServiceWorker();
        }
    }
    
    setupLazyLoading() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('cdek-loaded');
                    // Загружаем контент только когда он виден
                    this.loadDeliveryPoints(entry.target);
                }
            });
        });
        
        document.querySelectorAll('.cdek-delivery-point').forEach(el => {
            observer.observe(el);
        });
    }
    
    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/cdek-sw.js');
            console.log('🔧 Service Worker зарегистрирован:', registration);
        } catch (error) {
            console.log('ℹ️ Service Worker недоступен:', error);
        }
    }
    
    async loadDeliveryPoints(element) {
        // Современный fetch вместо jQuery.ajax
        try {
            const formData = new FormData();
            formData.append('action', 'get_cdek_points');
            formData.append('nonce', this.config.nonce);
            
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                signal: AbortSignal.timeout(30000) // Современный timeout
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            this.renderPoints(data, element);
            
        } catch (error) {
            console.error('🚨 Ошибка загрузки ПВЗ:', error);
            this.showError(element, 'Не удалось загрузить пункты выдачи');
        }
    }
    
    renderPoints(data, container) {
        // Современный рендеринг с Web Components
        const template = document.createElement('template');
        template.innerHTML = `
            <div class="cdek-points-ultra">
                ${data.map(point => `
                    <div class="cdek-point" data-code="${point.code}">
                        <h4>${point.name}</h4>
                        <p>${point.address}</p>
                        <span class="cdek-cost">${point.delivery_sum} ₽</span>
                    </div>
                `).join('')}
            </div>
        `;
        
        container.appendChild(template.content.cloneNode(true));
    }
    
    showError(container, message) {
        container.innerHTML = `
            <div class="cdek-error">
                <span class="cdek-error-icon">⚠️</span>
                <span class="cdek-error-text">${message}</span>
            </div>
        `;
    }
}

// Автоматическая инициализация если конфигурация доступна
if (typeof window.cdekConfig !== 'undefined') {
    const cdekModule = new CdekDeliveryModule();
    // Экспорт для глобального доступа
    window.cdekDelivery = cdekModule;
}

export default CdekDeliveryModule;