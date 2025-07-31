jQuery(document).ready(function($) {
    console.log('🔧 Автозаполнение textarea полей инициализировано');
    
    // Инициализируем данные доставки
    if (!window.currentDeliveryData) {
        window.currentDeliveryData = { dostavka: '', manager: '' };
    }
    
    // Основная функция заполнения полей
    function fillTextareaFields() {
        const textareas = $('.wp-block-checkout-fields-for-blocks-textarea textarea');
        
        textareas.each(function() {
            const textarea = this;
            const container = $(textarea).closest('.wp-block-checkout-fields-for-blocks-textarea');
            
            let value = '';
            
            if (container.hasClass('sdek') && window.currentDeliveryData.dostavka) {
                value = String(window.currentDeliveryData.dostavka);
            } else if (container.hasClass('manag') && window.currentDeliveryData.manager) {
                value = String(window.currentDeliveryData.manager);
            }
            
            if (value && textarea.value !== value) {
                textarea.value = value;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
                console.log(`🔄 Заполнено поле: ${value}`);
            }
        });
    }
    
    // Функция для работы с API плагина
    function updateCheckoutFieldsForBlocksAPI() {
        if (!window.wp?.data?.dispatch) return;
        
        try {
            const checkoutStore = window.wp.data.dispatch('wc/store/checkout');
            if (!checkoutStore || typeof checkoutStore.setExtensionData !== 'function') return;
            
            if (window.currentDeliveryData.dostavka) {
                const dostavkaValue = String(window.currentDeliveryData.dostavka);
                checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_dostavka', dostavkaValue);
                console.log('🔄 API: Установлено _meta_dostavka =', dostavkaValue);
            }
            
            if (window.currentDeliveryData.manager) {
                const managerValue = String(window.currentDeliveryData.manager);
                checkoutStore.setExtensionData('checkout-fields-for-blocks', '_meta_manager', managerValue);
                console.log('🔄 API: Установлено _meta_manager =', managerValue);
            }
        } catch (e) {
            console.log('❌ Ошибка API:', e);
        }
    }
    
    // Перехват отправки форм
    function interceptFormSubmission() {
        // Перехватываем Fetch API
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const [url, options] = args;
            
            if (url && (url.includes('wc-store/checkout') || url.includes('checkout'))) {
                console.log('📤 Перехват Fetch отправки чекаута');
                
                if (options?.body) {
                    try {
                        const originalBody = options.body;
                        let modifiedBody = options.body;
                        
                        if (typeof options.body === 'string') {
                            if (options.body.trim().startsWith('{')) {
                                // JSON
                                const jsonData = JSON.parse(options.body);
                                if (window.currentDeliveryData.dostavka) {
                                    jsonData.dostavka = String(window.currentDeliveryData.dostavka);
                                    jsonData._meta_dostavka = String(window.currentDeliveryData.dostavka);
                                }
                                if (window.currentDeliveryData.manager) {
                                    jsonData.manager = String(window.currentDeliveryData.manager);
                                    jsonData._meta_manager = String(window.currentDeliveryData.manager);
                                }
                                modifiedBody = JSON.stringify(jsonData);
                            } else {
                                // Form data
                                const formData = new URLSearchParams(options.body);
                                if (window.currentDeliveryData.dostavka) {
                                    formData.set('dostavka', String(window.currentDeliveryData.dostavka));
                                    formData.set('_meta_dostavka', String(window.currentDeliveryData.dostavka));
                                }
                                if (window.currentDeliveryData.manager) {
                                    formData.set('manager', String(window.currentDeliveryData.manager));
                                    formData.set('_meta_manager', String(window.currentDeliveryData.manager));
                                }
                                modifiedBody = formData.toString();
                            }
                        } else if (options.body instanceof FormData) {
                            const formData = new FormData();
                            for (let [key, value] of options.body.entries()) {
                                formData.append(key, value);
                            }
                            if (window.currentDeliveryData.dostavka) {
                                formData.append('dostavka', String(window.currentDeliveryData.dostavka));
                                formData.append('_meta_dostavka', String(window.currentDeliveryData.dostavka));
                            }
                            if (window.currentDeliveryData.manager) {
                                formData.append('manager', String(window.currentDeliveryData.manager));
                                formData.append('_meta_manager', String(window.currentDeliveryData.manager));
                            }
                            modifiedBody = formData;
                        }
                        
                        if (modifiedBody !== options.body) {
                            options.body = modifiedBody;
                            console.log('✅ Fetch данные модифицированы');
                        }
                    } catch (e) {
                        console.log('⚠️ Ошибка модификации Fetch:', e);
                    }
                }
            }
            
            return originalFetch.apply(this, args);
        };
        
        // Перехватываем AJAX
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url && (settings.url.includes('wc-store/checkout') || settings.url.includes('checkout'))) {
                console.log('📤 Перехват AJAX отправки чекаута');
                
                if (settings.data) {
                    try {
                        const originalData = settings.data;
                        let modifiedData = settings.data;
                        
                        if (typeof settings.data === 'string') {
                            if (settings.data.trim().startsWith('{')) {
                                const jsonData = JSON.parse(settings.data);
                                if (window.currentDeliveryData.dostavka) {
                                    jsonData.dostavka = String(window.currentDeliveryData.dostavka);
                                    jsonData._meta_dostavka = String(window.currentDeliveryData.dostavka);
                                }
                                if (window.currentDeliveryData.manager) {
                                    jsonData.manager = String(window.currentDeliveryData.manager);
                                    jsonData._meta_manager = String(window.currentDeliveryData.manager);
                                }
                                modifiedData = JSON.stringify(jsonData);
                            } else {
                                const formData = new URLSearchParams(settings.data);
                                if (window.currentDeliveryData.dostavka) {
                                    formData.set('dostavka', String(window.currentDeliveryData.dostavka));
                                    formData.set('_meta_dostavka', String(window.currentDeliveryData.dostavka));
                                }
                                if (window.currentDeliveryData.manager) {
                                    formData.set('manager', String(window.currentDeliveryData.manager));
                                    formData.set('_meta_manager', String(window.currentDeliveryData.manager));
                                }
                                modifiedData = formData.toString();
                            }
                        } else if (typeof settings.data === 'object') {
                            modifiedData = { ...settings.data };
                            if (window.currentDeliveryData.dostavka) {
                                modifiedData.dostavka = String(window.currentDeliveryData.dostavka);
                                modifiedData._meta_dostavka = String(window.currentDeliveryData.dostavka);
                            }
                            if (window.currentDeliveryData.manager) {
                                modifiedData.manager = String(window.currentDeliveryData.manager);
                                modifiedData._meta_manager = String(window.currentDeliveryData.manager);
                            }
                        }
                        
                        if (modifiedData !== settings.data) {
                            settings.data = modifiedData;
                            console.log('✅ AJAX данные модифицированы');
                        }
                    } catch (e) {
                        console.log('⚠️ Ошибка модификации AJAX:', e);
                    }
                }
            }
        });
    }
    
    // Инициализация
    setTimeout(function() {
        try {
            interceptFormSubmission();
            fillTextareaFields();
            updateCheckoutFieldsForBlocksAPI();
            console.log('✅ Автозаполнение готово к работе');
        } catch (error) {
            console.error('❌ Ошибка инициализации:', error);
        }
    }, 1000);
    
    // Периодическое обновление
    setInterval(function() {
        fillTextareaFields();
        updateCheckoutFieldsForBlocksAPI();
    }, 2000);
    
    // Глобальные функции для отладки
    window.updateTextareaFields = fillTextareaFields;
    window.updateCheckoutFieldsForBlocksAPI = updateCheckoutFieldsForBlocksAPI;
    
    console.log('🎯 Автозаполнение textarea полей инициализировано');
});