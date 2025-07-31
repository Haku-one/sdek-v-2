jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей инициализировано');
    
    // Инициализируем глобальные данные доставки если их нет
    if (!window.currentDeliveryData) {
        window.currentDeliveryData = {
            dostavka: '',
            manager: ''
        };
        console.log('✅ Инициализированы данные доставки');
    } else {
        console.log('ℹ️ Данные доставки уже существуют:', window.currentDeliveryData);
    }
    
    // Дебаунсинг для предотвращения частых вызовов
    let updateTimeout;
    function debouncedUpdate() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updateTextareaFields, 500);
    }
    
    // Функция для создания скрытых полей если их нет
    function ensureHiddenFields() {
        const form = $('form.wc-block-checkout__form, form.checkout, form').first();
        
        // Для плагина Checkout Fields for Blocks нужны поля с префиксами
        const fieldsToCreate = [
            { name: 'dostavka', metaName: '_meta_dostavka' },
            { name: 'manager', metaName: '_meta_manager' },
            { name: 'checkout_field_dostavka', metaName: 'checkout_field_dostavka' },
            { name: 'checkout_field_manager', metaName: 'checkout_field_manager' },
            { name: 'wc_checkout_field_dostavka', metaName: 'wc_checkout_field_dostavka' },
            { name: 'wc_checkout_field_manager', metaName: 'wc_checkout_field_manager' }
        ];
        
        fieldsToCreate.forEach(field => {
            if (!$(`input[name="${field.name}"]`).length && !$(`textarea[name="${field.name}"]`).length) {
                const hiddenField = $(`<input type="hidden" name="${field.name}" value="">`);
                form.append(hiddenField);
                console.log(`✅ Создано скрытое поле ${field.name}`);
            }
        });
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
                
                // Для WooCommerce блоков и плагина Checkout Fields for Blocks
                if (window.wp && window.wp.data) {
                    try {
                        const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
                        if (checkoutStore && checkoutStore.setExtensionData) {
                            // Определяем metaName на основе поля
                            let metaName = '';
                            if (this.className.includes('sdek') || this.name === 'dostavka') {
                                metaName = '_meta_dostavka';
                            } else if (this.className.includes('manag') || this.name === 'manager') {
                                metaName = '_meta_manager';
                            }
                            
                            if (metaName) {
                                // Устанавливаем данные для плагина Checkout Fields for Blocks
                                checkoutStore.setExtensionData('checkout-fields-for-blocks', metaName, value);
                                console.log(`✅ Установлено через setExtensionData: ${metaName} = ${value}`);
                            }
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
            // Сохраняем значения в глобальную переменную
            window.currentDeliveryData.dostavka = '';
            window.currentDeliveryData.manager = 'Доставка менеджером';
            
            // Обновляем через API плагина
            updateCheckoutFieldsForBlocksAPI();
            
            // Очищаем поле доставки и заполняем поле менеджера
            fillField(sdekField, '');
            fillField(managerField, 'Доставка менеджером', true);
            
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
            
            // Сохраняем значения в глобальную переменную
            window.currentDeliveryData.dostavka = cdekText;
            window.currentDeliveryData.manager = '';
            
            // Обновляем через API плагина
            updateCheckoutFieldsForBlocksAPI();
            
            // Очищаем поле менеджера и заполняем поле доставки
            fillField(managerField, '');
            fillField(sdekField, cdekText, true);
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
    
    // Глобальные переменные для хранения текущих значений
    window.currentDeliveryData = {
        dostavka: '',
        manager: ''
    };
    
    // Функция для перехвата отправки формы
    function interceptFormSubmission() {
        // Перехватываем все формы на странице
        $(document).on('submit', 'form', function(e) {
            console.log('📤 Перехват отправки формы');
            console.log('🎯 Текущие данные доставки:', window.currentDeliveryData);
            
            // Находим ВСЕ поля, которые могут быть связаны с доставкой
            const allFields = $('input, textarea, select').filter(function() {
                const name = this.name || '';
                const id = this.id || '';
                const className = this.className || '';
                
                return name.includes('dostavka') || name.includes('manager') || 
                       id.includes('dostavka') || id.includes('manager') ||
                       className.includes('sdek') || className.includes('manag');
            });
            
            console.log('🔍 Найдено всех связанных полей:', allFields.length);
            allFields.each(function(i) {
                console.log(`Поле ${i}:`, this.name, this.id, this.value, this.className);
            });
            
            // Принудительно устанавливаем значения
            const dostavkaField = $('textarea[name="dostavka"], input[name="dostavka"]');
            const managerField = $('textarea[name="manager"], input[name="manager"]');
            
            if (window.currentDeliveryData.dostavka) {
                dostavkaField.val(window.currentDeliveryData.dostavka);
                console.log('📝 Установлено значение dostavka:', window.currentDeliveryData.dostavka);
            }
            
            if (window.currentDeliveryData.manager) {
                managerField.val(window.currentDeliveryData.manager);
                console.log('📝 Установлено значение manager:', window.currentDeliveryData.manager);
            }
            
            // Проверяем итоговые значения всех полей
            console.log('📋 Итоговые значения полей:');
            allFields.each(function() {
                if (this.value) {
                    console.log(`${this.name || this.id}: ${this.value}`);
                }
            });
        });
        
        // Перехватываем AJAX отправки WooCommerce
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url && (settings.url.includes('wc-store/checkout') || settings.url.includes('checkout'))) {
                console.log('📤 Перехват AJAX отправки чекаута');
                console.log('🌐 URL:', settings.url);
                console.log('📦 Исходные данные:', settings.data);
                console.log('🎯 Текущие данные доставки:', window.currentDeliveryData);
                
                // Модифицируем данные перед отправкой
                if (settings.data) {
                    try {
                        // Сохраняем оригинальные данные для восстановления в случае ошибки
                        const originalData = settings.data;
                        let modifiedData = settings.data;
                        
                        if (typeof settings.data === 'string') {
                            // Проверяем, является ли это JSON
                            if (settings.data.trim().startsWith('{')) {
                                try {
                                    const jsonData = JSON.parse(settings.data);
                                    
                                    // Добавляем данные доставки в JSON
                                    if (window.currentDeliveryData.dostavka) {
                                        jsonData.dostavka = window.currentDeliveryData.dostavka;
                                        jsonData._meta_dostavka = window.currentDeliveryData.dostavka;
                                        jsonData.meta_dostavka = window.currentDeliveryData.dostavka;
                                    }
                                    
                                    if (window.currentDeliveryData.manager) {
                                        jsonData.manager = window.currentDeliveryData.manager;
                                        jsonData._meta_manager = window.currentDeliveryData.manager;
                                        jsonData.meta_manager = window.currentDeliveryData.manager;
                                    }
                                    
                                    modifiedData = JSON.stringify(jsonData);
                                    console.log('📝 Модифицирован JSON:', modifiedData);
                                } catch (jsonError) {
                                    console.log('⚠️ Не удалось распарсить JSON, пробуем как form data');
                                }
                            }
                            
                            // Если это не JSON, пробуем как form data
                            if (modifiedData === settings.data) {
                                try {
                                    const formData = new URLSearchParams(settings.data);
                                    
                                    if (window.currentDeliveryData.dostavka) {
                                        formData.set('dostavka', window.currentDeliveryData.dostavka);
                                        formData.set('_meta_dostavka', window.currentDeliveryData.dostavka);
                                        formData.set('meta_dostavka', window.currentDeliveryData.dostavka);
                                    }
                                    
                                    if (window.currentDeliveryData.manager) {
                                        formData.set('manager', window.currentDeliveryData.manager);
                                        formData.set('_meta_manager', window.currentDeliveryData.manager);
                                        formData.set('meta_manager', window.currentDeliveryData.manager);
                                    }
                                    
                                    modifiedData = formData.toString();
                                    console.log('📝 Модифицирован form data:', modifiedData);
                                } catch (formError) {
                                    console.log('⚠️ Не удалось обработать как form data, оставляем как есть');
                                    modifiedData = settings.data;
                                }
                            }
                        } else if (typeof settings.data === 'object') {
                            // Если данные в виде объекта
                            modifiedData = { ...settings.data };
                            
                            if (window.currentDeliveryData.dostavka) {
                                modifiedData.dostavka = window.currentDeliveryData.dostavka;
                                modifiedData._meta_dostavka = window.currentDeliveryData.dostavka;
                                modifiedData.meta_dostavka = window.currentDeliveryData.dostavka;
                            }
                            
                            if (window.currentDeliveryData.manager) {
                                modifiedData.manager = window.currentDeliveryData.manager;
                                modifiedData._meta_manager = window.currentDeliveryData.manager;
                                modifiedData.meta_manager = window.currentDeliveryData.manager;
                            }
                            
                            console.log('📝 Модифицирован объект:', modifiedData);
                        }
                        
                        // Обновляем данные только если они действительно изменились
                        if (modifiedData !== settings.data) {
                            settings.data = modifiedData;
                            console.log('✅ AJAX данные успешно модифицированы');
                        }
                        
                    } catch (e) {
                        console.log('⚠️ Ошибка модификации AJAX данных:', e);
                        // В случае ошибки оставляем оригинальные данные
                        console.log('🔄 Возвращаем оригинальные AJAX данные без изменений');
                    }
                    
                    // Дополнительная проверка валидности данных перед отправкой
                    if (settings.data && typeof settings.data === 'string') {
                        try {
                            // Если это JSON, проверяем что он валидный
                            if (settings.data.trim().startsWith('{')) {
                                JSON.parse(settings.data);
                                console.log('✅ AJAX JSON данные валидны');
                            }
                        } catch (jsonError) {
                            console.log('⚠️ Обнаружены невалидные AJAX JSON данные, исправляем...');
                            // Пытаемся исправить или вернуть оригинальные данные
                            if (originalData) {
                                settings.data = originalData;
                                console.log('🔄 Восстановлены оригинальные AJAX данные');
                            }
                        }
                    }
                }
            }
        });
        
        // Перехватываем Fetch API (для современных запросов)
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const [url, options] = args;
            
            if (url && (url.includes('wc-store/checkout') || url.includes('checkout'))) {
                console.log('📤 Перехват Fetch отправки чекаута');
                console.log('🌐 URL:', url);
                console.log('📦 Исходные данные:', options?.body);
                console.log('🎯 Текущие данные доставки:', window.currentDeliveryData);
                
                if (options && options.body) {
                    try {
                        // Сохраняем оригинальные данные для восстановления в случае ошибки
                        const originalBody = options.body;
                        let modifiedBody = options.body;
                        
                        if (typeof options.body === 'string') {
                            // Проверяем, является ли это JSON
                            if (options.body.trim().startsWith('{')) {
                                try {
                                    const jsonData = JSON.parse(options.body);
                                    
                                    // Добавляем данные доставки в JSON
                                    if (window.currentDeliveryData.dostavka) {
                                        jsonData.dostavka = window.currentDeliveryData.dostavka;
                                        jsonData._meta_dostavka = window.currentDeliveryData.dostavka;
                                        jsonData.meta_dostavka = window.currentDeliveryData.dostavka;
                                    }
                                    
                                    if (window.currentDeliveryData.manager) {
                                        jsonData.manager = window.currentDeliveryData.manager;
                                        jsonData._meta_manager = window.currentDeliveryData.manager;
                                        jsonData.meta_manager = window.currentDeliveryData.manager;
                                    }
                                    
                                    modifiedBody = JSON.stringify(jsonData);
                                    console.log('📝 Модифицирован JSON:', modifiedBody);
                                } catch (jsonError) {
                                    console.log('⚠️ Не удалось распарсить JSON, пробуем как form data');
                                }
                            }
                            
                            // Если это не JSON, пробуем как form data
                            if (modifiedBody === options.body) {
                                try {
                                    const formData = new URLSearchParams(options.body);
                                    
                                    if (window.currentDeliveryData.dostavka) {
                                        formData.set('dostavka', window.currentDeliveryData.dostavka);
                                        formData.set('_meta_dostavka', window.currentDeliveryData.dostavka);
                                        formData.set('meta_dostavka', window.currentDeliveryData.dostavka);
                                    }
                                    
                                    if (window.currentDeliveryData.manager) {
                                        formData.set('manager', window.currentDeliveryData.manager);
                                        formData.set('_meta_manager', window.currentDeliveryData.manager);
                                        formData.set('meta_manager', window.currentDeliveryData.manager);
                                    }
                                    
                                    modifiedBody = formData.toString();
                                    console.log('📝 Модифицирован form data:', modifiedBody);
                                } catch (formError) {
                                    console.log('⚠️ Не удалось обработать как form data, оставляем как есть');
                                    modifiedBody = options.body;
                                }
                            }
                        } else if (options.body instanceof FormData) {
                            // Если это FormData
                            const formData = new FormData();
                            
                            // Копируем все существующие данные
                            for (let [key, value] of options.body.entries()) {
                                formData.append(key, value);
                            }
                            
                            // Добавляем данные доставки
                            if (window.currentDeliveryData.dostavka) {
                                formData.append('dostavka', window.currentDeliveryData.dostavka);
                                formData.append('_meta_dostavka', window.currentDeliveryData.dostavka);
                                formData.append('meta_dostavka', window.currentDeliveryData.dostavka);
                            }
                            
                            if (window.currentDeliveryData.manager) {
                                formData.append('manager', window.currentDeliveryData.manager);
                                formData.append('_meta_manager', window.currentDeliveryData.manager);
                                formData.append('meta_manager', window.currentDeliveryData.manager);
                            }
                            
                            modifiedBody = formData;
                            console.log('📝 Модифицирован FormData');
                        }
                        
                        // Обновляем body только если он действительно изменился
                        if (modifiedBody !== options.body) {
                            options.body = modifiedBody;
                            console.log('✅ Fetch данные успешно модифицированы');
                        }
                        
                    } catch (e) {
                        console.log('⚠️ Ошибка модификации Fetch данных:', e);
                        // В случае ошибки оставляем оригинальные данные
                        console.log('🔄 Возвращаем оригинальные данные без изменений');
                    }
                    
                    // Дополнительная проверка валидности данных перед отправкой
                    if (options.body && typeof options.body === 'string') {
                        try {
                            // Если это JSON, проверяем что он валидный
                            if (options.body.trim().startsWith('{')) {
                                JSON.parse(options.body);
                                console.log('✅ JSON данные валидны');
                            }
                        } catch (jsonError) {
                            console.log('⚠️ Обнаружены невалидные JSON данные, исправляем...');
                            // Пытаемся исправить или вернуть оригинальные данные
                            if (originalBody) {
                                options.body = originalBody;
                                console.log('🔄 Восстановлены оригинальные данные');
                            }
                        }
                    }
                }
            }
            
            return originalFetch.apply(this, args);
        };
    }
    
    // Специальная функция для работы с плагином Checkout Fields for Blocks
    function handleCheckoutFieldsForBlocks() {
        // Ищем специфичные для плагина элементы
        const checkoutFieldsContainer = $('.wp-block-checkout-fields-for-blocks-textarea');
        
        if (checkoutFieldsContainer.length) {
            console.log('🔍 Найден контейнер Checkout Fields for Blocks');
            
            // Пытаемся найти React компоненты
            checkoutFieldsContainer.each(function() {
                const reactFiber = this._reactInternalFiber || this._reactInternalInstance;
                if (reactFiber) {
                    console.log('⚛️ Найден React компонент');
                }
            });
        }
        
        // Перехватываем события специфичные для этого плагина
        $(document).on('change input', '.wp-block-checkout-fields-for-blocks-textarea textarea', function() {
            console.log('📝 Изменение в поле Checkout Fields for Blocks:', this.value);
        });
    }
    
    // Функция для работы напрямую с API плагина Checkout Fields for Blocks
    function updateCheckoutFieldsForBlocksAPI() {
        if (!window.wp || !window.wp.data) {
            console.log('⚠️ WP Data API недоступен');
            return;
        }
        
        try {
            const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
            if (!checkoutStore) {
                console.log('⚠️ Checkout store недоступен');
                return;
            }
            
            // Проверяем доступность setExtensionData
            if (typeof checkoutStore.setExtensionData === 'function') {
                console.log('✅ setExtensionData доступен');
                
                // Устанавливаем данные через API плагина
                if (window.currentDeliveryData && window.currentDeliveryData.dostavka) {
                    try {
                        // Убеждаемся что данные - строка
                        const dostavkaValue = String(window.currentDeliveryData.dostavka);
                        checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_dostavka', dostavkaValue);
                        console.log('🔄 API: Установлено _meta_dostavka =', dostavkaValue);
                    } catch (e) {
                        console.log('⚠️ Ошибка установки _meta_dostavka:', e);
                    }
                }
                
                if (window.currentDeliveryData && window.currentDeliveryData.manager) {
                    try {
                        // Убеждаемся что данные - строка
                        const managerValue = String(window.currentDeliveryData.manager);
                        checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_manager', managerValue);
                        console.log('🔄 API: Установлено _meta_manager =', managerValue);
                    } catch (e) {
                        console.log('⚠️ Ошибка установки _meta_manager:', e);
                    }
                }
                
                // Также пробуем другие возможные имена
                const fieldMappings = [
                    { key: 'dostavka', value: window.currentDeliveryData?.dostavka },
                    { key: 'manager', value: window.currentDeliveryData?.manager }
                ];
                
                fieldMappings.forEach(field => {
                    if (field.value) {
                        try {
                            // Убеждаемся что данные - строка
                            const fieldValue = String(field.value);
                            checkoutStore.setExtensionData('checkout-fields-for-blocks', field.key, fieldValue);
                            console.log(`🔄 API: Установлено ${field.key} =`, fieldValue);
                        } catch (e) {
                            console.log(`⚠️ Ошибка установки ${field.key}:`, e);
                        }
                    }
                });
            } else {
                console.log('⚠️ setExtensionData недоступен, используем альтернативные методы');
                
                // Альтернативный способ - через DOM события
                const textareas = $('.wp-block-checkout-fields-for-blocks-textarea textarea');
                textareas.each(function() {
                    const textarea = this;
                    const container = $(textarea).closest('.wp-block-checkout-fields-for-blocks-textarea');
                    
                    let value = '';
                    
                    if (container.hasClass('sdek') && window.currentDeliveryData?.dostavka) {
                        value = window.currentDeliveryData.dostavka;
                    } else if (container.hasClass('manag') && window.currentDeliveryData?.manager) {
                        value = window.currentDeliveryData.manager;
                    }
                    
                    if (value && textarea.value !== value) {
                        textarea.value = value;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log(`🔄 DOM: Установлено значение ${value}`);
                    }
                });
            }
            
        } catch (e) {
            console.log('❌ Ошибка обновления через API:', e);
        }
    }
    
    // Функция для принудительного обновления полей через DOM события
    function forceUpdateCheckoutFields() {
        // Сначала пробуем через API
        updateCheckoutFieldsForBlocksAPI();
        
        // Затем через DOM
        const textareas = $('.wp-block-checkout-fields-for-blocks-textarea textarea');
        
        textareas.each(function() {
            const textarea = this;
            const container = $(textarea).closest('.wp-block-checkout-fields-for-blocks-textarea');
            
            let value = '';
            
            // Определяем какое значение устанавливать
            if (container.hasClass('sdek')) {
                value = window.currentDeliveryData.dostavka || '';
            } else if (container.hasClass('manag')) {
                value = window.currentDeliveryData.manager || '';
            }
            
            if (value && textarea.value !== value) {
                // Устанавливаем значение и эмулируем пользовательский ввод
                textarea.value = value;
                
                // Создаем и диспатчим события
                const inputEvent = new Event('input', { bubbles: true, cancelable: true });
                const changeEvent = new Event('change', { bubbles: true, cancelable: true });
                
                textarea.dispatchEvent(inputEvent);
                textarea.dispatchEvent(changeEvent);
                
                // Также через jQuery
                $(textarea).trigger('input').trigger('change');
                
                console.log(`🔄 DOM: Принудительно обновлено поле: ${value}`);
            }
        });
    }
    
    // Инициализация
    setTimeout(function() {
        try {
            ensureHiddenFields(); // Создаем поля сразу при инициализации
            interceptFormSubmission(); // Устанавливаем перехват отправки
            handleCheckoutFieldsForBlocks(); // Специальная обработка плагина
            updateTextareaFields();
            observeShippingBlock();
            console.log('✅ Автозаполнение textarea полей готово к работе');
        } catch (error) {
            console.error('❌ Ошибка инициализации автозаполнения:', error);
        }
    }, 1000);
    
    // Периодически принудительно обновляем поля
    setInterval(forceUpdateCheckoutFields, 2000);
    
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