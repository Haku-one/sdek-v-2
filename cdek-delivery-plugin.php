<?php
/**
 * Plugin Name: СДЭК Доставка для WooCommerce
 * Plugin URI: https://yoursite.com
 * Description: Плагин для интеграции доставки СДЭК с упрощенной формой адреса и картой пунктов выдачи
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: cdek-delivery
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Проверяем, активен ли WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('CDEK_DELIVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDEK_DELIVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CDEK_DELIVERY_VERSION', '1.0.0');

// Основной класс плагина
class CdekDeliveryPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Хуки для настройки полей адреса
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_filter('woocommerce_default_address_fields', array($this, 'customize_address_fields'));
        
        // Хуки для СДЭК
        add_action('woocommerce_shipping_init', array($this, 'init_cdek_shipping'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_cdek_shipping_method'));
        
        // AJAX обработчики
        add_action('wp_ajax_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_nopriv_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        add_action('wp_ajax_nopriv_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        
        // Обработчик для обновления стоимости доставки
        add_action('wp_ajax_update_cdek_shipping_cost', array($this, 'ajax_update_shipping_cost'));
        add_action('wp_ajax_nopriv_update_cdek_shipping_cost', array($this, 'ajax_update_shipping_cost'));
        
        // Хук для обработки стандартного обновления чекаута
        add_action('woocommerce_checkout_update_order_review', array($this, 'handle_checkout_update_order_review'));
        
        // Хук для обновления суммы заказа ПЕРЕД инициализацией платежа
        add_action('woocommerce_checkout_process', array($this, 'update_order_total_before_payment'), 5);
        add_filter('woocommerce_calculated_total', array($this, 'filter_calculated_total'), 10, 2);
        
        // Хук для обновления заказа после создания, но до платежа
        add_action('woocommerce_checkout_order_processed', array($this, 'update_order_after_creation'), 10, 3);
        
        // Регистрация настроек плагина
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Сохранение данных о выбранном пункте выдачи
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // Отображение информации о доставке в письмах и личном кабинете
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_cdek_info_in_order_details'));
        add_action('woocommerce_email_order_details', array($this, 'display_cdek_info_in_email'), 10, 4);
        
        // Хуки для трекинга статуса заказа СДЭК
        add_action('woocommerce_order_status_changed', array($this, 'track_order_status_change'), 10, 4);
        add_action('wp', array($this, 'schedule_cdek_status_check'));
        add_action('cdek_check_order_status', array($this, 'check_cdek_order_status'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // AJAX для тестирования email уведомлений
        add_action('wp_ajax_test_cdek_email_notification', array($this, 'ajax_test_email_notification'));
        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Скрытие ненужных полей через CSS
        add_action('wp_head', array($this, 'hide_checkout_fields_css'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Добавляем габариты в описание товара в корзине
        add_filter('woocommerce_get_item_data', array($this, 'add_dimensions_to_cart_item'), 10, 2);
        
        // Хуки для классического чекаута - ТОЛЬКО ОДИН хук для избежания дублирования
        add_action('woocommerce_checkout_after_customer_details', array($this, 'add_cdek_map_alternative_position'));
        
        // Шорткод для ручного размещения карты
        add_shortcode('cdek_delivery_map', array($this, 'cdek_delivery_map_shortcode'));
        
        // Дополнительные хуки для классического чекаута
        add_action('wp_head', array($this, 'add_classic_checkout_styles'));
        add_action('woocommerce_checkout_process', array($this, 'validate_cdek_point_selection'));
        add_filter('woocommerce_shipping_calculator_enable_city', '__return_false');
        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
        
        // Хук для обновления стоимости доставки
        add_filter('woocommerce_package_rates', array($this, 'update_cdek_shipping_rates'), 10, 2);
    }
    
    public function init() {
        load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            // Используем только классический чекаут
            $yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            
            // Проверяем, не загружены ли уже Яндекс.Карты
            if (!wp_script_is('yandex-maps', 'enqueued') && !wp_script_is('yandex-maps', 'done')) {
                // Формируем URL с обработкой ошибок
                $yandex_maps_url = 'https://api-maps.yandex.ru/2.1/?' . http_build_query(array(
                    'apikey' => $yandex_api_key,
                    'lang' => 'ru_RU',
                    'load' => 'package.full'
                ));
                
                wp_enqueue_script('yandex-maps', $yandex_maps_url, array(), CDEK_DELIVERY_VERSION, true);
                
                // Добавляем обработку ошибок загрузки
                wp_add_inline_script('yandex-maps', '
                    window.yandexMapsLoadError = false;
                    window.addEventListener("error", function(e) {
                        if (e.target && e.target.src && e.target.src.includes("api-maps.yandex.ru")) {
                            window.yandexMapsLoadError = true;
                            console.warn("Ошибка загрузки Яндекс.Карт:", e);
                        }
                    });
                ', 'before');
            }
            
            // Загружаем только JS для классического чекаута
            wp_enqueue_script('cdek-delivery-classic-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery-classic.js', array('jquery'), CDEK_DELIVERY_VERSION, true);
            
            // Добавляем скрипт для автозаполнения textarea полей
         
            
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), CDEK_DELIVERY_VERSION);
           
           
            wp_localize_script('cdek-delivery-classic-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce'),
                'yandex_api_key' => $yandex_api_key,
                'is_block_checkout' => false
            ));
            
            wp_localize_script('textarea-auto-fill', 'textarea_auto_fill', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('textarea_auto_fill_nonce')
            ));
        }
    }
    
    public function customize_checkout_fields($fields) {
        // Меняем метку для поля адреса
        $fields['shipping']['shipping_address_1']['label'] = 'Город доставки';
        $fields['shipping']['shipping_address_1']['placeholder'] = 'Например: Москва';
        $fields['shipping']['shipping_address_1']['required'] = true;
        
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        // Настраиваем поле адреса
        $fields['address_1']['label'] = 'Город доставки';
        $fields['address_1']['placeholder'] = 'Например: Москва';
        $fields['address_1']['required'] = true;
        
        return $fields;
    }
    
    public function init_cdek_shipping() {
        if (!class_exists('WC_Cdek_Shipping_Method')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-cdek-shipping-method.php';
        }
        
        // Инициализируем email уведомления
        if (!class_exists('CdekEmailNotifications')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-cdek-email-notifications.php';
            new CdekEmailNotifications();
        }
    }
    
    public function add_cdek_shipping_method($methods) {
        $methods['cdek_delivery'] = 'WC_Cdek_Shipping_Method';
        return $methods;
    }
    
    public function ajax_get_cdek_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $address = sanitize_text_field($_POST['address']);
        
        $cdek_api = new CdekAPI();
        $points = $cdek_api->get_delivery_points($address);
        
        // Логируем результат
        error_log('СДЭК AJAX: Получено пунктов: ' . count($points));
        
        wp_send_json_success($points);
    }
    
    public function ajax_calculate_delivery_cost() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $point_code = sanitize_text_field($_POST['point_code']);
        $point_data = json_decode(stripslashes($_POST['point_data']), true);
        $cart_weight = floatval($_POST['cart_weight']);
        $cart_dimensions = json_decode(stripslashes($_POST['cart_dimensions']), true);
        $cart_value = floatval($_POST['cart_value']);
        $has_real_dimensions = intval($_POST['has_real_dimensions']);
        
        // Проверяем, что у нас есть все необходимые данные
        if (empty($point_code)) {
            error_log('СДЭК расчет: Не указан код пункта выдачи');
            wp_send_json_error('Не указан код пункта выдачи');
            return;
        }
        
        if (empty($cart_dimensions) || !isset($cart_dimensions['length']) || !isset($cart_dimensions['width']) || !isset($cart_dimensions['height'])) {
            error_log('СДЭК расчет: Некорректные габариты товара');
            wp_send_json_error('Некорректные габариты товара');
            return;
        }
        
        $cdek_api = new CdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        if ($cost_data && isset($cost_data['delivery_sum']) && $cost_data['delivery_sum'] > 0) {
            // API расчет успешен
            $cost_data['api_success'] = true;
            wp_send_json_success($cost_data);
        } else {
            // API не смог рассчитать - проверяем, не слишком ли большой заказ
            $packages_count = isset($_POST['packages_count']) ? intval($_POST['packages_count']) : 1;
            
            if ($packages_count >= 5) {
                // Для больших заказов используем приблизительный расчет
                error_log('СДЭК расчет: API не справился с большим заказом (' . $packages_count . ' коробок). Используем приблизительный расчет.');
                
                $fallback_cost = $this->calculate_fallback_cost($cart_weight, $cart_value, $packages_count, $point_data);
                
                if ($fallback_cost > 0) {
                    wp_send_json_success(array(
                        'delivery_sum' => $fallback_cost,
                        'api_success' => false,
                        'fallback_used' => true,
                        'message' => 'Приблизительная стоимость для большого заказа'
                    ));
                } else {
                    wp_send_json_error(array(
                        'message' => 'Не удалось рассчитать стоимость доставки для большого заказа',
                        'api_response' => $cost_data
                    ));
                }
            } else {
                // Для обычных заказов - ошибка API
                wp_send_json_error(array(
                    'message' => 'API СДЭК недоступен, расчет стоимости невозможен',
                    'api_response' => $cost_data
                ));
            }
        }
    }
    
    /**
     * Приблизительный расчет стоимости доставки для больших заказов
     */
    private function calculate_fallback_cost($cart_weight, $cart_value, $packages_count, $point_data) {
        error_log('СДЭК fallback: Расчет для веса: ' . $cart_weight . 'г, стоимости: ' . $cart_value . 'руб, коробок: ' . $packages_count);
        
        // Базовая стоимость за первую коробку (примерно как 1 коробка через API)
        $base_cost = 350; // Базовая стоимость доставки в рублях
        
        // Дополнительная стоимость за каждую дополнительную коробку
        $additional_cost_per_package = 150;
        
        // Коэффициент в зависимости от региона
        $region_multiplier = 1.0;
        
        // Определяем регион по данным пункта
        if (isset($point_data['location']['city'])) {
            $city = strtolower($point_data['location']['city']);
            
            // Москва и область
            if (strpos($city, 'москв') !== false) {
                $region_multiplier = 1.2;
            }
            // Санкт-Петербург и область  
            elseif (strpos($city, 'петербург') !== false || strpos($city, 'спб') !== false) {
                $region_multiplier = 1.3;
            }
            // Дальние регионы
            elseif (strpos($city, 'владивосток') !== false || strpos($city, 'хабаровск') !== false || 
                    strpos($city, 'магадан') !== false || strpos($city, 'южно-сахалинск') !== false) {
                $region_multiplier = 2.5;
            }
            // Сибирь
            elseif (strpos($city, 'новосибирск') !== false || strpos($city, 'красноярск') !== false || 
                    strpos($city, 'иркутск') !== false || strpos($city, 'омск') !== false) {
                $region_multiplier = 1.8;
            }
            // Урал
            elseif (strpos($city, 'екатеринбург') !== false || strpos($city, 'челябинск') !== false || 
                    strpos($city, 'пермь') !== false || strpos($city, 'уфа') !== false) {
                $region_multiplier = 1.4;
            }
            // Юг России
            elseif (strpos($city, 'ростов') !== false || strpos($city, 'краснодар') !== false || 
                    strpos($city, 'волгоград') !== false || strpos($city, 'астрахань') !== false) {
                $region_multiplier = 1.3;
            }
        }
        
        // Рассчитываем базовую стоимость
        $total_cost = $base_cost;
        
        // Добавляем стоимость за дополнительные коробки
        if ($packages_count > 1) {
            $total_cost += ($packages_count - 1) * $additional_cost_per_package;
        }
        
        // Применяем региональный коэффициент
        $total_cost = round($total_cost * $region_multiplier);
        
        // Коэффициент за большой вес (свыше 10 кг)
        if ($cart_weight > 10000) { // 10 кг в граммах
            $weight_multiplier = 1 + (($cart_weight - 10000) / 50000); // +1% за каждые 500г свыше 10кг
            $total_cost = round($total_cost * $weight_multiplier);
        }
        
        // Минимальная стоимость
        $total_cost = max($total_cost, 300);
        
        // Максимальная стоимость (разумное ограничение)
        $total_cost = min($total_cost, 5000);
        
        error_log('СДЭК fallback: Рассчитанная стоимость: ' . $total_cost . ' руб. (регион: x' . $region_multiplier . ', коробок: ' . $packages_count . ')');
        
        return $total_cost;
    }
    
    public function ajax_get_address_suggestions() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $search = sanitize_text_field($_POST['search']);
        
        // Генерируем предложения адресов
        $suggestions = $this->generate_address_suggestions($search);
        
        wp_send_json_success($suggestions);
    }
    
    private function generate_address_suggestions($search) {
        $suggestions = array();
        $search_lower = mb_strtolower($search);
        
        // Список российских городов
        $cities = array(
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'
        );
        
        foreach ($cities as $city) {
            if (mb_strpos(mb_strtolower($city), $search_lower) !== false) {
                $suggestions[] = array(
                    'value' => $city,
                    'text' => $city,
                    'city' => $city,
                    'street' => ''
                );
            }
        }
        
        return array_slice($suggestions, 0, 10);
    }
    
    public function display_product_dimensions_checkout() {
        // Проверяем, что мы на странице чекаута и WooCommerce загружен
        if (!is_checkout() || !WC()->cart) {
            return;
        }
        
        // Получаем товары из корзины
        $cart_items = WC()->cart->get_cart();
        
        if (empty($cart_items)) {
            return;
        }
        
        echo '<div id="product-dimensions-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: block !important;">';
        echo '<h4>📦 Габариты товаров в заказе:</h4>';
        echo '<div class="dimensions-list">';
        
        $has_dimensions = false;
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            // Получаем габариты товара
            $length = $product->get_length();
            $width = $product->get_width(); 
            $height = $product->get_height();
            $weight = $product->get_weight();
            
            // Если хотя бы один из размеров указан, выводим товар
            if ($length || $width || $height || $weight) {
                $has_dimensions = true;
                
                echo '<div class="product-dimensions" style="margin-bottom: 10px; padding: 8px; background: white; border: 1px solid #e0e0e0; border-radius: 3px;">';
                echo '<strong>' . $product->get_name() . '</strong>';
                if ($quantity > 1) {
                    echo ' <span style="color: #666;">(×' . $quantity . ')</span>';
                }
                echo '<br>';
                echo '<span style="color: #666; font-size: 14px;">';
                
                // Выводим габариты если они есть
                if ($length && $width && $height) {
                    echo '📏 Габариты: ' . $length . '×' . $width . '×' . $height . ' см';
                } else {
                    // Выводим те размеры что есть
                    $dimensions = array();
                    if ($length) $dimensions[] = 'Д: ' . $length . 'см';
                    if ($width) $dimensions[] = 'Ш: ' . $width . 'см';
                    if ($height) $dimensions[] = 'В: ' . $height . 'см';
                    if (!empty($dimensions)) {
                        echo '📏 ' . implode(' | ', $dimensions);
                    }
                }
                
                // Выводим вес если он есть
                if ($weight) {
                    if ($length || $width || $height) {
                        echo ' | ';
                    }
                    echo '⚖️ Вес: ' . $weight;
                    // Определяем единицы измерения
                    if (get_option('woocommerce_weight_unit') === 'kg') {
                        echo ' кг';
                    } else {
                        echo ' г';
                    }
                }
                
                echo '</span>';
                echo '</div>';
            }
        }
        
        // Если ни у одного товара нет габаритов, показываем сообщение
        if (!$has_dimensions) {
            echo '<div style="padding: 15px; background: #f8d7da; border: 2px solid #f5c6cb; border-radius: 5px; color: #721c24; margin-bottom: 15px;">';
            echo '❌ <strong>Ошибка:</strong> У товаров в корзине не указаны габариты (Д×Ш×В) и вес.<br>';
            echo '📋 <strong>Для расчета доставки СДЭК необходимо:</strong><br>';
            echo '• Указать точные габариты (длина, ширина, высота) в сантиметрах<br>';
            echo '• Указать вес товара в граммах<br>';
            echo '• Все поля должны быть заполнены в настройках товара WooCommerce<br><br>';
            echo '💡 <strong>Без этих данных расчет стоимости доставки НЕВОЗМОЖЕН!</strong>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Добавляем скрытые поля с данными для JavaScript
        echo '<div id="wc-cart-data" style="display: none;">';
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            echo '<div class="cart-item-data" ';
            echo 'data-product-id="' . $product->get_id() . '" ';
            echo 'data-quantity="' . $quantity . '" ';
            echo 'data-length="' . ($product->get_length() ?: 0) . '" ';
            echo 'data-width="' . ($product->get_width() ?: 0) . '" ';
            echo 'data-height="' . ($product->get_height() ?: 0) . '" ';
            echo 'data-weight="' . ($product->get_weight() ?: 0) . '" ';
            echo 'data-price="' . $product->get_price() . '"';
            echo '></div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    public function add_dimensions_to_cart_item($item_data, $cart_item) {
        $product = $cart_item['data'];
        
        // Получаем габариты товара
        $length = $product->get_length();
        $width = $product->get_width(); 
        $height = $product->get_height();
        
        // Если есть габариты, добавляем их в метаданные
        if ($length && $width && $height) {
            $item_data[] = array(
                'name' => 'Габариты (Д×Ш×В)',
                'value' => $length . '×' . $width . '×' . $height . ' см'
            );
        }
        
        return $item_data;
    }
    
    public function hide_checkout_fields_css() {
        if (is_checkout()) {
            // Убираем пустой style tag, который может вызывать проблемы с headers
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Настройки СДЭК',
            'СДЭК Доставка',
            'manage_options',
            'cdek-delivery-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        include_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
    }
    
    public function save_cdek_point_data($order_id) {
        // Получаем тип доставки из POST или из сессии
        $delivery_type = 'cdek'; // По умолчанию
        
        if (isset($_POST['cdek_delivery_type'])) {
            $delivery_type = sanitize_text_field($_POST['cdek_delivery_type']);
        } else {
            // Если в POST нет, берем из сессии
            $session_delivery_type = WC()->session->get('cdek_delivery_type');
            if ($session_delivery_type) {
                $delivery_type = $session_delivery_type;
            }
        }
        
        // Сохраняем тип доставки
        update_post_meta($order_id, '_cdek_delivery_type', $delivery_type);
        
        // Сохраняем данные пункта выдачи только для доставки СДЭК
        if ($delivery_type === 'cdek') {
            if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
                update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_selected_point_code']));
            } else {
                // Если в POST нет, берем из сессии
                $session_point_code = WC()->session->get('cdek_selected_point_code');
                if ($session_point_code) {
                    update_post_meta($order_id, '_cdek_point_code', $session_point_code);
                }
            }
            
            if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
                $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
                if ($point_data) {
                    update_post_meta($order_id, '_cdek_point_data', $point_data);
                }
            } else {
                // Если в POST нет, берем из сессии
                $session_point_data = WC()->session->get('cdek_selected_point_data');
                if ($session_point_data) {
                    update_post_meta($order_id, '_cdek_point_data', $session_point_data);
                }
            }
        }
        
        // Логируем для отладки
        error_log('СДЭК: Сохранен заказ #' . $order_id . ' с типом доставки: ' . $delivery_type);
    }

    public function display_cdek_point_in_admin($order) {
        $delivery_type = get_post_meta($order->get_id(), '_cdek_delivery_type', true);
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        // Показываем информацию о доставке в зависимости от типа
        if ($delivery_type) {
            echo '<div class="cdek-delivery-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h4>Информация о доставке:</h4>';
            
            switch ($delivery_type) {
                case 'pickup':
                    echo '<strong>📍 Самовывоз</strong><br>';
                    echo 'Адрес: г.Саратов, ул. Осипова, д. 18а<br>';
                    echo 'Стоимость: Бесплатно';
                    break;
                    
                case 'manager':
                    echo '<strong>📞 Обсудить доставку с менеджером</strong><br>';
                    echo 'Стоимость: Бесплатно';
                    break;
                    
                case 'cdek':
                default:
                    if ($point_code && $point_data) {
                        echo '<strong>🚚 Пункт выдачи СДЭК:</strong><br>';
                        echo '<strong>' . esc_html($point_data['name']) . '</strong><br>';
                        echo 'Код: ' . esc_html($point_code) . '<br>';
                        if (isset($point_data['location']['address_full'])) {
                            echo 'Адрес: ' . esc_html($point_data['location']['address_full']) . '<br>';
                        }
                        if (isset($point_data['phones']) && is_array($point_data['phones']) && !empty($point_data['phones'])) {
                            $phone_numbers = array();
                            foreach ($point_data['phones'] as $phone) {
                                if (is_array($phone) && isset($phone['number'])) {
                                    $phone_numbers[] = $phone['number'];
                                } else {
                                    $phone_numbers[] = $phone;
                                }
                            }
                            if (!empty($phone_numbers)) {
                                echo 'Телефон: ' . esc_html(implode(', ', $phone_numbers)) . '<br>';
                            }
                        }
                    } else {
                        echo '<strong>🚚 Доставка СДЭК</strong><br>';
                        echo 'Пункт выдачи не выбран';
                    }
                    break;
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Отображение информации о доставке в личном кабинете клиента
     */
    public function display_cdek_info_in_order_details($order) {
        // Проверяем, что заказ существует
        if (!$order || !is_object($order)) {
            return;
        }
        
        $delivery_type = get_post_meta($order->get_id(), '_cdek_delivery_type', true);
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        // Проверяем, что это заказ с доставкой СДЭК
        $shipping_methods = $order->get_shipping_methods();
        $is_cdek_order = false;
        
        foreach ($shipping_methods as $item_id => $item) {
            if (strpos($item->get_method_id(), 'cdek_delivery') !== false) {
                $is_cdek_order = true;
                break;
            }
        }
        
        if (!$is_cdek_order || !$delivery_type) {
            return;
        }
        
        echo '<div class="cdek-delivery-details" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>Информация о получении заказа</h3>';
        
        switch ($delivery_type) {
            case 'pickup':
                echo '<p><strong>📍 Самовывоз</strong></p>';
                echo '<p>Адрес для самовывоза:<br><strong>г.Саратов, ул. Осипова, д. 18а</strong></p>';
                echo '<p>Стоимость: <strong>Бесплатно</strong></p>';
                echo '<p><em>Пожалуйста, свяжитесь с нами для уточнения времени получения заказа.</em></p>';
                break;
                
            case 'manager':
                echo '<p><strong>📞 Доставка по договоренности с менеджером</strong></p>';
                echo '<p>Наш менеджер свяжется с вами для уточнения деталей доставки.</p>';
                echo '<p>Стоимость: <strong>Бесплатно</strong></p>';
                break;
                
            case 'cdek':
            default:
                if ($point_code && $point_data) {
                    echo '<p><strong>🚚 Пункт выдачи СДЭК</strong></p>';
                    echo '<div style="margin-left: 20px;">';
                    echo '<p><strong>' . esc_html($point_data['name']) . '</strong></p>';
                    echo '<p>Код пункта: <strong>' . esc_html($point_code) . '</strong></p>';
                    
                    if (isset($point_data['location']['address_full'])) {
                        echo '<p>Адрес: ' . esc_html($point_data['location']['address_full']) . '</p>';
                    }
                    
                    // Режим работы
                    if (isset($point_data['work_time_list']) && is_array($point_data['work_time_list'])) {
                        echo '<p><strong>Режим работы:</strong><br>';
                        $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                        foreach ($point_data['work_time_list'] as $work_time) {
                            if (isset($work_time['day']) && isset($work_time['time'])) {
                                $day_index = intval($work_time['day']) - 1;
                                if ($day_index >= 0 && $day_index < 7) {
                                    echo $days[$day_index] . ': ' . esc_html($work_time['time']) . '<br>';
                                }
                            }
                        }
                        echo '</p>';
                    }
                    
                    // Телефоны
                    if (isset($point_data['phones']) && is_array($point_data['phones']) && !empty($point_data['phones'])) {
                        $phone_numbers = array();
                        foreach ($point_data['phones'] as $phone) {
                            if (is_array($phone) && isset($phone['number'])) {
                                $phone_numbers[] = $phone['number'];
                            } else {
                                $phone_numbers[] = $phone;
                            }
                        }
                        if (!empty($phone_numbers)) {
                            echo '<p>Телефон: ' . esc_html(implode(', ', $phone_numbers)) . '</p>';
                        }
                    }
                    
                    echo '</div>';
                    echo '<p><em>Заказ будет доставлен в выбранный пункт выдачи. После прибытия вы получите SMS-уведомление.</em></p>';
                } else {
                    echo '<p><strong>🚚 Доставка СДЭК</strong></p>';
                    echo '<p>Пункт выдачи: не выбран</p>';
                }
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Отображение информации о доставке в email уведомлениях
     */
    public function display_cdek_info_in_email($order, $sent_to_admin, $plain_text, $email) {
        // Проверяем, что заказ существует
        if (!$order || !is_object($order)) {
            return;
        }
        
        
        
        $delivery_type = get_post_meta($order->get_id(), '_cdek_delivery_type', true);
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        // Проверяем, что это заказ с доставкой СДЭК
        $shipping_methods = $order->get_shipping_methods();
        $is_cdek_order = false;
        
        foreach ($shipping_methods as $item_id => $item) {
            if (strpos($item->get_method_id(), 'cdek_delivery') !== false) {
                $is_cdek_order = true;
                break;
            }
        }
        
        if (!$is_cdek_order || !$delivery_type) {
            return;
        }
        
        if ($plain_text) {
            // Версия для обычного текста
            echo "\n" . "ИНФОРМАЦИЯ О ПОЛУЧЕНИИ ЗАКАЗА" . "\n";
            echo str_repeat('-', 40) . "\n";
            
            switch ($delivery_type) {
                case 'pickup':
                    echo "Самовывоз" . "\n";
                    echo "Адрес: г.Саратов, ул. Осипова, д. 18а" . "\n";
                    echo "Стоимость: Бесплатно" . "\n";
                    break;
                    
                case 'manager':
                    echo "Доставка по договоренности с менеджером" . "\n";
                    echo "Наш менеджер свяжется с вами для уточнения деталей доставки." . "\n";
                    echo "Стоимость: Бесплатно" . "\n";
                    break;
                    
                case 'cdek':
                default:
                    if ($point_code && $point_data) {
                        echo "Пункт выдачи СДЭК" . "\n";
                        echo "Название: " . $point_data['name'] . "\n";
                        echo "Код пункта: " . $point_code . "\n";
                        if (isset($point_data['location']['address_full'])) {
                            echo "Адрес: " . $point_data['location']['address_full'] . "\n";
                        }
                    }
                    break;
            }
            echo "\n";
        } else {
            // HTML версия
            echo '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;">';
            echo '<h3 style="margin-top: 0;">Информация о получении заказа</h3>';
            
            switch ($delivery_type) {
                case 'pickup':
                    echo '<p><strong>📍 Самовывоз</strong></p>';
                    echo '<p>Адрес: <strong>г.Саратов, ул. Осипова, д. 18а</strong></p>';
                    echo '<p>Стоимость: <strong>Бесплатно</strong></p>';
                    break;
                    
                case 'manager':
                    echo '<p><strong>📞 Доставка по договоренности с менеджером</strong></p>';
                    echo '<p>Наш менеджер свяжется с вами для уточнения деталей доставки.</p>';
                    echo '<p>Стоимость: <strong>Бесплатно</strong></p>';
                    break;
                    
                case 'cdek':
                default:
                    if ($point_code && $point_data) {
                        echo '<p><strong>🚚 Пункт выдачи СДЭК</strong></p>';
                        echo '<p><strong>' . esc_html($point_data['name']) . '</strong></p>';
                        echo '<p>Код пункта: <strong>' . esc_html($point_code) . '</strong></p>';
                        if (isset($point_data['location']['address_full'])) {
                            echo '<p>Адрес: ' . esc_html($point_data['location']['address_full']) . '</p>';
                        }
                    }
                    break;
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Отслеживание изменения статуса заказа
     */
    public function track_order_status_change($order_id, $old_status, $new_status, $order) {
        // Проверяем, что это заказ с доставкой СДЭК
        $delivery_type = get_post_meta($order_id, '_cdek_delivery_type', true);
        
        if (!$delivery_type || $delivery_type !== 'cdek') {
            return;
        }
        
        error_log('СДЭК: Изменение статуса заказа #' . $order_id . ' с "' . $old_status . '" на "' . $new_status . '"');
        
        // Если заказ переводится в статус "обработка" или "завершен", создаем заказ в СДЭК
        if ($new_status === 'processing' || $new_status === 'completed') {
            $this->create_cdek_order($order);
        }
        
        // Обновляем мета-данные заказа
        update_post_meta($order_id, '_cdek_last_status_check', current_time('timestamp'));
        update_post_meta($order_id, '_wc_order_status', $new_status);
    }
    
    /**
     * Планирование проверки статуса заказов СДЭК
     */
    public function schedule_cdek_status_check() {
        if (!wp_next_scheduled('cdek_check_order_status')) {
            wp_schedule_event(time(), 'hourly', 'cdek_check_order_status');
        }
    }
    
    /**
     * Проверка статуса заказов СДЭК
     */
    public function check_cdek_order_status() {
        // Получаем заказы со статусом "обработка" за последние 30 дней
        $args = array(
            'limit' => 50,
            'status' => array('processing', 'on-hold'),
            'date_created' => '>' . (time() - (30 * 24 * 60 * 60)),
            'meta_query' => array(
                array(
                    'key' => '_cdek_delivery_type',
                    'value' => 'cdek',
                    'compare' => '='
                )
            )
        );
        
        $orders = wc_get_orders($args);
        
        foreach ($orders as $order) {
            $this->update_order_status_from_cdek($order);
        }
    }
    
    /**
     * Создание заказа в СДЭК
     */
    private function create_cdek_order($order) {
        $order_id = $order->get_id();
        
        // Проверяем, не создан ли уже заказ в СДЭК
        $cdek_order_uuid = get_post_meta($order_id, '_cdek_order_uuid', true);
        if (!empty($cdek_order_uuid)) {
            error_log('СДЭК: Заказ #' . $order_id . ' уже создан в СДЭК с UUID: ' . $cdek_order_uuid);
            return;
        }
        
        $point_code = get_post_meta($order_id, '_cdek_point_code', true);
        $point_data = get_post_meta($order_id, '_cdek_point_data', true);
        
        if (empty($point_code) || empty($point_data)) {
            error_log('СДЭК: Нет данных о пункте выдачи для заказа #' . $order_id);
            return;
        }
        
        // Получаем данные товаров (это упрощенная версия, нужно доработать)
        $packages = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $packages[] = array(
                    'number' => $item->get_id(),
                    'weight' => intval($product->get_weight() * $item->get_quantity() * 1000), // в граммах
                    'length' => intval($product->get_length() ?: 20),
                    'width' => intval($product->get_width() ?: 15),
                    'height' => intval($product->get_height() ?: 10),
                    'comment' => $product->get_name()
                );
            }
        }
        
        if (empty($packages)) {
            error_log('СДЭК: Нет товаров для создания заказа #' . $order_id);
            return;
        }
        
        // Формируем данные заказа для API СДЭК
        $order_data = array(
            'number' => $order_id,
            'tariff_code' => 136, // Пункт выдачи
            'from_location' => array(
                'code' => get_option('cdek_sender_city', '428') // Саратов
            ),
            'to_location' => array(
                'code' => $point_code
            ),
            'packages' => $packages,
            'recipient' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'phones' => array(
                    array('number' => $order->get_billing_phone())
                )
            ),
            'sender' => array(
                'name' => get_bloginfo('name')
            )
        );
        
        // Отправляем запрос в API СДЭК
        $cdek_api = new CdekAPI();
        $response = $cdek_api->create_order($order_data);
        
        if ($response && isset($response['entity']['uuid'])) {
            $cdek_uuid = $response['entity']['uuid'];
            update_post_meta($order_id, '_cdek_order_uuid', $cdek_uuid);
            update_post_meta($order_id, '_cdek_order_created', current_time('timestamp'));
            
            // Добавляем заметку к заказу
            $order->add_order_note('Заказ создан в СДЭК. UUID: ' . $cdek_uuid);
            
            error_log('СДЭК: Заказ #' . $order_id . ' успешно создан в СДЭК с UUID: ' . $cdek_uuid);
        } else {
            error_log('СДЭК: Ошибка создания заказа #' . $order_id . ' в СДЭК: ' . print_r($response, true));
        }
    }
    
    /**
     * Обновление статуса заказа из СДЭК
     */
    private function update_order_status_from_cdek($order) {
        $order_id = $order->get_id();
        $cdek_uuid = get_post_meta($order_id, '_cdek_order_uuid', true);
        
        if (empty($cdek_uuid)) {
            return;
        }
        
        // Проверяем статус в СДЭК
        $cdek_api = new CdekAPI();
        $status_info = $cdek_api->get_order_status($cdek_uuid);
        
        if ($status_info && isset($status_info['statuses'])) {
            $latest_status = end($status_info['statuses']);
            $cdek_status_code = $latest_status['code'];
            $cdek_status_name = $latest_status['name'];
            
            // Сопоставляем статусы СДЭК со статусами WooCommerce
            $new_wc_status = $this->map_cdek_status_to_wc($cdek_status_code);
            
            if ($new_wc_status && $order->get_status() !== $new_wc_status) {
                $order->update_status($new_wc_status, 'Статус обновлен из СДЭК: ' . $cdek_status_name);
                
                // Сохраняем информацию о статусе СДЭК
                update_post_meta($order_id, '_cdek_status_code', $cdek_status_code);
                update_post_meta($order_id, '_cdek_status_name', $cdek_status_name);
                update_post_meta($order_id, '_cdek_last_status_update', current_time('timestamp'));
                
                error_log('СДЭК: Статус заказа #' . $order_id . ' обновлен на "' . $new_wc_status . '" (СДЭК: ' . $cdek_status_name . ')');
            }
        }
    }
    
    /**
     * Сопоставление статусов СДЭК со статусами WooCommerce
     */
    private function map_cdek_status_to_wc($cdek_status_code) {
        $status_map = array(
            'CREATED' => 'processing',           // Создан
            'ACCEPTED' => 'processing',          // Принят
            'READY_FOR_SHIPMENT' => 'processing', // Готов к отгрузке
            'SENT' => 'processing',              // Отправлен
            'IN_TRANSIT' => 'processing',        // В пути
            'DELIVERED' => 'completed',          // Доставлен
            'NOT_DELIVERED' => 'on-hold',        // Не доставлен
            'CANCELED' => 'cancelled'            // Отменен
        );
        
        return isset($status_map[$cdek_status_code]) ? $status_map[$cdek_status_code] : null;
    }
    
    public function ajax_test_cdek_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_connection')) {
            wp_die('Security check failed');
        }
        
        $cdek_api = new CdekAPI();
        $token = $cdek_api->get_auth_token();
        
        if ($token) {
            wp_send_json_success('Подключение к API СДЭК успешно установлено');
        } else {
            wp_send_json_error('Не удалось подключиться к API СДЭК. Проверьте учетные данные.');
        }
    }
    
    public function ajax_test_email_notification() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_email_notification')) {
            wp_die('Security check failed');
        }
        
        // Проверяем, включены ли email уведомления
        if (!get_option('cdek_email_notifications_enabled', 1)) {
            wp_send_json_error('Email уведомления отключены в настройках');
            return;
        }
        
        $type = sanitize_text_field($_POST['type']);
        $admin_email = get_option('cdek_admin_notification_email', get_option('admin_email'));
        $site_name = get_option('cdek_email_from_name', get_bloginfo('name'));
        
        // Создаем тестовые данные заказа
        $test_data = array(
            'order_id' => 'TEST-' . time(),
            'order_number' => 'TEST-' . time(),
            'customer_name' => 'Тестовый Клиент',
            'customer_phone' => '+7 (999) 123-45-67',
            'customer_email' => $admin_email, // Отправляем на email администратора
            'order_total' => '1 500 ₽',
            'site_name' => $site_name,
            'order_date' => date('d.m.Y H:i'),
            'pickup_address' => 'г.Саратов, ул. Осипова, д. 18а',
            'delivery_address' => 'г.Москва, ул. Тестовая, д. 1'
        );
        
        // Дополнительные данные для СДЭК
        if ($type === 'cdek') {
            $test_data['point_name'] = 'СДЭК Пункт выдачи (Тестовый)';
            $test_data['point_code'] = 'MSK123';
            $test_data['point_address'] = 'г.Москва, ул. Тестовая, д. 1, офис 101';
            $test_data['point_info'] = '<p><strong>Режим работы:</strong><br>Пн-Пт: 09:00-18:00<br>Сб-Вс: 10:00-16:00</p><p><strong>Телефон:</strong> +7 (495) 123-45-67</p>';
        }
        
        // Включаем email уведомления
        include_once plugin_dir_path(__FILE__) . 'includes/class-cdek-email-notifications.php';
        $email_notifications = new CdekEmailNotifications();
        
        try {
            switch ($type) {
                case 'pickup':
                    $subject = sprintf('[%s] ТЕСТ - Заказ #%s - Самовывоз', $site_name, $test_data['order_number']);
                    $message = $this->get_test_pickup_template($test_data);
                    break;
                    
                case 'manager':
                    $subject = sprintf('[%s] ТЕСТ - Заказ #%s - Обсуждение доставки', $site_name, $test_data['order_number']);
                    $message = $this->get_test_manager_template($test_data);
                    break;
                    
                case 'cdek':
                    $subject = sprintf('[%s] ТЕСТ - Заказ #%s - Доставка СДЭК', $site_name, $test_data['order_number']);
                    $message = $this->get_test_cdek_template($test_data);
                    break;
                    
                default:
                    wp_send_json_error('Неизвестный тип уведомления');
                    return;
            }
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
            );
            
            $result = wp_mail($admin_email, $subject, $message, $headers);
            
            if ($result) {
                wp_send_json_success('Тестовое письмо отправлено на ' . $admin_email);
            } else {
                wp_send_json_error('Ошибка отправки письма. Проверьте настройки почты WordPress.');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Ошибка: ' . $e->getMessage());
        }
    }
    
    private function get_test_pickup_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>ТЕСТ - Заказ на самовывоз</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; border: 2px solid #dc3545; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .pickup-info { background: #d4edda; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .test-notice { background: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 4px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-notice">
                    <strong>🧪 ЭТО ТЕСТОВОЕ ПИСЬМО</strong>
                </div>
                
                <div class="header">
                    <h1>📍 Заказ оформлен на самовывоз</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <p>Ваш заказ #<?php echo $data['order_number']; ?> успешно оформлен на <strong>самовывоз</strong>.</p>
                    
                    <div class="pickup-info">
                        <h3>📍 Адрес для самовывоза:</h3>
                        <p><strong><?php echo $data['pickup_address']; ?></strong></p>
                        <p><strong>Стоимость:</strong> Бесплатно</p>
                    </div>
                    
                    <p>Это тестовое письмо для проверки работы email уведомлений.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function get_test_manager_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>ТЕСТ - Обсуждение доставки</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; border: 2px solid #dc3545; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .manager-info { background: #d1ecf1; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .test-notice { background: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 4px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-notice">
                    <strong>🧪 ЭТО ТЕСТОВОЕ ПИСЬМО</strong>
                </div>
                
                <div class="header">
                    <h1>📞 Обсуждение доставки с менеджером</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <div class="manager-info">
                        <h3>📞 Что происходит дальше:</h3>
                        <p><strong>Наш менеджер свяжется с вами в ближайшее время</strong> для обсуждения деталей доставки.</p>
                    </div>
                    
                    <p>Это тестовое письмо для проверки работы email уведомлений.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function get_test_cdek_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>ТЕСТ - Доставка СДЭК</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; border: 2px solid #dc3545; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .cdek-info { background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .test-notice { background: #dc3545; color: white; padding: 10px; text-align: center; border-radius: 4px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-notice">
                    <strong>🧪 ЭТО ТЕСТОВОЕ ПИСЬМО</strong>
                </div>
                
                <div class="header">
                    <h1>🚚 Доставка СДЭК</h1>
                    <p>Заказ #<?php echo $data['order_number']; ?> от <?php echo $data['order_date']; ?></p>
                </div>
                
                <div class="content">
                    <p>Здравствуйте, <strong><?php echo $data['customer_name']; ?></strong>!</p>
                    
                    <div class="cdek-info">
                        <h3>🚚 Информация о доставке:</h3>
                        <p><strong>Пункт выдачи:</strong> <?php echo $data['point_name']; ?></p>
                        <p><strong>Код пункта:</strong> <?php echo $data['point_code']; ?></p>
                        <p><strong>Адрес:</strong> <?php echo $data['point_address']; ?></p>
                        <?php echo $data['point_info']; ?>
                    </div>
                    
                    <p>Это тестовое письмо для проверки работы email уведомлений.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    public function activate_plugin() {
        // Создание таблиц или начальных настроек при активации плагина
        if (!get_option('cdek_plugin_version')) {
            add_option('cdek_plugin_version', CDEK_DELIVERY_VERSION);
            add_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
            add_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
            add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            add_option('cdek_sender_city', '51'); // Саратов - код 51
        }
    }
    
    // Функция удалена - используем только add_cdek_map_alternative_position
    
    /**
     * Альтернативная позиция для карты в классическом чекауте
     */
    public function add_cdek_map_alternative_position() {
        // Показываем карту только если выбран метод доставки СДЭК
        ?>
        <div id="cdek-map-wrapper" style="display: block !important;">
            <?php echo $this->render_cdek_map_html(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Всегда показываем карту СДЭК
            $('#cdek-map-wrapper').show();
            
            // Показываем карту при выборе СДЭК доставки
            $('body').on('change', 'input[name^="shipping_method"]', function() {
                if ($(this).val().indexOf('cdek_delivery') !== -1) {
                    $('#cdek-map-wrapper').show();
                    // Инициализируем карту с задержкой
                    setTimeout(function() {
                        if (typeof window.initCdekDelivery === 'function') {
                            window.initCdekDelivery();
                        }
                    }, 300);
                } else {
                    $('#cdek-map-wrapper').hide();
                }
            });
            
            // Проверяем при загрузке страницы и всегда показываем карту
            $('#cdek-map-wrapper').show();
            $('input[name^="shipping_method"]:checked').each(function() {
                if ($(this).val().indexOf('cdek_delivery') !== -1) {
                    $('#cdek-map-wrapper').show();
                    // Инициализируем карту с задержкой
                    setTimeout(function() {
                        if (typeof window.initCdekDelivery === 'function') {
                            window.initCdekDelivery();
                        }
                    }, 300);
                }
            });
            
            // Принудительно инициализируем карту через 1 секунду
            setTimeout(function() {
                if (typeof window.initCdekDelivery === 'function') {
                    window.initCdekDelivery();
                }
            }, 1000);
        });
        </script>
        <?php
    }
    
    /**
     * Шорткод для карты СДЭК
     */
    public function cdek_delivery_map_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '450px',
            'show_always' => 'false'
        ), $atts);
        
        $style = $atts['show_always'] === 'true' ? '' : 'display: none;';
        $wrapper_id = $atts['show_always'] === 'true' ? 'cdek-shortcode-map' : 'cdek-map-wrapper';
        
        return '<div id="' . $wrapper_id . '" style="' . $style . '">' . 
               $this->render_cdek_map_html($atts['height']) . 
               '</div>';
    }
    
    /**
     * Рендеринг HTML карты СДЭК
     */
    private function render_cdek_map_html($height = '450px') {
        ob_start();
        ?>
        <div id="cdek-map-container" style="margin-top: 20px;">
            <h4>Выберите способ получения заказа:</h4>
            
            <!-- Кнопки выбора способа доставки -->
            <div id="cdek-delivery-options" style="margin-bottom: 20px;">
                <button type="button" class="cdek-delivery-option" data-option="pickup" style="margin-right: 10px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <img draggable="false" role="img" class="emoji" alt="📍" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4cd.svg"> Самовывоз (г.Саратов, ул. Осипова, д. 18а) — Бесплатно
                </button>
                <button type="button" class="cdek-delivery-option" data-option="manager" style="margin-right: 10px; padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <img draggable="false" role="img" class="emoji" alt="📞" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4de.svg"> Обсудить доставку с менеджером — Бесплатно
                </button>
                <button type="button" class="cdek-delivery-option active" data-option="cdek" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <img draggable="false" role="img" class="emoji" alt="🚚" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f69a.svg"> Доставка СДЭК
                </button>
            </div>
            
            <div id="cdek-delivery-content">
                <div id="cdek-points-info" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                    <strong>Информация:</strong>
                    <div id="cdek-points-count">Введите город в поле «Адрес» выше для поиска пунктов выдачи</div>
                </div>
                
                <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; display: none;">
                    <strong>Выбранный пункт выдачи:</strong>
                    <div id="cdek-point-info"></div>
                    <button type="button" id="cdek-clear-selection" style="margin-top: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">
                        Очистить выбор
                    </button>
                </div>
                
                <div id="cdek-map" style="width: 100%; height: <?php echo esc_attr($height); ?>; border: 1px solid #ddd; border-radius: 6px; display: block !important; visibility: visible !important;"></div>
                
                <div id="cdek-points-list" style="margin-top: 15px; max-height: 300px; overflow-y: auto; display: none;">
                    <h5>Список пунктов выдачи:</h5>
                    <div id="cdek-points-list-content"></div>
                </div>
            </div>
            
            <p style="font-size: 14px; color: #666; margin-top: 10px;">
                <img draggable="false" role="img" class="emoji" alt="💡" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4a1.svg"> Введите город в поле «Адрес» выше, затем выберите пункт выдачи на карте или в списке
            </p>
        </div>
        
        <!-- Скрытые поля для передачи данных -->
        <input type="hidden" id="cdek-selected-point-code" name="cdek_selected_point_code" value="">
        <input type="hidden" id="cdek-selected-point-data" name="cdek_selected_point_data" value="">
        <input type="hidden" id="cdek-delivery-cost" name="cdek_delivery_cost" value="">
        <input type="hidden" id="cdek-delivery-type" name="cdek_delivery_type" value="cdek">
        <?php
        return ob_get_clean();
    }
    
    // Функция удалена - используем только add_cdek_map_alternative_position
    
    /**
     * Добавление стилей для классического чекаута
     */
    public function add_classic_checkout_styles() {
        if (is_checkout()) {
            ?>
            <style>
            /* Стили для классического чекаута СДЭК */
            #cdek-map-container, #cdek-map-fallback-wrapper, #cdek-map-wrapper {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            /* Принудительно показываем карту */
            #cdek-map {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                width: 100% !important;
                height: 450px !important;
                position: relative !important;
            }
            
            /* Кнопки выбора способа доставки */
            .cdek-delivery-option {
                transition: all 0.3s ease;
                opacity: 0.7;
            }
            
            .cdek-delivery-option:hover {
                opacity: 1;
                transform: translateY(-1px);
            }
            
            .cdek-delivery-option.active {
                opacity: 1;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            
            #cdek-map-container h4 {
                margin-top: 0;
                color: #333;
                font-size: 18px;
            }
            
            #cdek-address-search input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            
            #cdek-points-info {
                background: #e3f2fd;
                border: 1px solid #2196f3;
                padding: 12px;
                border-radius: 6px;
                margin: 10px 0;
            }
            
            #cdek-selected-point {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                padding: 12px;
                border-radius: 6px;
                margin: 10px 0;
            }
            
            #cdek-clear-selection {
                background: #dc3545;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 10px;
            }
            
            #cdek-clear-selection:hover {
                background: #c82333;
            }
            
            #cdek-points-list {
                max-height: 300px;
                overflow-y: auto;
                margin-top: 15px;
            }
            
            .cdek-point-item {
                padding: 12px;
                margin-bottom: 8px;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
                background: white;
            }
            
            .cdek-point-item:hover {
                background: #f8f9fa;
                border-color: #007cba;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            /* Поля адреса остаются видимыми */
            
            /* Адаптивность для мобильных */
            @media (max-width: 768px) {
                #cdek-map-container {
                    margin: 15px 0;
                    padding: 10px;
                }
                
                #cdek-map {
                    min-height: 300px;
                }
                
                .cdek-point-item {
                    padding: 10px;
                }
            }
            </style>
            <?php
        }
    }
    
    /**
     * Валидация выбора пункта выдачи СДЭК
     */
    public function validate_cdek_point_selection() {
        // Проверяем только если выбран метод доставки СДЭК
        $shipping_methods = WC()->session->get('chosen_shipping_methods');
        $is_cdek_selected = false;
        
        if (!empty($shipping_methods)) {
            foreach ($shipping_methods as $method) {
                if (strpos($method, 'cdek_delivery') !== false) {
                    $is_cdek_selected = true;
                    break;
                }
            }
        }
        
        if ($is_cdek_selected) {
            // Получаем тип доставки
            $delivery_type = isset($_POST['cdek_delivery_type']) ? sanitize_text_field($_POST['cdek_delivery_type']) : 'cdek';
            
            // Проверяем пункт выдачи только для доставки СДЭК, но НЕ для самовывоза и менеджера
            if ($delivery_type === 'cdek') {
                $point_code = isset($_POST['cdek_selected_point_code']) ? sanitize_text_field($_POST['cdek_selected_point_code']) : '';
                
                if (empty($point_code)) {
                    wc_add_notice('Пожалуйста, выберите пункт выдачи СДЭК на карте или в списке.', 'error');
                }
            }
            // Для самовывоза (pickup) и менеджера (manager) валидация пункта не нужна
        }
    }
    
    public function ajax_update_shipping_cost() {
        // Проверяем nonce для безопасности
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $cost = 0;
        $delivery_type = isset($_POST['cdek_delivery_type']) ? sanitize_text_field($_POST['cdek_delivery_type']) : 'cdek';
        
        // Сохраняем тип доставки в сессии
        WC()->session->set('cdek_delivery_type', $delivery_type);
        
        // Обрабатываем стоимость доставки в зависимости от типа
        if ($delivery_type === 'manager' || $delivery_type === 'pickup') {
            // Для менеджера и самовывоза стоимость всегда 0
            $cost = 0;
            WC()->session->set('cdek_delivery_cost', $cost);
            // Очищаем данные о пункте выдачи
            WC()->session->__unset('cdek_selected_point_code');
            WC()->session->__unset('cdek_selected_point_data');
            error_log('СДЭК: Очищена стоимость доставки для типа: ' . $delivery_type);
        } else {
            // Для доставки СДЭК сохраняем переданную стоимость
            if (isset($_POST['cdek_delivery_cost'])) {
                $cost = floatval($_POST['cdek_delivery_cost']);
                WC()->session->set('cdek_delivery_cost', $cost);
                error_log('СДЭК: Сохранена стоимость доставки в сессии: ' . $cost);
            }
            
            if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
                $point_code = sanitize_text_field($_POST['cdek_selected_point_code']);
                WC()->session->set('cdek_selected_point_code', $point_code);
                error_log('СДЭК: Сохранен код пункта в сессии: ' . $point_code);
            }
        }
        
        // Принудительно очищаем кеш доставки
        WC()->shipping()->reset_shipping();
        
        // Пересчитываем корзину
        WC()->cart->calculate_totals();
        
        // Возвращаем обновленные данные
        ob_start();
        woocommerce_order_review();
        $order_review = ob_get_clean();
        
        // Получаем общую сумму заказа для передачи в JavaScript
        $cart_total = WC()->cart->get_total();
        
        wp_send_json_success(array(
            'fragments' => array(
                '.shop_table.woocommerce-checkout-review-order-table' => $order_review
            ),
            'cost' => $cost,
            'cart_total' => $cart_total,
            'delivery_type' => $delivery_type,
            'refresh_checkout' => true
        ));
    }
    
    public function handle_checkout_update_order_review($posted_data) {
        // Парсим данные из POST
        $post_data = array();
        if ($posted_data) {
            parse_str($posted_data, $post_data);
        }
        
        // Если есть данные СДЭК в POST, сохраняем их в сессию
        if (isset($post_data['cdek_delivery_cost']) && !empty($post_data['cdek_delivery_cost'])) {
            $cost = floatval($post_data['cdek_delivery_cost']);
            WC()->session->set('cdek_delivery_cost', $cost);
            error_log('СДЭК: Сохранена стоимость доставки из чекаута: ' . $cost);
        }
        
        if (isset($post_data['cdek_selected_point_code']) && !empty($post_data['cdek_selected_point_code'])) {
            $point_code = sanitize_text_field($post_data['cdek_selected_point_code']);
            WC()->session->set('cdek_selected_point_code', $point_code);
            error_log('СДЭК: Сохранен код пункта из чекаута: ' . $point_code);
        }
        
        // Принудительно очищаем кеш доставки
        WC()->shipping()->reset_shipping();
        
        // Принудительно пересчитываем корзину
        WC()->cart->calculate_totals();
        
        // Логируем для отладки
        error_log('СДЭК: Итого в корзине после пересчета: ' . WC()->cart->get_total());
    }
    
    public function update_order_total_before_payment() {
        // Обновляем сумму заказа ПЕРЕД созданием платежа в Т-Банке
        $cdek_cost = WC()->session->get('cdek_delivery_cost');
        
        if (!empty($cdek_cost) && $cdek_cost > 0) {
            error_log('СДЭК: Обновляем сумму заказа перед платежом. Доставка: ' . $cdek_cost);
            
            // Принудительно пересчитываем корзину с учетом доставки
            WC()->shipping()->reset_shipping();
            WC()->cart->calculate_totals();
            
            error_log('СДЭК: Новая сумма заказа: ' . WC()->cart->get_total());
        }
    }
    
    public function filter_calculated_total($total, $cart) {
        // Фильтр для корректировки итоговой суммы при расчетах
        $cdek_cost = WC()->session->get('cdek_delivery_cost');
        
        if (!empty($cdek_cost) && $cdek_cost > 0) {
            // Получаем сумму без доставки
            $subtotal = $cart->get_subtotal() + $cart->get_subtotal_tax();
            $new_total = $subtotal + $cdek_cost;
            
            error_log('СДЭК: Фильтр суммы. Подытог: ' . $subtotal . ', Доставка: ' . $cdek_cost . ', Итого: ' . $new_total);
            
            return $new_total;
        }
        
        return $total;
    }
    
    public function update_order_after_creation($order_id, $posted_data, $order) {
        // Обновляем заказ сразу после создания, но до отправки в платежную систему
        $cdek_cost = WC()->session->get('cdek_delivery_cost');
        
        if (!empty($cdek_cost) && $cdek_cost > 0) {
            error_log('СДЭК: Корректируем сумму заказа #' . $order_id . ' с доставкой: ' . $cdek_cost);
            
            // Пересчитываем общую сумму с учетом доставки СДЭК
            $original_total = $order->get_total();
            $subtotal = $order->get_subtotal();
            $new_total = $subtotal + $cdek_cost;
            
            // Обновляем total в заказе
            $order->set_total($new_total);
            $order->save();
            
            error_log('СДЭК: Заказ #' . $order_id . ' обновлен. Было: ' . $original_total . ', Стало: ' . $new_total);
            
            // Сохраняем информацию о доставке в мета-данных заказа
            $order->update_meta_data('_cdek_delivery_cost', $cdek_cost);
            $order->update_meta_data('_cdek_point_code', WC()->session->get('cdek_selected_point_code'));
            $order->save_meta_data();
        }
    }
    
    public function update_cdek_shipping_rates($rates, $package) {
        // Получаем стоимость доставки из сессии
        $cdek_cost = WC()->session->get('cdek_delivery_cost');
        
        if (!empty($cdek_cost) && $cdek_cost > 0) {
            // Обновляем стоимость для методов доставки СДЭК
            foreach ($rates as $rate_key => $rate) {
                if (strpos($rate_key, 'cdek_delivery') !== false) {
                    $rates[$rate_key]->cost = floatval($cdek_cost);
                    $rates[$rate_key]->label = 'СДЭК доставка';
                    error_log('СДЭК: Обновлена стоимость метода доставки: ' . $cdek_cost);
                }
            }
        }
        
        return $rates;
    }
}

// Инициализация плагина
new CdekDeliveryPlugin();

// Класс для работы с СДЭК API
class CdekAPI {
    
    private $account;
    private $password;
    private $test_mode;
    private $base_url;
    
    public function __construct() {
        $this->account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        
        // ПРИНУДИТЕЛЬНО ОТКЛЮЧАЕМ ТЕСТОВЫЙ РЕЖИМ - он не работает с данными учетными данными
        $this->test_mode = 0;
        update_option('cdek_test_mode', 0);
        $this->base_url = 'https://api.cdek.ru/v2'; // Всегда используем продакшн API
        
        // Устанавливаем город отправителя как Саратов (правильный код 428)
        update_option('cdek_sender_city', '428');
        
        
    }
    
    public function get_auth_token() {
        
        $auth_data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->account,
            'client_secret' => $this->password
        );
        
        
        $response = wp_remote_post($this->base_url . '/oauth/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress/CDEK-Plugin'
            ),
            'body' => $auth_data,
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            error_log('🔑 СДЭК AUTH: HTTP код: ' . $response_code);
            error_log('🔑 СДЭК AUTH: Ответ: ' . $body);
            
            $parsed_body = json_decode($body, true);
            if (isset($parsed_body['access_token'])) {
                $token = $parsed_body['access_token'];
                return $token;
            } else {
            }
        } else {
        }
        
        return false;
    }
    
    public function get_delivery_points($address) {
        $token = $this->get_auth_token();
        if (!$token) {
            return array();
        }
        
        // Извлекаем город из адреса
        $city = $this->extract_city_from_address($address);
        
        error_log('СДЭК API: Поиск пунктов для города: ' . $city);
        
        // Параметры запроса с фильтрацией по городу - БЕЗ ОГРАНИЧЕНИЙ
        $params = array(
            'country_code' => 'RU'
        );
        
        // Добавляем город для фильтрации
        if (!empty($city)) {
            $params['city'] = $city;
        }
        
        // Строим URL для GET запроса
        $url = add_query_arg($params, $this->base_url . '/deliverypoints');
        
        error_log('СДЭК API: URL запроса: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            error_log('СДЭК API: Код ответа: ' . $response_code);
            
            if ($response_code === 200 && $body) {
                $points = array();
                
                // Проверяем различные форматы ответа СДЭК API
                if (isset($body['entity']) && is_array($body['entity'])) {
                    $points = $body['entity'];
                } elseif (is_array($body) && !empty($body)) {
                    $points = $body;
                }
                
                // Дополнительная фильтрация по городу на стороне PHP
                if (!empty($city) && !empty($points)) {
                    $points = $this->filter_points_by_city($points, $city);
                }
                
                error_log('СДЭК API: Получено пунктов после фильтрации: ' . count($points));
                return $points;
            } else {
                if (isset($body['errors'])) {
                    error_log('СДЭК API: Ошибки в ответе: ' . print_r($body['errors'], true));
                }
                return array();
            }
        } else {
            error_log('СДЭК API: Ошибка HTTP: ' . $response->get_error_message());
        }
        
        return array();
    }
    
    private function filter_points_by_city($points, $city) {
        if (empty($points) || empty($city)) {
            return $points;
        }
        
        $city_lower = mb_strtolower(trim($city));
        $filtered_points = array();
        
        foreach ($points as $point) {
            $point_city = '';
            
            // Пытаемся извлечь город из различных полей
            if (isset($point['location']['city']) && !empty($point['location']['city'])) {
                $point_city = $point['location']['city'];
            } elseif (isset($point['location']['address']) && !empty($point['location']['address'])) {
                // Извлекаем город из адреса
                $address_parts = explode(',', $point['location']['address']);
                if (!empty($address_parts[0])) {
                    $point_city = trim($address_parts[0]);
                }
            } elseif (isset($point['location']['address_full']) && !empty($point['location']['address_full'])) {
                // Извлекаем город из полного адреса
                $address_parts = explode(',', $point['location']['address_full']);
                foreach ($address_parts as $part) {
                    $part = trim($part);
                    // Ищем часть с названием города
                    if (preg_match('/^(г\.?\s*)?([А-Яа-я\-\s]+)$/u', $part, $matches)) {
                        $city_candidate = trim($matches[2]);
                        // Проверяем, что это известный город
                        $known_cities = ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород', 'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж', 'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'];
                        if (in_array($city_candidate, $known_cities)) {
                            $point_city = $city_candidate;
                            break;
                        }
                    }
                }
            }
            
            if (!empty($point_city)) {
                // Очищаем от префиксов "г.", "город"
                $point_city = preg_replace('/^(г\.?\s*|город\s+)/ui', '', $point_city);
                $point_city_lower = mb_strtolower(trim($point_city));
                
                // Строгая проверка соответствия города
                $is_match = false;
                
                // 1. Точное совпадение
                if ($point_city_lower === $city_lower) {
                    $is_match = true;
                }
                // 2. Проверяем совпадение по началу (только для похожих названий)
                elseif (mb_strlen($city_lower) >= 4 && mb_strlen($point_city_lower) >= 4) {
                    $starts_match = (mb_strpos($point_city_lower, $city_lower) === 0) || 
                                   (mb_strpos($city_lower, $point_city_lower) === 0);
                    
                    if ($starts_match) {
                        // Только если разница в длине не более 3 символов
                        $length_diff = abs(mb_strlen($point_city_lower) - mb_strlen($city_lower));
                        if ($length_diff <= 3) {
                            $is_match = true;
                        }
                    }
                }
                // 3. Проверяем по словам (для составных названий)
                elseif (mb_strlen($city_lower) >= 4) {
                    $search_words = preg_split('/[\s\-]+/u', $city_lower);
                    $point_words = preg_split('/[\s\-]+/u', $point_city_lower);
                    
                    foreach ($search_words as $search_word) {
                        if (mb_strlen($search_word) >= 4) {
                            foreach ($point_words as $point_word) {
                                if (mb_strlen($point_word) >= 4 && $search_word === $point_word) {
                                    $is_match = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if ($is_match) {
                    $filtered_points[] = $point;
                    error_log('СДЭК фильтр: ✅ Пункт прошел: ' . $point_city . ' (искали: ' . $city . ')');
                } else {
                    error_log('СДЭК фильтр: 🚫 Пункт отфильтрован: ' . $point_city . ' (искали: ' . $city . ')');
                }
            }
        }
        
        return $filtered_points;
    }
    
    public function calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions) {
        
        $token = $this->get_auth_token();
        if (!$token) {
            return false;
        }
        
        
        // Подготавливаем данные для расчета  
        $from_location = array(
            'code' => get_option('cdek_sender_city', '428') // Саратов (правильный код для API)
        );
        
        // Определяем локацию назначения
        $to_location = array();
        
        // Для расчета до пункта выдачи используем данные пункта
        if ($point_code && $point_data) {
            error_log('СДЭК API: Данные пункта для определения локации: ' . print_r($point_data, true));
            
            // Множественные способы определения локации
            $location_found = false;
            
            // Способ 1: city_code
            if (isset($point_data['location']['city_code']) && !empty($point_data['location']['city_code'])) {
                $to_location['code'] = intval($point_data['location']['city_code']);
                $location_found = true;
            }
            // Способ 2: postal_code 
            elseif (isset($point_data['location']['postal_code']) && !empty($point_data['location']['postal_code'])) {
                $to_location['postal_code'] = $point_data['location']['postal_code'];
                $location_found = true;
            }
            // Способ 3: city name
            elseif (isset($point_data['location']['city']) && !empty($point_data['location']['city'])) {
                $city_name = trim($point_data['location']['city']);
                $to_location['city'] = $city_name;
                $location_found = true;
            }
            
            // Способ 4: извлечение из name пункта
            if (!$location_found && isset($point_data['name'])) {
                $name_parts = explode(',', $point_data['name']);
                if (count($name_parts) >= 2) {
                    $city_from_name = trim($name_parts[1]);
                    if ($city_from_name) {
                        $to_location['city'] = $city_from_name;
                        $location_found = true;
                    }
                }
            }
            
            // Способ 5: извлечение из полного адреса
            if (!$location_found && isset($point_data['location']['address_full'])) {
                $address_parts = explode(',', $point_data['location']['address_full']);
                foreach ($address_parts as $part) {
                    $part = trim($part);
                    // Ищем часть с "Москва", "Санкт-Петербург" и т.д.
                    if (preg_match('/^(г\.?\s*)?([А-Яа-я\-\s]+)$/u', $part, $matches)) {
                        $city_candidate = trim($matches[2]);
                        if (in_array($city_candidate, ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород', 'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж', 'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'])) {
                            $to_location['city'] = $city_candidate;
                            error_log('СДЭК API: Извлекли известный город из адреса: ' . $city_candidate);
                            $location_found = true;
                            break;
                        }
                    }
                }
            }
            
            // Способ 6: Определение города по коду пункта
            if (!$location_found) {
                $city_codes = array(
                    'MSK' => array('code' => 44, 'name' => 'Москва'),
                    'SPB' => array('code' => 137, 'name' => 'Санкт-Петербург'),
                    'MKHCH' => array('code' => 470, 'name' => 'Махачкала'),
                    'NSK' => array('code' => 270, 'name' => 'Новосибирск'),
                    'EKB' => array('code' => 51, 'name' => 'Екатеринбург'),
                    'KZN' => array('code' => 172, 'name' => 'Казань'),
                    'NN' => array('code' => 276, 'name' => 'Нижний Новгород'),
                    'CHE' => array('code' => 56, 'name' => 'Челябинск'),
                    'SAM' => array('code' => 350, 'name' => 'Самара'),
                    'UFA' => array('code' => 414, 'name' => 'Уфа'),
                    'ROV' => array('code' => 335, 'name' => 'Ростов-на-Дону'),
                    'KRD' => array('code' => 93, 'name' => 'Краснодар'),
                    'PERM' => array('code' => 296, 'name' => 'Пермь'),
                    'VRN' => array('code' => 432, 'name' => 'Воронеж'),
                    'VGG' => array('code' => 438, 'name' => 'Волгоград'),
                    'KRS' => array('code' => 207, 'name' => 'Красноярск'),
                    'SRT' => array('code' => 354, 'name' => 'Саратов'),
                    'TYU' => array('code' => 409, 'name' => 'Тюмень')
                );
                
                foreach ($city_codes as $prefix => $city_info) {
                    if (stripos($point_code, $prefix) === 0) {
                        $to_location['code'] = $city_info['code'];
                        $location_found = true;
                        break;
                    }
                }
                
                if (!$location_found) {
                }
            }
            
            if (!$location_found) {
                return false;
            }
            
        } else {
            return false;
        }
        
        // Проверяем, что переданы корректные габариты
        if (empty($cart_dimensions['length']) || empty($cart_dimensions['width']) || empty($cart_dimensions['height'])) {
            error_log('СДЭК расчет: Не переданы габариты товара');
            wp_send_json_error('Не переданы габариты товара');
            return;
        }
        
        if ($cart_weight <= 0) {
            error_log('СДЭК расчет: Не указан вес товара');
            wp_send_json_error('Не указан вес товара');
            return;
        }
        
        // Получаем количество коробок
        $packages_count = isset($_POST['packages_count']) ? intval($_POST['packages_count']) : 1;
        
        // Проверяем и корректируем количество коробок
        if ($packages_count < 1) $packages_count = 1;
        if ($packages_count > 10) $packages_count = 10; // Ограничиваем разумным количеством
        
        error_log('СДЭК расчет: Количество коробок: ' . $packages_count);
        
        // Создаем массив коробок
        $packages = array();
        
        // Распределяем вес по коробкам
        $weight_per_package = ceil($cart_weight / $packages_count);
        
        for ($i = 0; $i < $packages_count; $i++) {
            // Для последней коробки корректируем вес
            if ($i == $packages_count - 1) {
                $remaining_weight = $cart_weight - ($weight_per_package * ($packages_count - 1));
                $weight_per_package = max(100, $remaining_weight); // Минимум 100г на коробку
            }
            
            $packages[] = array(
                'weight' => intval($weight_per_package),
                'length' => intval($cart_dimensions['length']),
                'width' => intval($cart_dimensions['width']),
                'height' => intval($cart_dimensions['height'])
            );
        }
        
        error_log('СДЭК расчет: Коробки: ' . print_r($packages, true));
        
        // Проверяем ограничения API СДЭК для большого количества коробок
        if ($packages_count > 5) {
            error_log('СДЭК расчет: ВНИМАНИЕ! Большое количество коробок (' . $packages_count . '). API может не справиться.');
        }
        
        // Определяем тариф для доставки ИЗ САРАТОВА до пункта выдачи
        // 136 - Посылка склад-постамат/пункт выдачи (ПРАВИЛЬНЫЙ для ПВЗ)
        // 138 - Посылка дверь-постамат
        $tariff_code = 136; // Возвращаем обратно для пунктов выдачи
        
        // Формируем запрос согласно официальной документации API СДЭК
        $data = array(
            'date' => date('Y-m-d\TH:i:sO'), // Правильный формат даты с часовым поясом
            'type' => 1, // Тип заказа: интернет-магазин
            'currency' => 1, // Валюта RUB
            'lang' => 'rus', // Язык ответа
            'tariff_code' => $tariff_code,
            'from_location' => $from_location,
            'to_location' => $to_location,
            'packages' => $packages
        );
        
        
        // Добавляем услуги если нужны
        $services = array();
        
        // Страхование если стоимость товара больше 3000 руб
        if ($cart_value > 3000) {
            $services[] = array(
                'code' => 'INSURANCE',
                'parameter' => strval(intval($cart_value))
            );
        }
        
        if (!empty($services)) {
            $data['services'] = $services;
        }
        
        
        // Делаем запрос к API СДЭК
        error_log('СДЭК расчет: Отправляем запрос к API: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30 // Увеличиваем таймаут
        ));
        
        if (is_wp_error($response)) {
            error_log('СДЭК расчет: Ошибка HTTP запроса: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        error_log('СДЭК расчет: Ответ API (код: ' . $response_code . '): ' . $body);
        
        $parsed_body = json_decode($body, true);
        
        if ($response_code === 200 && $parsed_body) {
            
            if (isset($parsed_body['delivery_sum']) && $parsed_body['delivery_sum'] > 0) {
                error_log('🎉 СДЭК API: Успешно получена стоимость от API: ' . $parsed_body['delivery_sum'] . ' руб.');
                return array(
                    'delivery_sum' => intval($parsed_body['delivery_sum']),
                    'period_min' => isset($parsed_body['period_min']) ? $parsed_body['period_min'] : null,
                    'period_max' => isset($parsed_body['period_max']) ? $parsed_body['period_max'] : null,
                    'api_success' => true
                );
            } elseif (isset($parsed_body['errors']) && !empty($parsed_body['errors'])) {
                
                // Анализируем ошибки для понимания проблемы
                foreach ($parsed_body['errors'] as $error) {
                    if (isset($error['code']) && isset($error['message'])) {
                        error_log('❌ СДЭК API: Ошибка ' . $error['code'] . ': ' . $error['message']);
                    }
                }
                
                // Только API, без альтернативных расчетов
                return false;
            } else {
                
                // Проверяем, есть ли warnings
                if (isset($parsed_body['warnings']) && !empty($parsed_body['warnings'])) {
                    error_log('⚠️ СДЭК API: Предупреждения в ответе: ' . print_r($parsed_body['warnings'], true));
                }
                
                // Только API, без альтернативных расчетов
                return false;
            }
        } else {
            if (!$parsed_body && $body) {
            }
            return false;
        }
        
        return false;
    }
    

    
    private function extract_city_from_address($address) {
        // Улучшенное извлечение города из адреса
        $address = trim($address);
        
        // Если адрес "Россия", возвращаем пустую строку для поиска по всем городам
        if (strtolower($address) === 'россия' || strtolower($address) === 'russia') {
            error_log('СДЭК API: Адрес "Россия" - будем искать по всем городам');
            return '';
        }
        
        // Очищаем от префиксов "г.", "город", "г "
        $city = preg_replace('/^(г\.?\s*|город\s+)/ui', '', $address);
        
        // Если есть запятые, берем первую часть
        $parts = explode(',', $city);
        $city = trim($parts[0]);
        
        return $city;
    }
    
    /**
     * Создание заказа в СДЭК
     */
    public function create_order($order_data) {
        $token = $this->get_auth_token();
        if (!$token) {
            return false;
        }
        
        error_log('СДЭК API: Создание заказа с данными: ' . print_r($order_data, true));
        
        $response = wp_remote_post($this->base_url . '/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($order_data),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('СДЭК API: Создание заказа - код ответа: ' . $response_code);
            error_log('СДЭК API: Создание заказа - ответ: ' . $body);
            
            if ($response_code === 201 || $response_code === 200) {
                $parsed_body = json_decode($body, true);
                return $parsed_body;
            } else {
                error_log('СДЭК API: Ошибка создания заказа: ' . $body);
                return false;
            }
        } else {
            error_log('СДЭК API: HTTP ошибка при создании заказа: ' . $response->get_error_message());
            return false;
        }
    }
    
    /**
     * Получение статуса заказа из СДЭК
     */
    public function get_order_status($order_uuid) {
        $token = $this->get_auth_token();
        if (!$token) {
            return false;
        }
        
        $url = $this->base_url . '/orders/' . $order_uuid;
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $parsed_body = json_decode($body, true);
                return isset($parsed_body['entity']) ? $parsed_body['entity'] : $parsed_body;
            } else {
                error_log('СДЭК API: Ошибка получения статуса заказа: ' . $body);
                return false;
            }
        } else {
            error_log('СДЭК API: HTTP ошибка при получении статуса заказа: ' . $response->get_error_message());
            return false;
        }
    }
}
