jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей - только React эмуляция');
    
    let updateTimeout;
    
    // Глобальные переменные для хранения текущих значений
    window.currentDeliveryData = {
        dostavka: '',
        manager: ''
    };
    
    // Дебаунсинг для предотвращения частых вызовов
    function debouncedUpdate() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updateTextareaFields, 500);
    }
    
    // Функция для поиска и обновления React состояния inputValue
    function updateReactInputState(element, value) {
        try {
            // Ищем React fiber
            const fiber = element._reactInternalFiber || 
                         element._reactInternalInstance ||
                         element[Object.keys(element).find(key => key.startsWith('__reactFiber'))];
            
            if (!fiber) return false;
            
            // Проходим по дереву компонентов вверх
            let currentFiber = fiber;
            while (currentFiber) {
                // Если у fiber есть state или hooks
                if (currentFiber.stateNode && currentFiber.stateNode.setState) {
                    // Это class component
                    if (currentFiber.stateNode.state && 'inputValue' in currentFiber.stateNode.state) {
                        currentFiber.stateNode.setState({ inputValue: value });
                        console.log('🎯 Обновлено state.inputValue в class component');
                        return true;
                    }
                }
                
                // Если это functional component с hooks
                if (currentFiber.memoizedState) {
                    let hook = currentFiber.memoizedState;
                    let hookIndex = 0;
                    
                    while (hook) {
                        // Если это useState hook (есть queue с dispatch)
                        if (hook.queue && hook.queue.dispatch) {
                            // Пробуем обновить первый useState (обычно это inputValue)
                            if (hookIndex === 0) {
                                hook.queue.dispatch(value);
                                console.log('🎯 Обновлен первый useState hook (inputValue)');
                                return true;
                            }
                        }
                        hook = hook.next;
                        hookIndex++;
                    }
                }
                
                currentFiber = currentFiber.return;
            }
            
            return false;
        } catch (e) {
            console.log('Ошибка обновления React состояния:', e);
            return false;
        }
    }
    
    // Функция для эмуляции пользовательского ввода в React поле
    function simulateUserInput(element, value) {
        if (!element || !element.length) return;
        
        element.each(function() {
            const textarea = this;
            
            // Устанавливаем значение
            textarea.value = value;
            
            // 0. Сначала пытаемся обновить React состояние напрямую
            const stateUpdated = updateReactInputState(textarea, value);
            if (stateUpdated) {
                console.log(`✅ React состояние обновлено для: "${value}"`);
                return; // Выходим, состояние обновлено
            }
            
            // 1. Пытаемся найти React onChange функцию в props
            let onChangeFound = false;
            
            // Ищем React props в разных возможных местах
            const possibleKeys = Object.keys(textarea).filter(key => 
                key.startsWith('__reactProps') || 
                key.startsWith('__reactEventHandlers') ||
                key.startsWith('__reactInternalInstance')
            );
            
            for (let key of possibleKeys) {
                if (textarea[key] && textarea[key].onChange) {
                    console.log('🎯 Найдена React onChange функция');
                    textarea[key].onChange({
                        target: { 
                            value: value,
                            name: textarea.name
                        },
                        currentTarget: textarea,
                        type: 'change'
                    });
                    onChangeFound = true;
                    break;
                }
            }
            
            // 2. Если не нашли onChange в props, ищем в React fiber
            if (!onChangeFound) {
                const reactFiber = textarea._reactInternalFiber || 
                                  textarea._reactInternalInstance ||
                                  Object.keys(textarea).find(key => key.startsWith('__reactFiber')) && textarea[Object.keys(textarea).find(key => key.startsWith('__reactFiber'))];
                
                if (reactFiber && reactFiber.memoizedProps && reactFiber.memoizedProps.onChange) {
                    console.log('🎯 Найдена React onChange в fiber');
                    reactFiber.memoizedProps.onChange({
                        target: { 
                            value: value,
                            name: textarea.name
                        },
                        currentTarget: textarea,
                        type: 'change'
                    });
                    onChangeFound = true;
                }
            }
            
            // 3. Ищем onChange в событиях элемента
            if (!onChangeFound && textarea._events && textarea._events.change) {
                console.log('🎯 Найдена onChange в _events');
                textarea._events.change({ target: { value: value } });
                onChangeFound = true;
            }
            
            // 4. Попытка обновить React Hook состояние напрямую
            if (!onChangeFound) {
                const reactFiber = textarea._reactInternalFiber || 
                                  textarea._reactInternalInstance ||
                                  Object.keys(textarea).find(key => key.startsWith('__reactFiber')) && textarea[Object.keys(textarea).find(key => key.startsWith('__reactFiber'))];
                
                if (reactFiber) {
                    let currentFiber = reactFiber;
                    while (currentFiber) {
                        if (currentFiber.memoizedState) {
                            let hook = currentFiber.memoizedState;
                            while (hook) {
                                if (hook.queue && hook.queue.dispatch && typeof hook.queue.dispatch === 'function') {
                                    console.log('🎯 Найден useState hook, обновляем состояние');
                                    hook.queue.dispatch(value);
                                    onChangeFound = true;
                                    break;
                                }
                                hook = hook.next;
                            }
                            if (onChangeFound) break;
                        }
                        currentFiber = currentFiber.return;
                    }
                }
            }
            
            // 5. Эмулируем пользовательский ввод с правильными объектами событий
            const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
            nativeInputValueSetter.call(textarea, value);
            
            const inputEvent = new Event('input', { bubbles: true });
            const changeEvent = new Event('change', { bubbles: true });
            
            // Добавляем target.value к событиям
            Object.defineProperty(inputEvent, 'target', {
                writable: false,
                value: { value: value, name: textarea.name }
            });
            Object.defineProperty(changeEvent, 'target', {
                writable: false,
                value: { value: value, name: textarea.name }
            });
            
            // Фокусируемся на поле
            textarea.focus();
            
            // Диспатчим события
            textarea.dispatchEvent(inputEvent);
            textarea.dispatchEvent(changeEvent);
            
            // Убираем фокус
            textarea.blur();
            
            // Также через jQuery для совместимости с детальными данными события
            $(textarea).val(value);
            $(textarea).trigger({
                type: 'input',
                target: { value: value, name: textarea.name }
            });
            $(textarea).trigger({
                type: 'change', 
                target: { value: value, name: textarea.name }
            });
            
            // 6. Последняя попытка - симуляция клавиатурного ввода
            if (!onChangeFound) {
                console.log('🎯 Пробуем симуляцию клавиатурного ввода');
                
                // Очищаем поле и эмулируем набор текста
                textarea.value = '';
                textarea.focus();
                
                // Эмулируем последовательный ввод символов
                for (let i = 0; i < value.length; i++) {
                    textarea.value += value[i];
                    const keyEvent = new KeyboardEvent('keydown', {
                        key: value[i],
                        bubbles: true,
                        cancelable: true
                    });
                    const inputEventChar = new Event('input', { bubbles: true });
                    
                    textarea.dispatchEvent(keyEvent);
                    textarea.dispatchEvent(inputEventChar);
                }
                
                // Финальные события
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
                textarea.blur();
            }
            
            if (onChangeFound) {
                console.log(`✅ React onChange вызвана для: "${value}"`);
            } else {
                console.log(`⚠️ React onChange не найдена, использована симуляция ввода для: "${value}"`);
            }
        });
    }
    
    // Функция для заполнения textarea полей
    function fillTextareaFields(deliveryType, deliveryInfo = null) {
        console.log('📝 Заполняем поля для типа доставки:', deliveryType);
        
        const sdekField = $('.wp-block-checkout-fields-for-blocks-textarea.sdek textarea');
        const managerField = $('.wp-block-checkout-fields-for-blocks-textarea.manag textarea');
        
        console.log('Найдено полей СДЭК:', sdekField.length);
        console.log('Найдено полей Менеджер:', managerField.length);
        
        if (deliveryType === 'manager') {
            window.currentDeliveryData.dostavka = '';
            window.currentDeliveryData.manager = 'Доставка менеджером';
            
            // Эмулируем пользовательский ввод
            simulateUserInput(sdekField, '');
            simulateUserInput(managerField, 'Доставка менеджером');
            
        } else if (deliveryType === 'cdek') {
            let cdekText = '';
            
            // Получаем информацию о выбранном пункте СДЭК
            const selectedPoint = getSelectedCdekPoint();
            
            // Если получили информацию из DOM (блока итогов)
            if (selectedPoint && selectedPoint.code === 'from_dom') {
                cdekText = selectedPoint.name;
                if (selectedPoint.price) {
                    cdekText += ' - ' + selectedPoint.price;
                }
                if (selectedPoint.address) {
                    cdekText += '\nАдрес: ' + selectedPoint.address;
                }
            } else {
                // Стандартная логика для deliveryInfo
                if (deliveryInfo && deliveryInfo.label) {
                    cdekText += deliveryInfo.label;
                }
                
                if (deliveryInfo && deliveryInfo.price) {
                    cdekText += ' - ' + deliveryInfo.price;
                }
                
                // Добавляем информацию о пункте СДЭК если есть
                if (selectedPoint) {
                    cdekText += '\nПункт выдачи: ' + selectedPoint.name;
                    if (selectedPoint.address) {
                        cdekText += '\nАдрес: ' + selectedPoint.address;
                    }
                }
            }
            
            // Если текст пустой, пытаемся получить базовую информацию
            if (!cdekText.trim()) {
                cdekText = 'Доставка СДЭК';
                
                // Пытаемся найти цену доставки в DOM
                const deliveryCostElement = $('.wc-block-components-totals-item__value');
                deliveryCostElement.each(function() {
                    const costText = $(this).text().trim();
                    if (costText.includes('руб') && $(this).closest('.wc-block-components-totals-item').find('.wc-block-components-totals-item__description').length) {
                        cdekText += ' - ' + costText;
                        return false; // break
                    }
                });
            }
            
            console.log('📦 Заполняем СДЭК поле текстом:', cdekText);
            
            window.currentDeliveryData.dostavka = cdekText;
            window.currentDeliveryData.manager = '';
            
            // Эмулируем пользовательский ввод
            simulateUserInput(managerField, '');
            simulateUserInput(sdekField, cdekText);
        } else {
            console.log('⚠️ Неизвестный тип доставки или нет данных');
        }
    }
    
    // Функция для получения информации о выбранном пункте СДЭК
    function getSelectedCdekPoint() {
        try {
            // 1. ПРИОРИТЕТ: Пытаемся получить из блока с информацией о выбранном ПВЗ в DOM (самые актуальные данные)
            const shippingBlock = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            if (shippingBlock.length) {
                console.log('📦 Найдено блоков доставки в DOM:', shippingBlock.length);
                let foundPoint = null;
                shippingBlock.each(function() {
                    const label = $(this).find('.wc-block-components-totals-item__label').text().trim();
                    const value = $(this).find('.wc-block-components-totals-item__value').text().trim();
                    const description = $(this).find('.wc-block-components-totals-item__description small').text().trim();
                    
                    console.log('📦 Проверяем блок:', { label, value, description });
                    console.log('📦 Проверяем содержимое элементов:');
                    console.log('  - label элемент:', $(this).find('.wc-block-components-totals-item__label'));
                    console.log('  - value элемент:', $(this).find('.wc-block-components-totals-item__value'));
                    console.log('  - description элемент:', $(this).find('.wc-block-components-totals-item__description small'));
                    
                    // Проверяем, что это информация о ПВЗ (содержит адрес с Россией)
                    if (description && description.includes('Россия')) {
                        foundPoint = {
                            name: label,
                            price: value,
                            address: description,
                            code: 'from_dom'
                        };
                        console.log('📦 Найден ПВЗ в DOM:', foundPoint);
                        return false; // break из each
                    }
                });
                if (foundPoint) {
                    return foundPoint;
                }
            }
            
            // 2. Проверяем скрытые поля формы
            const pointDataField = $('#cdek-selected-point-data');
            if (pointDataField.length && pointDataField.val()) {
                const pointData = JSON.parse(pointDataField.val());
                console.log('📦 Получен ПВЗ из скрытого поля:', pointData);
                return {
                    code: pointData.code,
                    name: pointData.name,
                    address: pointData.location && pointData.location.address ? pointData.location.address : '',
                    city: pointData.location && pointData.location.city ? pointData.location.city : ''
                };
            }
            
            // 3. Проверяем localStorage
            const storedPoint = localStorage.getItem('selectedCdekPoint');
            if (storedPoint) {
                console.log('📦 Получен ПВЗ из localStorage:', JSON.parse(storedPoint));
                return JSON.parse(storedPoint);
            }
            
            // 4. Проверяем глобальную переменную
            if (window.selectedCdekPoint) {
                console.log('📦 Получен ПВЗ из window.selectedCdekPoint:', window.selectedCdekPoint);
                return window.selectedCdekPoint;
            }
            
            return null;
        } catch (e) {
            console.log('Ошибка получения информации о пункте СДЭК:', e);
            return null;
        }
    }
    
    // Функция для определения текущего типа доставки
    function getCurrentDeliveryType() {
        // Проверяем, выбрана ли доставка с менеджером
        const discussSelected = $('#discuss_selected').val();
        if (discussSelected === '1') {
            return 'manager';
        }
        
        // Проверяем активную вкладку доставки
        const activeTab = $('.wc-block-checkout__shipping-method-option--selected');
        if (activeTab.length) {
            const titleText = activeTab.find('.wc-block-checkout__shipping-method-option-title').text();
            
            if (titleText.includes('менеджером') || titleText.includes('Обсудить')) {
                return 'manager';
            } else if (titleText.includes('СДЭК') || titleText.includes('Доставка')) {
                return 'cdek';
            }
        }
        
        // Проверяем выбранный метод доставки через радиокнопки
        const selectedShipping = $('input[name^="radio-control-wc-shipping-method"]:checked');
        if (selectedShipping.length) {
            const shippingValue = selectedShipping.val();
            if (shippingValue && shippingValue.includes('cdek')) {
                return 'cdek';
            }
        }
        
        return null;
    }
    
    // Функция для получения информации о доставке
    function getDeliveryInfo() {
        const activeTab = $('.wc-block-checkout__shipping-method-option--selected');
        if (activeTab.length) {
            const label = activeTab.find('.wc-block-checkout__shipping-method-option-title').text().trim();
            const priceElement = activeTab.find('.wc-block-formatted-money-amount');
            const price = priceElement.length ? priceElement.text().trim() : '';
            
            return {
                label: label,
                price: price,
                description: ''
            };
        }
        
        return null;
    }
    
    // Основная функция для обновления полей
    function updateTextareaFields() {
        console.log('🔄 Обновляем textarea поля');
        
        const deliveryType = getCurrentDeliveryType();
        if (!deliveryType) {
            console.log('❓ Тип доставки не определен');
            return;
        }
        
        console.log('📦 Определен тип доставки:', deliveryType);
        
        if (deliveryType === 'manager') {
            fillTextareaFields('manager');
        } else if (deliveryType === 'cdek') {
            const deliveryInfo = getDeliveryInfo();
            fillTextareaFields('cdek', deliveryInfo);
        }
    }
    
    // Наблюдатель за изменениями в блоке доставки
    function observeShippingBlock() {
        const targetNode = document.querySelector('.wc-block-checkout__shipping-method, .wp-block-woocommerce-checkout-shipping-methods-block');
        
        if (targetNode) {
            const observer = new MutationObserver(function(mutationsList) {
                for (let mutation of mutationsList) {
                    if (mutation.type === 'childList' || mutation.type === 'attributes') {
                        console.log('👁️ Обнаружены изменения в блоке доставки');
                        debouncedUpdate();
                        break;
                    }
                }
            });
            
            observer.observe(targetNode, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'aria-checked', 'checked']
            });
            
            console.log('👁️ Наблюдатель за блоком доставки установлен');
        }
    }
    
    // Слушаем изменения в localStorage (для синхронизации с другими скриптами)
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedCdekPoint') {
            console.log('💾 Изменения в localStorage, обновляем поля');
            debouncedUpdate();
        }
    });
    
    // Слушаем изменения радиокнопок доставки
    $(document).on('change', 'input[name^="radio-control-wc-shipping-method"]', function() {
        console.log('📻 Изменение метода доставки');
        debouncedUpdate();
    });
    
    // Слушаем клики по опциям доставки
    $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
        console.log('🖱️ Клик по опции доставки');
        debouncedUpdate();
    });
    
    // Слушаем изменения в скрытых полях СДЭК
    $(document).on('DOMNodeInserted', function(e) {
        if (e.target.id === 'cdek-selected-point-data' || e.target.id === 'cdek-selected-point-code') {
            console.log('📦 Обнаружены изменения в данных ПВЗ СДЭК');
            debouncedUpdate();
        }
    });
    
    // Наблюдаем за изменениями в блоке итогов заказа (где отображается информация о ПВЗ)
    function observeTotalsBlock() {
        const totalsBlock = document.querySelector('.wc-block-components-totals-wrapper, .wc-block-checkout__totals-wrapper');
        if (totalsBlock) {
            const observer = new MutationObserver(function(mutationsList) {
                for (let mutation of mutationsList) {
                    // Отслеживаем изменения содержимого и добавление новых элементов
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        // Проверяем, есть ли изменения в блоке доставки
                        const target = mutation.target;
                        const isShippingRelated = target.closest && target.closest('.wc-block-components-totals-shipping') ||
                                                 target.classList && target.classList.contains('wc-block-components-totals-shipping') ||
                                                 target.querySelector && target.querySelector('.wc-block-components-totals-shipping');
                        
                        if (isShippingRelated) {
                            console.log('📦 Обнаружены изменения в блоке доставки');
                            debouncedUpdate();
                            continue;
                        }
                        
                        // Проверяем добавленные узлы
                        if (mutation.addedNodes) {
                            const addedNodes = Array.from(mutation.addedNodes);
                            const hasShippingInfo = addedNodes.some(node => 
                                node.nodeType === 1 && 
                                (node.classList && node.classList.contains('wc-block-components-totals-shipping') ||
                                 node.querySelector && node.querySelector('.wc-block-components-totals-shipping'))
                            );
                            
                            if (hasShippingInfo) {
                                console.log('📦 Обнаружена информация о доставке/ПВЗ в блоке итогов');
                                debouncedUpdate();
                            }
                        }
                    }
                }
            });
            
            observer.observe(totalsBlock, {
                childList: true,
                subtree: true,
                characterData: true,
                characterDataOldValue: true
            });
            
            console.log('👁️ Наблюдатель за блоком итогов установлен');
        }
        
        // Дополнительный наблюдатель конкретно за блоком доставки
        const shippingBlock = document.querySelector('.wc-block-components-totals-shipping');
        if (shippingBlock) {
            const shippingObserver = new MutationObserver(function(mutationsList) {
                for (let mutation of mutationsList) {
                    console.log('📦 Изменение в блоке доставки, тип:', mutation.type);
                    debouncedUpdate();
                    break; // Достаточно одного срабатывания
                }
            });
            
            shippingObserver.observe(shippingBlock, {
                childList: true,
                subtree: true,
                characterData: true,
                attributes: true
            });
            
            console.log('👁️ Прямой наблюдатель за блоком доставки установлен');
        }
    }
    
    // Инициализация
    setTimeout(function() {
        updateTextareaFields();
        observeShippingBlock();
        observeTotalsBlock();
        console.log('✅ Автозаполнение готово (только React эмуляция)');
    }, 1000);
    
    // Делаем функции доступными глобально для отладки
    window.updateTextareaFields = updateTextareaFields;
    window.fillTextareaFields = fillTextareaFields;
    window.getCurrentDeliveryType = getCurrentDeliveryType;
    window.simulateUserInput = simulateUserInput;
    window.updateReactInputState = updateReactInputState;
    
    // Функция для ручного тестирования
    window.testTextareaFill = function(value) {
        const managerField = $('.wp-block-checkout-fields-for-blocks-textarea.manag textarea');
        if (managerField.length) {
            simulateUserInput(managerField, value || 'Тест заполнения');
        } else {
            console.log('Поле менеджера не найдено');
        }
    };
    
    // Функция для принудительного обновления (для отладки)
    window.forceUpdateTextarea = function() {
        console.log('🔄 Принудительное обновление автозаполнения...');
        updateTextareaFields();
    };
    
    // Функция для отладки получения данных ПВЗ
    window.debugCdekPoint = function() {
        console.log('🔍 Отладка получения данных ПВЗ:');
        console.log('- localStorage:', localStorage.getItem('selectedCdekPoint'));
        console.log('- window.selectedCdekPoint:', window.selectedCdekPoint);
        console.log('- скрытое поле:', $('#cdek-selected-point-data').val());
        console.log('- блоки итогов:', $('.wc-block-components-totals-item').length);
        console.log('- блоки доставки:', $('.wc-block-components-totals-shipping .wc-block-components-totals-item').length);
        
        // Проверяем, что есть в DOM
        $('.wc-block-components-totals-shipping .wc-block-components-totals-item').each(function(index) {
            const label = $(this).find('.wc-block-components-totals-item__label').text().trim();
            const value = $(this).find('.wc-block-components-totals-item__value').text().trim();
            const description = $(this).find('.wc-block-components-totals-item__description small').text().trim();
            console.log(`📦 Блок ${index + 1}:`, { label, value, description });
        });
        
        const point = getSelectedCdekPoint();
        console.log('- результат getSelectedCdekPoint():', point);
        
        const deliveryType = getCurrentDeliveryType();
        console.log('- текущий тип доставки:', deliveryType);
    };
    
    console.log('🎯 Автозаполнение - только эмуляция пользовательского ввода');
});