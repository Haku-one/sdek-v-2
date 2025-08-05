<?php
/**
 * Функции для изменения порядка блоков в классическом чекауте WooCommerce
 * 
 * Меняет местами блоки "Детали" и "Ваш заказ" в правой колонке чекаута
 * 
 * Текущий порядок:
 * 1. Оплата и доставка (левая колонка)
 * 2. Детали (правая колонка, сверху)  
 * 3. Ваш заказ (правая колонка, снизу)
 * 
 * Новый порядок:
 * 1. Оплата и доставка (левая колонка)
 * 2. Ваш заказ (правая колонка, сверху)
 * 3. Детали (правая колонка, снизу)
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для управления порядком блоков в чекауте
 */
class WooCommerce_Checkout_Blocks_Reorder {
    
    public function __construct() {
        // Инициализируем хуки только для классического чекаута
        add_action('wp_head', array($this, 'add_checkout_reorder_styles'));
        add_action('wp_footer', array($this, 'add_checkout_reorder_scripts'));
        
        // Хук для совместимости с AJAX обновлениями чекаута
        add_action('woocommerce_checkout_update_order_review', array($this, 'handle_checkout_ajax_update'));
        
        // Дополнительный хук для принудительного применения изменений
        add_action('woocommerce_checkout_init', array($this, 'ensure_blocks_reordering'));
    }
    
    /**
     * Добавляет CSS стили для перестановки блоков через flexbox
     */
    public function add_checkout_reorder_styles() {
        // Применяем только на странице чекаута
        if (!is_checkout() || is_admin()) {
            return;
        }
        ?>
        <style id="checkout-blocks-reorder-css">
        /* Стили для перестановки блоков "Детали" и "Ваш заказ" в классическом чекауте */
        
        /* Основной контейнер чекаута */
        .woocommerce-checkout #order_review_heading,
        .woocommerce-checkout #order_review {
            /* Делаем блок "Ваш заказ" более приоритетным для flexbox */
            order: -1;
        }
        
        /* Блок с деталями заказа (customer details) */
        .woocommerce-checkout .col2-set,
        .woocommerce-checkout #customer_details {
            /* Понижаем приоритет блока с деталями */
            order: 1;
        }
        
        /* Правая колонка чекаута */
        .woocommerce-checkout .col-2 {
            display: flex;
            flex-direction: column;
        }
        
        /* Заголовок "Ваш заказ" */
        .woocommerce-checkout #order_review_heading {
            order: -2;
            margin-bottom: 1em;
        }
        
        /* Таблица заказа */
        .woocommerce-checkout #order_review {
            order: -1;
            margin-bottom: 2em;
        }
        
        /* Все остальные элементы в правой колонке (включая детали) */
        .woocommerce-checkout .col-2 > *:not(#order_review_heading):not(#order_review) {
            order: 1;
        }
        
        /* Специальные стили для различных тем */
        
        /* Для тем с классом checkout-layout */
        .checkout-layout .woocommerce-checkout .col-2,
        .woocommerce-checkout-layout .col-2 {
            display: flex !important;
            flex-direction: column !important;
        }
        
        /* Для тем Storefront и Twenty Twenty */
        .storefront .woocommerce-checkout .col-2,
        .theme-twentytwenty .woocommerce-checkout .col-2,
        .theme-twentytwentyone .woocommerce-checkout .col-2,
        .theme-twentytwentytwo .woocommerce-checkout .col-2 {
            display: flex !important;
            flex-direction: column !important;
        }
        
        /* Альтернативный подход через CSS Grid для более сложных макетов */
        @supports (display: grid) {
            .woocommerce-checkout .col-2 {
                display: grid !important;
                grid-template-rows: auto auto auto;
                gap: 1em;
            }
            
            .woocommerce-checkout #order_review_heading {
                grid-row: 1;
            }
            
            .woocommerce-checkout #order_review {
                grid-row: 2;
            }
            
            .woocommerce-checkout .col-2 > *:not(#order_review_heading):not(#order_review) {
                grid-row: 3;
            }
        }
        
        /* Обеспечиваем корректное отображение на мобильных устройствах */
        @media (max-width: 768px) {
            .woocommerce-checkout .col-2 {
                display: flex !important;
                flex-direction: column !important;
            }
            
            .woocommerce-checkout #order_review_heading {
                order: -2 !important;
            }
            
            .woocommerce-checkout #order_review {
                order: -1 !important;
                margin-bottom: 1.5em !important;
            }
        }
        
        /* Стили для плавного перехода */
        .woocommerce-checkout .col-2 > * {
            transition: all 0.3s ease;
        }
        
        /* Дополнительные стили для лучшей визуализации */
        .woocommerce-checkout #order_review {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .woocommerce-checkout #order_review_heading {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 0.5em;
        }
        </style>
        <?php
    }
    
    /**
     * Добавляет JavaScript для более надежной перестановки элементов
     */
    public function add_checkout_reorder_scripts() {
        // Применяем только на странице чекаута
        if (!is_checkout() || is_admin()) {
            return;
        }
        ?>
        <script id="checkout-blocks-reorder-js">
        (function($) {
            'use strict';
            
            // Объект для управления перестановкой блоков
            var CheckoutBlocksReorder = {
                
                // Инициализация
                init: function() {
                    this.reorderBlocks();
                    this.bindEvents();
                    this.observeChanges();
                },
                
                // Основная функция перестановки блоков
                reorderBlocks: function() {
                    var self = this;
                    
                    // Ждем полной загрузки DOM
                    $(document).ready(function() {
                        self.performReorder();
                    });
                    
                    // Также выполняем после загрузки всех ресурсов
                    $(window).on('load', function() {
                        setTimeout(function() {
                            self.performReorder();
                        }, 100);
                    });
                },
                
                // Выполняет физическую перестановку элементов
                performReorder: function() {
                    var $checkoutForm = $('.woocommerce-checkout');
                    if ($checkoutForm.length === 0) {
                        return;
                    }
                    
                    var $rightColumn = $checkoutForm.find('.col-2');
                    if ($rightColumn.length === 0) {
                        return;
                    }
                    
                    var $orderReviewHeading = $('#order_review_heading');
                    var $orderReview = $('#order_review');
                    
                    if ($orderReviewHeading.length && $orderReview.length) {
                        // Перемещаем блок "Ваш заказ" в начало правой колонки
                        $rightColumn.prepend($orderReviewHeading);
                        $rightColumn.prepend($orderReview);
                        
                        // Добавляем класс для CSS стилизации
                        $rightColumn.addClass('checkout-blocks-reordered');
                        
                        // Логируем успешную перестановку
                        if (window.console && console.log) {
                            console.log('✅ Checkout blocks reordered: "Ваш заказ" moved to top');
                        }
                    }
                },
                
                // Привязка событий для совместимости с AJAX
                bindEvents: function() {
                    var self = this;
                    
                    // Обработка стандартного обновления чекаута WooCommerce
                    $('body').on('updated_checkout', function() {
                        setTimeout(function() {
                            self.performReorder();
                        }, 50);
                    });
                    
                    // Обработка AJAX событий
                    $(document).ajaxComplete(function(event, xhr, settings) {
                        if (settings.url && settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
                            setTimeout(function() {
                                self.performReorder();
                            }, 100);
                        }
                    });
                    
                    // Обработка изменений в форме оплаты
                    $('body').on('payment_method_selected', function() {
                        setTimeout(function() {
                            self.performReorder();
                        }, 50);
                    });
                },
                
                // Наблюдение за изменениями DOM для автоматической корректировки
                observeChanges: function() {
                    var self = this;
                    
                    // Используем MutationObserver если доступен
                    if (window.MutationObserver) {
                        var observer = new MutationObserver(function(mutations) {
                            var shouldReorder = false;
                            
                            mutations.forEach(function(mutation) {
                                if (mutation.type === 'childList') {
                                    // Проверяем, изменились ли элементы чекаута
                                    var $target = $(mutation.target);
                                    if ($target.closest('.woocommerce-checkout').length > 0) {
                                        shouldReorder = true;
                                    }
                                }
                            });
                            
                            if (shouldReorder) {
                                setTimeout(function() {
                                    self.performReorder();
                                }, 100);
                            }
                        });
                        
                        // Наблюдаем за изменениями в области чекаута
                        var $checkoutArea = $('.woocommerce-checkout');
                        if ($checkoutArea.length > 0) {
                            observer.observe($checkoutArea[0], {
                                childList: true,
                                subtree: true
                            });
                        }
                    }
                }
            };
            
            // Запускаем перестановку блоков
            CheckoutBlocksReorder.init();
            
            // Глобальная функция для принудительной перестановки (для отладки)
            window.forceCheckoutReorder = function() {
                CheckoutBlocksReorder.performReorder();
            };
            
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Обработка AJAX обновлений чекаута
     */
    public function handle_checkout_ajax_update($posted_data) {
        // Этот хук вызывается при каждом AJAX обновлении чекаута
        // Здесь можно добавить дополнительную логику если необходимо
        
        // Добавляем флаг для JavaScript о том, что произошло обновление
        if (!is_admin()) {
            add_action('wp_footer', function() {
                echo '<script>if(window.forceCheckoutReorder) { setTimeout(window.forceCheckoutReorder, 150); }</script>';
            }, 99);
        }
    }
    
    /**
     * Обеспечивает применение перестановки блоков при инициализации чекаута
     */
    public function ensure_blocks_reordering($checkout) {
        // Добавляем дополнительный CSS класс для идентификации
        add_filter('body_class', function($classes) {
            if (is_checkout()) {
                $classes[] = 'checkout-blocks-reorder-active';
            }
            return $classes;
        });
    }
}

// Инициализация только если WooCommerce активен
if (class_exists('WooCommerce')) {
    new WooCommerce_Checkout_Blocks_Reorder();
}

/**
 * Дополнительные функции для совместимости с различными темами
 */

/**
 * Добавляет совместимость с популярными темами
 */
function add_theme_specific_checkout_compatibility() {
    $theme = get_template();
    
    switch ($theme) {
        case 'storefront':
            add_action('wp_head', function() {
                if (is_checkout()) {
                    echo '<style>.storefront .woocommerce-checkout .col-2 { display: flex !important; flex-direction: column !important; }</style>';
                }
            });
            break;
            
        case 'astra':
            add_action('wp_head', function() {
                if (is_checkout()) {
                    echo '<style>.ast-theme .woocommerce-checkout .col-2 { display: flex !important; flex-direction: column !important; }</style>';
                }
            });
            break;
            
        case 'oceanwp':
            add_action('wp_head', function() {
                if (is_checkout()) {
                    echo '<style>.oceanwp-theme .woocommerce-checkout .col-2 { display: flex !important; flex-direction: column !important; }</style>';
                }
            });
            break;
    }
}
add_action('after_setup_theme', 'add_theme_specific_checkout_compatibility');

/**
 * Функция для отладки - показывает структуру чекаута в консоли
 */
function debug_checkout_structure() {
    if (!is_checkout() || !current_user_can('manage_options')) {
        return;
    }
    ?>
    <script>
    console.log('🔍 Checkout Debug Info:');
    console.log('Theme:', '<?php echo get_template(); ?>');
    console.log('WooCommerce Version:', '<?php echo WC()->version ?? "Unknown"; ?>');
    console.log('Checkout Elements:', {
        rightColumn: $('.woocommerce-checkout .col-2').length,
        orderReviewHeading: $('#order_review_heading').length,
        orderReview: $('#order_review').length,
        customerDetails: $('#customer_details').length
    });
    </script>
    <?php
}
add_action('wp_footer', 'debug_checkout_structure');

/**
 * Шорткод для принудительной активации перестановки блоков
 * Использование: [force_checkout_reorder]
 */
function force_checkout_reorder_shortcode($atts) {
    if (!is_checkout()) {
        return '';
    }
    
    $atts = shortcode_atts(array(
        'message' => 'Применение перестановки блоков чекаута...'
    ), $atts);
    
    ob_start();
    ?>
    <script>
    jQuery(document).ready(function($) {
        if (window.console && console.log) {
            console.log('<?php echo esc_js($atts['message']); ?>');
        }
        if (window.forceCheckoutReorder) {
            window.forceCheckoutReorder();
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('force_checkout_reorder', 'force_checkout_reorder_shortcode');