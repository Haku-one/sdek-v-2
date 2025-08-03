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
        
        // Кастомный классический checkout
        add_shortcode('classic_checkout', array($this, 'classic_checkout_shortcode'));
        add_action('woocommerce_checkout_before_customer_details', array($this, 'add_manager_button'));
        

        
        // Новое поле для выбора менеджера доставки (для блочного чекаута)
        add_action('woocommerce_init', array($this, 'register_delivery_manager_field'));
        
        // Хуки для сохранения и отображения поля
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_manager_field'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_delivery_manager_in_admin'));
        add_action('woocommerce_order_item_meta_end', array($this, 'display_delivery_manager_in_emails'), 10, 3);
        add_action('woocommerce_email_order_meta', array($this, 'add_delivery_manager_to_emails'), 10, 3);
        
        // Валидация поля (только для классического чекаута, если поле видимое)
        // add_action('woocommerce_checkout_process', array($this, 'validate_delivery_manager_field'));
        
        // AJAX обработчики
        add_action('wp_ajax_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_nopriv_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        add_action('wp_ajax_nopriv_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        
        // Регистрация настроек плагина
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Сохранение данных о выбранном пункте выдачи
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // Отправка уведомлений о новых заказах в админку
        add_action('woocommerce_new_order', array($this, 'send_order_notification_to_admin'));
        add_action('woocommerce_checkout_order_processed', array($this, 'send_order_email_to_admin'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Скрытие ненужных полей через CSS
        add_action('wp_head', array($this, 'hide_checkout_fields_css'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Поддержка новых блоков WooCommerce
        add_action('plugins_loaded', array($this, 'load_blocks_integration'));
        
        // Добавляем габариты в описание товара в корзине
        add_filter('woocommerce_get_item_data', array($this, 'add_dimensions_to_cart_item'), 10, 2);
        
        // Подключаем обработчик данных доставки
        add_action('plugins_loaded', array($this, 'load_delivery_data_handler'));
    }
    
    public function init() {
        load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            // Подключаем CSS для классического checkout
            wp_enqueue_style('classic-checkout-style', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/classic-checkout.css', array(), CDEK_DELIVERY_VERSION);
            
            // Подключаем JS для классического checkout
            wp_enqueue_script('classic-checkout-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/classic-checkout.js', array('jquery'), CDEK_DELIVERY_VERSION, true);
            
            // Проверяем, не загружены ли уже Яндекс.Карты
            if (!wp_script_is('yandex-maps', 'enqueued') && !wp_script_is('yandex-maps', 'done')) {
                // Получаем API ключ из настроек или используем по умолчанию
                $yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
                
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
            
            wp_enqueue_script('cdek-delivery-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js', array('jquery'), CDEK_DELIVERY_VERSION, true);
            
            // Добавляем скрипт для автозаполнения textarea полей
            wp_enqueue_script('textarea-auto-fill', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/textarea-auto-fill.js', array('jquery'), CDEK_DELIVERY_VERSION, true);
            
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), CDEK_DELIVERY_VERSION);
           
            
            wp_localize_script('cdek-delivery-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce'),
                'yandex_api_key' => $yandex_api_key
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
        
        // Удалено лишнее скрытое поле - используем textarea поля
        
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        // Настраиваем поле адреса
        $fields['address_1']['label'] = 'Город доставки';
        $fields['address_1']['placeholder'] = 'Например: Москва';
        $fields['address_1']['required'] = true;
        
        return $fields;
    }
    
    /**
     * Регистрация поля для блочного чекаута - удалено, используем textarea поля
     */
    public function register_delivery_manager_field() {
        // Удалено - используем существующие textarea поля
    }
    
    /**
     * Сохранение значения поля при оформлении заказа
     */
    public function save_delivery_manager_field($order_id) {
        // Убрано сохранение - данные из textarea полей сохраняются автоматически плагином
    }
    
    /**
     * Отображение поля в админке заказа
     */
    public function display_delivery_manager_in_admin($order) {
        // Убрано отображение - данные отображаются плагином автоматически
    }
    
    /**
     * Отображение поля в email уведомлениях
     */
    public function display_delivery_manager_in_emails($item_id, $item, $order) {
        // Убрано отображение - данные отображаются плагином автоматически
    }
    
    /**
     * Добавление информации о способе доставки в email
     */
    public function add_delivery_manager_to_emails($order, $sent_to_admin, $plain_text) {
        // Убрано отображение - данные отображаются плагином автоматически
    }
    
    /**
     * Валидация полей - удалено, используем textarea поля
     */
    
    public function init_cdek_shipping() {
        if (!class_exists('WC_Cdek_Shipping_Method')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-cdek-shipping-method.php';
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
        
        // Добавляем отладочную информацию
       
        
        $cdek_api = new CdekAPI();
        $points = $cdek_api->get_delivery_points($address);
        
        // Логируем результат
        error_log('СДЭК AJAX: Получено пунктов: ' . count($points));
        if (!empty($points)) {
           
        }
        
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
            
            // Убедимся что передаем флаг успешного API расчета
            $cost_data['api_success'] = true;
            $cost_data['fallback'] = false;
            
            wp_send_json_success($cost_data);
        } else {
            
            // НЕТ РЕЗЕРВНОГО РАСЧЕТА! Возвращаем ошибку
            wp_send_json_error(array(
                'message' => 'API СДЭК недоступен, расчет стоимости невозможен',
                'api_response' => $cost_data,
                'debug_info' => array(
                    'point_code' => $point_code,
                    'cart_weight' => $cart_weight,
                    'cart_value' => $cart_value,
                    'cart_dimensions' => $cart_dimensions
                )
            ));
        }
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
    
    private function calculate_fallback_cost($weight, $value, $dimensions, $has_real_dimensions) {
        $base_cost = 300; // Базовая стоимость
        
        // Дополнительная стоимость за вес свыше 500г
        if ($weight > 500) {
            $extra_weight = ceil(($weight - 500) / 500);
            $base_cost += $extra_weight * 35;
        }
        
        // Дополнительная стоимость за габариты
        if ($has_real_dimensions && $dimensions) {
            $volume = $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
            if ($volume > 12000) {
                $extra_volume = ceil(($volume - 12000) / 6000);
                $base_cost += $extra_volume * 50;
            }
        }
        
        // Страховка за высокую стоимость
        if ($value > 3000) {
            $base_cost += ceil(($value - 3000) / 1000) * 20;
        }
        
        return $base_cost;
    }
    
    public function display_product_dimensions_checkout() {
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
            echo '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; color: #856404;">';
            echo '⚠️ <strong>Внимание:</strong> У товаров в корзине не указаны габариты и вес.<br>';
            echo 'Стоимость доставки будет рассчитана приблизительно.';
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
            echo '<style></style>';
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
        if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
            update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_selected_point_code']));
        }
        
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            if ($point_data) {
                update_post_meta($order_id, '_cdek_point_data', $point_data);
            }
        }
    }
    

    
    public function display_cdek_point_in_admin($order) {
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        if ($point_code && $point_data) {
            echo '<div class="cdek-point-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h4>Пункт выдачи СДЭК:</h4>';
            echo '<strong>' . esc_html($point_data['name']) . '</strong><br>';
            echo 'Код: ' . esc_html($point_code) . '<br>';
            echo 'Адрес: ' . esc_html($point_data['location']['address_full']) . '<br>';
            if (isset($point_data['phone'])) {
                echo 'Телефон: ' . esc_html($point_data['phone']) . '<br>';
            }
            echo '</div>';
        }
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
    
    public function load_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-blocks-integration.php';
        }
    }
    
    public function load_delivery_data_handler() {
        // Подключаем обработчик данных доставки
        if (file_exists(plugin_dir_path(__FILE__) . 'cdek-delivery-data-handler.php')) {
            include_once plugin_dir_path(__FILE__) . 'cdek-delivery-data-handler.php';
           
        } else {
            
        }
        
        // Подключаем функции темы для обработки кастомных данных
        if (file_exists(plugin_dir_path(__FILE__) . 'theme-functions-cdek.php')) {
            include_once plugin_dir_path(__FILE__) . 'theme-functions-cdek.php';
            
            // Принудительно вызываем инициализацию функций темы
            if (function_exists('cdek_theme_init')) {
                cdek_theme_init();
                
            }
        } else {
           
        }
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
        
        // УБИРАЕМ ВСЕ ОГРАНИЧЕНИЯ - показываем ВСЕ пункты выдачи без фильтров
        $params = array(
            'country_code' => 'RU', // Только код страны для России
            'size' => 5000, // Максимальное количество результатов
            'page' => 0 // Первая страница
        );
        
        // Добавляем город только если он указан
        if (!empty($city)) {
            $params['city'] = $city;
        }
        
        // Строим URL с минимальными параметрами для GET запроса
        $url = add_query_arg($params, $this->base_url . '/deliverypoints');
        
        error_log('СДЭК API: 🔓 УБРАНЫ ВСЕ ОГРАНИЧЕНИЯ - URL запроса: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30 // Увеличиваем таймаут для больших запросов
        ));
        
        // Дополнительно делаем запрос БЕЗ ОГРАНИЧЕНИЙ для сравнения
        $params_unrestricted = array(
            'country_code' => 'RU',
            'size' => 5000,
            'page' => 0
        );
        
        if (!empty($city)) {
            $params_unrestricted['city'] = $city;
        }
        
        $url_unrestricted = add_query_arg($params_unrestricted, $this->base_url . '/deliverypoints');
        
        $response_unrestricted = wp_remote_get($url_unrestricted, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response_unrestricted)) {
            $body_unrestricted = wp_remote_retrieve_body($response_unrestricted);
            $data_unrestricted = json_decode($body_unrestricted, true);
            $count_unrestricted = is_array($data_unrestricted) ? count($data_unrestricted) : 0;
        }
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            
            if ($response_code === 200 && $body) {
                // Проверяем различные форматы ответа СДЭК API
                if (isset($body['entity']) && is_array($body['entity'])) {
                    return $body['entity'];
                } elseif (is_array($body) && !empty($body)) {
                    // Если ответ - массив пунктов напрямую
                    return $body;
                } else {
                    return array();
                }
            } else {
                if (isset($body['errors'])) {
                }
                return array();
            }
        } else {
        }
        
        return array();
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
        
        // Подготавливаем данные о посылках
        $packages = array(
            array(
                'weight' => max(100, intval($cart_weight)), // Минимум 100г
                'length' => max(10, intval($cart_dimensions['length'])), // Минимум 10см
                'width' => max(10, intval($cart_dimensions['width'])), // Минимум 10см
                'height' => max(5, intval($cart_dimensions['height'])) // Минимум 5см
            )
        );
        
        
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
                
                // Пробуем альтернативный способ расчета
                return $this->try_alternative_calculation($data, $token);
            } else {
                
                // Проверяем, есть ли warnings
                if (isset($parsed_body['warnings']) && !empty($parsed_body['warnings'])) {
                }
                
                return $this->try_alternative_calculation($data, $token);
            }
        } else {
            if (!$parsed_body && $body) {
            }
            return false;
        }
        
        return false;
    }
    
    private function try_alternative_calculation($original_data, $token) {
        error_log('СДЭК расчет: Пробуем альтернативный метод расчета');
        
        // Попробуем разные тарифы ИЗ САРАТОВА
        $alternative_tariffs = [136, 138, 233, 234]; // ПВЗ, Постамат, Эконом, Стандарт
        
        foreach ($alternative_tariffs as $tariff) {
            $data = $original_data;
            $data['tariff_code'] = $tariff;
            
            // Добавляем недостающие поля если их нет
            if (!isset($data['date'])) {
                $data['date'] = date('Y-m-d\TH:i:sO');
            }
            if (!isset($data['currency'])) {
                $data['currency'] = 1; // RUB
            }
            if (!isset($data['lang'])) {
                $data['lang'] = 'rus';
            }
            
            // Упростим локацию - используем только город Москва если не указано
            if (!isset($data['to_location']['code'])) {
                $data['to_location'] = array('code' => 44); // Москва
            }
            
            error_log('СДЭК расчет: Пробуем тариф ' . $tariff . ' с данными: ' . print_r($data, true));
            
            $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                        error_log('СДЭК расчет: Альтернативный расчет успешен с тарифом ' . $tariff . ': ' . $body['delivery_sum']);
                        return array(
                            'delivery_sum' => intval($body['delivery_sum']),
                            'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                            'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                            'api_success' => true,
                            'alternative_tariff' => $tariff
                        );
                    }
                }
            }
        }
        
        error_log('СДЭК расчет: Альтернативные методы не сработали');
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
     * Кастомный шорткод для классического checkout
     */
    public function classic_checkout_shortcode($atts) {
        // Проверяем, что WooCommerce активен и есть товары в корзине
        if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
            return '<div class="woocommerce-message">Ваша корзина пуста.</div>';
        }
        
        ob_start();
        
        // Получаем стандартную форму checkout
        if (function_exists('woocommerce_checkout')) {
            woocommerce_checkout();
        } else {
            echo '<div class="woocommerce-checkout">';
            wc_get_template('checkout/form-checkout.php', array(
                'checkout' => WC()->checkout()
            ));
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Добавляет кнопку "Обсудить доставку с менеджером" в форму checkout
     */
    public function add_manager_button() {
        ?>
        <div class="delivery-manager-section" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px;">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 5px 0; color: #856404; font-size: 16px;">
                        <span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span>
                        Нужна консультация по доставке?
                    </h4>
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        Наш менеджер поможет выбрать оптимальный способ доставки
                    </p>
                </div>
                <div>
                    <button type="button" id="discuss-delivery-btn" class="button" style="
                        background: #856404; 
                        color: white; 
                        padding: 8px 16px; 
                        border: none; 
                        border-radius: 3px; 
                        font-size: 14px; 
                        cursor: pointer;
                    " onclick="window.location.href='mailto:manager@dobriytravnik.ru?subject=Консультация по доставке заказа'">
                        Обсудить доставку с менеджером
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .delivery-manager-section .dashicons {
            vertical-align: middle;
            font-size: 16px;
        }
        
        #discuss-delivery-btn:hover {
            background: #6c5206 !important;
        }
        
        @media (max-width: 768px) {
            .delivery-manager-section > div {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            #discuss-delivery-btn {
                width: 100%;
                padding: 10px 16px;
            }
        }
                </style>
        <?php
    }
    
    /**
     * Отправка уведомления администратору о новом заказе
     */
    public function send_order_notification_to_admin($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Новый заказ #%s', $site_name, $order->get_order_number());
        
        $message = $this->format_order_email($order);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>'
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
        
        // Логируем отправку
        error_log('Отправлено уведомление администратору о заказе #' . $order_id);
    }
    
    /**
     * Дополнительная отправка email после обработки checkout
     */
    public function send_order_email_to_admin($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Отправляем дополнительное уведомление с полной информацией
        $this->send_detailed_order_email($order);
    }
    
    /**
     * Отправка детального уведомления о заказе
     */
    private function send_detailed_order_email($order) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Подробности заказа #%s - СДЭК Доставка', $site_name, $order->get_order_number());
        
        $message = $this->format_detailed_order_email($order);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
            'Reply-To: ' . $order->get_billing_email()
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Форматирование email с информацией о заказе
     */
    private function format_order_email($order) {
        $order_date = $order->get_date_created()->date('d.m.Y H:i');
        $order_total = $order->get_formatted_order_total();
        
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h2 style="color: #007cba; border-bottom: 2px solid #007cba; padding-bottom: 10px;">Новый заказ #' . $order->get_order_number() . '</h2>';
        
        // Информация о заказе
        $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        $html .= '<h3 style="margin-top: 0; color: #333;">Основная информация</h3>';
        $html .= '<p><strong>Дата заказа:</strong> ' . $order_date . '</p>';
        $html .= '<p><strong>Сумма заказа:</strong> ' . $order_total . '</p>';
        $html .= '<p><strong>Статус:</strong> ' . wc_get_order_status_name($order->get_status()) . '</p>';
        $html .= '</div>';
        
        // Информация о клиенте
        $html .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        $html .= '<h3 style="margin-top: 0; color: #856404;">Информация о клиенте</h3>';
        $html .= '<p><strong>Имя:</strong> ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</p>';
        $html .= '<p><strong>Email:</strong> <a href="mailto:' . $order->get_billing_email() . '">' . $order->get_billing_email() . '</a></p>';
        if ($order->get_billing_phone()) {
            $html .= '<p><strong>Телефон:</strong> <a href="tel:' . $order->get_billing_phone() . '">' . $order->get_billing_phone() . '</a></p>';
        }
        $html .= '</div>';
        
        // Адрес доставки
        $html .= '<div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        $html .= '<h3 style="margin-top: 0; color: #007cba;">Адрес доставки</h3>';
        $shipping_address = $order->get_formatted_shipping_address();
        if ($shipping_address) {
            $html .= '<p>' . nl2br($shipping_address) . '</p>';
        } else {
            $html .= '<p>' . nl2br($order->get_formatted_billing_address()) . '</p>';
        }
        $html .= '</div>';
        
        // Товары в заказе
        $html .= '<div style="background: #ffffff; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 15px 0;">';
        $html .= '<h3 style="margin-top: 0; color: #333;">Товары в заказе</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background: #f8f9fa;">';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6;">Товар</th>';
        $html .= '<th style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">Кол-во</th>';
        $html .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">Цена</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $html .= '<tr>';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . $item->get_name() . '</td>';
            $html .= '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">' . $item->get_quantity() . '</td>';
            $html .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #eee;">' . wc_price($item->get_total()) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Примечания к заказу
        $customer_note = $order->get_customer_note();
        if ($customer_note) {
            $html .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            $html .= '<h3 style="margin-top: 0; color: #856404;">Примечания клиента</h3>';
            $html .= '<p>' . nl2br(esc_html($customer_note)) . '</p>';
            $html .= '</div>';
        }
        
        $html .= '<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">';
        $html .= '<p style="color: #666; font-size: 14px;">Для управления заказом перейдите в <a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">админку WooCommerce</a></p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Форматирование детального email с СДЭК информацией
     */
    private function format_detailed_order_email($order) {
        $html = $this->format_order_email($order);
        
        // Добавляем информацию о СДЭК доставке
        $cdek_point = get_post_meta($order->get_id(), '_cdek_point_data', true);
        if ($cdek_point) {
            $cdek_html = '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745;">';
            $cdek_html .= '<h3 style="margin-top: 0; color: #155724;">Информация о доставке СДЭК</h3>';
            
            if (isset($cdek_point['name'])) {
                $cdek_html .= '<p><strong>Пункт выдачи:</strong> ' . esc_html($cdek_point['name']) . '</p>';
            }
            if (isset($cdek_point['address'])) {
                $cdek_html .= '<p><strong>Адрес:</strong> ' . esc_html($cdek_point['address']) . '</p>';
            }
            if (isset($cdek_point['code'])) {
                $cdek_html .= '<p><strong>Код пункта:</strong> ' . esc_html($cdek_point['code']) . '</p>';
            }
            if (isset($cdek_point['work_time'])) {
                $cdek_html .= '<p><strong>Режим работы:</strong> ' . esc_html($cdek_point['work_time']) . '</p>';
            }
            
            $cdek_html .= '</div>';
            
            // Вставляем информацию о СДЭК после адреса доставки
            $html = str_replace('</div>', $cdek_html . '</div>', $html);
        }
        
        return $html;
    }
 
}

// Инициализация плагина
new CdekDeliveryPlugin();