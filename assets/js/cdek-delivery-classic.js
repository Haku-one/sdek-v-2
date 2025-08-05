/**
 * СДЭК Доставка - Версия для классического чекаута WooCommerce
 * Адаптировано для работы с классическими элементами формы вместо блоков
 * ✅ Работает С jQuery
 */

console.log('🚀 СДЭК Delivery Classic загружается с jQuery...');

// Глобальная защита от создания нескольких карт
window.cdekMapCreationLock = false;

// ========== УТИЛИТЫ ДЛЯ ОПТИМИЗАЦИИ ==========

// Утилиты для сокращения кода
const Utils = {
    log: (message, data) => {
        if (data) console.log(message, data);
        else console.log(message);
    },
    
    delay: (fn, ms = 100) => setTimeout(fn, ms),
    
    select: (selector, all = false) => {
        return all ? document.querySelectorAll(selector) : document.querySelector(selector);
    },
    
    hide: (elements) => {
        const els = Array.isArray(elements) ? elements : [elements];
        els.forEach(el => el?.style?.setProperty('display', 'none', 'important'));
    },
    
    show: (elements) => {
        const els = Array.isArray(elements) ? elements : [elements];
        els.forEach(el => {
            if (el?.style) {
                el.style.removeProperty('display');
                el.style.removeProperty('visibility'); 
                el.style.removeProperty('opacity');
                el.style.setProperty('display', 'block', 'important');
                el.style.setProperty('visibility', 'visible', 'important');
                el.style.setProperty('opacity', '1', 'important');
            }
        });
    },
    
    // Замена для jQuery.ajax
    ajax: (options) => {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const method = options.type || options.method || 'GET';
            const url = options.url;
            
            xhr.open(method, url);
            xhr.timeout = options.timeout || 30000;
            
            // Устанавливаем заголовки
            if (method === 'POST') {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            
            // Обработчики
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    let response = xhr.responseText;
                    if (options.dataType === 'json') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            reject(new Error('Invalid JSON response'));
                            return;
                        }
                    }
                    if (options.success) options.success(response);
                    resolve(response);
                } else {
                    if (options.error) options.error(xhr, xhr.statusText, xhr.statusText);
                    reject(new Error(xhr.statusText));
                }
            };
            
            xhr.onerror = () => {
                if (options.error) options.error(xhr, 'error', 'Network Error');
                reject(new Error('Network Error'));
            };
            
            xhr.ontimeout = () => {
                if (options.error) options.error(xhr, 'timeout', 'Timeout');
                reject(new Error('Timeout'));
            };
            
            // Отправляем данные
            if (options.data) {
                if (typeof options.data === 'object') {
                    const formData = new URLSearchParams();
                    for (const key in options.data) {
                        formData.append(key, options.data[key]);
                    }
                    xhr.send(formData.toString());
                } else {
                    xhr.send(options.data);
                }
            } else {
                xhr.send();
            }
        });
    },
    
    // Замена для $(selector)
    $: (selector) => {
        if (typeof selector === 'string') {
            const elements = document.querySelectorAll(selector);
            return {
                length: elements.length,
                get: (index) => elements[index],
                first: () => elements[0],
                each: (callback) => {
                    elements.forEach((el, index) => callback.call(el, index, el));
                },
                on: (event, handler) => {
                    elements.forEach(el => el.addEventListener(event, handler));
                },
                off: (event, handler) => {
                    elements.forEach(el => el.removeEventListener(event, handler));
                },
                val: (value) => {
                    if (value !== undefined) {
                        elements.forEach(el => el.value = value);
                        return { length: elements.length };
                    }
                    return elements[0]?.value || '';
                },
                text: (text) => {
                    if (text !== undefined) {
                        elements.forEach(el => el.textContent = text);
                        return { length: elements.length };
                    }
                    return elements[0]?.textContent || '';
                },
                html: (html) => {
                    if (html !== undefined) {
                        elements.forEach(el => el.innerHTML = html);
                        return { length: elements.length };
                    }
                    return elements[0]?.innerHTML || '';
                },
                show: () => {
                    elements.forEach(el => el.style.display = '');
                    return { length: elements.length };
                },
                hide: () => {
                    elements.forEach(el => el.style.display = 'none');
                    return { length: elements.length };
                },
                css: (styles) => {
                    elements.forEach(el => {
                        if (typeof styles === 'object') {
                            Object.assign(el.style, styles);
                        }
                    });
                    return { length: elements.length };
                },
                remove: () => {
                    elements.forEach(el => el.remove());
                },
                append: (content) => {
                    elements.forEach(el => {
                        if (typeof content === 'string') {
                            el.insertAdjacentHTML('beforeend', content);
                        } else {
                            el.appendChild(content);
                        }
                    });
                    return { length: elements.length };
                },
                parent: () => {
                    return Utils.$(elements[0]?.parentElement);
                },
                trigger: (event, data) => {
                    elements.forEach(el => {
                        const evt = new CustomEvent(event, { detail: data, bubbles: true });
                        el.dispatchEvent(evt);
                    });
                    return { length: elements.length };
                },
                data: (attr, value) => {
                    if (value !== undefined) {
                        elements.forEach(el => el.setAttribute('data-' + attr, value));
                        return { length: elements.length };
                    }
                    return elements[0]?.getAttribute('data-' + attr);
                },
                is: (selector) => {
                    return elements[0]?.matches(selector) || false;
                },
                addClass: (className) => {
                    elements.forEach(el => el.classList.add(className));
                    return { length: elements.length };
                },
                removeClass: (className) => {
                    elements.forEach(el => el.classList.remove(className));
                    return { length: elements.length };
                },
                slice: (start, end) => {
                    const sliced = Array.from(elements).slice(start, end);
                    return {
                        length: sliced.length,
                        remove: () => sliced.forEach(el => el.remove())
                    };
                }
            };
        } else if (selector === document) {
            return {
                ready: (callback) => {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', callback);
                    } else {
                        callback();
                    }
                },
                on: (event, selector, handler) => {
                    if (typeof selector === 'function') {
                        handler = selector;
                        selector = null;
                    }
                    if (selector) {
                        document.addEventListener(event, (e) => {
                            // Проверяем сам элемент и его родителей
                            const targetElement = e.target.closest(selector);
                            if (targetElement) {
                                handler.call(targetElement, e);
                            }
                        });
                    } else {
                        document.addEventListener(event, handler);
                    }
                },
                                 trigger: (event, data) => {
                     const evt = new CustomEvent(event, { detail: data });
                     document.dispatchEvent(evt);
                 }
             };
         } else if (selector === document.body || (typeof selector === 'object' && selector.tagName === 'BODY')) {
             const body = document.body;
             return {
                 trigger: (event, data) => {
                     const evt = new CustomEvent(event, { detail: data, bubbles: true });
                     body.dispatchEvent(evt);
                 }
             };
         }
         return { length: 0 };
     },
    
    mapResize: () => {
        if (window.cdekMap?.container) {
            try {
                window.cdekMap.container.fitToViewport();
                Utils.log('✅ Размер карты обновлен');
            } catch (e) {
                Utils.log('🚨 Ошибка обновления размера карты:', e);
            }
        }
    }
};

// Умный дебаунсер с приоритетами
class SmartDebouncer {
    constructor() {
        this.timers = new Map();
        this.priorities = new Map();
    }
    
    debounce(key, fn, delay, priority = 0) {
        if (priority > 5) {
            this.cancel(key);
            return fn();
        }
        
        this.cancel(key);
        
        const timer = setTimeout(() => {
            fn();
            this.timers.delete(key);
            this.priorities.delete(key);
        }, delay);
        
        this.timers.set(key, timer);
        this.priorities.set(key, priority);
    }
    
    cancel(key) {
        if (this.timers.has(key)) {
            clearTimeout(this.timers.get(key));
            this.timers.delete(key);
            this.priorities.delete(key);
        }
    }
}

// Батчинг DOM операций
class DOMBatcher {
    constructor() {
        this.operations = [];
        this.scheduled = false;
    }
    
    add(operation) {
        this.operations.push(operation);
        if (!this.scheduled) {
            this.scheduled = true;
            requestAnimationFrame(() => this.flush());
        }
    }
    
    flush() {
        this.operations.forEach(op => {
            try {
                op();
            } catch (error) {
                console.error('DOM operation error:', error);
            }
        });
        this.operations = [];
        this.scheduled = false;
    }
}

// ========== УМНЫЙ ПОИСК АДРЕСОВ ==========

class SmartAddressSearch {
    constructor() {
        this.debouncer = new SmartDebouncer();
        this.userLocation = null;
        
        // Список российских городов
        this.popularCities = [
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул',
            'Ульяновск', 'Владивосток', 'Ярославль', 'Иркутск', 'Хабаровск', 'Махачкала', 'Томск',
            'Оренбург', 'Кемерово', 'Новокузнецк', 'Рязань', 'Астрахань', 'Пенза', 'Липецк',
            'Тула', 'Киров', 'Чебоксары', 'Калининград', 'Брянск', 'Курск', 'Иваново', 'Магнитогорск'
        ];
        
        this.initUserLocation();
    }
    
    async initUserLocation() {
        try {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                    },
                    () => this.setDefaultLocation(),
                    { timeout: 5000, maximumAge: 30000 }
                );
            } else {
                this.setDefaultLocation();
            }
        } catch (error) {
            this.setDefaultLocation();
        }
    }
    
    setDefaultLocation() {
        this.userLocation = {
            lat: 55.7558,
            lng: 37.6176,
            city: 'Москва'
        };
    }
    
    search(query, callback) {
        this.debouncer.debounce('address-search', () => {
            this.performSearch(query, callback);
        }, 100);
    }
    
    performSearch(query, callback) {
        if (!query || query.length < 2) {
            callback([]);
            return;
        }
        
        // Сначала пробуем DaData API
        this.searchWithDaData(query, (dadataResults) => {
            if (dadataResults && dadataResults.length > 0) {
                // Если DaData вернул результаты, используем их
                callback(dadataResults);
            } else {
                // Иначе используем локальный поиск как fallback
                const localResults = this.searchInCities(query);
                callback(localResults);
            }
        });
    }
    
    searchWithDaData(query, callback) {
        if (typeof cdek_ajax === 'undefined' || !cdek_ajax.ajax_url) {
            console.log('⚠️ CDEK AJAX не инициализирован, используем локальный поиск');
            callback([]);
            return;
        }
        
        // Проверяем наличие jQuery
        if (typeof jQuery === 'undefined') {
            console.log('⚠️ jQuery не загружен, используем локальный поиск');
            callback([]);
            return;
        }
        
        console.log('🔍 Поиск через DaData API:', query);
        
        jQuery.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 5000, // Короткий таймаут для быстрого fallback
            data: {
                action: 'get_dadata_suggestions',
                search: query,
                search_type: 'address', // Ищем адреса, а не только города
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    console.log('✅ DaData API: Получено адресов:', response.data.length);
                    
                    // Показываем все результаты от DaData как есть
                    console.log('🎯 Показываем все адреса от DaData:', response.data.length);
                    callback(response.data);
                } else {
                    console.log('⚠️ DaData API: Пустой ответ, используем локальный поиск');
                    callback([]);
                }
            },
            error: function(xhr, status, error) {
                console.log('❌ DaData API ошибка:', error, '- используем локальный поиск');
                callback([]);
            }
        });
    }
    
    searchInCities(query) {
        const queryLower = query.toLowerCase().trim();
        const results = [];
        const maxResults = 10;
        
        this.popularCities.forEach(city => {
            if (results.length >= maxResults) return;
            
            const cityLower = city.toLowerCase();
            let score = 0;
            
            if (cityLower === queryLower) {
                score = 1000;
            } else if (cityLower.startsWith(queryLower)) {
                score = 500;
            } else if (cityLower.includes(queryLower)) {
                score = 200;
            }
            
            if (score > 0) {
                const popularityIndex = this.popularCities.indexOf(city);
                const popularityBonus = (this.popularCities.length - popularityIndex) * 2;
                score += popularityBonus;
                
                if (this.userLocation && this.userLocation.city === city) {
                    score += 200;
                }
                
                results.push({
                    city: city,
                    display: city,
                    score: score,
                    type: 'city'
                });
            }
        });
        
        results.sort((a, b) => b.score - a.score);
        return results.slice(0, maxResults);
    }
}

// ========== ОСНОВНОЙ КОД СДЭК ДЛЯ КЛАССИЧЕСКОГО ЧЕКАУТА ==========

jQuery(document).ready(function($) {
    
    console.log('✅ СДЭК инициализируется с jQuery');
    console.log('📊 Состояние jQuery:', typeof jQuery !== 'undefined' ? 'загружен' : 'НЕ загружен');
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false;
    
    // Инициализируем утилиты оптимизации
    const debouncer = new SmartDebouncer();
    const domBatcher = new DOMBatcher();
    const addressSearch = new SmartAddressSearch();
    
    // ========== ФУНКЦИИ ДЛЯ РАСЧЕТА ГАБАРИТОВ И СТОИМОСТИ ==========
    
    // Вспомогательная функция для корректного парсинга цен
    function parsePrice(priceText) {
        if (!priceText) return 0;
        
        var originalText = priceText.toString();
        
        // Удаляем все символы кроме цифр, точек и запятых
        var cleanText = originalText.replace(/[^\d.,]/g, '');
        
        if (!cleanText) return 0;
        
        var result = 0;
        
        // Простая логика: если число содержит точку или запятую с 3 цифрами после - это разделитель тысяч
        // Если с 1-2 цифрами после - это десятичный разделитель
        
        // Заменяем все точки и запятые, которые являются разделителями тысяч (за которыми следует ровно 3 цифры)
        cleanText = cleanText.replace(/[.,](\d{3})(?=\d)/g, '$1'); // Убираем разделители тысяч
        
        // Теперь заменяем оставшуюся точку или запятую на точку (десятичный разделитель)
        cleanText = cleanText.replace(/,([^,]*)$/, '.$1'); // Последняя запятая -> точка
        
        result = parseFloat(cleanText) || 0;
        result = Math.round(result);
        
        console.log('💰 Парсинг цены:', originalText, '→', cleanText, '→', result);
        return result;
    }
    
    function getCartDataForCalculation() {
        var cartWeight = 0;
        var cartValue = 0;
        var totalVolume = 0;
        var maxLength = 0, maxWidth = 0, maxHeight = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        var packagesCount = 1;
        
        console.log('Получение данных корзины для классического чекаута...');
        
        // Получаем данные из скрытых полей с информацией о товарах
        var cartItems = $('#wc-cart-data .cart-item-data');
        
        if (cartItems.length === 0) {
            console.error('❌ Нет данных о товарах в корзине! Расчет невозможен.');
            return null;
        }
        
        cartItems.each(function() {
            var $item = $(this);
            var quantity = parseInt($item.data('quantity')) || 0;
            var length = parseFloat($item.data('length')) || 0;
            var width = parseFloat($item.data('width')) || 0;
            var height = parseFloat($item.data('height')) || 0;
            var weight = parseFloat($item.data('weight')) || 0;
            var price = parseFloat($item.data('price')) || 0;
            
            console.log('📦 Обработка товара:', {
                length: length,
                width: width, 
                height: height,
                weight: weight,
                quantity: quantity,
                price: price
            });
            
            // Проверяем, что у товара есть ВСЕ необходимые габариты
            if (length > 0 && width > 0 && height > 0 && weight > 0) {
                hasValidDimensions = true;
                
                // Собираем общий вес и стоимость
                cartWeight += (weight * quantity);
                cartValue += (price * quantity);
                totalItems += quantity;
                
                // Рассчитываем объем товаров
                var itemVolume = (length * width * height) * quantity;
                totalVolume += itemVolume;
                
                // Обновляем максимальные размеры (для самого большого товара)
                maxLength = Math.max(maxLength, length);
                maxWidth = Math.max(maxWidth, width); 
                maxHeight = Math.max(maxHeight, height);
                
                console.log('✅ Товар с полными габаритами добавлен');
            } else {
                console.error('❌ У товара отсутствуют габариты или вес! Товар пропущен.');
                console.error('📋 Требуются: длина, ширина, высота И вес');
            }
        });
        
        // Проверяем, что у нас есть товары с габаритами
        if (!hasValidDimensions || totalItems === 0) {
            console.error('❌ В корзине нет товаров с полными габаритами! Расчет стоимости доставки невозможен.');
            console.error('📋 Необходимо указать габариты (Д×Ш×В) и вес для всех товаров в WooCommerce.');
            return null;
        }
        
        // Получаем общую стоимость заказа из элементов страницы
        var orderTotalElement = $('.cart-subtotal .amount');
        if (orderTotalElement.length > 0) {
            var totalText = orderTotalElement.first().text();
            var parsedValue = parsePrice(totalText);
            if (parsedValue > 0) {
                cartValue = parsedValue;
            }
        }
        
        // Определяем размеры коробки на основе ТОЛЬКО реальных данных
        var dimensions = calculateOptimalBoxSize(totalVolume, maxLength, maxWidth, maxHeight, totalItems);
        
        console.log('📊 Итоговые данные корзины:', {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions,
            packagesCount: packagesCount,
            totalVolume: totalVolume,
            totalItems: totalItems
        });
        
        return {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions,
            packagesCount: dimensions.packagesCount || 1
        };
    }
    
    // Функция для расчета оптимального размера коробки на основе ТОЛЬКО реальных габаритов
    function calculateOptimalBoxSize(totalVolume, maxLength, maxWidth, maxHeight, totalItems) {
        console.log('📦 Расчет коробок для большого заказа');
        console.log('📏 Общий объем товаров:', totalVolume, 'см³');
        console.log('📏 Максимальные размеры:', {length: maxLength, width: maxWidth, height: maxHeight});
        console.log('📦 Общее количество товаров:', totalItems);
        
        // Добавляем 20% запас для упаковки (уменьшаем с 30% для больших заказов)
        var packingVolume = totalVolume * 1.2;
        
        // Стандартные коробки СДЭК (длина x ширина x высота в см)
        var standardBoxes = [
            { name: 'S', length: 19, width: 17, height: 10, volume: 3230, maxWeight: 5000 },
            { name: 'M', length: 24, width: 17, height: 10, volume: 4080, maxWeight: 5000 },
            { name: 'L', length: 34, width: 24, height: 17, volume: 13872, maxWeight: 10000 },
            { name: 'XL', length: 39, width: 27, height: 21, volume: 22113, maxWeight: 15000 },
            { name: 'XXL', length: 60, width: 40, height: 35, volume: 84000, maxWeight: 30000 }
        ];
        
        // Определяем количество коробок для больших заказов
        var packagesCount = 1;
        var selectedBox = null;
        
        // Для больших заказов (более 200 товаров) используем множественные коробки
        if (totalItems > 200) {
            console.log('🚚 Большой заказ (' + totalItems + ' товаров), рассчитываем несколько коробок');
            
            // Рассчитываем количество коробок исходя из объема
            var maxBoxVolume = standardBoxes[standardBoxes.length - 1].volume; // Самая большая коробка
            packagesCount = Math.ceil(packingVolume / maxBoxVolume);
            
                    // Ограничиваем максимальное количество коробок для API СДЭК
        if (packagesCount > 5) {
            packagesCount = 5;
            console.log('⚠️ Ограничиваем количество коробок до 5 (ограничение API СДЭК)');
        }
            
            // Объем на одну коробку
            var volumePerBox = packingVolume / packagesCount;
            
            console.log('📦 Планируем ' + packagesCount + ' коробок, объем на коробку: ' + Math.round(volumePerBox) + ' см³');
            
            // Находим подходящую коробку для нового объема
            for (var i = 0; i < standardBoxes.length; i++) {
                var box = standardBoxes[i];
                
                // Проверяем, что товары помещаются по размерам И по объему
                var fitsSize = (maxLength <= box.length && maxWidth <= box.width && maxHeight <= box.height);
                var fitsVolume = (volumePerBox <= box.volume);
                
                console.log('🔍 Проверяем коробку ' + box.name + ' для ' + packagesCount + ' коробок:', {
                    fitsSize: fitsSize,
                    fitsVolume: fitsVolume,
                    requiredVolumePerBox: Math.round(volumePerBox),
                    boxVolume: box.volume
                });
                
                if (fitsSize && fitsVolume) {
                    selectedBox = box;
                    break;
                }
            }
        } else {
            // Для обычных заказов используем стандартную логику
            for (var i = 0; i < standardBoxes.length; i++) {
                var box = standardBoxes[i];
                
                var fitsSize = (maxLength <= box.length && maxWidth <= box.width && maxHeight <= box.height);
                var fitsVolume = (packingVolume <= box.volume);
                
                console.log('🔍 Проверяем коробку ' + box.name + ':', {
                    fitsSize: fitsSize,
                    fitsVolume: fitsVolume,
                    requiredVolume: Math.round(packingVolume),
                    boxVolume: box.volume
                });
                
                if (fitsSize && fitsVolume) {
                    selectedBox = box;
                    break;
                }
            }
        }
        
        // Если не нашли подходящую коробку, берем самую большую
        if (!selectedBox) {
            selectedBox = standardBoxes[standardBoxes.length - 1];
            console.log('⚠️ Используем максимальную коробку:', selectedBox.name);
        }
        
        var result = {
            length: selectedBox.length,
            width: selectedBox.width,
            height: selectedBox.height,
            packagesCount: packagesCount,
            boxName: selectedBox.name
        };
        
        console.log('📦 Итоговый план упаковки:', {
            коробка: selectedBox.name,
            размеры: result.length + '×' + result.width + '×' + result.height + ' см',
            количество: packagesCount + ' шт.',
            общий_объем: Math.round(totalVolume) + ' см³',
            объем_с_упаковкой: Math.round(packingVolume) + ' см³'
        });
        
        return result;
    }
    
    function calculateDeliveryCost(point, callback) {
        var cartData = getCartDataForCalculation();
        
        // Проверяем, что данные корзины получены
        if (!cartData) {
            console.error('❌ Нет данных корзины с габаритами! Расчет невозможен.');
            callback(0);
            return;
        }
        
        if (typeof cdek_ajax === 'undefined' || !cdek_ajax.ajax_url) {
            console.error('CDEK AJAX не инициализирован');
            callback(0);
            return;
        }
        
        if (!point || !point.code) {
            console.error('Не указан пункт выдачи или его код');
            callback(0);
            return;
        }
        
        console.log('Запрос расчета стоимости доставки для пункта:', point.code);
        console.log('Данные корзины:', cartData);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'calculate_cdek_delivery_cost',
                point_code: point.code,
                point_data: JSON.stringify(point),
                cart_weight: cartData.weight,
                cart_dimensions: JSON.stringify(cartData.dimensions),
                cart_value: cartData.value,
                has_real_dimensions: cartData.hasRealDimensions ? 1 : 0,
                packages_count: cartData.packagesCount || 1,
                nonce: cdek_ajax.nonce || ''
            },
            success: function(response) {
                if (response && response.success && response.data && response.data.delivery_sum) {
                    var deliveryCost = parseInt(response.data.delivery_sum);
                    
                    // Проверяем, использовался ли fallback расчет
                    if (response.data.fallback_used) {
                        console.log('⚠️ Используется приблизительный расчет для большого заказа: ' + deliveryCost + ' руб.');
                        
                        // Показываем пользователю предупреждение
                        if (cartData.packagesCount >= 5) {
                            setTimeout(function() {
                                var warningHtml = '<div class="cdek-fallback-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 14px;">' +
                                    '<strong>ℹ️ Приблизительная стоимость</strong><br>' +
                                    'Для большого заказа (' + cartData.packagesCount + ' коробок) показана приблизительная стоимость доставки. ' +
                                    'Точная стоимость будет уточнена при обработке заказа.' +
                                    '</div>';
                                
                                $('.cdek-fallback-warning').remove(); // Удаляем предыдущие предупреждения
                                $('#cdek-delivery-content').prepend(warningHtml);
                            }, 500);
                        }
                    } else {
                        // Убираем предупреждение если расчет точный
                        $('.cdek-fallback-warning').remove();
                    }
                    
                    if (cartData.packagesCount > 1 && !response.data.fallback_used) {
                        deliveryCost = deliveryCost * cartData.packagesCount;
                    }
                    
                    callback(deliveryCost);
                } else {
                    console.log('❌ API СДЭК не вернул стоимость');
                    callback(0);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Ошибка запроса к API СДЭК:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                // Пытаемся парсить ответ для получения деталей ошибки
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.data) {
                        console.error('📋 Детали ошибки API СДЭК:', errorResponse.data);
                        if (errorResponse.data.debug_info) {
                            console.error('🔍 Отладочная информация:', errorResponse.data.debug_info);
                        }
                        if (errorResponse.data.api_response) {
                            console.error('🌐 Ответ API СДЭК:', errorResponse.data.api_response);
                        }
                    }
                } catch (e) {
                    console.error('❌ Не удалось парсить ответ об ошибке');
                }
                
                console.log('❌ Расчет стоимости невозможен');
                callback(0);
            }
        });
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С АДРЕСАМИ ==========
    
    function parseAddress(address) {
        var result = { city: '', street: '' };
        
        if (!address || address.trim() === '') {
            return result;
        }
        
        var parts = address.split(/[,\s]+/);
        
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (!part) continue;
            
            if (!result.city && !result.street) {
                result.city = part;
            } else if (result.city && !result.street) {
                result.street = parts.slice(i).join(' ');
                break;
            }
        }
        
        return result;
    }
    
    function initAddressAutocomplete() {
        // Используем стандартные поля адреса WooCommerce
        var addressInput = $('#billing_address_1, #shipping_address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        $('#address-suggestions').remove();
        
        setupSmartAutocomplete();
    }
    
    function setupSmartAutocomplete() {
        var addressInput = $('#billing_address_1, #shipping_address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        // Работаем с первым найденным полем
        addressInput = addressInput.first();
        
        var suggestionsContainer = $(`
            <div id="address-suggestions" class="smart-address-suggestions" style="display: none;">
                <div class="suggestions-header">
                    <span class="suggestions-title">Выберите адрес</span>
                    <span class="suggestions-count"></span>
                </div>
                <div class="suggestions-list"></div>
                <div class="suggestions-footer">
                    <small>💡 Начните вводить город или улицу</small>
                </div>
            </div>
        `);
        
        addressInput.parent().css('position', 'relative');
        addressInput.parent().append(suggestionsContainer);
        
        // Добавляем стили
        if (!$('#smart-search-styles').length) {
            $('head').append(`
                <style id="smart-search-styles">
                .smart-address-suggestions {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #e1e5e9;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    z-index: 1000;
                    max-height: 250px;
                    overflow-y: auto;
                    margin-top: 4px;
                }
                
                .suggestions-header {
                    padding: 10px 12px;
                    border-bottom: 1px solid #f0f0f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                }
                
                .suggestions-title {
                    font-weight: 600;
                    color: #333;
                    font-size: 13px;
                }
                
                .suggestions-count {
                    font-size: 11px;
                    color: #666;
                }
                
                .suggestion-item {
                    display: flex;
                    align-items: center;
                    padding: 12px 14px;
                    cursor: pointer;
                    transition: background-color 0.15s ease;
                    border-bottom: 1px solid #f5f5f5;
                }
                
                .suggestion-item:hover {
                    background-color: #f8f9fa;
                }
                
                .suggestion-item:last-child {
                    border-bottom: none;
                }
                
                .suggestion-icon {
                    font-size: 16px;
                    margin-right: 10px;
                    opacity: 0.7;
                }
                
                .suggestion-content {
                    flex: 1;
                }
                
                .suggestion-title {
                    font-weight: 500;
                    color: #333;
                    margin-bottom: 2px;
                    font-size: 14px;
                }
                
                .suggestion-title mark {
                    background-color: #fff3cd;
                    color: #856404;
                    padding: 0 2px;
                    border-radius: 2px;
                }
                
                .suggestion-subtitle {
                    font-size: 12px;
                    color: #666;
                }
                
                .suggestions-footer {
                    padding: 8px 12px;
                    background: #f8f9fa;
                    border-top: 1px solid #f0f0f0;
                    text-align: center;
                    position: sticky;
                    bottom: 0;
                }
                
                .suggestions-footer small {
                    color: #666;
                    font-size: 11px;
                }
                
                .suggestion-separator {
                    background: #f5f5f5 !important;
                    border-top: 1px solid #e0e0e0;
                    border-bottom: 1px solid #e0e0e0;
                    margin: 4px 0;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .suggestion-no-cdek {
                    opacity: 0.7;
                }
                
                .suggestion-no-cdek .suggestion-icon {
                    opacity: 0.5;
                }
                
                .suggestion-no-cdek .suggestion-subtitle {
                    color: #999 !important;
                }
                </style>
            `);
        }
        
        var currentSuggestions = [];
        
        addressInput.on('input', function() {
            var query = $(this).val().trim();
            
            if (query.length >= 2) {
                // Показываем индикатор поиска городов
                showSearchLoader();
                
                addressSearch.search(query, function(suggestions) {
                    currentSuggestions = suggestions;
                    hideSearchLoader();
                    showAddressSuggestions(suggestions, query);
                });
            } else {
                hideAddressSuggestions();
                hideSearchLoader();
            }
        });
        
        function showSearchLoader() {
            var container = suggestionsContainer.find('.suggestions-list');
            container.html(`
                <div class="suggestion-item">
                    <div class="suggestion-icon">🔄</div>
                    <div class="suggestion-content">
                        <div class="suggestion-title">Поиск адресов...</div>
                        <div class="suggestion-subtitle">Ищем города и улицы с пунктами СДЭК</div>
                    </div>
                </div>
            `);
            suggestionsContainer.find('.suggestions-count').text('Поиск...');
            suggestionsContainer.show();
        }
        
        function hideSearchLoader() {
            // Лоадер скрывается при показе результатов
        }
        
        function showAddressSuggestions(suggestions, query) {
            var container = suggestionsContainer.find('.suggestions-list');
            container.empty();
            
            if (suggestions.length === 0) {
                container.html('<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">Ничего не найдено</div><div class="suggestion-subtitle">Попробуйте изменить запрос</div></div></div>');
                suggestionsContainer.find('.suggestions-count').text('0 результатов');
            } else {
                // Теперь показываем только города (без группировки)
                suggestions.forEach(function(suggestion, index) {
                    var item = createSuggestionItem(suggestion, index, query, true);
                    container.append(item.element);
                    item.element.on('click', function() {
                        selectSuggestion(item.suggestion);
                    });
                });
                
                var cdekCount = suggestions.filter(s => s.has_cdek).length;
                var countText = `${suggestions.length} городов`;
                if (cdekCount > 0) {
                    countText += ` (${cdekCount} с СДЭК)`;
                }
                suggestionsContainer.find('.suggestions-count').text(countText);
            }
            
            suggestionsContainer.show();
        }
        
        function createSuggestionItem(suggestion, index, query, isCity) {
            var displayText = suggestion.value || suggestion.city || suggestion.display;
            var highlightedText = highlightQuery(displayText, query);
            
            var icon = '🏙️';
            var subtitle = 'Россия';
            
            // Обработка данных от DaData
            if (suggestion.data) {
                var data = suggestion.data;
                icon = suggestion.has_cdek ? '🎯' : '🏙️';
                subtitle = data.region || 'Россия';
                if (suggestion.has_cdek) {
                    subtitle += ' • доступна доставка СДЭК';
                } else {
                    subtitle += ' • СДЭК недоступен';
                }
            } else if (suggestion.source === 'dadata') {
                // Старый формат DaData
                icon = suggestion.has_cdek ? '🎯' : '🏙️';
                subtitle = 'Точный адрес';
                if (suggestion.cdek_code) {
                    subtitle += ' • СДЭК: ' + suggestion.cdek_code;
                }
            } else {
                // Локальный поиск - город
                icon = '🏙️';
                subtitle = 'Россия • проверим доступность СДЭК';
            }
            
            var itemClass = 'suggestion-item';
            if (!suggestion.has_cdek && suggestion.source === 'dadata') {
                itemClass += ' suggestion-no-cdek';
            }
            
            var element = $(`
                <div class="${itemClass}" data-index="${index}">
                    <div class="suggestion-icon">${icon}</div>
                    <div class="suggestion-content">
                        <div class="suggestion-title">${highlightedText}</div>
                        <div class="suggestion-subtitle">${subtitle}</div>
                    </div>
                </div>
            `);
            
            return {
                element: element,
                suggestion: suggestion
            };
        }
        
        function highlightQuery(text, query) {
            if (!query || !text) return text;
            
            var regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        function selectSuggestion(suggestion) {
            var fullAddress, cityName;
            
            // Обработка результата от DaData (только города)
            if (suggestion.data) {
                var data = suggestion.data;
                
                // Извлекаем название города
                cityName = data.city || data.settlement || data.region;
                
                // Формируем красивое отображение города
                if (data.city_type_full && data.city) {
                    fullAddress = data.city_type_full + ' ' + data.city;
                } else if (data.settlement_type_full && data.settlement) {
                    fullAddress = data.settlement_type_full + ' ' + data.settlement;
                } else {
                    fullAddress = cityName;
                }
            } else {
                // Локальный результат
                fullAddress = suggestion.value || suggestion.city;
                cityName = suggestion.city;
            }
            
            addressInput.val(fullAddress);
            hideAddressSuggestions();
            
            saveRecentSearch(suggestion);
            
            // Запоминаем выбранный город и его данные
            window.lastSelectedCity = cityName;
            window.lastSelectedCityData = suggestion; // Сохраняем все данные включая СДЭК код
            
            // Если есть СДЭК код из DaData, сохраняем его в сессии
            if (suggestion.cdek_code || (suggestion.data && suggestion.data.cdek_code)) {
                var cdekCode = suggestion.cdek_code || suggestion.data.cdek_code;
                saveCdekCodeToSession(cdekCode, cityName);
            }
            
            // Очищаем предыдущий выбор ПВЗ только при смене города
            if (window.currentSearchCity && window.currentSearchCity !== cityName) {
                clearSelectedPoint();
            }
            
            // Показываем индикатор загрузки ПВЗ
            showPvzLoader();
            
            debouncer.debounce('cdek-search', () => {
                searchCdekPoints(cityName, suggestion);
            }, 50, 6);
        }
        
        function saveCdekCodeToSession(cdekCode, city) {
            if (typeof cdek_ajax === 'undefined' || !cdek_ajax.ajax_url) {
                return;
            }
            
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_dadata_cdek_code',
                    cdek_code: cdekCode,
                    city: city,
                    nonce: cdek_ajax.nonce
                },
                success: function(response) {
                    console.log('✅ СДЭК код из DaData сохранен в сессии:', cdekCode);
                },
                error: function() {
                    console.log('❌ Ошибка сохранения СДЭК кода в сессии');
                }
            });
        }
        
        function saveRecentSearch(suggestion) {
            try {
                var recentSearches = JSON.parse(localStorage.getItem('cdek_recent_searches') || '[]');
                
                recentSearches = recentSearches.filter(item => item.city !== suggestion.city);
                
                recentSearches.unshift({
                    city: suggestion.city,
                    timestamp: Date.now()
                });
                
                recentSearches = recentSearches.slice(0, 5);
                
                localStorage.setItem('cdek_recent_searches', JSON.stringify(recentSearches));
            } catch (error) {
                console.log('Ошибка сохранения истории поиска:', error);
            }
        }
        
        function hideAddressSuggestions() {
            suggestionsContainer.hide();
        }
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#address-suggestions, #billing_address_1, #shipping_address_1').length) {
                hideAddressSuggestions();
            }
        });
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С КАРТОЙ ==========
    
    function initYandexMap() {
        const mapContainer = document.getElementById('cdek-map');
        
        if (!mapContainer) {
            console.log('🚫 Контейнер карты не найден');
            return;
        }
        
        // Принудительно показываем контейнер карты
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important; opacity: 1 !important;';
        
        // Проверяем, не создана ли уже карта
        if (window.cdekMap && typeof window.cdekMap.getCenter === 'function') {
            console.log('✅ Карта уже существует, обновляем размер');
            setTimeout(() => {
                try {
                    window.cdekMap.container.fitToViewport();
                } catch (e) {
                    console.log('Ошибка обновления карты:', e);
                }
            }, 100);
            return;
        }
        
        // Проверяем загрузку Яндекс.Карт
        if (typeof ymaps === 'undefined') {
            console.warn('🔄 Яндекс.Карты еще не загружены, ждем...');
            setTimeout(() => initYandexMap(), 500);
            return;
        }
        
        console.log('🗺️ Инициализируем новую Яндекс карту');
        
        ymaps.ready(function() {
            try {
                // Очищаем контейнер
                mapContainer.innerHTML = '';
                
                // Создаем новую карту
                cdekMap = new ymaps.Map(mapContainer, {
                    center: [55.753994, 37.622093], // Москва
                    zoom: 10,
                    controls: ['zoomControl', 'searchControl']
                }, {
                    suppressMapOpenBlock: true
                });
                
                // Сохраняем в глобальной переменной
                window.cdekMap = cdekMap;
                
                console.log('✅ Яндекс карта успешно создана');
                
                // Обновляем размер карты
                setTimeout(() => {
                    if (cdekMap && cdekMap.container) {
                        cdekMap.container.fitToViewport();
                    }
                }, 100);
                
                // Если есть пункты выдачи, отображаем их
                if (cdekPoints && cdekPoints.length > 0) {
                    displayCdekPoints(cdekPoints);
                }
                
            } catch (error) {
                console.error('❌ Ошибка создания карты:', error);
                showMapFallback();
            }
        });
    }
    
    function showMapFallback() {
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) return;
        
        mapContainer.innerHTML = `
            <div style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 450px;
                background: #f8f9fa;
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                color: #6c757d;
                text-align: center;
                padding: 20px;
            ">
                <div style="font-size: 48px; margin-bottom: 20px;">🗺️</div>
                <h4 style="margin: 0 0 10px 0; color: #495057;">Карта временно недоступна</h4>
                <p style="margin: 0 0 15px 0; font-size: 14px;">Яндекс.Карты не загрузились, но вы можете выбрать пункт выдачи из списка ниже</p>
                <div id="fallback-points-list"></div>
            </div>
        `;
        
        // Если есть пункты выдачи, показываем их списком
        if (cdekPoints && cdekPoints.length > 0) {
            displayPointsAsList();
        }
    }
    
    function displayPointsAsList() {
        var listContainer = document.getElementById('fallback-points-list');
        if (!listContainer || !cdekPoints) return;
        
        var html = '<h5 style="margin: 0 0 15px 0;">Доступные пункты выдачи:</h5>';
        
        cdekPoints.forEach(function(point, index) {
            var pointName = point.name || 'Пункт выдачи';
            var address = '';
            
            if (point.location && point.location.address_full) {
                address = point.location.address_full;
            } else if (point.location && point.location.address) {
                address = point.location.address;
            }
            
            html += `
                <div class="fallback-point-item" style="
                    padding: 10px;
                    margin-bottom: 10px;
                    border: 1px solid #e9ecef;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background-color 0.2s;
                " data-point-index="${index}" onclick="selectPointFromList(${index})">
                    <div style="font-weight: bold; margin-bottom: 5px;">${pointName}</div>
                    <div style="font-size: 12px; color: #6c757d;">${address}</div>
                    <div style="font-size: 12px; color: #007cba;">Код: ${point.code}</div>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
        
        // Добавляем стили при наведении
        $(document).on('mouseenter', '.fallback-point-item', function() {
            $(this).css('background-color', '#f8f9fa');
        }).on('mouseleave', '.fallback-point-item', function() {
            $(this).css('background-color', 'transparent');
        });
    }
    
    function selectPointFromList(index) {
        if (cdekPoints && cdekPoints[index]) {
            selectCdekPoint(cdekPoints[index]);
        }
    }
    
    // Делаем функцию глобальной для использования в onclick
    window.selectPointFromList = selectPointFromList;
    
    // ========== ФУНКЦИИ ДЛЯ ПОИСКА И ОТОБРАЖЕНИЯ ПУНКТОВ ВЫДАЧИ ==========
    
    // Функция для добавления кластеризованных маркеров
    function addClusteredMarkers(points) {
        if (!cdekMap || !points || points.length === 0) {
            return;
        }
        
        console.log(`🗺️ Добавляем ${points.length} маркеров с кластеризацией`);
        
        // Создаем кластеризатор с красивыми кружочками и цифрами
        var clusterer = new ymaps.Clusterer({
            preset: 'islands#redClusterIcons',
            clusterHideIconOnBalloonOpen: false,
            geoObjectHideIconOnBalloonOpen: false,
            gridSize: 80, // Размер сетки для группировки
            clusterDisableClickZoom: false,
            clusterOpenBalloonOnClick: true,
            // Настройки кластеров разного размера
            clusterNumbers: [10, 100], // При 10+ пунктах средний кластер, при 100+ большой
            hasBalloon: true,
            hasHint: true
        });
        
        var geoObjects = [];
        var bounds = [];
        
        // Создаем маркеры для всех пунктов
        points.forEach(function(point) {
            if (point.location && point.location.latitude && point.location.longitude) {
                var coords = [point.location.latitude, point.location.longitude];
                bounds.push(coords);
                
                var placemark = new ymaps.Placemark(coords, {
                    balloonContent: formatPointInfo(point),
                    hintContent: point.name
                }, {
                    preset: 'islands#redIcon'
                });
                
                placemark.events.add('click', function() {
                    selectCdekPoint(point);
                });
                
                geoObjects.push(placemark);
            }
        });
        
        // Добавляем все маркеры в кластеризатор
        clusterer.add(geoObjects);
        
        // Обработчик клика по кластеру
        clusterer.events.add('click', function(e) {
            var cluster = e.get('target');
            var clusteredObjects = cluster.properties.get('geoObjects');
            
            console.log(`🔍 Клик по кластеру с ${clusteredObjects.length} пунктами`);
            
            // Можно добавить свою логику обработки клика по кластеру
            // Например, показать список пунктов в балуне кластера
        });
        
        // Добавляем кластеризатор на карту
        cdekMap.geoObjects.add(clusterer);
        
        // Центрируем карту
        centerMapOnBounds(bounds);
        
        console.log(`✅ Добавлено ${geoObjects.length} маркеров в кластеры`);
    }
    
    // Функция для центрирования карты
    function centerMapOnBounds(bounds) {
        if (bounds.length > 0 && cdekMap) {
            if (bounds.length === 1) {
                cdekMap.setCenter(bounds[0], 14);
            } else {
                var minLat = Math.min.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var maxLat = Math.max.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var minLon = Math.min.apply(null, bounds.map(function(coord) { return coord[1]; }));
                var maxLon = Math.max.apply(null, bounds.map(function(coord) { return coord[1]; }));
                
                var centerLat = (minLat + maxLat) / 2;
                var centerLon = (minLon + maxLon) / 2;
                
                var latDiff = maxLat - minLat;
                var lonDiff = maxLon - minLon;
                var maxDiff = Math.max(latDiff, lonDiff);
                
                var zoom = 12;
                if (maxDiff < 0.01) zoom = 15;
                else if (maxDiff < 0.05) zoom = 13;
                else if (maxDiff < 0.1) zoom = 12;
                else if (maxDiff < 0.5) zoom = 10;
                else zoom = 8;
                
                cdekMap.setCenter([centerLat, centerLon], zoom);
            }
        }
    }
    
    function searchCdekPoints(address, cityData) {
        var parsedAddress = parseAddress(address);
        
        // Проверяем, не ищем ли мы тот же город повторно
        if (window.currentSearchCity === parsedAddress.city && cdekPoints && cdekPoints.length > 0) {
            hidePvzLoader();
            displayCdekPoints(cdekPoints);
            return;
        }
        
        // Очищаем выбор ПВЗ только при смене города
        if (window.currentSearchCity && window.currentSearchCity !== parsedAddress.city) {
            console.log('🔄 Смена города с', window.currentSearchCity, 'на', parsedAddress.city, '- очищаем данные');
            clearSelectedPoint();
        }
        
        window.currentSearchCity = parsedAddress.city;
        window.currentCityData = cityData; // Сохраняем данные города
        
        console.log('🔍 Поиск пунктов СДЭК для города:', parsedAddress.city);
        if (cityData && cityData.cdek_code) {
            console.log('🎯 Используем СДЭК код из DaData:', cityData.cdek_code);
        }
        
        performCdekSearch();
    }
    
    function performCdekSearch() {
        if (typeof cdek_ajax === 'undefined') return;
        
        // Формируем адрес для поиска
        var searchAddress = 'Россия';
        if (window.currentSearchCity) {
            searchAddress = window.currentSearchCity;
        }
        
        console.log('📡 Запрос к API СДЭК для:', searchAddress);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'get_cdek_points',
                address: searchAddress,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                hidePvzLoader();
                if (response.success && response.data) {
                    console.log('✅ Получено пунктов выдачи:', response.data.length);
                    displayCdekPoints(response.data);
                } else {
                    showPvzError('Не удалось загрузить пункты выдачи');
                }
            },
            error: function(xhr, status, error) {
                hidePvzLoader();
                console.error('❌ Ошибка загрузки пунктов выдачи:', error);
                showPvzError('Ошибка загрузки пунктов выдачи');
            }
        });
    }
    
    function displayCdekPoints(points) {
        cdekPoints = points;
        
        if (!points || points.length === 0) {
            var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            $('#cdek-points-count').text(`Пункты выдачи не найдены${cityInfo}`);
            return;
        }
        
        // Фильтруем пункты по городу
        var filteredPoints = points.filter(function(point) {
            if (window.currentSearchCity) {
                var pointCity = '';
                
                if (point.location && point.location.city) {
                    pointCity = point.location.city.trim();
                }
                
                if (!pointCity && point.location && point.location.address) {
                    var addressParts = point.location.address.split(',');
                    if (addressParts.length > 0) {
                        pointCity = addressParts[0].trim();
                    }
                }
                
                if (pointCity) {
                    pointCity = pointCity.replace(/^(г\.?\s*|город\s+)/i, '').trim();
                }
                
                var searchCityLower = window.currentSearchCity.toLowerCase().trim();
                var pointCityLower = pointCity.toLowerCase().trim();
                
                if (pointCityLower && searchCityLower) {
                    // Более строгая проверка на точное соответствие
                    var isMatch = false;
                    
                    // 1. Точное совпадение
                    if (pointCityLower === searchCityLower) {
                        isMatch = true;
                    }
                    
                    // 2. Проверяем совпадение по началу (для случаев типа "Санкт-Петербург" и "СПб")
                    else if (pointCityLower.startsWith(searchCityLower) || searchCityLower.startsWith(pointCityLower)) {
                        // Но только если разница в длине не слишком большая (не более 3 символов)
                        var lengthDiff = Math.abs(pointCityLower.length - searchCityLower.length);
                        if (lengthDiff <= 3) {
                            isMatch = true;
                        }
                    }
                    
                    // 3. Проверяем по словам (для случаев "Санкт-Петербург" -> "петербург")
                    else if (searchCityLower.length >= 4) {
                        var searchWords = searchCityLower.split(/[\s\-]+/);
                        var pointWords = pointCityLower.split(/[\s\-]+/);
                        
                        var hasMatchingWord = false;
                        for (var i = 0; i < searchWords.length; i++) {
                            for (var j = 0; j < pointWords.length; j++) {
                                if (searchWords[i].length >= 4 && pointWords[j].length >= 4) {
                                    if (searchWords[i] === pointWords[j]) {
                                        hasMatchingWord = true;
                                        break;
                                    }
                                }
                            }
                            if (hasMatchingWord) break;
                        }
                        isMatch = hasMatchingWord;
                    }
                    
                    if (!isMatch) {
                        console.log('🚫 Пункт отфильтрован:', pointCity, '(искали:', window.currentSearchCity + ')');
                        return false;
                    } else {
                       
                    }
                }
            }
            
            return true;
        });
        
        
        var pointsToShow = filteredPoints;
        
        var pointsInfo = '';
        if (filteredPoints.length > 0) {
            var locationInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            pointsInfo = `Найдено ${filteredPoints.length} пунктов выдачи${locationInfo}`;
        } else {
            var locationInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            pointsInfo = `Пункты выдачи не найдены${locationInfo}`;
        }
        $('#cdek-points-count').text(pointsInfo);
        
        // Также отображаем список пунктов
        displayPointsList(pointsToShow);
        
        // Если карта не загружена, показываем только список
        if (!cdekMap && typeof ymaps === 'undefined') {
            displayPointsAsList();
            return;
        }
        
        // Если карта не готова, ждем
        if (!cdekMap) {
            setTimeout(() => displayCdekPoints(points), 200);
            return;
        }
        
        // Очищаем карту и добавляем новые точки с кластеризацией
        cdekMap.geoObjects.removeAll();
        
        // Добавляем маркеры с кластеризацией
        addClusteredMarkers(pointsToShow);
    }
    
    function displayPointsList(points) {
        var listContainer = $('#cdek-points-list-content');
        var listWrapper = $('#cdek-points-list');
        
        if (!points || points.length === 0) {
            listWrapper.hide();
            return;
        }
        
        var html = '';
        points.forEach(function(point, index) {
            var pointName = point.name || 'Пункт выдачи';
            var address = '';
            
            if (point.location && point.location.address_full) {
                address = point.location.address_full;
            } else if (point.location && point.location.address) {
                address = point.location.address;
            }
            
            html += `
                <div class="cdek-point-item" style="
                    padding: 12px;
                    margin-bottom: 8px;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.2s;
                " data-point-index="${index}">
                    <div style="font-weight: 600; margin-bottom: 6px; color: #333;">${pointName}</div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 4px;">${address}</div>
                    <div style="font-size: 12px; color: #007cba;">Код: ${point.code}</div>
                </div>
            `;
        });
        
        listContainer.html(html);
        listWrapper.show();
        
        // Обработчики кликов на элементы списка
        $('.cdek-point-item').on('click', function() {
            var index = $(this).data('point-index');
            if (points[index]) {
                selectCdekPoint(points[index]);
            }
        });
        
        // Стили при наведении
        $('.cdek-point-item').on('mouseenter', function() {
            $(this).css({
                'background-color': '#f8f9fa',
                'border-color': '#007cba',
                'transform': 'translateY(-1px)',
                'box-shadow': '0 2px 8px rgba(0,0,0,0.1)'
            });
        }).on('mouseleave', function() {
            $(this).css({
                'background-color': 'transparent',
                'border-color': '#e9ecef',
                'transform': 'translateY(0)',
                'box-shadow': 'none'
            });
        });
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        console.log('✅ Выбран пункт выдачи:', point.code, point.name);
        
        // Добавляем скрытое поле с типом доставки СДЭК
        $('#cdek-delivery-type').remove(); // Удаляем предыдущее поле
        $('form.checkout').append('<input type="hidden" id="cdek-delivery-type" name="cdek_delivery_type" value="cdek">');
        
        // Сохраняем информацию о выбранном ПВЗ
        window.selectedCdekPoint = {
            code: point.code,
            name: point.name,
            address: point.location && point.location.address ? point.location.address : '',
            city: point.location && point.location.city ? point.location.city : ''
        };
        
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        if (cdekMap && point.location) {
            cdekMap.setCenter([point.location.latitude, point.location.longitude], 15);
        }
        
        // Обновляем скрытые поля
        $('#cdek-selected-point-code').val(point.code);
        $('#cdek-selected-point-data').val(JSON.stringify(point));
        
        // Сохраняем hash корзины для отслеживания изменений
        if (typeof cdek_ajax !== 'undefined' && cdek_ajax.ajax_url) {
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_cart_hash_for_cdek',
                    nonce: cdek_ajax.nonce
                },
                success: function(response) {
                    console.log('✅ Hash корзины сохранен для СДЭК');
                },
                error: function() {
                    console.log('❌ Ошибка сохранения hash корзины');
                }
            });
        }
        
        updateOrderSummary(point);
    }
    
    function clearSelectedPoint() {
        selectedPoint = null;
        window.selectedCdekPoint = null;
        
        $('#cdek-selected-point').hide();
        $('#cdek-point-info').html('');
        
        // Очищаем ВСЕ скрытые поля
        $('#cdek-selected-point-code').val('');
        $('#cdek-selected-point-data').val('');
        $('#cdek-delivery-cost').val('0');
        
        // ПОЛНОСТЬЮ очищаем данные СДЭК в сессии через AJAX
        if (typeof cdek_ajax !== 'undefined' && cdek_ajax.ajax_url) {
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_cdek_shipping_cost',
                    cdek_delivery_cost: 0,
                    cdek_delivery_type: 'cdek', // Оставляем тип СДЭК, но очищаем стоимость
                    cdek_selected_point_code: '', // Очищаем код пункта
                    cdek_selected_point_data: '', // Очищаем данные пункта
                    nonce: cdek_ajax.nonce
                },
                success: function(response) {
                    console.log('✅ Данные пункта выдачи очищены из сессии');
                },
                error: function() {
                    console.log('❌ Ошибка при очистке данных пункта из сессии');
                }
            });
        }
        
        // Очищаем отображение стоимости доставки в HTML
        clearShippingCostDisplay();
        
        console.log('🗑️ Очищен выбор пункта выдачи');
    }
    
    function clearShippingCostDisplay() {
        // Очищаем отображение стоимости доставки в таблице
        var shippingRow = $('.woocommerce-shipping-totals.shipping td');
        if (shippingRow.length > 0) {
            console.log('🧹 Очищаем строку доставки в таблице');
            shippingRow.html('<span class="amount">0 руб.</span>');
        }
        
        // Сбрасываем labels методов доставки
        var cdekShippingLabels = $('label[for*="shipping_method"]:contains("СДЭК"), label[for*="shipping_method"]:contains("cdek")');
        cdekShippingLabels.each(function() {
            var $label = $(this);
            $label.html('СДЭК доставка: <span class="woocommerce-Price-amount amount"><bdi>0&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi></span>');
        });
        
        // Сбрасываем общую стоимость на стоимость товаров
        resetTotalToSubtotal();
    }
    
    function resetTotalToSubtotal() {
        // Получаем стоимость товаров
        var subtotalElement = $('.cart-subtotal .amount, .order-subtotal .amount');
        var subtotal = 0;
        
        if (subtotalElement.length > 0) {
            var subtotalText = subtotalElement.first().text();
            subtotal = parsePrice(subtotalText);
            console.log('📊 Сбрасываем итог на стоимость товаров:', subtotal, 'руб.');
        }
        
        // Обновляем итоговую стоимость
        var totalElements = [
            $('.order-total .amount'),
            $('.order-total .woocommerce-Price-amount'),
            $('.order-total td strong')
        ];
        
        totalElements.forEach(function(elements) {
            if (elements.length > 0) {
                elements.html('<bdi>' + subtotal + '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi>');
            }
        });
        
        console.log('✅ Итоговая стоимость сброшена на:', subtotal, 'руб.');
    }
    
    function formatPointInfo(point) {
        var pointName = point.name || 'Пункт выдачи';
        if (pointName.includes(',')) {
            pointName = pointName.split(',').slice(1).join(',').trim();
        }
        
        var html = `<strong>${pointName}</strong><br>`;
        
        if (point.location && point.location.address_full) {
            html += `Адрес: ${point.location.address_full}<br>`;
        } else if (point.address) {
            html += `Адрес: ${point.address}<br>`;
        }
        
        if (point.phones && Array.isArray(point.phones) && point.phones.length > 0) {
            var phoneNumbers = point.phones.map(function(phone) {
                return phone.number || phone;
            }).join(', ');
            html += `Телефон: ${phoneNumbers}<br>`;
        } else if (point.phone) {
            html += `Телефон: ${point.phone}<br>`;
        }
        
        html += `Режим работы: ${formatWorkTime(point.work_time, point.work_time_list)}<br>`;
        
        if (point.code) {
            html += `Код: ${point.code}<br>`;
        }
        
        return html;
    }
    
    function formatWorkTime(workTime, workTimeList) {
        if (workTimeList && Array.isArray(workTimeList) && workTimeList.length > 0) {
            var days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            var schedule = '';
            
            workTimeList.forEach(function(time) {
                if (time.day !== undefined && time.time) {
                    schedule += days[time.day - 1] + ': ' + time.time + ' ';
                }
            });
            
            return schedule || 'Не указан';
        }
        
        if (workTime && typeof workTime === 'string') {
            return workTime;
        }
        
        return 'Не указан';
    }
    
    function updateOrderSummary(point) {
        showDeliveryCalculationLoader();
        
        calculateDeliveryCost(point, function(deliveryCost) {
            hideDeliveryCalculationLoader();
            
            console.log('💰 Получена стоимость доставки:', deliveryCost, 'руб.');
            
            // Сохраняем стоимость доставки в скрытое поле
            $('#cdek-delivery-cost').val(deliveryCost);
            
            // Обновляем отображение стоимости доставки в классическом чекауте
            updateClassicShippingCost(point, deliveryCost);
            
            // Принудительно обновляем чекаут для пересчета итоговой суммы
            console.log('🔄 Запускаем обновление чекаута...');
            $('body').trigger('update_checkout');
            
            // Используем только стандартное обновление WooCommerce
            console.log('🔄 Используем только стандартное обновление WooCommerce...');
            
            // Сохраняем данные в скрытые поля
            $('#cdek-selected-point-code').val(point.code);
            $('#cdek-delivery-cost').val(deliveryCost);
            
            // Обновляем чекаут стандартным способом
            $(document.body).trigger('update_checkout');
            
            // Обновляем итог через нашу функцию с задержкой
            setTimeout(() => {
                updateTotalCost(deliveryCost);
                
                // Мягкий перезапуск Т-Банка
                setTimeout(() => {
                    console.log('🔄 Мягкий перезапуск Т-Банка...');
                    
                    // 1. Попытка через глобальные функции
                    if (typeof window.tbank_init === 'function') {
                        window.tbank_init();
                        console.log('✅ Перезапущен через tbank_init');
                    }
                    
                    if (typeof window.TinkoffPayRow !== 'undefined') {
                        window.TinkoffPayRow.init();
                        console.log('✅ Перезапущен TinkoffPayRow');
                    }
                    
                    // 2. Мягкий trigger события
                    var $tbankRadio = $('input[name="payment_method"][value="tbank"]');
                    if ($tbankRadio.length > 0) {
                        $tbankRadio.trigger('change');
                        console.log('✅ Т-Банк события запущены');
                    }
                    
                }, 200);
            }, 500);
        });
    }
    
    function updateClassicShippingCost(point, deliveryCost) {
        console.log('💰 Обновляем стоимость доставки:', deliveryCost, 'руб.');
        
        // Обновляем текст в методе доставки СДЭК
        var cdekShippingLabels = $('label[for*="shipping_method"]:contains("СДЭК"), label[for*="shipping_method"]:contains("cdek")');
        
        if (cdekShippingLabels.length === 0) {
            // Расширенный поиск если основной не нашел
            cdekShippingLabels = $('label, .woocommerce-shipping-method label').filter(function() {
                var text = $(this).text().toLowerCase();
                return text.includes('сдэк') || text.includes('cdek') || text.includes('доставка');
            });
        }
        
        cdekShippingLabels.each(function() {
            var $label = $(this);
            
            var newText;
            if (deliveryCost === 0) {
                // Для самовывоза и менеджера
                if (point.name.includes('Самовывоз')) {
                    newText = '📍 ' + point.name + ' - Бесплатно';
                } else if (point.name.includes('менеджером') || point.name.includes('менеджер')) {
                    newText = '📞 ' + point.name + ' - Бесплатно';
                } else {
                    newText = point.name + ' - Бесплатно';
                }
            } else {
                // Для доставки СДЭК
                var pointName = point.name || 'Пункт выдачи';
                if (pointName.includes(',')) {
                    pointName = pointName.split(',').slice(1).join(',').trim();
                }
                
                var displayName = pointName;
                if (point.location && point.location.city) {
                    displayName = point.location.city + ', ' + pointName.replace(point.location.city, '').replace(/^[,\s]+/, '');
                }
                
                newText = '🚚 СДЭК: ' + displayName + ' - ' + deliveryCost + ' руб.';
            }
            
            $label.html(newText);
        });
        
        // Обновляем в таблице заказа - ИСПРАВЛЕННАЯ ВЕРСИЯ
        var shippingRow = $('.woocommerce-shipping-totals.shipping td');
        if (shippingRow.length > 0) {
            console.log('📊 Обновляем строку доставки в таблице');
            shippingRow.html('<span class="amount">' + deliveryCost + ' руб.</span>');
        }
        
        // Обновляем общую стоимость напрямую
        updateTotalCost(deliveryCost);
        
        // ПРИНУДИТЕЛЬНО обновляем WooCommerce
        setTimeout(() => {
            console.log('🔄 Принудительное обновление чекаута...');
            $('body').trigger('update_checkout');
            
            // Дополнительно обновляем методы доставки
            $('input[name^="shipping_method"]').trigger('change');
        }, 100);
    }
    
    function updateTotalCost(deliveryCost) {
        console.log('💰 Обновляем итоговую стоимость с доставкой:', deliveryCost, 'руб.');
        
        // Получаем текущую стоимость товаров
        var subtotalElement = $('.cart-subtotal .amount, .order-subtotal .amount');
        var subtotal = 0;
        
        if (subtotalElement.length > 0) {
            var subtotalText = subtotalElement.first().text();
            subtotal = parsePrice(subtotalText);
            console.log('📊 Подытог без доставки:', subtotal, 'руб.');
        }
        
        // Вычисляем новую общую стоимость
        var newTotal = subtotal + deliveryCost;
        console.log('🧮 Новая общая сумма:', newTotal, 'руб.');
        
        // Обновляем отображение общей стоимости - несколько вариантов селекторов
        var totalUpdated = false;
        
        // Вариант 1: .order-total .amount
        var totalElement = $('.order-total .amount');
        if (totalElement.length > 0) {
            totalElement.html(newTotal + ' руб.');
            totalUpdated = true;
            console.log('✅ Обновлена итоговая сумма (.order-total .amount)');
        }
        
        // Вариант 2: .order-total .woocommerce-Price-amount
        var totalElement2 = $('.order-total .woocommerce-Price-amount');
        if (totalElement2.length > 0) {
            totalElement2.html(newTotal + ' руб.');
            totalUpdated = true;
            console.log('✅ Обновлена итоговая сумма (.order-total .woocommerce-Price-amount)');
        }
        
        // Вариант 3: .order-total td strong
        var totalElement3 = $('.order-total td strong');
        if (totalElement3.length > 0) {
            totalElement3.html('<span class="woocommerce-Price-amount amount">' + newTotal + ' руб.</span>');
            totalUpdated = true;
            console.log('✅ Обновлена итоговая сумма (.order-total td strong)');
        }
        
        // Вариант 4: Более широкий поиск итоговой суммы
        if (!totalUpdated) {
            var totalElement4 = $('.order-total strong, .order-total .amount, .woocommerce-checkout-review-order-table .order-total .amount');
            if (totalElement4.length > 0) {
                totalElement4.each(function() {
                    $(this).html('<span class="woocommerce-Price-amount amount">' + newTotal + ' руб.</span>');
                });
                totalUpdated = true;
                console.log('✅ Обновлена итоговая сумма (широкий поиск)');
            }
        }
        
        // Вариант 5: Попытка обновить через data-атрибуты
        if (!totalUpdated) {
            var totalElement5 = $('[data-total], .total, .checkout-total');
            if (totalElement5.length > 0) {
                totalElement5.html(newTotal + ' руб.');
                totalUpdated = true;
                console.log('✅ Обновлена итоговая сумма (data-атрибуты)');
            }
        }
        
        if (!totalUpdated) {
            console.warn('⚠️ Не удалось найти элемент для обновления итоговой суммы');
            console.log('🔍 Доступные элементы на странице:');
            console.log('order-total elements:', $('.order-total').length);
            console.log('amount elements:', $('.amount').length);
            console.log('total elements:', $('[class*="total"]').length);
        }
        
        // Принудительно уведомляем о изменении цены для интеграций
        $(document).trigger('cdek_price_updated', {
            newTotal: newTotal,
            deliveryCost: deliveryCost,
            subtotal: subtotal
        });
        
        // Дополнительные события для платежных систем
        $(document).trigger('checkout_updated');
        $(document).trigger('woocommerce_checkout_updated');
        $(document).trigger('payment_method_updated');
        
        // Специальные события для Т-Банка
        $(document).trigger('tbank_amount_updated', { amount: newTotal });
        
        // Деликатное обновление платежных форм
        setTimeout(() => {
            console.log('🔄 Деликатно обновляем платежные формы...');
            
            // 1. Мягкое обновление Т-Банка без удаления DOM
            var $tbankMethod = $('input[name="payment_method"][value="tbank"]');
            if ($tbankMethod.length > 0 && $tbankMethod.is(':checked')) {
                console.log('🎯 Найден активный Т-Банк, мягкий перезапуск...');
                
                // Только trigger события без удаления DOM
                $tbankMethod.trigger('change');
                setTimeout(() => {
                    $tbankMethod.trigger('click');
                    console.log('🔄 Т-Банк мягко перезапущен');
                }, 50);
            }
            
            // 2. Мягкое обновление чекаута
            $(document.body).trigger('update_checkout');
            
        }, 100);
        
        // Простое уведомление об обновлении
        console.log('💰 Итоговая сумма обновлена:', newTotal, '₽', '(товары:', subtotal, '+ доставка:', deliveryCost, ')');
        
        // Принудительно обновляем все поля с суммой
        setTimeout(() => {
            $('*').filter(function() {
                return $(this).text().includes('180') && $(this).text().includes('₽');
            }).each(function() {
                var currentText = $(this).text();
                var newText = currentText.replace(/180\s*₽/g, newTotal + ' ₽');
                if (currentText !== newText) {
                    $(this).text(newText);
                    console.log('🔄 Обновлен текст с 180₽ на', newTotal + '₽');
                }
            });
            
            // Безопасное уведомление об изменении суммы
            console.log('🔄 Сумма обновлена до:', newTotal, '₽');
            
        }, 500);
        
        // Безопасное обновление только конкретных элементов
        setTimeout(() => {
            console.log('🔧 Безопасное обновление конкретных элементов...');
            
            // Обновляем только элементы в таблице заказа (безопасная зона)
            $('.shop_table .order-total .amount').each(function() {
                var $elem = $(this);
                var text = $elem.text();
                if (text.includes('180') && text.includes('₽')) {
                    $elem.html('<bdi>' + newTotal + '&nbsp;<span class="woocommerce-Price-currencySymbol">₽</span></bdi>');
                    console.log('🔧 Обновлен order-total amount');
                }
            });
            
        }, 200);
        
        // Финальное мягкое обновление
        setTimeout(() => {
            console.log('🔄 Финальное мягкое обновление чекаута...');
            $(document.body).trigger('update_checkout');
        }, 1000);
    }
    
    // ========== ФУНКЦИИ ДЛЯ ЗАГРУЗЧИКОВ И ОШИБОК ==========
    
    function showDeliveryCalculationLoader() {
        // Показываем лоадер в блоке выбранного пункта
        $('#cdek-point-info').append('<div id="cost-loader" style="margin-top: 10px; color: #666;"><i>Расчет стоимости...</i></div>');
    }
    
    function hideDeliveryCalculationLoader() {
        $('#cost-loader').remove();
    }
    
    function showPvzLoader() {
        $('#cdek-points-count').html('🔄 Загружаем пункты выдачи...');
        
        // Показываем лоадер в контейнере карты
        var mapContainer = $('#cdek-map-container');
        if (mapContainer.length > 0 && $('#pvz-loader').length === 0) {
            var loader = $(`
                <div id="pvz-loader" style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: rgba(255, 255, 255, 0.95);
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 1000;
                    text-align: center;
                ">
                    <div style="
                        width: 30px;
                        height: 30px;
                        border: 3px solid #f3f3f3;
                        border-top: 3px solid #007cba;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 10px;
                    "></div>
                    <div style="color: #666; font-size: 14px;">Загрузка пунктов выдачи...</div>
                </div>
            `);
            mapContainer.css('position', 'relative').append(loader);
            
            // Добавляем CSS анимацию если её нет
            if (!$('#pvz-loader-styles').length) {
                $('head').append(`
                    <style id="pvz-loader-styles">
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    </style>
                `);
            }
        }
    }
    
    function hidePvzLoader() {
        $('#pvz-loader').remove();
    }
    
    function showPvzError(message) {
        $('#cdek-points-count').html('❌ ' + message);
        setTimeout(() => {
            $('#cdek-points-count').html('Выберите город для поиска пунктов выдачи');
        }, 3000);
    }
    
    // Функции для управления подсказкой СДЭК
    function hideCdekHint() {
        // Скрываем подсказку о выборе города
        $('p:contains("Введите город в поле «Адрес» выше, затем выберите пункт выдачи")').hide();
    }
    
    function showCdekHint() {
        // Показываем подсказку о выборе города
        $('p:contains("Введите город в поле «Адрес» выше, затем выберите пункт выдачи")').show();
    }
    
    // Функция для показа информации о менеджере
    function showManagerInfo() {
        // Убираем предыдущие блоки информации
        $('#cdek-manager-info, #cdek-pickup-info').remove();
        
        var managerInfoHtml = `
            <div id="cdek-manager-info" style="margin-top: 15px; padding: 15px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #2e7d32;">📞 Обсуждение доставки с менеджером</h4>
                <p style="margin: 0 0 10px 0; color: #333;">Наш менеджер свяжется с вами для обсуждения удобного способа доставки.</p>
                <div style="background: #fff; padding: 10px; border-radius: 4px; margin-top: 10px;">
                    <strong style="color: #2e7d32;">Доставляем:</strong> ПОЧТА, ЯНДЕКС, 5 POST
                </div>
            </div>
        `;
        
        $('#cdek-delivery-options').after(managerInfoHtml);
    }
    
    // Функция для показа информации о самовывозе
    function showPickupInfo() {
        // Убираем предыдущие блоки информации
        $('#cdek-manager-info, #cdek-pickup-info').remove();
        
        var pickupInfoHtml = `
            <div id="cdek-pickup-info" style="margin-top: 15px; padding: 15px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #1976d2;">📍 Самовывоз</h4>
                <p style="margin: 0 0 5px 0; color: #333;"><strong>Адрес:</strong> г. Саратов, ул. Осипова, д. 18а</p>
                <p style="margin: 0 0 5px 0; color: #333;"><strong>Режим работы:</strong> Пн-Пт 9:00-18:00, Сб 10:00-16:00</p>
                <p style="margin: 0; color: #666; font-size: 14px;">Заберите заказ самостоятельно по указанному адресу</p>
            </div>
        `;
        
        $('#cdek-delivery-options').after(pickupInfoHtml);
    }
    
    // Функция для скрытия информационных блоков
    function hideDeliveryInfo() {
        $('#cdek-manager-info, #cdek-pickup-info').remove();
    }
    
    // ========== ИНИЦИАЛИЗАЦИЯ ДЛЯ КЛАССИЧЕСКОГО ЧЕКАУТА ==========
    
    function initCdekDelivery() {
        console.log('🚀 Инициализация СДЭК доставки для классического чекаута');
        
        if (isInitialized) {
            console.log('⏭️ СДЭК уже инициализирован');
            return;
        }
        
        // Проверяем на дублирование карт и удаляем лишние
        var mapContainers = $('#cdek-map-container');
        if (mapContainers.length > 1) {
            console.log('🗑️ Найдено дублирование карт, удаляем лишние');
            mapContainers.slice(1).remove(); // Оставляем только первую карту
        }
        
        // Инициализируем автокомплит для поиска городов
        setTimeout(() => initAddressAutocomplete(), 200);
        
        // Инициализируем карту
        setTimeout(() => initYandexMap(), 300);
        
        isInitialized = true;
        
        console.log('✅ СДЭК доставка инициализирована для классического чекаута');
    }
    
    // Делаем функцию глобальной
    window.initCdekDelivery = initCdekDelivery;
    
    // ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
    
    // Обработчики для кнопок выбора способа доставки
    $(document).on('click', '.cdek-delivery-option', function() {
        var option = $(this).data('option');
        
        // Убираем активный класс со всех кнопок
        $('.cdek-delivery-option').removeClass('active');
        // Добавляем активный класс на выбранную кнопку
        $(this).addClass('active');
        
        // Сохраняем тип доставки
        $('#cdek-delivery-type').val(option);
        
        if (option === 'pickup') {
            // Самовывоз
            $('#cdek-delivery-content').hide();
            hideCdekHint();
            clearSelectedPoint();
            showPickupInfo();
            $('#cdek-delivery-cost').val(0);
            updateShippingTextForPickup(); // Вызываем после очистки
        } else if (option === 'manager') {
            // Обсудить с менеджером
            $('#cdek-delivery-content').hide();
            hideCdekHint();
            clearSelectedPoint();
            showManagerInfo();
            $('#cdek-delivery-cost').val(0);
            updateShippingTextForManager(); // Вызываем после очистки
        } else if (option === 'cdek') {
            // Доставка СДЭК
            $('#cdek-delivery-content').show();
            showCdekHint();
            hideDeliveryInfo(); // Скрываем блоки информации о самовывозе/менеджере
            // Автоматически ищем пункты если город уже введен
            var currentAddress = $('#billing_address_1').val();
            if (currentAddress && currentAddress.length > 2) {
                var city = currentAddress.split(',')[0].trim();
                if (city.length > 2) {
                    setTimeout(() => searchCdekPoints(city), 200);
                }
            }
        }
    });
    
    // Функции для обновления текста доставки
    window.updateShippingTextForPickup = function() {
        console.log('🏪 Выбран самовывоз - очищаем ВСЕ данные СДЭК');
        // Скрываем подсказку о выборе города
        hideCdekHint();
        
        // Добавляем скрытое поле с типом доставки
        $('#cdek-delivery-type').remove(); // Удаляем предыдущее поле
        $('form.checkout').append('<input type="hidden" id="cdek-delivery-type" name="cdek_delivery_type" value="pickup">');
        
        // Очищаем локальные переменные
        clearSelectedPoint();
        window.lastSelectedCityData = null;
        window.currentCityData = null;
        
        // ПОЛНОСТЬЮ очищаем все данные СДЭК
        $('#cdek-selected-point-code').val('');
        $('#cdek-selected-point-data').val('');
        $('#cdek-delivery-cost').val('0');
        
        // Принудительно очищаем стоимость доставки СДЭК в сессии
        if (typeof cdek_ajax !== 'undefined' && cdek_ajax.ajax_url) {
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_cdek_shipping_cost',
                    cdek_delivery_cost: 0,
                    cdek_delivery_type: 'pickup',
                    cdek_selected_point_code: '', // Очищаем код пункта
                    cdek_selected_point_data: '', // Очищаем данные пункта
                    nonce: cdek_ajax.nonce
                },
                success: function(response) {
                    console.log('✅ ВСЕ данные СДЭК очищены для самовывоза');
                    // Принудительно обновляем чекаут после очистки
                    $('body').trigger('update_checkout');
                    // Обновляем отображение в чекауте ПОСЛЕ очистки сессии
                    setTimeout(function() {
                        updateClassicShippingCost({name: 'Самовывоз (г.Саратов, ул. Осипова, д. 18а)'}, 0);
                    }, 100);
                },
                error: function() {
                    console.log('❌ Ошибка при очистке данных СДЭК');
                    // Даже при ошибке обновляем отображение
                    updateClassicShippingCost({name: 'Самовывоз (г.Саратов, ул. Осипова, д. 18а)'}, 0);
                }
            });
        } else {
            // Если AJAX недоступен, просто обновляем отображение
            updateClassicShippingCost({name: 'Самовывоз (г.Саратов, ул. Осипова, д. 18а)'}, 0);
        }
    };
    
    window.updateShippingTextForManager = function() {
        console.log('📞 Выбрано обсуждение с менеджером - очищаем ВСЕ данные СДЭК');
        // Скрываем подсказку о выборе города
        hideCdekHint();
        
        // Добавляем скрытое поле с типом доставки
        $('#cdek-delivery-type').remove(); // Удаляем предыдущее поле
        $('form.checkout').append('<input type="hidden" id="cdek-delivery-type" name="cdek_delivery_type" value="manager">');
        
        // Очищаем локальные переменные
        clearSelectedPoint();
        window.lastSelectedCityData = null;
        window.currentCityData = null;
        
        // ПОЛНОСТЬЮ очищаем все данные СДЭК
        $('#cdek-selected-point-code').val('');
        $('#cdek-selected-point-data').val('');
        $('#cdek-delivery-cost').val('0');
        
        // Принудительно очищаем стоимость доставки СДЭК в сессии
        if (typeof cdek_ajax !== 'undefined' && cdek_ajax.ajax_url) {
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_cdek_shipping_cost',
                    cdek_delivery_cost: 0,
                    cdek_delivery_type: 'manager',
                    cdek_selected_point_code: '', // Очищаем код пункта
                    cdek_selected_point_data: '', // Очищаем данные пункта
                    nonce: cdek_ajax.nonce
                },
                success: function(response) {
                    console.log('✅ ВСЕ данные СДЭК очищены для менеджера');
                    // Принудительно обновляем чекаут после очистки
                    $('body').trigger('update_checkout');
                    // Обновляем отображение в чекауте ПОСЛЕ очистки сессии
                    setTimeout(function() {
                        updateClassicShippingCost({name: 'Обсудить доставку с менеджером'}, 0);
                    }, 100);
                },
                error: function() {
                    console.log('❌ Ошибка при очистке данных СДЭК');
                    // Даже при ошибке обновляем отображение
                    updateClassicShippingCost({name: 'Обсудить доставку с менеджером'}, 0);
                }
            });
        } else {
            // Если AJAX недоступен, просто обновляем отображение
            updateClassicShippingCost({name: 'Обсудить доставку с менеджером'}, 0);
        }
    };
    
    // Инициализация при изменении метода доставки
    $(document).on('change', 'input[name^="shipping_method"]', function() {
        console.log('🔄 Изменен метод доставки:', $(this).val());
        
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            console.log('✅ Выбрана доставка СДЭК');
            $('#cdek-map-container, #cdek-map-wrapper').show();
            
            // Принудительно показываем карту
            $('#cdek-map').css({
                'display': 'block !important',
                'visibility': 'visible !important',
                'opacity': '1 !important'
            });
            
            debouncer.debounce('init-cdek', () => initCdekDelivery(), 100);
        } else {
            console.log('❌ Выбран другой метод доставки');
            $('#cdek-map-container, #cdek-map-wrapper').hide();
            clearSelectedPoint();
        }
    });
    
    // Обработчик поиска по городу в поле адреса
    $(document).on('input', '#billing_address_1, #shipping_address_1', function() {
        var address = $(this).val().trim();
        var city = address.split(',')[0].trim();
        
        // Автоматически ищем пункты СДЭК только если выбрана доставка СДЭК
        if ($('#cdek-delivery-type').val() === 'cdek' && city.length > 2) {
            // Проверяем, есть ли сохраненные данные города из DaData
            var cityData = null;
            if (window.lastSelectedCityData && window.lastSelectedCityData.city === city) {
                cityData = window.lastSelectedCityData;
            }
            
            debouncer.debounce('city-search', () => searchCdekPoints(city, cityData), 500);
        } else if (city.length <= 2) {
            $('#cdek-points-list').hide();
            if (cdekMap) {
                cdekMap.geoObjects.removeAll();
            }
            $('#cdek-points-count').text('Введите город в поле "Адрес" для поиска пунктов выдачи');
        }
    });
    
    // Обработчик очистки выбора
    $(document).on('click', '#cdek-clear-selection', function() {
        clearSelectedPoint();
        $('#cdek-points-list').hide();
        if (cdekMap) {
            cdekMap.geoObjects.removeAll();
        }
    });
    
    // Проверяем при загрузке страницы
    $(document).ready(function() {
        console.log('📄 Страница загружена, проверяем состояние доставки');
        
        // Проверяем, выбрана ли доставка СДЭК
        var cdekSelected = false;
        $('input[name^="shipping_method"]:checked').each(function() {
            if ($(this).val().indexOf('cdek_delivery') !== -1) {
                cdekSelected = true;
                console.log('✅ СДЭК доставка уже выбрана при загрузке');
            }
        });
        
        // Удаляем дублирующиеся карты при загрузке
        var mapContainers = $('#cdek-map-container');
        if (mapContainers.length > 1) {
            console.log('🗑️ Найдено ' + mapContainers.length + ' карт при загрузке, удаляем лишние');
            mapContainers.slice(1).remove(); // Оставляем только первую карту
        }
        
        // ВСЕГДА показываем карту при загрузке
        $('#cdek-map-container, #cdek-map-wrapper').show();
        
        // Принудительно показываем карту
        $('#cdek-map').css({
            'display': 'block !important',
            'visibility': 'visible !important',
            'opacity': '1 !important'
        });
        
        debouncer.debounce('init-cdek-load', () => initCdekDelivery(), 500);
    });
    
    // Обновление чекаута при изменениях
    $(document).on('updated_checkout', function() {
        // Переинициализируем обработчики после обновления чекаута
        setTimeout(() => {
            // Удаляем дублирующиеся карты после обновления
            var mapContainers = $('#cdek-map-container');
            if (mapContainers.length > 1) {
                console.log('🗑️ После обновления чекаута найдено ' + mapContainers.length + ' карт, удаляем лишние');
                mapContainers.slice(1).remove(); // Оставляем только первую карту
            }
            
            if ($('#cdek-map-wrapper').is(':visible')) {
                // Восстанавливаем состояние кнопок
                var deliveryType = $('#cdek-delivery-type').val() || 'cdek';
                $('.cdek-delivery-option').removeClass('active');
                $('.cdek-delivery-option[data-option="' + deliveryType + '"]').addClass('active');
                
                if (deliveryType === 'cdek') {
                    $('#cdek-delivery-content').show();
                } else {
                    $('#cdek-delivery-content').hide();
                }
            }
        }, 100);
    });
    
    console.log('📋 СДЭК доставка для классического чекаута загружена');
    console.log('✅ Возвращен к использованию jQuery');
});
