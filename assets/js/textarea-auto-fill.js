jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей инициализировано');
    
    // Дебаунсинг для предотвращения частых вызовов
    let updateTimeout;
    function debouncedUpdate() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updateTextareaFields, 500);
    }
    
    // Функция для создания скрытых полей если их нет
    function ensureHiddenFields() {
        const form = $('form.wc-block-checkout__form, form.checkout, form').first();
        
        if (!$('input[name="dostavka"]').length && !$('textarea[name="dostavka"]').length) {
            const hiddenDostavka = $('<input type="hidden" name="dostavka" value="">');
            form.append(hiddenDostavka);
            console.log('✅ Создано скрытое поле dostavka');
        }
        
        if (!$('input[name="manager"]').length && !$('textarea[name="manager"]').length) {
            const hiddenManager = $('<input type="hidden" name="manager" value="">');
            form.append(hiddenManager);
            console.log('✅ Создано скрытое поле manager');
        }
    }
    
    // Функция для симуляции реального ввода посимвольно
    function simulateTyping(element, text) {
        return new Promise((resolve) => {
            // Очищаем поле
            element.value = '';
            element.focus();
            
            let index = 0;
            const typeChar = () => {
                if (index < text.length) {
                    element.value += text[index];
                    
                    // Эмулируем события для каждого символа
                    element.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: text[index] }));
                    element.dispatchEvent(new Event('input', { bubbles: true }));
                    element.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: text[index] }));
                    
                    index++;
                    setTimeout(typeChar, 10); // Небольшая задержка между символами
                } else {
                    element.dispatchEvent(new Event('change', { bubbles: true }));
                    element.blur();
                    resolve();
                }
            };
            
            setTimeout(typeChar, 50);
        });
    }
    
    // Универсальная функция для заполнения поля
    function fillField(field, value, useTypingSimulation = false) {
        if (!field.length) return;
        
        const currentValue = field.val();
        if (currentValue === value) {
            console.log('ℹ️ Поле уже содержит нужное значение:', value);
            return;
        }
        
        // Если включена симуляция набора и это не пустое значение
        if (useTypingSimulation && value) {
            console.log('🎯 Используем симуляцию набора для:', value);
            field.each(async function() {
                await simulateTyping(this, value);
            });
            return;
        }
        
        // Заполняем поле
        field.val(value);
        
        // Эмулируем пользовательский ввод для каждого элемента
        field.each(function() {
            // Сохраняем ссылку на элемент
            const element = this;
            
            // Устанавливаем значение разными способами
            element.value = value;
            element.defaultValue = value;
            $(element).val(value);
            
            // Отмечаем как измененное
            element.setAttribute('data-dirty', 'true');
            element.setAttribute('data-filled', 'true');
            element.setAttribute('aria-invalid', 'false');
            
            // Меняем placeholder если он "Не выбрано"
            if (element.placeholder === 'Не выбрано') {
                element.placeholder = '';
            }
            
            // Убираем класс ошибок если есть
            $(element).removeClass('has-error wc-invalid');
            
            // Принудительно отмечаем поле как "touched" для React форм
            if (element._valueTracker) {
                element._valueTracker.setValue('');
            }
            
            // Эмулируем полную последовательность событий как при реальном вводе
            const events = [
                new Event('focus', { bubbles: true, cancelable: true }),
                new Event('focusin', { bubbles: true, cancelable: true }),
                new KeyboardEvent('keydown', { bubbles: true, cancelable: true, key: 'a' }),
                new Event('input', { bubbles: true, cancelable: true }),
                new KeyboardEvent('keyup', { bubbles: true, cancelable: true, key: 'a' }),
                new Event('change', { bubbles: true, cancelable: true }),
                new Event('blur', { bubbles: true, cancelable: true }),
                new Event('focusout', { bubbles: true, cancelable: true })
            ];
            
            // Диспатчим события с небольшими задержками
            events.forEach((event, index) => {
                setTimeout(() => {
                    element.dispatchEvent(event);
                }, index * 10);
            });
        });
        
        // Дополнительные jQuery события с задержкой
        setTimeout(() => {
            field.trigger('focus').trigger('input').trigger('change').trigger('blur');
            
            // Уведомляем форму
            const form = field.closest('form');
            if (form.length) {
                form.trigger('change');
            }
            
            // Пытаемся уведомить React/WooCommerce о изменениях
            field.each(function() {
                // Для React компонентов
                const reactProps = Object.keys(this).find(key => key.startsWith('__reactProps'));
                if (reactProps && this[reactProps] && this[reactProps].onChange) {
                    this[reactProps].onChange({ target: { value: value } });
                }
                
                // Для WooCommerce блоков
                if (window.wp && window.wp.data) {
                    try {
                        const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
                        if (checkoutStore && checkoutStore.setExtensionData) {
                            const fieldName = this.name || (this.className.includes('sdek') ? 'dostavka' : 'manager');
                            checkoutStore.setExtensionData('checkout-fields', {
                                [fieldName]: value
                            });
                        }
                    } catch (e) {
                        console.log('Не удалось обновить WooCommerce store:', e);
                    }
                }
            });
        }, 100);
        
        console.log('✅ Заполнено поле значением:', value);
    }
    
    // Функция для заполнения textarea полей
    function fillTextareaFields(deliveryType, deliveryInfo = null) {
        console.log('📝 Заполняем textarea поля для типа доставки:', deliveryType);
        
        // Убеждаемся что скрытые поля существуют
        ensureHiddenFields();
        
        // Находим поля по именам полей
        const sdekField = $('textarea[name="dostavka"], input[name="dostavka"], .wp-block-checkout-fields-for-blocks-textarea.sdek textarea');
        const managerField = $('textarea[name="manager"], input[name="manager"], .wp-block-checkout-fields-for-blocks-textarea.manag textarea');
        
        console.log('Найдено полей СДЭК:', sdekField.length, sdekField);
        console.log('Найдено полей Менеджер:', managerField.length, managerField);
        
        // Отладочная информация о найденных полях
        sdekField.each(function(i) {
            console.log(`СДЭК поле ${i}:`, this.name, this.type, $(this).attr('class'));
        });
        managerField.each(function(i) {
            console.log(`Менеджер поле ${i}:`, this.name, this.type, $(this).attr('class'));
        });
        
        if (deliveryType === 'manager') {
            // Очищаем поле доставки и заполняем поле менеджера
            fillField(sdekField, '');
            fillField(managerField, 'Доставка менеджером', true); // Используем симуляцию набора
            
        } else if (deliveryType === 'cdek' && deliveryInfo) {
            // Формируем текст для поля СДЭК
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
            
            // Очищаем поле менеджера и заполняем поле доставки
            fillField(managerField, '');
            fillField(sdekField, cdekText, true); // Используем симуляцию набора
        } else {
            console.log('⚠️ Неизвестный тип доставки или нет данных, поля не изменяются');
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
            
            // Проверяем глобальные переменные из основного скрипта
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
        } else {
            console.log('⚠️ Тип доставки не определен, пропускаем обновление');
        }
    }
    
    // Слушаем изменения в методах доставки (блочный чекаут)
    $(document).on('change', 'input[name^="radio-control-wc-shipping-method"]', function() {
        console.log('📻 Изменен метод доставки (блочный чекаут)');
        debouncedUpdate();
    });
    
    // Слушаем клики по вкладкам доставки
    $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
        console.log('🖱️ Клик по вкладке доставки');
        debouncedUpdate();
    });
    
    // Слушаем изменения в классическом чекауте
    $(document).on('change', 'input[name^="shipping_method"]', function() {
        console.log('📻 Изменен метод доставки (классический чекаут)');
        debouncedUpdate();
    });
    
    // Слушаем события обновления чекаута
    $(document).on('updated_checkout checkout_updated', function() {
        console.log('🔄 Событие обновления чекаута');
        debouncedUpdate();
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
                    debouncedUpdate();
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
        if (e.key === 'selectedCdekPoint') {
            console.log('💾 Изменения в localStorage, обновляем поля');
            debouncedUpdate();
        }
    });
    
    // Инициализация
    setTimeout(function() {
        ensureHiddenFields(); // Создаем поля сразу при инициализации
        updateTextareaFields();
        observeShippingBlock();
        console.log('✅ Автозаполнение textarea полей готово к работе');
    }, 1000);
    
    // Периодическая проверка отключена - может мешать отправке данных
    // setInterval(function() {
    //     if ($('.wp-block-woocommerce-checkout').length) {
    //         updateTextareaFields();
    //     }
    // }, 10000);
    
    // Делаем функции доступными глобально для отладки
    window.updateTextareaFields = updateTextareaFields;
    window.fillTextareaFields = fillTextareaFields;
    window.getCurrentDeliveryType = getCurrentDeliveryType;
    
    console.log('🎯 Автозаполнение textarea полей полностью инициализировано');
});