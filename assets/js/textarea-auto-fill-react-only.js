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
    
    // Функция для эмуляции пользовательского ввода в React поле
    function simulateUserInput(element, value) {
        if (!element || !element.length) return;
        
        element.each(function() {
            const textarea = this;
            
            // Устанавливаем значение
            textarea.value = value;
            
            // Получаем React fiber instance
            const reactFiber = textarea._reactInternalFiber || 
                              textarea._reactInternalInstance ||
                              Object.keys(textarea).find(key => key.startsWith('__reactFiber')) && textarea[Object.keys(textarea).find(key => key.startsWith('__reactFiber'))];
            
            // Эмулируем пользовательские события
            const inputEvent = new Event('input', { bubbles: true });
            const changeEvent = new Event('change', { bubbles: true });
            const focusEvent = new Event('focus', { bubbles: true });
            const blurEvent = new Event('blur', { bubbles: true });
            
            // Фокусируемся на поле
            textarea.focus();
            textarea.dispatchEvent(focusEvent);
            
            // Диспатчим события как от пользователя
            textarea.dispatchEvent(inputEvent);
            textarea.dispatchEvent(changeEvent);
            
            // Убираем фокус
            textarea.blur();
            textarea.dispatchEvent(blurEvent);
            
            // Также через jQuery для совместимости
            $(textarea).trigger('focus').trigger('input').trigger('change').trigger('blur');
            
            console.log(`✅ Эмулирован пользовательский ввод: "${value}"`);
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
            
        } else if (deliveryType === 'cdek' && deliveryInfo) {
            let cdekText = '';
            
            if (deliveryInfo.label) {
                cdekText += deliveryInfo.label;
            }
            
            if (deliveryInfo.price) {
                cdekText += ' - ' + deliveryInfo.price;
            }
            
            // Добавляем информацию о пункте СДЭК если есть
            const selectedPoint = getSelectedCdekPoint();
            if (selectedPoint) {
                cdekText += '\nПункт выдачи: ' + selectedPoint.name;
                if (selectedPoint.address) {
                    cdekText += '\nАдрес: ' + selectedPoint.address;
                }
            }
            
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
            const storedPoint = localStorage.getItem('selectedCdekPoint');
            if (storedPoint) {
                return JSON.parse(storedPoint);
            }
            
            if (window.selectedCdekPoint) {
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
    
    // Инициализация
    setTimeout(function() {
        updateTextareaFields();
        observeShippingBlock();
        console.log('✅ Автозаполнение готово (только React эмуляция)');
    }, 1000);
    
    // Делаем функции доступными глобально для отладки
    window.updateTextareaFields = updateTextareaFields;
    window.fillTextareaFields = fillTextareaFields;
    window.getCurrentDeliveryType = getCurrentDeliveryType;
    window.simulateUserInput = simulateUserInput;
    
    console.log('🎯 Автозаполнение - только эмуляция пользовательского ввода');
});