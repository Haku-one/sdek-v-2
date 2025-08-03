/**
 * Классический checkout JavaScript
 * Обработчики для кнопки менеджера и улучшений UX
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Объект для управления модальным окном менеджера
    const ManagerModal = {
        init: function() {
            this.bindEvents();
            this.setupAccessibility();
        },
        
        bindEvents: function() {
            // Открытие модального окна
            $(document).on('click', '#contact-manager-btn', this.openModal);
            
            // Закрытие модального окна
            $(document).on('click', '#close-manager-modal', this.closeModal);
            $(document).on('click', '#manager-modal', this.closeModalOnBackdrop);
            
            // Закрытие по клавише ESC
            $(document).on('keydown', this.handleKeyPress);
            
            // Отслеживание кликов по контактным методам
            $(document).on('click', '.manager-contact-options a', this.trackContactMethod);
        },
        
        openModal: function(e) {
            e.preventDefault();
            
            const $modal = $('#manager-modal');
            const $body = $('body');
            
            // Добавляем класс для анимации
            $modal.removeClass('closing').addClass('opening');
            $modal.css('display', 'flex');
            $body.addClass('modal-open').css('overflow', 'hidden');
            
            // Устанавливаем фокус на кнопку закрытия для доступности
            setTimeout(function() {
                $('#close-manager-modal').focus();
            }, 100);
            
            // Логируем открытие модального окна
            ManagerModal.trackEvent('modal_opened', {
                source: 'manager_button'
            });
        },
        
        closeModal: function(e) {
            if (e) e.preventDefault();
            
            const $modal = $('#manager-modal');
            const $body = $('body');
            
            // Добавляем класс для анимации закрытия
            $modal.removeClass('opening').addClass('closing');
            
            setTimeout(function() {
                $modal.hide().removeClass('closing');
                $body.removeClass('modal-open').css('overflow', '');
            }, 300);
            
            // Возвращаем фокус на кнопку открытия
            $('#contact-manager-btn').focus();
            
            ManagerModal.trackEvent('modal_closed');
        },
        
        closeModalOnBackdrop: function(e) {
            if (e.target === this) {
                ManagerModal.closeModal();
            }
        },
        
        handleKeyPress: function(e) {
            // Закрытие по ESC
            if (e.keyCode === 27 && $('#manager-modal').is(':visible')) {
                ManagerModal.closeModal();
            }
        },
        
        setupAccessibility: function() {
            // Устанавливаем ARIA атрибуты
            $('#contact-manager-btn').attr({
                'aria-haspopup': 'dialog',
                'aria-expanded': 'false'
            });
            
            $('#manager-modal').attr({
                'role': 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': 'manager-modal-title'
            });
        },
        
        trackContactMethod: function(e) {
            const $link = $(this);
            const href = $link.attr('href');
            let method = 'unknown';
            
            if (href.includes('tel:')) {
                method = 'phone';
            } else if (href.includes('wa.me')) {
                method = 'whatsapp';
            } else if (href.includes('mailto:')) {
                method = 'email';
            }
            
            ManagerModal.trackEvent('contact_method_clicked', {
                method: method,
                href: href
            });
        },
        
        trackEvent: function(eventName, data = {}) {
            // Логирование для аналитики
            if (typeof console !== 'undefined' && console.log) {
                console.log('Manager Modal Event:', eventName, data);
            }
            
            // Отправка в Google Analytics если доступен
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    'event_category': 'manager_contact',
                    'custom_data': data
                });
            }
            
            // Отправка в Яндекс.Метрику если доступна
            if (typeof ym !== 'undefined') {
                ym('reachGoal', eventName, data);
            }
        }
    };
    
    // Улучшения для формы checkout
    const CheckoutEnhancements = {
        init: function() {
            this.setupFormValidation();
            this.setupProgressIndicator();
            this.setupFieldAnimations();
            this.setupAutofill();
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
        },
        
        setupProgressIndicator: function() {
            // Индикатор заполнения формы
            const $progressBar = $('<div class="checkout-progress"><div class="progress-fill"></div></div>');
            $('.woocommerce-checkout').prepend($progressBar);
            
            // Обновление прогресса при заполнении полей
            $(document).on('input change', '.woocommerce-checkout input, .woocommerce-checkout select', function() {
                CheckoutEnhancements.updateProgress();
            });
        },
        
        updateProgress: function() {
            const $requiredFields = $('.woocommerce-checkout input[aria-required="true"], .woocommerce-checkout select[aria-required="true"]');
            const filledFields = $requiredFields.filter(function() {
                return $(this).val().trim() !== '';
            });
            
            const progress = (filledFields.length / $requiredFields.length) * 100;
            $('.progress-fill').css('width', progress + '%');
            
            // Изменение цвета в зависимости от прогресса
            if (progress < 30) {
                $('.progress-fill').css('background', '#dc3545');
            } else if (progress < 70) {
                $('.progress-fill').css('background', '#ffc107');
            } else {
                $('.progress-fill').css('background', '#28a745');
            }
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
        
        setupAutofill: function() {
            // Автозаполнение адреса доставки из адреса оплаты
            $(document).on('change', '#ship-to-different-address-checkbox', function() {
                if (!$(this).is(':checked')) {
                    CheckoutEnhancements.copyBillingToShipping();
                }
            });
        },
        
        copyBillingToShipping: function() {
            const billingFields = ['first_name', 'last_name', 'address_1', 'city', 'state', 'postcode'];
            
            billingFields.forEach(function(field) {
                const billingValue = $('#billing_' + field).val();
                $('#shipping_' + field).val(billingValue);
            });
        }
    };
    
    // Система уведомлений
    const NotificationSystem = {
        show: function(message, type = 'info', duration = 5000) {
            const $notification = $(`
                <div class="checkout-notification ${type}">
                    <span class="notification-message">${message}</span>
                    <button type="button" class="notification-close">&times;</button>
                </div>
            `);
            
            $('body').append($notification);
            
            // Автоматическое скрытие
            setTimeout(function() {
                NotificationSystem.hide($notification);
            }, duration);
            
            // Закрытие по клику
            $notification.find('.notification-close').on('click', function() {
                NotificationSystem.hide($notification);
            });
        },
        
        hide: function($notification) {
            $notification.addClass('hiding');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }
    };
    
    // Помощник для работы с локальным хранилищем
    const StorageHelper = {
        save: function(key, data) {
            try {
                localStorage.setItem('checkout_' + key, JSON.stringify(data));
            } catch (e) {
                console.warn('LocalStorage не доступен:', e);
            }
        },
        
        load: function(key) {
            try {
                const data = localStorage.getItem('checkout_' + key);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                console.warn('Ошибка чтения LocalStorage:', e);
                return null;
            }
        },
        
        remove: function(key) {
            try {
                localStorage.removeItem('checkout_' + key);
            } catch (e) {
                console.warn('Ошибка удаления из LocalStorage:', e);
            }
        }
    };
    
    // Автосохранение данных формы
    const AutoSave = {
        init: function() {
            this.loadSavedData();
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Сохранение при изменении полей
            $(document).on('input change', '.woocommerce-checkout input, .woocommerce-checkout select, .woocommerce-checkout textarea', 
                _.debounce(AutoSave.saveFormData, 1000)
            );
            
            // Очистка при успешной отправке
            $(document).on('checkout_place_order_success', AutoSave.clearSavedData);
        },
        
        saveFormData: function() {
            const formData = {};
            $('.woocommerce-checkout input, .woocommerce-checkout select, .woocommerce-checkout textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (name && !name.includes('password') && !name.includes('nonce')) {
                    formData[name] = $field.val();
                }
            });
            
            StorageHelper.save('form_data', formData);
        },
        
        loadSavedData: function() {
            const savedData = StorageHelper.load('form_data');
            if (savedData) {
                Object.keys(savedData).forEach(function(name) {
                    const $field = $(`[name="${name}"]`);
                    if ($field.length && !$field.val()) {
                        $field.val(savedData[name]);
                    }
                });
                
                NotificationSystem.show('Восстановлены ранее введённые данные', 'info', 3000);
            }
        },
        
        clearSavedData: function() {
            StorageHelper.remove('form_data');
        }
    };
    
    // Инициализация всех компонентов
    ManagerModal.init();
    CheckoutEnhancements.init();
    
    // Инициализация автосохранения только если включены куки
    if (navigator.cookieEnabled) {
        AutoSave.init();
    }
    
    // Debounce функция (если не доступна lodash)
    if (typeof _ === 'undefined' || !_.debounce) {
        window._ = window._ || {};
        _.debounce = function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = function() {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        };
    }
    
    // Глобальные стили для уведомлений и прогресс-бара
    const dynamicStyles = `
        <style>
        .checkout-progress {
            width: 100%;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        
        .checkout-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007cba;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 300px;
            animation: slideInRight 0.3s ease;
        }
        
        .checkout-notification.success { background: #28a745; }
        .checkout-notification.error { background: #dc3545; }
        .checkout-notification.warning { background: #ffc107; color: #333; }
        
        .checkout-notification.hiding {
            animation: slideOutRight 0.3s ease;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            float: right;
            font-size: 18px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .form-row.focused {
            transform: scale(1.02);
            transition: transform 0.2s ease;
        }
        
        .form-row input.valid,
        .form-row select.valid {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1) !important;
        }
        
        .form-row input.error,
        .form-row select.error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }
        </style>
    `;
    
    $('head').append(dynamicStyles);
});