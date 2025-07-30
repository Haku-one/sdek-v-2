/**
 * СДЭК Доставка - Интеграция с WooCommerce Blocks
 * Улучшенная версия для корректной передачи данных
 */

(function($) {
    'use strict';

    // Глобальная переменная для хранения данных СДЭК
    window.cdekDeliveryData = {
        discussDeliverySelected: false,
        pointCode: null,
        pointData: null
    };

    /**
     * Инициализация интеграции
     */
    function initCdekBlocksIntegration() {
        console.log('🚀 Инициализация СДЭК Blocks интеграции');

        // Ждем загрузки WooCommerce Blocks
        if (typeof wp !== 'undefined' && wp.data) {
            setupBlocksIntegration();
        } else {
            // Fallback для классического checkout
            setupClassicIntegration();
        }

        // Отслеживаем выбор "Обсудить доставку с менеджером"
        monitorDiscussDeliverySelection();

        // Перехватываем отправку формы
        interceptFormSubmission();
    }

    /**
     * Настройка интеграции с WooCommerce Blocks
     */
    function setupBlocksIntegration() {
        console.log('🔧 Настройка интеграции с WooCommerce Blocks');

        // Подписываемся на изменения в store
        if (wp.data.subscribe) {
            wp.data.subscribe(() => {
                const checkoutData = wp.data.select('wc/store/checkout');
                if (checkoutData) {
                    // Отслеживаем изменения в checkout
                    handleCheckoutDataChange(checkoutData);
                }
            });
        }

        // Расширяем данные checkout через extensionData
        if (wp.hooks && wp.hooks.addFilter) {
            wp.hooks.addFilter(
                'woocommerce_blocks_checkout_submit_data',
                'cdek-delivery',
                addCdekDataToCheckout
            );
        }
    }

    /**
     * Настройка для классического checkout
     */
    function setupClassicIntegration() {
        console.log('🔧 Настройка для классического checkout');

        // Отслеживаем изменения в форме
        $(document.body).on('update_checkout', function() {
            addHiddenFieldsToForm();
        });
    }

    /**
     * Отслеживание выбора "Обсудить доставку с менеджером"
     */
    function monitorDiscussDeliverySelection() {
        // Отслеживаем клики по кастомной вкладке
        $(document).on('click', '#discuss-tab', function() {
            console.log('🎯 Выбрана опция "Обсудить доставку с менеджером"');
            
            window.cdekDeliveryData.discussDeliverySelected = true;
            
            // Добавляем скрытое поле
            addDiscussDeliveryField();
            
            // Уведомляем WooCommerce Blocks
            notifyBlocksAboutSelection();
            
            // Отправляем AJAX запрос для немедленного сохранения
            sendDeliveryDataViaAjax();
        });

        // Отслеживаем другие вкладки доставки
        $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
            const titleText = $(this).find('.wc-block-checkout__shipping-method-option-title').text();
            
            if (titleText.includes('Обсудить доставку') || titleText.includes('обсудить')) {
                console.log('🎯 Выбрана опция обсуждения доставки через стандартную вкладку');
                
                window.cdekDeliveryData.discussDeliverySelected = true;
                addDiscussDeliveryField();
                notifyBlocksAboutSelection();
                sendDeliveryDataViaAjax();
            } else {
                // Сбрасываем выбор, если выбрана другая опция
                window.cdekDeliveryData.discussDeliverySelected = false;
                removeDiscussDeliveryField();
            }
        });
    }

    /**
     * Добавление скрытого поля для обсуждения доставки
     */
    function addDiscussDeliveryField() {
        // Удаляем существующее поле
        $('#discuss_delivery_selected').remove();

        // Создаем новое поле
        const hiddenField = $('<input>', {
            type: 'hidden',
            id: 'discuss_delivery_selected',
            name: 'discuss_delivery_selected',
            value: '1'
        });

        // Ищем подходящую форму
        const targetForm = findCheckoutForm();
        if (targetForm.length) {
            targetForm.append(hiddenField);
            console.log('✅ Добавлено скрытое поле discuss_delivery_selected');
        }
    }

    /**
     * Удаление скрытого поля
     */
    function removeDiscussDeliveryField() {
        $('#discuss_delivery_selected').remove();
        console.log('🗑️ Удалено скрытое поле discuss_delivery_selected');
    }

    /**
     * Поиск формы checkout
     */
    function findCheckoutForm() {
        const selectors = [
            'form.woocommerce-checkout',
            'form.checkout',
            'form[name="checkout"]',
            '.wc-block-checkout__form',
            '.wc-block-checkout form',
            'form'
        ];

        for (let selector of selectors) {
            const form = $(selector).first();
            if (form.length) {
                console.log('📋 Найдена форма:', selector);
                return form;
            }
        }

        console.warn('⚠️ Форма checkout не найдена, используем body');
        return $('body');
    }

    /**
     * Уведомление WooCommerce Blocks о выборе
     */
    function notifyBlocksAboutSelection() {
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                const checkoutStore = wp.data.dispatch('wc/store/checkout');
                
                // Способ 1: через setExtensionData
                if (checkoutStore.setExtensionData) {
                    checkoutStore.setExtensionData('cdek-delivery', {
                        discuss_delivery_selected: '1'
                    });
                    console.log('✅ Данные переданы через setExtensionData');
                }

                // Способ 2: через __internalSetExtensionData
                if (checkoutStore.__internalSetExtensionData) {
                    checkoutStore.__internalSetExtensionData('cdek-delivery', {
                        discuss_delivery_selected: '1'
                    });
                    console.log('✅ Данные переданы через __internalSetExtensionData');
                }

            } catch (e) {
                console.warn('⚠️ Ошибка уведомления WC Blocks:', e);
            }
        }

        // Дополнительный способ через кастомное событие
        $(document.body).trigger('cdek_discuss_delivery_selected', {
            selected: true,
            value: '1'
        });
    }

    /**
     * Отправка данных через AJAX
     */
    function sendDeliveryDataViaAjax() {
        if (typeof cdek_ajax === 'undefined') {
            console.warn('⚠️ AJAX данные недоступны');
            return;
        }

        const data = {
            action: 'cdek_save_delivery_choice',
            nonce: cdek_ajax.nonce,
            discuss_delivery: window.cdekDeliveryData.discussDeliverySelected ? '1' : '0',
            cdek_delivery_data: JSON.stringify(window.cdekDeliveryData)
        };

        $.post(cdek_ajax.ajax_url, data)
            .done(function(response) {
                if (response.success) {
                    console.log('✅ Данные доставки сохранены через AJAX');
                } else {
                    console.warn('⚠️ Ошибка сохранения через AJAX:', response.data);
                }
            })
            .fail(function() {
                console.warn('⚠️ AJAX запрос не удался');
            });
    }

    /**
     * Добавление данных СДЭК к данным checkout
     */
    function addCdekDataToCheckout(data) {
        console.log('📦 Добавление данных СДЭК к checkout');

        // Добавляем данные в extensions
        if (!data.extensions) {
            data.extensions = {};
        }

        data.extensions['cdek-delivery'] = {
            discuss_delivery_selected: window.cdekDeliveryData.discussDeliverySelected ? '1' : '0',
            point_code: window.cdekDeliveryData.pointCode,
            point_data: window.cdekDeliveryData.pointData
        };

        console.log('📋 Данные СДЭК добавлены в extensions:', data.extensions['cdek-delivery']);
        return data;
    }

    /**
     * Добавление скрытых полей в форму
     */
    function addHiddenFieldsToForm() {
        if (window.cdekDeliveryData.discussDeliverySelected) {
            addDiscussDeliveryField();
        }

        // Добавляем данные СДЭК пункта, если есть
        if (window.cdekDeliveryData.pointCode) {
            addHiddenField('cdek_point_code', window.cdekDeliveryData.pointCode);
        }

        if (window.cdekDeliveryData.pointData) {
            addHiddenField('cdek_point_data', JSON.stringify(window.cdekDeliveryData.pointData));
        }
    }

    /**
     * Добавление произвольного скрытого поля
     */
    function addHiddenField(name, value) {
        $('#' + name).remove();
        
        const field = $('<input>', {
            type: 'hidden',
            id: name,
            name: name,
            value: value
        });

        const targetForm = findCheckoutForm();
        targetForm.append(field);
    }

    /**
     * Перехват отправки формы
     */
    function interceptFormSubmission() {
        // Для WooCommerce Blocks
        if (typeof wp !== 'undefined' && wp.hooks) {
            wp.hooks.addAction(
                'woocommerce_blocks_checkout_submit',
                'cdek-delivery',
                function() {
                    console.log('📤 Отправка checkout через Blocks');
                    ensureDataIsSet();
                }
            );
        }

        // Для классического checkout
        $(document).on('submit', 'form.checkout, form.woocommerce-checkout', function() {
            console.log('📤 Отправка классического checkout');
            ensureDataIsSet();
            addHiddenFieldsToForm();
        });

        // Дополнительный перехват через MutationObserver
        observeCheckoutChanges();
    }

    /**
     * Убеждаемся, что данные установлены
     */
    function ensureDataIsSet() {
        if (window.cdekDeliveryData.discussDeliverySelected) {
            console.log('🔒 Финальная проверка: данные СДЭК установлены');
            
            // Последняя попытка добавить поля
            addHiddenFieldsToForm();
            
            // Отправляем через REST API
            sendDataViaRestApi();
        }
    }

    /**
     * Отправка данных через REST API
     */
    function sendDataViaRestApi() {
        if (window.cdekDeliveryData.discussDeliverySelected) {
            fetch('/wp-json/cdek/v1/save-delivery-choice', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    discuss_delivery: true,
                    point_code: window.cdekDeliveryData.pointCode,
                    point_data: window.cdekDeliveryData.pointData
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('✅ Данные отправлены через REST API:', data);
            })
            .catch(error => {
                console.warn('⚠️ Ошибка REST API:', error);
            });
        }
    }

    /**
     * Наблюдение за изменениями в checkout
     */
    function observeCheckoutChanges() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Проверяем, появились ли новые элементы checkout
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const $node = $(node);
                            if ($node.hasClass('wc-block-checkout') || $node.find('.wc-block-checkout').length) {
                                console.log('🔄 Обнаружены изменения в checkout, переинициализация');
                                setTimeout(initCdekBlocksIntegration, 100);
                            }
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Обработка изменений данных checkout
     */
    function handleCheckoutDataChange(checkoutData) {
        // Здесь можно добавить логику реагирования на изменения в checkout
        // Например, обновление данных СДЭК при изменении адреса доставки
    }

    /**
     * Интеграция с существующим кодом СДЭК
     */
    function integrationWithExistingCdekCode() {
        // Слушаем события от основного СДЭК кода
        $(document).on('cdek_point_selected', function(e, pointData) {
            window.cdekDeliveryData.pointCode = pointData.code;
            window.cdekDeliveryData.pointData = pointData;
            console.log('📍 Выбран пункт СДЭК:', pointData.code);
        });

        // Слушаем событие обсуждения доставки
        $(document).on('cdek_discuss_delivery_selected', function(e, data) {
            window.cdekDeliveryData.discussDeliverySelected = data.selected;
            console.log('💬 Выбрано обсуждение доставки:', data.selected);
        });
    }

    /**
     * Инициализация при загрузке страницы
     */
    $(document).ready(function() {
        console.log('🎯 СДЭК Blocks Integration загружен');
        
        // Ждем полной загрузки WooCommerce
        if (typeof wc !== 'undefined' || typeof wp !== 'undefined') {
            initCdekBlocksIntegration();
        } else {
            // Ждем загрузки WooCommerce
            let attempts = 0;
            const waitForWC = setInterval(function() {
                if (typeof wc !== 'undefined' || typeof wp !== 'undefined' || attempts > 50) {
                    clearInterval(waitForWC);
                    if (attempts <= 50) {
                        initCdekBlocksIntegration();
                    }
                }
                attempts++;
            }, 100);
        }

        // Интеграция с существующим кодом
        integrationWithExistingCdekCode();
    });

    // Экспортируем функции для использования другими скриптами
    window.cdekBlocksIntegration = {
        setDiscussDelivery: function(selected) {
            window.cdekDeliveryData.discussDeliverySelected = selected;
            if (selected) {
                addDiscussDeliveryField();
                notifyBlocksAboutSelection();
            } else {
                removeDiscussDeliveryField();
            }
        },
        setCdekPoint: function(code, data) {
            window.cdekDeliveryData.pointCode = code;
            window.cdekDeliveryData.pointData = data;
        },
        getData: function() {
            return window.cdekDeliveryData;
        }
    };

})(jQuery);