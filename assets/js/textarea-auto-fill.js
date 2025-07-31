jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей инициализировано');
    
    // Функция для заполнения textarea полей
    function fillTextareaFields(deliveryType, deliveryInfo = null) {
        console.log('📝 Заполняем textarea поля для типа доставки:', deliveryType);
        
        // Находим поля СДЭК и Менеджер по классам
        const sdekField = $('.wp-block-checkout-fields-for-blocks-textarea.sdek textarea');
        const managerField = $('.wp-block-checkout-fields-for-blocks-textarea.manag textarea');
        
        console.log('Найдено полей СДЭК:', sdekField.length);
        console.log('Найдено полей Менеджер:', managerField.length);
        
        // Очищаем все поля сначала
        sdekField.val('');
        managerField.val('');
        
        if (deliveryType === 'manager') {
            // Заполняем поле менеджера
            const managerText = 'Доставка менеджером';
            managerField.val(managerText);
            managerField.trigger('change');
            managerField.trigger('input');
            console.log('✅ Заполнено поле менеджера:', managerText);
            
        } else if (deliveryType === 'cdek' && deliveryInfo) {
            // Заполняем поле СДЭК информацией о выбранном варианте
            let cdekText = '';
            
            if (deliveryInfo.label) {
                cdekText += deliveryInfo.label;
            }
            
            if (deliveryInfo.price) {
                cdekText += ' - ' + deliveryInfo.price;
            }
            
            if (deliveryInfo.description) {
                cdekText += ' (' + deliveryInfo.description + ')';
            }
            
            // Если есть информация о выбранном пункте СДЭК
            const selectedPoint = getSelectedCdekPoint();
            if (selectedPoint) {
                cdekText += '\nПункт выдачи: ' + selectedPoint.name;
                if (selectedPoint.address) {
                    cdekText += '\nАдрес: ' + selectedPoint.address;
                }
            }
            
            sdekField.val(cdekText);
            sdekField.trigger('change');
            sdekField.trigger('input');
            console.log('✅ Заполнено поле СДЭК:', cdekText);
        }
    }
    
    // Функция для получения информации о выбранном пункте СДЭК
    function getSelectedCdekPoint() {
        try {
            // Проверяем localStorage
            const storedPoint = localStorage.getItem('selectedCdekPoint');
            if (storedPoint) {
                return JSON.parse(storedPoint);
            }
            
            // Проверяем скрытые поля
            const pointCode = $('input[name="cdek_selected_point_code"]').val();
            const pointName = $('input[name="cdek_selected_point_name"]').val();
            const pointAddress = $('input[name="cdek_selected_point_address"]').val();
            
            if (pointCode || pointName) {
                return {
                    code: pointCode,
                    name: pointName,
                    address: pointAddress
                };
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
    
    // Функция для получения информации о доставке из блока
    function getDeliveryInfo() {
        const shippingBlock = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
        
        if (shippingBlock.length) {
            const label = shippingBlock.find('.wc-block-components-totals-item__label').text().trim();
            const price = shippingBlock.find('.wc-block-components-totals-item__value').text().trim();
            const description = shippingBlock.find('.wc-block-components-totals-item__description').text().trim();
            
            return {
                label: label,
                price: price,
                description: description
            };
        }
        
        return null;
    }
    
    // Основная функция обновления полей
    function updateTextareaFields() {
        const deliveryType = getCurrentDeliveryType();
        const deliveryInfo = getDeliveryInfo();
        
        console.log('🔄 Обновление textarea полей. Тип доставки:', deliveryType);
        
        if (deliveryType) {
            fillTextareaFields(deliveryType, deliveryInfo);
        }
    }
    
    // Слушаем изменения в методах доставки (блочный чекаут)
    $(document).on('change', 'input[name^="radio-control-wc-shipping-method"]', function() {
        console.log('📻 Изменен метод доставки (блочный чекаут)');
        setTimeout(updateTextareaFields, 500);
    });
    
    // Слушаем клики по вкладкам доставки
    $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
        console.log('🖱️ Клик по вкладке доставки');
        setTimeout(updateTextareaFields, 300);
    });
    
    // Слушаем изменения в классическом чекауте
    $(document).on('change', 'input[name^="shipping_method"]', function() {
        console.log('📻 Изменен метод доставки (классический чекаут)');
        setTimeout(updateTextareaFields, 300);
    });
    
    // Слушаем события обновления чекаута
    $(document).on('updated_checkout checkout_updated', function() {
        console.log('🔄 Событие обновления чекаута');
        setTimeout(updateTextareaFields, 500);
    });
    
    // Слушаем изменения в блоке доставки
    function observeShippingBlock() {
        const shippingBlock = document.querySelector('.wp-block-woocommerce-checkout-order-summary-shipping-block');
        
        if (shippingBlock) {
            const observer = new MutationObserver(function(mutations) {
                let shouldUpdate = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || 
                        mutation.type === 'characterData' || 
                        (mutation.type === 'attributes' && mutation.attributeName === 'class')) {
                        shouldUpdate = true;
                    }
                });
                
                if (shouldUpdate) {
                    console.log('👁️ Обнаружены изменения в блоке доставки');
                    setTimeout(updateTextareaFields, 200);
                }
            });
            
            observer.observe(shippingBlock, {
                childList: true,
                subtree: true,
                characterData: true,
                attributes: true,
                attributeFilter: ['class']
            });
            
            console.log('👁️ Наблюдатель за блоком доставки активирован');
        } else {
            // Если блок еще не загружен, попробуем позже
            setTimeout(observeShippingBlock, 1000);
        }
    }
    
    // Слушаем изменения в localStorage (для синхронизации с другими скриптами)
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedCdekPoint' || e.key === 'cdek_delivery_manager') {
            console.log('💾 Изменения в localStorage, обновляем поля');
            setTimeout(updateTextareaFields, 100);
        }
    });
    
    // Инициализация
    setTimeout(function() {
        updateTextareaFields();
        observeShippingBlock();
        console.log('✅ Автозаполнение textarea полей готово к работе');
    }, 1000);
    
    // Периодическая проверка (каждые 3 секунды)
    setInterval(function() {
        if ($('.wp-block-woocommerce-checkout').length) {
            updateTextareaFields();
        }
    }, 3000);
    
    // Делаем функции доступными глобально для отладки
    window.updateTextareaFields = updateTextareaFields;
    window.fillTextareaFields = fillTextareaFields;
    window.getCurrentDeliveryType = getCurrentDeliveryType;
    
    console.log('🎯 Автозаполнение textarea полей полностью инициализировано');
});