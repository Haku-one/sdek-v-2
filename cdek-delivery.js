/**
 * СДЭК Доставка - Максимально быстрая версия без кэширования
 * Оптимизации: прямые API вызовы, асинхронность, минимум операций
 */

// ========== БЫСТРЫЕ УТИЛИТЫ БЕЗ КЭШИРОВАНИЯ ==========

// Простой дебаунсер без приоритетов
class FastDebouncer {
    constructor() {
        this.timers = new Map();
    }
    
    debounce(key, fn, delay) {
        if (this.timers.has(key)) {
            clearTimeout(this.timers.get(key));
        }
        
        const timer = setTimeout(() => {
            fn();
            this.timers.delete(key);
        }, delay);
        
        this.timers.set(key, timer);
    }
}

// Батчинг DOM операций для быстродействия
class FastDOMBatcher {
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

// Исправление дублированных цен - только основная логика
class PriceFormatter {
    static fixDuplicatedPrice(priceText) {
        if (!priceText || typeof priceText !== 'string') {
            return priceText;
        }
        
        const numbers = priceText.match(/\d+/g);
        if (!numbers || numbers.length === 0) {
            return priceText;
        }
        
        const mainNumber = numbers[0];
        const numValue = parseInt(mainNumber);
        
        // НЕ трогаем валидные итоговые суммы
        if (numValue >= 100000 && numValue <= 999999) {
            return priceText;
        }
        
        if (mainNumber.length >= 6) {
            const halfLen = Math.floor(mainNumber.length / 2);
            const prefix = mainNumber.substring(0, halfLen);
            const suffix = mainNumber.substring(halfLen);
            
            if (prefix === suffix && prefix.length >= 2) {
                const correctedText = priceText.replace(mainNumber, prefix);
                console.log(`🔧 Исправлена дублированная цена: ${priceText} -> ${correctedText}`);
                return correctedText;
            }
        }
        
        return priceText;
    }
    
    static extractCleanPrice(priceText) {
        const fixed = this.fixDuplicatedPrice(priceText);
        const match = fixed.match(/(\d+(?:\.\d+)?)/);
        return match ? parseFloat(match[1]) : 0;
    }
}

// ========== БЫСТРЫЙ ПОИСК АДРЕСОВ ==========

class FastAddressSearch {
    constructor() {
        this.debouncer = new FastDebouncer();
        
        // Только самые популярные города
        this.cities = [
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул',
            'Ульяновск', 'Владивосток', 'Ярославль', 'Иркутск', 'Хабаровск', 'Махачкала', 'Томск',
            'Оренбург', 'Кемерово', 'Новокузнецк', 'Рязань', 'Астрахань', 'Пенза', 'Липецк'
        ];
    }
    
    search(query, callback) {
        this.debouncer.debounce('search', () => {
            this.performSearch(query, callback);
        }, 150);
    }
    
    performSearch(query, callback) {
        if (!query || query.length < 2) {
            callback([]);
            return;
        }
        
        const queryLower = query.toLowerCase().trim();
        const results = [];
        const maxResults = 8;
        
        this.cities.forEach(city => {
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
                results.push({
                    city: city,
                    display: city,
                    score: score,
                    type: 'city'
                });
            }
        });
        
        results.sort((a, b) => b.score - a.score);
        callback(results.slice(0, maxResults));
    }
}

// ========== ОСНОВНОЙ КОД СДЭК ==========

jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false;
    
    // Быстрые утилиты
    const debouncer = new FastDebouncer();
    const domBatcher = new FastDOMBatcher();
    const addressSearch = new FastAddressSearch();
    
    // ========== БЫСТРОЕ ИСПРАВЛЕНИЕ ЦЕН ==========
    
    function fixExistingDuplicatedPrices() {
        $('.wc-block-components-totals-item__value, .wc-block-formatted-money-amount').each(function() {
            const $element = $(this);
            
            // Проверяем, не является ли это итоговой суммой
            const isTotal = $element.closest('.wc-block-components-totals-footer-item').length > 0 ||
                           $element.siblings('.wc-block-components-totals-item__label').text().indexOf('Итого') !== -1;
            
            if (!isTotal) {
                const currentText = $element.text().trim();
                const fixedText = PriceFormatter.fixDuplicatedPrice(currentText);
                
                if (currentText !== fixedText) {
                    $element.text(fixedText);
                }
            }
        });
    }
    
    function startPriceMonitoring() {
        setInterval(fixExistingDuplicatedPrices, 1500); // Быстрый интервал
        
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver((mutations) => {
                let shouldCheck = false;
                
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        const target = mutation.target;
                        if (target.classList && 
                            (target.classList.contains('wc-block-components-totals-item__value') ||
                             target.classList.contains('wc-block-formatted-money-amount'))) {
                            shouldCheck = true;
                        }
                    }
                });
                
                if (shouldCheck) {
                    debouncer.debounce('price-fix', fixExistingDuplicatedPrices, 50);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }
    }
    
    // ========== БЫСТРОЕ ПОЛУЧЕНИЕ ДАННЫХ КОРЗИНЫ ==========
    
    function getCartDataForCalculation() {
        var cartWeight = 0;
        var cartValue = 0;
        var totalVolume = 0;
        var maxLength = 0, maxWidth = 0, maxHeight = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        var packagesCount = 1;
        
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = parseInt(quantityElement.text()) || 1;
            
            // Габариты
            var dimensionsElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                var labelText = siblingLabel.text();
                return labelText.indexOf('Габариты') !== -1 || labelText.indexOf('Размеры') !== -1;
            });
            
            if (dimensionsElement.length > 0) {
                var dimensionsText = dimensionsElement.text().trim();
                var dimensionsMatch = dimensionsText.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/);
                
                if (dimensionsMatch) {
                    var length = parseFloat(dimensionsMatch[1]);
                    var width = parseFloat(dimensionsMatch[2]);
                    var height = parseFloat(dimensionsMatch[3]);
                    
                    var itemVolume = length * width * height * quantity;
                    totalVolume += itemVolume;
                    totalItems += quantity;
                    
                    maxLength = Math.max(maxLength, length);
                    maxWidth = Math.max(maxWidth, width);
                    maxHeight = Math.max(maxHeight, height);
                    
                    hasValidDimensions = true;
                }
            }
            
            // Вес
            var weightElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                return siblingLabel.text().indexOf('Вес') !== -1;
            });
            
            if (weightElement.length > 0) {
                var weightText = weightElement.text().trim();
                var weightMatch = weightText.match(/(\d+(?:\.\d+)?)/);
                
                if (weightMatch) {
                    var weight = parseFloat(weightMatch[1]);
                    
                    if (weightText.includes('кг')) {
                        weight = weight * 1000;
                    }
                    
                    cartWeight += weight * quantity;
                }
            }
            
            // Цена
            var totalPriceElement = $item.find('.wc-block-components-order-summary-item__total-price .wc-block-components-product-price__value');
            
            if (totalPriceElement.length > 0) {
                var totalPriceText = totalPriceElement.text().trim();
                var totalPrice = PriceFormatter.extractCleanPrice(totalPriceText);
                cartValue += totalPrice;
            }
        });
        
        // Итоговая сумма заказа
        var totalOrderElement = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value');
        var orderTotalFromFooter = 0;
        
        if (totalOrderElement.length > 0) {
            var totalText = totalOrderElement.first().text().trim();
            orderTotalFromFooter = PriceFormatter.extractCleanPrice(totalText);
        }
        
        // Быстрый расчет размеров упаковки
        var dimensions;
        if (hasValidDimensions && totalVolume > 0) {
            if (totalItems <= 2) {
                dimensions = {
                    length: Math.ceil(maxLength * 1.05),
                    width: Math.ceil(maxWidth * 1.05),
                    height: Math.ceil(maxHeight * 1.05)
                };
            } else {
                var volumeRatio = Math.pow(totalVolume / (maxLength * maxWidth * maxHeight), 1/3);
                
                dimensions = {
                    length: Math.ceil(maxLength * Math.max(volumeRatio, 1) * 1.1),
                    width: Math.ceil(maxWidth * Math.max(volumeRatio, 1) * 1.1),
                    height: Math.ceil(maxHeight * Math.max(volumeRatio, 1) * 1.1)
                };
            }
            
            dimensions.length = Math.max(10, Math.min(dimensions.length, 150));
            dimensions.width = Math.max(10, Math.min(dimensions.width, 150));
            dimensions.height = Math.max(5, Math.min(dimensions.height, 150));
            
            // Проверка объема упаковки
            var volume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
            if (volume > 300) {
                packagesCount = Math.ceil(volume / 280);
                
                var targetVolume = 280;
                var scaleFactor = Math.pow(targetVolume / volume, 1/3);
                
                dimensions = {
                    length: Math.max(10, Math.min(Math.ceil(dimensions.length * scaleFactor), 100)),
                    width: Math.max(10, Math.min(Math.ceil(dimensions.width * scaleFactor), 100)),
                    height: Math.max(5, Math.min(Math.ceil(dimensions.height * scaleFactor), 100))
                };
                
                cartWeight = cartWeight / packagesCount;
            }
        } else {
            dimensions = {
                length: 30,
                width: 20,
                height: 15
            };
        }
        
        if (cartWeight === 0) {
            cartWeight = 500;
        }
        
        if (orderTotalFromFooter > 0) {
            cartValue = orderTotalFromFooter;
        } else if (cartValue === 0) {
            cartValue = 1000;
        }
        
        return {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions,
            packagesCount: packagesCount
        };
    }
    
    // ========== БЫСТРЫЙ РАСЧЕТ СТОИМОСТИ ДОСТАВКИ ==========
    
    function calculateDeliveryCost(point, callback) {
        var cartData = getCartDataForCalculation();
        
        if (typeof cdek_ajax === 'undefined' || !cdek_ajax.ajax_url) {
            console.error('CDEK AJAX не инициализирован');
            callback(calculateFallbackCost(point, cartData));
            return;
        }
        
        if (!point || !point.code) {
            console.error('Не указан пункт выдачи или его код');
            callback(calculateFallbackCost(point, cartData));
            return;
        }
        
        console.log('🚀 Быстрый расчет стоимости для пункта:', point.code);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 15000, // Уменьшили таймаут для быстроты
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
                    
                    if (cartData.packagesCount > 1) {
                        deliveryCost = deliveryCost * cartData.packagesCount;
                    }
                    
                    console.log('✅ Быстро получена стоимость:', deliveryCost, 'руб.');
                    callback(deliveryCost);
                } else {
                    var fallbackCost = calculateFallbackCost(point, cartData);
                    console.log('🔄 Fallback стоимость:', fallbackCost, 'руб.');
                    callback(fallbackCost);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Ошибка API:', status, error);
                var fallbackCost = calculateFallbackCost(point, cartData);
                console.log('🔄 Fallback стоимость:', fallbackCost, 'руб.');
                callback(fallbackCost);
            }
        });
    }
    
    function calculateFallbackCost(point, cartData) {
        var baseCost = 350;
        
        if (!cartData) {
            return baseCost;
        }
        
        if (cartData.weight > 500) {
            var extraWeight = Math.ceil((cartData.weight - 500) / 500);
            baseCost += extraWeight * 40;
        }
        
        if (cartData.hasRealDimensions && cartData.dimensions) {
            var volume = cartData.dimensions.length * cartData.dimensions.width * cartData.dimensions.height;
            if (volume > 12000) {
                var extraVolume = Math.ceil((volume - 12000) / 6000);
                baseCost += extraVolume * 60;
            }
        }
        
        if (cartData.value > 3000) {
            baseCost += Math.ceil((cartData.value - 3000) / 1000) * 25;
        }
        
        if (cartData.packagesCount > 1) {
            baseCost = baseCost * cartData.packagesCount;
        }
        
        return baseCost;
    }
    
    // ========== БЫСТРЫЙ ПОИСК АДРЕСОВ ==========
    
    function parseAddress(address) {
        var result = { city: '', street: '' };
        
        if (!address || address.trim() === '') {
            return result;
        }
        
        var parts = address.split(/[,\s]+/);
        
        if (parts.length > 0) {
            result.city = parts[0].trim();
        }
        if (parts.length > 1) {
            result.street = parts.slice(1).join(' ');
        }
        
        return result;
    }
    
    function initAddressAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        $('#address-suggestions').remove();
        setupFastAutocomplete();
    }
    
    function setupFastAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        var suggestionsContainer = $(`
            <div id="address-suggestions" class="fast-address-suggestions" style="display: none;">
                <div class="suggestions-list"></div>
            </div>
        `);
        
        addressInput.parent().css('position', 'relative');
        addressInput.parent().append(suggestionsContainer);
        
        if (!$('#fast-search-styles').length) {
            $('head').append(`
                <style id="fast-search-styles">
                .fast-address-suggestions {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #e1e5e9;
                    border-radius: 6px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    z-index: 1000;
                    max-height: 200px;
                    overflow-y: auto;
                    margin-top: 2px;
                }
                
                .suggestion-item {
                    display: flex;
                    align-items: center;
                    padding: 10px 12px;
                    cursor: pointer;
                    transition: background-color 0.1s ease;
                    border-bottom: 1px solid #f5f5f5;
                    min-height: 40px;
                }
                
                .suggestion-item:hover {
                    background-color: #f8f9fa;
                }
                
                .suggestion-item:last-child {
                    border-bottom: none;
                }
                
                .suggestion-icon {
                    font-size: 16px;
                    margin-right: 8px;
                    opacity: 0.7;
                }
                
                .suggestion-content {
                    flex: 1;
                }
                
                .suggestion-title {
                    font-weight: 500;
                    color: #333;
                    font-size: 14px;
                }
                
                .suggestion-title mark {
                    background-color: #fff3cd;
                    color: #856404;
                    padding: 0 2px;
                    border-radius: 2px;
                }
                </style>
            `);
        }
        
        var currentSuggestions = [];
        
        addressInput.on('input', function() {
            var query = $(this).val().trim();
            
            if (query.length >= 2) {
                addressSearch.search(query, function(suggestions) {
                    currentSuggestions = suggestions;
                    showAddressSuggestions(suggestions, query);
                });
            } else {
                hideAddressSuggestions();
            }
        });
        
        function showAddressSuggestions(suggestions, query) {
            var container = suggestionsContainer.find('.suggestions-list');
            container.empty();
            
            if (suggestions.length === 0) {
                container.html('<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">Ничего не найдено</div></div></div>');
            } else {
                suggestions.forEach(function(suggestion, index) {
                    var highlightedCity = highlightQuery(suggestion.city, query);
                    
                    var item = $(`
                        <div class="suggestion-item" data-index="${index}">
                            <div class="suggestion-icon">🏙️</div>
                            <div class="suggestion-content">
                                <div class="suggestion-title">${highlightedCity}</div>
                            </div>
                        </div>
                    `);
                    
                    item.on('click', function() {
                        selectSuggestion(suggestion);
                    });
                    
                    container.append(item);
                });
            }
            
            suggestionsContainer.show();
        }
        
        function highlightQuery(text, query) {
            if (!query || !text) return text;
            
            var regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        function selectSuggestion(suggestion) {
            addressInput.val(suggestion.city);
            hideAddressSuggestions();
            
            // Быстрый поиск ПВЗ
            debouncer.debounce('cdek-search', () => {
                searchCdekPoints(suggestion.city);
            }, 100);
        }
        
        function hideAddressSuggestions() {
            suggestionsContainer.hide();
        }
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#address-suggestions, #shipping-address_1').length) {
                hideAddressSuggestions();
            }
        });
    }
    
    // ========== БЫСТРЫЙ ПОИСК ПВЗ ==========
    
    function searchCdekPoints(address) {
        var parsedAddress = parseAddress(address);
        
        console.log('🔍 Быстрый поиск ПВЗ для:', parsedAddress.city);
        
        window.currentSearchCity = parsedAddress.city;
        
        // Прямой вызов API без геокодирования
        performCdekSearch();
    }
    
    function performCdekSearch() {
        if (typeof cdek_ajax === 'undefined') return;
        
        var searchAddress = window.currentSearchCity || 'Россия';
        
        console.log('🚀 Быстрый запрос к API СДЭК для:', searchAddress);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 15000, // Уменьшили таймаут
            data: {
                action: 'get_cdek_points',
                address: searchAddress,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    console.log('✅ Быстро получено ПВЗ:', response.data.length);
                    displayCdekPoints(response.data);
                } else {
                    console.error('❌ Ошибка получения ПВЗ:', response);
                    showPvzError('Не удалось загрузить пункты выдачи');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Ошибка запроса ПВЗ:', error);
                showPvzError('Ошибка загрузки пунктов выдачи');
            }
        });
    }
    
    // ========== БЫСТРОЕ ОТОБРАЖЕНИЕ ПВЗ ==========
    
    function displayCdekPoints(points) {
        cdekPoints = points;
        
        if (!cdekMap || typeof ymaps === 'undefined') {
            setTimeout(function() { displayCdekPoints(points); }, 500);
            return;
        }
        
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            $('#cdek-points-count').text(`Пункты выдачи не найдены${cityInfo}`);
            return;
        }
        
        // Быстрая фильтрация
        var filteredPoints = points.filter(function(point) {
            if (!window.currentSearchCity) return true;
            
            var pointCity = '';
            
            if (point.location && point.location.city) {
                pointCity = point.location.city.trim();
            } else if (point.location && point.location.address) {
                pointCity = point.location.address.split(',')[0].trim();
            }
            
            if (pointCity) {
                pointCity = pointCity.replace(/^(г\.?\s*|город\s+)/i, '').trim();
            }
            
            var searchCityLower = window.currentSearchCity.toLowerCase().trim();
            var pointCityLower = pointCity.toLowerCase().trim();
            
            return pointCityLower === searchCityLower || 
                   pointCityLower.includes(searchCityLower) || 
                   searchCityLower.includes(pointCityLower);
        });
        
        console.log('🔍 Фильтрация:', points.length, '->', filteredPoints.length);
        
        var maxPoints = 100; // Ограничиваем для быстроты
        var pointsToShow = filteredPoints.slice(0, maxPoints);
        
        var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
        $('#cdek-points-count').text(`Найдено ${filteredPoints.length} пунктов выдачи${cityInfo}`);
        
        var bounds = [];
        
        pointsToShow.forEach(function(point, index) {
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
                
                cdekMap.geoObjects.add(placemark);
            }
        });
        
        // Быстрое центрирование карты
        if (bounds.length > 0) {
            if (bounds.length === 1) {
                cdekMap.setCenter(bounds[0], 14);
            } else {
                var centerLat = bounds.reduce((sum, coord) => sum + coord[0], 0) / bounds.length;
                var centerLon = bounds.reduce((sum, coord) => sum + coord[1], 0) / bounds.length;
                cdekMap.setCenter([centerLat, centerLon], 12);
            }
        }
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        if (cdekMap && point.location) {
            cdekMap.setCenter([point.location.latitude, point.location.longitude], 15);
        }
        
        // Сохраняем данные
        if ($('#cdek-selected-point-code').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'cdek-selected-point-code',
                name: 'cdek_selected_point_code',
                value: point.code
            }).appendTo('form.checkout, form.woocommerce-checkout');
        } else {
            $('#cdek-selected-point-code').val(point.code);
        }
        
        if ($('#cdek-selected-point-data').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'cdek-selected-point-data',
                name: 'cdek_selected_point_data',
                value: JSON.stringify(point)
            }).appendTo('form.checkout, form.woocommerce-checkout');
        } else {
            $('#cdek-selected-point-data').val(JSON.stringify(point));
        }
        
        updateOrderSummary(point);
        
        console.log('✅ Выбран ПВЗ:', point.name, '(код:', point.code + ')');
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
        // Быстрый расчет без лоадера
        calculateDeliveryCost(point, function(deliveryCost) {
            var allShippingBlocks = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('СДЭК') !== -1 || 
                       labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                       labelText.match(/^[А-Яа-я\s,\.\-]+$/);
            });
            
            allShippingBlocks.each(function() {
                updateShippingBlock($(this), point, deliveryCost);
            });
            
            updateOrderTotal(deliveryCost);
        });
    }
    
    function updateShippingBlock(block, point, deliveryCost) {
        var pointName = point.name || 'Пункт выдачи';
        if (pointName.includes(',')) {
            pointName = pointName.split(',').slice(1).join(',').trim();
        }
        
        var displayName = pointName;
        if (point.location && point.location.city) {
            displayName = point.location.city + ', ' + pointName.replace(point.location.city, '').replace(/^[,\s]+/, '');
        }
        
        var address = '';
        if (point.location && point.location.address_full) {
            address = point.location.address_full;
        } else if (point.location && point.location.address) {
            address = point.location.address;
        } else if (point.address) {
            address = point.address;
        }
        
        var labelElement = block.find('.wc-block-components-totals-item__label');
        var valueElement = block.find('.wc-block-components-totals-item__value');
        var descriptionElement = block.find('.wc-block-components-totals-item__description');
        
        labelElement.text(displayName);
        valueElement.text(deliveryCost + ' руб.');
        
        if (address) {
            if (descriptionElement.length === 0) {
                descriptionElement = $('<div class="wc-block-components-totals-item__description"></div>');
                block.append(descriptionElement);
            }
            descriptionElement.html('<small style="color: #666;">' + address + '</small>');
        }
        
        window.currentDeliveryCost = deliveryCost;
        
        $(document.body).trigger('updated_checkout');
        $(document.body).trigger('updated_cart_totals');
    }
    
    function updateOrderTotal(deliveryCost) {
        var totalBlock = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
        });
        
        if (totalBlock.length > 0) {
            totalBlock = totalBlock.first();
            
            var subtotalBlock = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
            });
            
            if (subtotalBlock.length > 0) {
                var subtotalText = subtotalBlock.find('.wc-block-components-totals-item__value').text();
                var subtotal = PriceFormatter.extractCleanPrice(subtotalText);
                
                var taxBlock = $('.wc-block-components-totals-taxes .wc-block-components-totals-item__value');
                var tax = 0;
                if (taxBlock.length > 0) {
                    var taxText = taxBlock.text();
                    tax = PriceFormatter.extractCleanPrice(taxText);
                }
                
                var newTotal = subtotal + deliveryCost + tax;
                var formattedTotal = newTotal.toLocaleString('ru-RU') + ' руб.';
                
                var totalValueElement = totalBlock.find('.wc-block-components-totals-item__value');
                totalValueElement.text(formattedTotal);
            }
        }
    }
    
    function showPvzError(message) {
        $('#cdek-points-count').html('❌ ' + message);
        setTimeout(() => {
            $('#cdek-points-count').html('Выберите город для поиска пунктов выдачи');
        }, 3000);
    }
    
    // ========== ИНИЦИАЛИЗАЦИЯ КАРТЫ ==========
    
    function initYandexMap() {
        if (cdekMap) return;
        
        if (typeof ymaps === 'undefined') {
            setTimeout(initYandexMap, 500);
            return;
        }
        
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) {
            setTimeout(initYandexMap, 250);
            return;
        }
        
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important;';
        
        try {
            cdekMap = new ymaps.Map(mapContainer, {
                center: [55.753994, 37.622093],
                zoom: 10,
                controls: ['zoomControl', 'searchControl']
            });
        
            if (cdekPoints && cdekPoints.length > 0) {
                displayCdekPoints(cdekPoints);
            }
        } catch (error) {
            setTimeout(initYandexMap, 500);
        }
    }
    
    function initCdekDelivery() {
        if (isInitialized) return;
        
        if ($('#cdek-map-container').length === 0) {
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px; display: block !important;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
                    <div id="cdek-points-info" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                        <strong>Информация:</strong>
                        <div id="cdek-points-count">Введите город в поле адреса выше для поиска пунктов выдачи</div>
                    </div>
                    <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
                        <strong>Выбранный пункт:</strong>
                        <div id="cdek-point-info"></div>
                    </div>
                    <div id="cdek-map" style="width: 100%; height: 450px; border: 1px solid #ddd; border-radius: 6px; display: block !important;"></div>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        💡 Введите город в поле адреса выше, затем выберите пункт выдачи на карте
                    </p>
                </div>
            `;
            
            var mapBlock = $('.wp-block-cdek-checkout-map-block');
            var insertTarget = null;
            
            if (mapBlock.length > 0) {
                insertTarget = mapBlock;
                insertTarget.html(mapHtml);
            } else {
                var addressForm = $('.wc-block-components-address-form');
                var shippingBlock = $('.wp-block-woocommerce-checkout-shipping-address-block');
                
                insertTarget = addressForm.length ? addressForm : shippingBlock;
                    
                if (insertTarget.length > 0) {
                    insertTarget.after(mapHtml);
                }
            }
        }
        
        $('#cdek-map-container').show();
        
        setTimeout(initYandexMap, 250);
        setTimeout(initAddressAutocomplete, 500);
        
        isInitialized = true;
        
        console.log('✅ СДЭК доставка быстро инициализирована');
    }
    
    // ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
    
    startPriceMonitoring();
    
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            debouncer.debounce('init-cdek', initCdekDelivery, 50);
        }
    });
    
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        debouncer.debounce('init-cdek-click', initCdekDelivery, 100);
    });
    
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        var city = address.split(',')[0].trim();
        
        if (city.length > 2) {
            debouncer.debounce('address-change', () => searchCdekPoints(city), 200);
        }
    });
    
    var observer = new MutationObserver(function(mutations) {
        var needsUpdate = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                var cdekMethod = $('input[value*="cdek_delivery"]');
                if (cdekMethod.length > 0 && !isInitialized) {
                    needsUpdate = true;
                }
            }
        });
        
        if (needsUpdate) {
            debouncer.debounce('mutation-init', () => {
                var cdekSelected = $('input[value*="cdek_delivery"]:checked');
                
                if (cdekSelected.length > 0 && $('#cdek-map-container').length === 0) {
                    initCdekDelivery();
                }
            }, 200);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Быстрая инициализация
    setTimeout(() => {
        var cdekMethod = $('input[value*="cdek_delivery"]');
        if (cdekMethod.length > 0) {
            initCdekDelivery();
        }
    }, 1000);
    
    setTimeout(initAddressAutocomplete, 2000);
    
    // Быстрая проверка цен
    setInterval(fixExistingDuplicatedPrices, 1000);
    
    console.log('🚀 СДЭК Delivery FAST v3.0 загружен');
    console.log('⚡ Максимальная скорость без кэширования');
    console.log('🔥 Прямые API вызовы, минимум задержек');
});
