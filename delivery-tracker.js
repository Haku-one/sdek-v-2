jQuery(document).ready(function($) {
    
    // Функция для извлечения информации о доставке из блока
    function extractDeliveryInfo() {
        var deliveryInfo = {
            label: '',
            price: '',
            description: '',
            full_text: ''
        };
        
        // Ищем блок доставки
        var shippingBlock = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
        
        if (shippingBlock.length) {
            var label = shippingBlock.find('.wc-block-components-totals-item__label').text().trim();
            var price = shippingBlock.find('.wc-block-components-totals-item__value').text().trim();
            var description = shippingBlock.find('.wc-block-components-totals-item__description').text().trim();
            
            deliveryInfo.label = label;
            deliveryInfo.price = price;
            deliveryInfo.description = description;
            deliveryInfo.full_text = label + ' - ' + price + (description ? ' (' + description + ')' : '');
        }
        
        return deliveryInfo;
    }
    
    // Функция для обновления скрытого поля
    function updateDeliveryField(deliveryInfo) {
        var fieldValue = JSON.stringify(deliveryInfo);
        
        // Для классического чекаута
        var classicField = $('input[name="delivery_manager"]');
        if (classicField.length) {
            classicField.val(fieldValue);
            console.log('Classic checkout field updated:', fieldValue);
        }
        
        // Для блочного чекаута - пытаемся обновить через разные способы
        try {
            // Способ 1: через wp.data (если доступен)
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                var checkoutStore = wp.data.dispatch('wc/store/checkout');
                if (checkoutStore && checkoutStore.setExtensionData) {
                    checkoutStore.setExtensionData('cdek-delivery', {
                        'delivery-manager': fieldValue
                    });
                    console.log('Block checkout updated via wp.data:', fieldValue);
                }
            }
            
            // Способ 2: через скрытое поле (если есть)
            var hiddenField = $('input[name*="delivery-manager"], input[name*="cdek-delivery"]').filter(':hidden');
            if (hiddenField.length) {
                hiddenField.val(fieldValue);
                console.log('Hidden field updated:', fieldValue);
            }
            
            // Способ 3: создаем скрытое поле если его нет
            if (!hiddenField.length && !classicField.length) {
                var form = $('.wp-block-woocommerce-checkout form, form.checkout').first();
                if (form.length) {
                    var newHiddenField = $('<input type="hidden" name="cdek_delivery_manager" value="' + fieldValue.replace(/"/g, '&quot;') + '">');
                    form.append(newHiddenField);
                    console.log('Created new hidden field:', fieldValue);
                }
            }
            
        } catch (error) {
            console.error('Error updating delivery field:', error);
        }
        
        // Сохраняем в localStorage для отладки
        localStorage.setItem('cdek_delivery_manager', fieldValue);
    }
    
    // Наблюдатель за изменениями в DOM
    function createObserver() {
        var targetNode = document.querySelector('.wp-block-woocommerce-checkout-order-summary-shipping-block');
        
        if (!targetNode) {
            // Если блока еще нет, попробуем найти его позже
            setTimeout(createObserver, 1000);
            return;
        }
        
        var observer = new MutationObserver(function(mutations) {
            var shouldUpdate = false;
            
            mutations.forEach(function(mutation) {
                // Проверяем изменения в тексте или структуре
                if (mutation.type === 'childList' || 
                    mutation.type === 'characterData' || 
                    (mutation.type === 'attributes' && mutation.attributeName === 'class')) {
                    shouldUpdate = true;
                }
            });
            
            if (shouldUpdate) {
                setTimeout(function() {
                    var deliveryInfo = extractDeliveryInfo();
                    if (deliveryInfo.label) {
                        updateDeliveryField(deliveryInfo);
                    }
                }, 100);
            }
        });
        
        observer.observe(targetNode, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['class']
        });
        
        console.log('Observer created for shipping block');
    }
    
    // Функция для первоначальной инициализации
    function initializeDeliveryTracking() {
        var deliveryInfo = extractDeliveryInfo();
        if (deliveryInfo.label) {
            updateDeliveryField(deliveryInfo);
        }
        
        // Создаем наблюдатель
        createObserver();
    }
    
    // Для блочного чекаута
    if ($('.wp-block-woocommerce-checkout').length) {
        console.log('Block checkout detected');
        
        // Ждем полной загрузки блоков
        setTimeout(initializeDeliveryTracking, 1500);
        
        // Также слушаем события обновления чекаута
        $(document).on('updated_checkout checkout_updated', function() {
            setTimeout(function() {
                var deliveryInfo = extractDeliveryInfo();
                if (deliveryInfo.label) {
                    updateDeliveryField(deliveryInfo);
                }
            }, 500);
        });
        
        // Слушаем изменения в методах доставки
        $(document).on('change', 'input[name^="radio-control-wc-shipping-method"]', function() {
            setTimeout(function() {
                var deliveryInfo = extractDeliveryInfo();
                if (deliveryInfo.label) {
                    updateDeliveryField(deliveryInfo);
                }
            }, 300);
        });
    }
    
    // Для классического чекаута (резервный вариант)
    if ($('form.checkout').length && !$('.wp-block-woocommerce-checkout').length) {
        console.log('Classic checkout detected');
        
        $(document).on('change', 'input[name^="shipping_method"]', function() {
            setTimeout(function() {
                var selectedShipping = $('input[name^="shipping_method"]:checked').closest('tr');
                var shippingText = selectedShipping.find('label').text();
                var deliveryInfo = {
                    label: shippingText,
                    price: '',
                    description: '',
                    full_text: shippingText
                };
                updateDeliveryField(deliveryInfo);
            }, 100);
        });
    }
    
    // Периодическая проверка (каждые 3 секунды)
    setInterval(function() {
        if ($('.wp-block-woocommerce-checkout').length) {
            var currentInfo = extractDeliveryInfo();
            var lastInfo = localStorage.getItem('cdek_delivery_manager_last');
            
            if (currentInfo.label && JSON.stringify(currentInfo) !== lastInfo) {
                updateDeliveryField(currentInfo);
                localStorage.setItem('cdek_delivery_manager_last', JSON.stringify(currentInfo));
            }
        }
    }, 3000);
    
    console.log('Delivery tracker initialized');
});