/**
 * Классический checkout JavaScript
 * Улучшения UX для классического checkout без модального окна
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Улучшения для формы checkout
    const CheckoutEnhancements = {
        init: function() {
            this.setupFormValidation();
            this.setupFieldAnimations();
            this.setupAutoProgress();
        },
        
        setupFormValidation: function() {
            // Валидация телефона в реальном времени
            $(document).on('input', 'input[type="tel"]', function() {
                const $field = $(this);
                const value = $field.val().replace(/\D/g, '');
                
                if (value.length >= 10) {
                    $field.removeClass('error').addClass('valid');
                } else {
                    $field.removeClass('valid');
                }
            });
            
            // Валидация email
            $(document).on('input', 'input[type="email"]', function() {
                const $field = $(this);
                const email = $field.val();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (emailRegex.test(email)) {
                    $field.removeClass('error').addClass('valid');
                } else {
                    $field.removeClass('valid');
                }
            });
            
            // Валидация обязательных полей
            $(document).on('input', 'input[aria-required="true"], select[aria-required="true"]', function() {
                const $field = $(this);
                const value = $field.val();
                
                if (value && value.trim() !== '') {
                    $field.removeClass('error').addClass('valid');
                } else {
                    $field.removeClass('valid').addClass('error');
                }
            });
        },
        
        setupFieldAnimations: function() {
            // Анимация фокуса на полях
            $(document).on('focus', '.woocommerce-checkout input, .woocommerce-checkout select, .woocommerce-checkout textarea', function() {
                $(this).closest('.form-row').addClass('focused');
            });
            
            $(document).on('blur', '.woocommerce-checkout input, .woocommerce-checkout select, .woocommerce-checkout textarea', function() {
                $(this).closest('.form-row').removeClass('focused');
            });
        },
        
        setupAutoProgress: function() {
            // Автозаполнение адреса доставки из адреса оплаты при снятии чекбокса
            $(document).on('change', '#ship-to-different-address-checkbox', function() {
                if (!$(this).is(':checked')) {
                    CheckoutEnhancements.copyBillingToShipping();
                }
            });
            
            // Показ/скрытие полей доставки
            $(document).on('change', '#ship-to-different-address-checkbox', function() {
                if ($(this).is(':checked')) {
                    $('.shipping_address').slideDown(300);
                } else {
                    $('.shipping_address').slideUp(300);
                }
            });
        },
        
        copyBillingToShipping: function() {
            const billingFields = ['first_name', 'last_name', 'address_1', 'city', 'state', 'postcode'];
            
            billingFields.forEach(function(field) {
                const billingValue = $('#billing_' + field).val();
                const $shippingField = $('#shipping_' + field);
                if (billingValue && $shippingField.length) {
                    $shippingField.val(billingValue).trigger('change');
                }
            });
            
            // Показываем уведомление
            CheckoutEnhancements.showNotification('Адрес доставки скопирован из адреса оплаты', 'info');
        },
        
        showNotification: function(message, type = 'info') {
            // Простое уведомление без сложных анимаций
            const $notification = $('<div class="checkout-notification ' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            // Показываем
            $notification.fadeIn(300);
            
            // Автоматически скрываем через 3 секунды
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Помощник для отслеживания кликов на кнопку менеджера
    const ManagerTracking = {
        init: function() {
            $(document).on('click', '#discuss-delivery-btn', this.trackManagerClick);
        },
        
        trackManagerClick: function() {
            // Логируем клик для аналитики
            if (typeof console !== 'undefined' && console.log) {
                console.log('Клик по кнопке "Обсудить доставку с менеджером"');
            }
            
            // Отправка в Google Analytics если доступен
            if (typeof gtag !== 'undefined') {
                gtag('event', 'manager_button_click', {
                    'event_category': 'checkout',
                    'event_label': 'delivery_consultation'
                });
            }
            
            // Отправка в Яндекс.Метрику если доступна
            if (typeof ym !== 'undefined') {
                ym('reachGoal', 'manager_delivery_click');
            }
        }
    };
    
    // Улучшения для СДЭК функциональности
    const CdekEnhancements = {
        init: function() {
            this.setupPointSelection();
            this.improveMapInterface();
        },
        
        setupPointSelection: function() {
            // Улучшение интерфейса выбора пунктов СДЭК
            $(document).on('change', 'input[name="cdek_point"]', function() {
                const selectedPoint = $(this).closest('.cdek-point');
                $('.cdek-point').removeClass('selected');
                selectedPoint.addClass('selected');
                
                // Показываем информацию о выбранном пункте
                const pointName = selectedPoint.find('.point-name').text();
                CheckoutEnhancements.showNotification('Выбран пункт: ' + pointName, 'success');
            });
        },
        
        improveMapInterface: function() {
            // Добавляем loading состояние для карты
            $(document).on('click', '.show-map-btn', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.text('Загрузка карты...').prop('disabled', true);
                
                setTimeout(function() {
                    $btn.text(originalText).prop('disabled', false);
                }, 2000);
            });
        }
    };
    
    // Автосохранение данных формы в localStorage
    const FormAutoSave = {
        storageKey: 'checkout_form_data',
        saveDelay: 1000,
        
        init: function() {
            if (!this.isStorageAvailable()) return;
            
            this.loadSavedData();
            this.setupAutoSave();
            this.setupClearOnSubmit();
        },
        
        isStorageAvailable: function() {
            try {
                localStorage.setItem('test', 'test');
                localStorage.removeItem('test');
                return true;
            } catch (e) {
                return false;
            }
        },
        
        setupAutoSave: function() {
            let saveTimeout;
            
            $(document).on('input change', '.woocommerce-checkout input:not([type="password"]), .woocommerce-checkout select, .woocommerce-checkout textarea', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    FormAutoSave.saveFormData();
                }, FormAutoSave.saveDelay);
            });
        },
        
        saveFormData: function() {
            const formData = {};
            $('.woocommerce-checkout input:not([type="password"]), .woocommerce-checkout select, .woocommerce-checkout textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                
                if (name && !name.includes('nonce') && !name.includes('_wp_http_referer')) {
                    formData[name] = $field.val();
                }
            });
            
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(formData));
            } catch (e) {
                console.warn('Не удалось сохранить данные формы:', e);
            }
        },
        
        loadSavedData: function() {
            try {
                const savedData = localStorage.getItem(this.storageKey);
                if (!savedData) return;
                
                const formData = JSON.parse(savedData);
                let fieldsRestored = 0;
                
                Object.keys(formData).forEach(function(name) {
                    const $field = $('[name="' + name + '"]');
                    if ($field.length && !$field.val() && formData[name]) {
                        $field.val(formData[name]);
                        fieldsRestored++;
                    }
                });
                
                if (fieldsRestored > 0) {
                    CheckoutEnhancements.showNotification('Восстановлено ' + fieldsRestored + ' полей из сохранённых данных', 'info');
                }
            } catch (e) {
                console.warn('Не удалось загрузить сохранённые данные:', e);
            }
        },
        
        setupClearOnSubmit: function() {
            $(document).on('submit', '.woocommerce-checkout', function() {
                FormAutoSave.clearSavedData();
            });
        },
        
        clearSavedData: function() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch (e) {
                console.warn('Не удалось очистить сохранённые данные:', e);
            }
        }
    };
    
    // Инициализация всех компонентов
    CheckoutEnhancements.init();
    ManagerTracking.init();
    CdekEnhancements.init();
    FormAutoSave.init();
    
    // Добавляем стили для уведомлений
    const notificationStyles = `
        <style>
        .checkout-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            max-width: 300px;
            display: none;
        }
        
        .checkout-notification.info {
            background: #007cba;
        }
        
        .checkout-notification.success {
            background: #28a745;
        }
        
        .checkout-notification.error {
            background: #dc3545;
        }
        
        .checkout-notification.warning {
            background: #ffc107;
            color: #333;
        }
        
        .cdek-point.selected {
            background: #e8f4f8 !important;
            border-color: #007cba !important;
        }
        
        .form-row.focused input,
        .form-row.focused select,
        .form-row.focused textarea {
            border-color: #007cba !important;
            box-shadow: 0 0 0 3px rgba(0, 123, 186, 0.1) !important;
        }
        
        @media (max-width: 768px) {
            .checkout-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
        </style>
    `;
    
    $('head').append(notificationStyles);
});