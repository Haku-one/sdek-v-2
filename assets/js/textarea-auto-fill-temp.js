jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей инициализировано (упрощенная версия)');
    
    // Переменные для контроля
    let updateTimeout;
    let lastAPIUpdateTime = 0;
    let lastAPIUpdateData = { dostavka: '', manager: '' };
    
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
    
    // Простая функция для обновления API (с защитой от циклов)
    function updateCheckoutFieldsAPI() {
        const now = Date.now();
        if (now - lastAPIUpdateTime < 2000) { // Не чаще раза в 2 секунды
            return;
        }
        
        const currentData = { 
            dostavka: window.currentDeliveryData?.dostavka || '',
            manager: window.currentDeliveryData?.manager || ''
        };
        
        if (JSON.stringify(currentData) === JSON.stringify(lastAPIUpdateData)) {
            return; // Данные не изменились
        }
        
        if (window.wp && window.wp.data) {
            try {
                const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
                if (checkoutStore && checkoutStore.setExtensionData) {
                    checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_dostavka', currentData.dostavka);
                    checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_manager', currentData.manager);
                    console.log('🔄 API обновлено:', currentData);
                    
                    lastAPIUpdateTime = now;
                    lastAPIUpdateData = { ...currentData };
                }
            } catch (e) {
                console.log('Ошибка API:', e.message);
            }
        }
    }
    
    // Простое заполнение полей без симуляции печати
    function fillTextareaFields(deliveryType, deliveryInfo = null) {
        console.log('📝 Заполняем поля для:', deliveryType);
        
        const sdekField = $('.wp-block-checkout-fields-for-blocks-textarea.sdek textarea');
        const managerField = $('.wp-block-checkout-fields-for-blocks-textarea.manag textarea');
        
        if (deliveryType === 'manager') {
            window.currentDeliveryData.dostavka = '';
            window.currentDeliveryData.manager = 'Доставка менеджером';
            
            // Простое заполнение без лишних событий
            sdekField.val('').trigger('change');
            managerField.val('Доставка менеджером').trigger('change');
            
            updateCheckoutFieldsAPI();
            
        } else if (deliveryType === 'cdek' && deliveryInfo) {
            let cdekText = deliveryInfo.label || '';
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
            
            managerField.val('').trigger('change');
            sdekField.val(cdekText).trigger('change');
            
            updateCheckoutFieldsAPI();
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
            return null;
        }
    }
    
    // Функция для определения текущего типа доставки
    function getCurrentDeliveryType() {
        const discussSelected = $('#discuss_selected').val();
        if (discussSelected === '1') {
            return 'manager';
        }
        
        const activeTab = $('.wc-block-checkout__shipping-method-option--selected');
        if (activeTab.length) {
            const titleText = activeTab.find('.wc-block-checkout__shipping-method-option-title').text();
            if (titleText.includes('менеджером') || titleText.includes('Обсудить')) {
                return 'manager';
            } else if (titleText.includes('СДЭК') || titleText.includes('Доставка')) {
                return 'cdek';
            }
        }
        
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
        const deliveryType = getCurrentDeliveryType();
        if (!deliveryType) {
            return;
        }
        
        if (deliveryType === 'manager') {
            fillTextareaFields('manager');
        } else if (deliveryType === 'cdek') {
            const deliveryInfo = getDeliveryInfo();
            fillTextareaFields('cdek', deliveryInfo);
        }
    }
    
    // Перехват отправки формы для гарантированной передачи данных
    function interceptFormSubmission() {
        $(document).on('submit', 'form', function(e) {
            console.log('📤 Отправка формы - синхронизируем данные');
            
            // Принудительная синхронизация перед отправкой
            if (window.wp && window.wp.data) {
                try {
                    const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
                    if (checkoutStore && checkoutStore.setExtensionData) {
                        checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_dostavka', window.currentDeliveryData.dostavka);
                        checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_manager', window.currentDeliveryData.manager);
                        console.log('🔄 Данные синхронизированы перед отправкой');
                    }
                } catch (e) {
                    console.log('Ошибка синхронизации:', e);
                }
            }
        });
    }
    
    // Наблюдатель за изменениями
    function observeShippingBlock() {
        const targetNode = document.querySelector('.wc-block-checkout__shipping-method, .wp-block-woocommerce-checkout-shipping-methods-block');
        
        if (targetNode) {
            const observer = new MutationObserver(function(mutationsList) {
                for (let mutation of mutationsList) {
                    if (mutation.type === 'childList' || mutation.type === 'attributes') {
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
        }
    }
    
    // Слушаем изменения в localStorage
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedCdekPoint') {
            debouncedUpdate();
        }
    });
    
    // Инициализация
    setTimeout(function() {
        interceptFormSubmission();
        updateTextareaFields();
        observeShippingBlock();
        console.log('✅ Автозаполнение готово (упрощенная версия)');
    }, 1000);
    
    // Глобальные функции для отладки
    window.updateTextareaFields = updateTextareaFields;
    window.fillTextareaFields = fillTextareaFields;
    window.getCurrentDeliveryType = getCurrentDeliveryType;
    
    console.log('🎯 Автозаполнение textarea полей инициализировано (упрощенная версия)');
});